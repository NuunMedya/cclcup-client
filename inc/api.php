<?php
/**
 * elitlig-server istemcisi.
 *
 * Her istek sunucu tarafında yapılır ve cache/ klasöründe kısa süreli
 * saklanır. API'ye ulaşılamazsa süresi geçmiş önbellek kaydı gösterilir;
 * böylece Heroku uyurken ya da geçici bir hata olduğunda site boş kalmaz.
 */

final class ApiError extends RuntimeException {}

function ccl_config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

/**
 * GET isteği atar, JSON çözer. Hata durumunda ApiError fırlatır
 * (elde süresi geçmiş önbellek yoksa).
 */
function api_get(string $path, array $params = [], ?int $ttl = null)
{
    $cfg = ccl_config();
    $ttl = $ttl ?? $cfg['cache_ttl'];

    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    ksort($params);
    $url = $cfg['api_base'] . '/' . ltrim($path, '/');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $cacheFile = rtrim($cfg['cache_dir'], '/') . '/' . sha1($url) . '.json';
    if ($ttl > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if ($cached !== null) {
            return $cached;
        }
    }

    try {
        $body = http_fetch($url, (int) $cfg['timeout']);
        $data = json_decode($body, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiError('Geçersiz yanıt: ' . $path);
        }
        if ($ttl > 0 && is_dir($cfg['cache_dir']) && is_writable($cfg['cache_dir'])) {
            $tmp = $cacheFile . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, $body) !== false) {
                @rename($tmp, $cacheFile);
            }
        }
        return $data;
    } catch (ApiError $e) {
        // Yedek: eski önbellek kaydı varsa onu kullan.
        if (is_file($cacheFile)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if ($cached !== null) {
                return $cached;
            }
        }
        throw $e;
    }
}

function http_fetch(string $url, int $timeout): string
{
    $headers = ['Accept: application/json', 'User-Agent: cclcup.com/1.0'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new ApiError('Bağlantı hatası: ' . $error);
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new ApiError('Bağlantı hatası.');
        }
        $status = 200;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    if ($status === 404) {
        throw new ApiError('Bulunamadı', 404);
    }
    if ($status >= 400) {
        throw new ApiError('API hatası (' . $status . ')', $status);
    }
    return (string) $body;
}

/** Hata fırlatmak yerine varsayılan değer döndüren sarmalayıcı. */
function api_try(callable $fn, $default = null)
{
    try {
        return $fn();
    } catch (Throwable $e) {
        $GLOBALS['ccl_api_errors'][] = $e->getMessage();
        return $default;
    }
}

/* ------------------------------------------------------------------ */
/*  CCL CUP kapsamı                                                    */
/* ------------------------------------------------------------------ */

function ccl_scope(): array
{
    static $scope = null;
    if ($scope !== null) {
        return $scope;
    }
    $cfg = ccl_config();
    $seasonId = (int) $cfg['season_id'];
    $seasonName = '';

    $seasons = api_try(fn() => api_get('/api/meta/seasons', [
        'cityId' => $cfg['city_id'],
        'leagueId' => $cfg['league_id'],
    ], 600)['seasons'] ?? [], []);

    if ($seasonId <= 0 && $seasons) {
        $seasonId = (int) $seasons[0]['id'];
    }
    foreach ($seasons as $s) {
        if ((int) $s['id'] === $seasonId) {
            $seasonName = (string) $s['label'];
        }
    }

    return $scope = [
        'cityId'   => (int) $cfg['city_id'],
        'leagueId' => (int) $cfg['league_id'],
        'seasonId' => $seasonId,
        'seasonName' => $seasonName,
    ];
}

/** Sezonun tüm (taslak dışı) maçları, tarihe göre artan. */
function ccl_matches(): array
{
    $s = ccl_scope();
    $rows = api_try(fn() => api_get('/maclar', [
        'league_id' => $s['leagueId'],
        'season_id' => $s['seasonId'],
    ]), []);
    $rows = is_array($rows) ? array_values(array_filter($rows, static function ($m) use ($s) {
        return is_array($m) && (int) ($m['season_id'] ?? 0) === $s['seasonId'];
    })) : [];
    usort($rows, static fn($a, $b) => strcmp(match_sort_key($a), match_sort_key($b)) ?: ($a['id'] <=> $b['id']));
    return $rows;
}

function ccl_standings(?int $groupId = null): array
{
    $s = ccl_scope();
    $data = api_try(fn() => api_get('/api/standings', [
        'cityId' => $s['cityId'],
        'leagueId' => $s['leagueId'],
        'seasonId' => $s['seasonId'],
        'groupId' => $groupId,
    ]), []);
    return is_array($data['standings'] ?? null) ? $data['standings'] : [];
}

function ccl_groups(): array
{
    $s = ccl_scope();
    $data = api_try(fn() => api_get('/api/season-groups/season/' . $s['seasonId'], [], 300), []);
    $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];
    return [
        'settings' => $data['settings'] ?? [],
        'groups'   => $groups,
    ];
}

