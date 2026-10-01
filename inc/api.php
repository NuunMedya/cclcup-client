<?php
/**
 * Veri sunucusu istemcisi.
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
function api_get(string $path, array $params = [], $ttl = null)
{
    $cfg = ccl_config();
    $ttl = $ttl === null ? (int) $cfg['cache_ttl'] : (int) $ttl;

    $params = array_filter($params, static function ($v) {
        return $v !== null && $v !== '';
    });
    ksort($params);
    $url = $cfg['api_base'] . '/' . ltrim($path, '/');
    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    $cacheFile = rtrim($cfg['cache_dir'], '/') . '/' . sha1($url) . '.json';
    $age = is_file($cacheFile) ? time() - (int) filemtime($cacheFile) : null;
    if ($ttl > 0 && $age !== null && $age < $ttl) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if ($cached !== null) {
            return $cached;
        }
    }

    // Süresi yeni dolmuş kayıt: ziyaretçiye hemen eskisini göster, yenilemeyi
    // yanıt gönderildikten sonra yap (yalnızca PHP-FPM'de mümkün).
    if ($ttl > 0 && $age !== null && $age < 1800 && function_exists('fastcgi_finish_request')) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if ($cached !== null) {
            api_queue_refresh($url, $cacheFile, $path);
            return $cached;
        }
    }

    try {
        return api_fetch_and_store($url, $cacheFile, $path, $ttl > 0);
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

function api_fetch_and_store(string $url, string $cacheFile, string $path, bool $store)
{
    $cfg = ccl_config();
    $body = http_fetch($url, (int) $cfg['timeout']);
    $data = json_decode($body, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new ApiError('Geçersiz yanıt: ' . $path);
    }
    if ($store && is_dir($cfg['cache_dir']) && is_writable($cfg['cache_dir'])) {
        $tmp = $cacheFile . '.' . uniqid('', true) . '.tmp';
        if (@file_put_contents($tmp, $body) !== false) {
            @rename($tmp, $cacheFile);
        }
    }
    return $data;
}

/** Yanıt gönderildikten sonra yenilenecek önbellek kayıtları. */
function api_queue_refresh(string $url, string $cacheFile, string $path): void
{
    static $queue = null;
    if ($queue === null) {
        $queue = [];
        register_shutdown_function(static function () use (&$queue) {
            if (!$queue) {
                return;
            }
            fastcgi_finish_request();
            foreach ($queue as $item) {
                // Aynı kaydı başka bir istek az önce yenilediyse atla.
                if (is_file($item[1]) && time() - (int) filemtime($item[1]) < 5) {
                    continue;
                }
                @touch($item[1]); // eşzamanlı isteklerin aynı yenilemeyi yapmasını önle
                try {
                    api_fetch_and_store($item[0], $item[1], $item[2], true);
                } catch (Exception $e) {
                    // Eski kayıt kullanılmaya devam eder.
                }
            }
        });
    }
    $queue[$cacheFile] = [$url, $cacheFile, $path];
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

/** api_get'in hata fırlatmak yerine varsayılan değer döndüren hâli. */
function api_try(string $path, array $params = [], $ttl = null, $default = [])
{
    try {
        return api_get($path, $params, $ttl);
    } catch (Exception $e) {
        // 404 "kayıt yok" demektir; hata sayılmaz.
        if ((int) $e->getCode() !== 404) {
            $GLOBALS['ccl_api_errors'][] = $e->getMessage();
        }
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

    $meta = api_try('/api/meta/seasons', [
        'cityId' => $cfg['city_id'],
        'leagueId' => $cfg['league_id'],
    ], 600);
    $seasons = isset($meta['seasons']) && is_array($meta['seasons']) ? $meta['seasons'] : [];

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
    $rows = api_try('/maclar', [
        'league_id' => $s['leagueId'],
        'season_id' => $s['seasonId'],
    ], null, []);
    $rows = is_array($rows) ? array_values(array_filter($rows, static function ($m) use ($s) {
        return is_array($m) && (int) ($m['season_id'] ?? 0) === $s['seasonId'];
    })) : [];
    usort($rows, static function ($a, $b) {
        return strcmp(match_sort_key($a), match_sort_key($b)) ?: ($a['id'] <=> $b['id']);
    });
    return $rows;
}

function ccl_standings(?int $groupId = null): array
{
    $s = ccl_scope();
    $data = api_try('/api/standings', [
        'cityId' => $s['cityId'],
        'leagueId' => $s['leagueId'],
        'seasonId' => $s['seasonId'],
        'groupId' => $groupId,
    ], null, []);
    return is_array($data['standings'] ?? null) ? $data['standings'] : [];
}

function ccl_groups(): array
{
    $s = ccl_scope();
    $data = api_try('/api/season-groups/season/' . $s['seasonId'], [], 300, []);
    $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];
    return [
        'settings' => $data['settings'] ?? [],
        'groups'   => $groups,
    ];
}

/**
 * Oyuncu istatistikleri.
 * sort: topScorers, mostAssists, mostMatches, mostCards,
 *       goalsPerMatch, pointsPerMatch, mostSaves
 */
function ccl_player_stats(array $opts = []): array
{
    $s = ccl_scope();
    $data = api_try('/api/players/statistics', [
        'cityId' => $s['cityId'],
        'leagueId' => $s['leagueId'],
        'seasonId' => $s['seasonId'],
        'sort' => $opts['sort'] ?? 'topScorers',
        'limit' => $opts['limit'] ?? 50,
        'offset' => $opts['offset'] ?? 0,
        'teamId' => $opts['teamId'] ?? null,
        'search' => $opts['search'] ?? null,
        'position' => $opts['position'] ?? null,
        'matchId' => $opts['matchId'] ?? null,
    ], null, []);
    return [
        'players' => is_array($data['players'] ?? null) ? $data['players'] : [],
        'count'   => (int) ($data['pagination']['count'] ?? 0),
    ];
}

function ccl_match(int $id): ?array
{
    $m = api_try('/maclar/' . $id, [], null, null);
    if (!is_array($m) || !isset($m['id'])) {
        return null;
    }
    // Yalnızca CCL CUP maçları gösterilir.
    return (int) ($m['season_id'] ?? 0) === ccl_scope()['seasonId'] ? $m : null;
}

function ccl_match_events(int $id): array
{
    $rows = api_try('/api/maclar/' . $id . '/olaylar', [], 30, []);
    if (isset($rows['events']) && is_array($rows['events'])) {
        $rows = $rows['events'];
    }
    return is_array($rows) ? $rows : [];
}

function ccl_match_lineup(int $id): array
{
    $data = api_try('/maclar/' . $id . '/kadro', [], null, []);
    return [
        'home' => is_array($data['home'] ?? null) ? $data['home'] : [],
        'away' => is_array($data['away'] ?? null) ? $data['away'] : [],
    ];
}

function ccl_team(int $id): ?array
{
    $t = api_try('/takimlar/' . $id, [], 300, null);
    return is_array($t) && isset($t['id']) ? $t : null;
}

function ccl_player(int $id): ?array
{
    $p = api_try('/oyuncular/' . $id, [], 300, null);
    if (is_array($p) && isset($p[0]) && is_array($p[0])) {
        $p = $p[0];
    }
    return is_array($p) && isset($p['id']) ? $p : null;
}

function ccl_player_events(int $id): array
{
    $data = api_try('/mac-olaylari', ['oyuncu_id' => $id], null, []);
    $rows = $data['macOlaylari'] ?? $data;
    return is_array($rows) ? $rows : [];
}

/**
 * Sezondaki tüm maç olayları (gol, kart, kurtarış, pozisyon...). Tek istekte
 * maç id listesiyle çekilir; maç sayısı büyürse parçalara bölünür.
 * Dönüş: mac_id => [olaylar]
 */
function ccl_season_events(): array
{
    static $byMatch = null;
    if ($byMatch !== null) {
        return $byMatch;
    }
    $byMatch = [];
    $ids = [];
    foreach (ccl_matches() as $m) {
        if (match_is_played($m) || match_is_live($m)) {
            $ids[] = (int) $m['id'];
        }
    }
    foreach (array_chunk($ids, 120) as $chunk) {
        $data = api_try('/mac-olaylari', ['mac_ids' => implode(',', $chunk), 'limit' => 20000], null, []);
        $rows = isset($data['macOlaylari']) && is_array($data['macOlaylari']) ? $data['macOlaylari'] : [];
        foreach ($rows as $ev) {
            $byMatch[(int) $ev['mac_id']][] = $ev;
        }
    }
    foreach ($byMatch as &$events) {
        usort($events, 'compare_events');
    }
    unset($events);
    return $byMatch;
}

function compare_events(array $a, array $b): int
{
    return [(int) ($a['devre'] ?? 1), (int) ($a['dakika'] ?? 0), (int) $a['id']]
        <=> [(int) ($b['devre'] ?? 1), (int) ($b['dakika'] ?? 0), (int) $b['id']];
}

/** Bir maçtaki oyuncuların maç içi istatistikleri (gol, asist, kurtarış, puan...). */
function ccl_match_player_stats(int $matchId): array
{
    return ccl_player_stats(['matchId' => $matchId, 'limit' => 100, 'sort' => 'mostMatches'])['players'];
}

/* ------------------------------------------------------------------ */
/*  Maç medyası ve haber alanları                                      */
/* ------------------------------------------------------------------ */

function media_value($v): string
{
    $v = trim((string) $v);
    return ($v === '' || strtolower($v) === 'none' || strtolower($v) === 'null') ? '' : $v;
}

/** Maçın kapak fotoğrafı (yönetim panelinden yüklenen match_picture). */
function match_cover(array $m): string
{
    $url = media_value($m['match_picture'] ?? '');
    return preg_match('#^https?://#i', $url) ? $url : '';
}

function match_video_url(array $m): string
{
    $url = media_value($m['match_video'] ?? '');
    return preg_match('#^https?://#i', $url) ? $url : '';
}

function match_interview_url(array $m): string
{
    foreach (['match_interview', 'post_roportaj'] as $k) {
        $url = media_value($m[$k] ?? '');
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
    }
    return '';
}

/** YouTube bağlantısından video kimliği. */
function youtube_id(string $url): string
{
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|live/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return '';
}

/** Panelde girilen "maçın enleri" (post_enler / post_macin_enleri JSON). */
function match_awards(array $m): array
{
    foreach (['post_enler', 'post_macin_enleri'] as $k) {
        $raw = media_value($m[$k] ?? '');
        if ($raw === '') {
            continue;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            continue;
        }
        $labels = [
            'best_player' => 'Maçın Oyuncusu', 'best_goalkeeper' => 'En İyi Kaleci', 'best_defender' => 'En İyi Defans',
            'best_midfielder' => 'En İyi Orta Saha', 'best_forward' => 'En İyi Forvet', 'best_goal' => 'Maçın Golü',
            'best_save' => 'Maçın Kurtarışı',
        ];
        $out = [];
        foreach ($labels as $key => $label) {
            $name = trim((string) ($data[$key . '_name'] ?? $data[$key] ?? ''));
            if ($name !== '') {
                $out[$key] = ['label' => $label, 'name' => $name, 'id' => (int) ($data[$key . '_id'] ?? 0)];
            }
        }
        if ($out) {
            return $out;
        }
    }
    return [];
}

/** Oyuncunun bu sezondaki maç günlüğü (rakip, skor, puan, gol, asist...). */
function ccl_player_match_log(int $id): array
{
    $s = ccl_scope();
    $data = api_try('/oyuncular/' . $id . '/match-log', [
        'cityId' => $s['cityId'], 'leagueId' => $s['leagueId'], 'seasonId' => $s['seasonId'], 'limit' => 100,
    ], null, []);
    return [
        'matches' => isset($data['matches']) && is_array($data['matches']) ? $data['matches'] : [],
        'summary' => isset($data['summary']) && is_array($data['summary']) ? $data['summary'] : [],
    ];
}

/** Yayınlanmış "haftanın takımı / enleri" setleri. */
function ccl_weekly_awards(int $limit = 6): array
{
    $s = ccl_scope();
    $data = api_try('/api/weekly-awards/public', [
        'cityId' => $s['cityId'], 'leagueId' => $s['leagueId'], 'seasonId' => $s['seasonId'], 'limit' => $limit,
    ], 300, []);
    return isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
}

/** Yalnızca CCL CUP ligi için yazılmış haberler (diğer liglerin ve genel haberler hariç). */
function ccl_news(int $limit = 12): array
{
    $s = ccl_scope();
    $data = api_try('/api/news', [
        'cityId' => $s['cityId'], 'leagueId' => $s['leagueId'], 'seasonId' => $s['seasonId'], 'limit' => $limit,
    ], 300, []);
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    return array_values(array_filter($items, static function ($n) use ($s) {
        return (int) ($n['league_id'] ?? 0) === $s['leagueId'];
    }));
}

/** Maç galerisindeki fotoğraf adresleri (virgül/satır ayrımlı ya da JSON dizi). */
function match_gallery(array $m): array
{
    $raw = media_value($m['match_images'] ?? '');
    if ($raw === '') {
        return [];
    }
    $list = json_decode($raw, true);
    if (!is_array($list)) {
        $list = preg_split('/[\s,]+/', $raw) ?: [];
    }
    $out = [];
    foreach ($list as $u) {
        $u = trim((string) (is_array($u) ? ($u['url'] ?? '') : $u));
        if (preg_match('#^https?://#i', $u)) {
            $out[] = $u;
        }
    }
    return array_values(array_unique($out));
}

/** Görsel dosyası mı (galeri bağlantısı değil)? */
function is_image_url(string $u): bool
{
    return (bool) preg_match('#\.(jpe?g|png|webp|gif)(\?.*)?$#i', $u);
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

/** Henüz oynanmamış ve canlı olmayan maç mı? */
function match_is_upcoming(array $m): bool
{
    return !match_is_played($m) && !match_is_live($m);
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