/**
 * Oyuncu istatistikleri.
 * sort: mostValuable, topScorers, mostAssists, mostMatches, mostCards,
 *       goalsPerMatch, pointsPerMatch, mostSaves, marketValue
 */
function ccl_player_stats(array $opts = []): array
{
    $s = ccl_scope();
    $data = api_try(fn() => api_get('/api/players/statistics', [
        'cityId' => $s['cityId'],
        'leagueId' => $s['leagueId'],
        'seasonId' => $s['seasonId'],
        'sort' => $opts['sort'] ?? 'mostValuable',
        'limit' => $opts['limit'] ?? 50,
        'offset' => $opts['offset'] ?? 0,
        'teamId' => $opts['teamId'] ?? null,
        'search' => $opts['search'] ?? null,
        'position' => $opts['position'] ?? null,
    ]), []);
    return [
        'players' => is_array($data['players'] ?? null) ? $data['players'] : [],
        'count'   => (int) ($data['pagination']['count'] ?? 0),
    ];
}

function ccl_match(int $id): ?array
{
    $m = api_try(fn() => api_get('/maclar/' . $id), null);
    if (!is_array($m) || !isset($m['id'])) {
        return null;
    }
    // Yalnızca CCL CUP maçları gösterilir.
    return (int) ($m['season_id'] ?? 0) === ccl_scope()['seasonId'] ? $m : null;
}

function ccl_match_events(int $id): array
{
    $rows = api_try(fn() => api_get('/api/maclar/' . $id . '/olaylar', [], 30), []);
    if (isset($rows['events']) && is_array($rows['events'])) {
        $rows = $rows['events'];
    }
    return is_array($rows) ? $rows : [];
}

function ccl_match_lineup(int $id): array
{
    $data = api_try(fn() => api_get('/maclar/' . $id . '/kadro'), []);
    return [
        'home' => is_array($data['home'] ?? null) ? $data['home'] : [],
        'away' => is_array($data['away'] ?? null) ? $data['away'] : [],
    ];
}

function ccl_team(int $id): ?array
{
    $t = api_try(fn() => api_get('/takimlar/' . $id, [], 300), null);
    return is_array($t) && isset($t['id']) ? $t : null;
}

function ccl_player(int $id): ?array
{
    $p = api_try(fn() => api_get('/oyuncular/' . $id, [], 300), null);
    if (is_array($p) && isset($p[0]) && is_array($p[0])) {
        $p = $p[0];
    }
    return is_array($p) && isset($p['id']) ? $p : null;
}

function ccl_player_events(int $id): array
{
    $data = api_try(fn() => api_get('/mac-olaylari', ['oyuncu_id' => $id]), []);
    $rows = $data['macOlaylari'] ?? $data;
    return is_array($rows) ? $rows : [];
}

/* ------------------------------------------------------------------ */
/*  Türetilmiş veriler                                                 */
/* ------------------------------------------------------------------ */

/** Takım id => [name, logo] haritası (puan durumu + maçlardan). */
function ccl_team_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    foreach (ccl_standings() as $row) {
        $id = (int) ($row['team_id'] ?? 0);
        if ($id) {
            $map[$id] = ['id' => $id, 'name' => $row['team_name'] ?? '', 'logo' => $row['logo'] ?? null];
        }
    }
    foreach (ccl_matches() as $m) {
        foreach ([['home_team_id', 'first_team_name'], ['away_team_id', 'second_team_name']] as [$idKey, $nameKey]) {
            $id = (int) ($m[$idKey] ?? 0);
            if ($id && !isset($map[$id])) {
                $map[$id] = ['id' => $id, 'name' => $m[$nameKey] ?? '', 'logo' => null];
            }
        }
    }
    return $map;
}

function ccl_has_team(int $id): bool
{
    return isset(ccl_team_map()[$id]);
}

function match_sort_key(array $m): string
{
    $date = substr((string) ($m['date'] ?? ''), 0, 10) ?: '9999-12-31';
    $time = substr((string) ($m['time'] ?? ''), 0, 5) ?: '99:99';
    return $date . ' ' . $time;
}

function match_is_live(array $m): bool
{
    return ($m['mac_durumu'] ?? '') === 'canli';
}

/** Sonucu girilmiş (oynanmış) maç mı? */
function match_is_played(array $m): bool
{
    if (match_is_live($m)) {
        return false;
    }
    $status = (string) ($m['mac_durumu'] ?? '');
    if (in_array($status, ['zamanlanmis', 'zamanlandi'], true)) {
        return false;
    }
    if ((int) ($m['is_it_fixture'] ?? 0) === 1) {
        return false;
    }
    return ($m['first_team_score'] ?? null) !== null && ($m['second_team_score'] ?? null) !== null;
}
