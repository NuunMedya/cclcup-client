<?php
/**
 * Sezon analizi: maç listesi ve maç olaylarından takım, oyuncu ve turnuva
 * istatistikleri türetir. Tüm hesaplar istek başına bir kez yapılır.
 */

const GOAL_BUCKETS = ['0-10', '11-20', '21-30', '31-40', '41-50', '50+'];

function goal_bucket(int $minute): int
{
    if ($minute <= 10) return 0;
    if ($minute <= 20) return 1;
    if ($minute <= 30) return 2;
    if ($minute <= 40) return 3;
    if ($minute <= 50) return 4;
    return 5;
}

/** Gol olay kodunu gol tipine çevirir. */
function goal_kind(string $code): string
{
    $c = strtoupper($code);
    $map = [
        'GOAL_RIGHT_FOOT' => 'right', 'GOAL_LEFT_FOOT' => 'left', 'GOAL_HEADER' => 'header',
        'GOAL_PENALTY' => 'penalty', 'PEN_GOAL' => 'penalty', 'GOAL_FREEKICK' => 'freekick',
        'GOAL_LONG_RIGHT' => 'long', 'GOAL_LONG_LEFT' => 'long',
    ];
    return $map[$c] ?? 'other';
}

const GOAL_KIND_LABELS = [
    'right' => 'Sağ ayak', 'left' => 'Sol ayak', 'header' => 'Kafa', 'penalty' => 'Penaltı',
    'freekick' => 'Serbest vuruş', 'long' => 'Uzaktan', 'other' => 'Diğer',
];

/**
 * Sezonun tüm oyuncuları: id => istatistik satırı (ad, foto, takım, toplamlar).
 */
function ccl_player_index(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }
    $index = [];
    $offset = 0;
    do {
        $page = ccl_player_stats(['limit' => 100, 'offset' => $offset, 'sort' => 'mostMatches']);
        foreach ($page['players'] as $p) {
            $index[(int) $p['playerId']] = $p;
        }
        $offset += 100;
    } while ($page['players'] && $offset < $page['count'] && $offset < 1000);
    return $index;
}

function player_name(int $id, string $fallback = 'Oyuncu'): string
{
    $idx = ccl_player_index();
    return isset($idx[$id]) ? (string) $idx[$id]['playerName'] : $fallback;
}

/** Bir maçın golleri: [side, minute, half, player_id, kind, own] */
function match_goals(array $m, array $events): array
{
    $homeId = (int) $m['home_team_id'];
    $awayId = (int) $m['away_team_id'];
    $goals = [];
    foreach ($events as $ev) {
        $info = event_info((string) $ev['olay_kodu']);
        if ($info['type'] !== 'goal' && $info['type'] !== 'own-goal') {
            continue;
        }
        $tid = (int) $ev['takim_id'];
        $side = $tid === $homeId ? 'home' : ($tid === $awayId ? 'away' : null);
        if (!$side) {
            continue;
        }
        $own = $info['type'] === 'own-goal';
        if ($own) {
            $side = $side === 'home' ? 'away' : 'home';
        }
        $goals[] = [
            'side' => $side,
            'minute' => (int) $ev['dakika'],
            'half' => (int) ($ev['devre'] ?? 1),
            'player' => (int) ($ev['oyuncu_id'] ?? 0),
            'kind' => $own ? 'own' : goal_kind((string) $ev['olay_kodu']),
            'own' => $own,
        ];
    }
    return $goals;
}

function empty_record(): array
{
    return ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0];
}

/**
 * Tüm sezon modeli.
 */
function season_model(): array
{
    static $model = null;
    if ($model !== null) {
        return $model;
    }

    $matches = ccl_matches();
    $eventsByMatch = ccl_season_events();
    $teamMap = ccl_team_map();

    $teams = [];
    foreach ($teamMap as $id => $t) {
        $teams[$id] = [
            'id' => $id, 'name' => $t['name'], 'logo' => $t['logo'],
            'all' => empty_record(), 'home' => empty_record(), 'away' => empty_record(),
            'cs' => 0, 'fts' => 0, 'pts' => 0,
            'buckets_for' => array_fill(0, 6, 0), 'buckets_against' => array_fill(0, 6, 0),
            'half_for' => [1 => 0, 2 => 0], 'half_against' => [1 => 0, 2 => 0],
            'kinds' => [], 'yellow' => 0, 'red' => 0, 'saves' => 0, 'chances' => 0, 'blocks' => 0,
            'fouls' => 0, 'subs' => 0, 'first_goal' => 0, 'first_goal_wins' => 0,
            'results' => [], 'best_win' => null, 'worst_loss' => null,
        ];
    }

    $played = [];
    $totals = [
        'matches' => 0, 'goals' => 0, 'home_wins' => 0, 'away_wins' => 0, 'draws' => 0,
        'yellow' => 0, 'red' => 0, 'saves' => 0, 'chances' => 0, 'kinds' => [],
        'buckets' => array_fill(0, 6, 0), 'half' => [1 => 0, 2 => 0],
    ];
    $records = ['biggest_win' => null, 'highest_scoring' => null, 'fastest_goal' => null, 'hattricks' => []];
    $playerMatch = []; // player_id => match_id => counts

    foreach ($matches as $m) {
        if (!match_is_played($m)) {
            continue;
        }
        $mid = (int) $m['id'];
        $h = (int) $m['home_team_id'];
        $a = (int) $m['away_team_id'];
        $hs = (int) $m['first_team_score'];
        $as = (int) $m['second_team_score'];
        $events = $eventsByMatch[$mid] ?? [];
        $goals = match_goals($m, $events);
        $played[] = $m;

        $totals['matches']++;
        $totals['goals'] += $hs + $as;
        if ($hs > $as) $totals['home_wins']++; elseif ($as > $hs) $totals['away_wins']++; else $totals['draws']++;

        $margin = abs($hs - $as);
        if ($margin > 0 && (!$records['biggest_win'] || $margin > $records['biggest_win']['value'])) {
            $records['biggest_win'] = ['value' => $margin, 'match' => $m];
        }
        if (!$records['highest_scoring'] || $hs + $as > $records['highest_scoring']['value']) {
            $records['highest_scoring'] = ['value' => $hs + $as, 'match' => $m];
        }

        foreach ([[$h, $a, $hs, $as, 'home'], [$a, $h, $as, $hs, 'away']] as [$tid, $oid, $f, $g, $venue]) {
            if (!isset($teams[$tid])) {
                continue;
            }
            $t = &$teams[$tid];
            $res = $f > $g ? 'w' : ($f < $g ? 'l' : 'd');
            foreach (['all', $venue] as $k) {
                $t[$k]['p']++;
                $t[$k][$res]++;
                $t[$k]['gf'] += $f;
                $t[$k]['ga'] += $g;
            }
            $t['pts'] += $res === 'w' ? 3 : ($res === 'd' ? 1 : 0);
            if ($g === 0) $t['cs']++;
            if ($f === 0) $t['fts']++;
            $t['results'][] = ['match' => $m, 'res' => $res, 'gf' => $f, 'ga' => $g, 'venue' => $venue, 'opp' => $oid];
            $diff = $f - $g;
            if ($diff > 0 && (!$t['best_win'] || $diff > $t['best_win']['diff'])) {
                $t['best_win'] = ['diff' => $diff, 'match' => $m];
            }
            if ($diff < 0 && (!$t['worst_loss'] || $diff < $t['worst_loss']['diff'])) {
                $t['worst_loss'] = ['diff' => $diff, 'match' => $m];
            }
            unset($t);
        }

        // İlk golü atan
        if ($goals) {
            $first = $goals[0];
            $ft = $first['side'] === 'home' ? $h : $a;
            if (isset($teams[$ft])) {
                $teams[$ft]['first_goal']++;
                if (($first['side'] === 'home' && $hs > $as) || ($first['side'] === 'away' && $as > $hs)) {
                    $teams[$ft]['first_goal_wins']++;
                }
            }
        }

        $goalsByPlayer = [];
        foreach ($goals as $gl) {
            $scorer = $gl['side'] === 'home' ? $h : $a;
            $conceder = $gl['side'] === 'home' ? $a : $h;
            $b = goal_bucket($gl['minute']);
            $half = $gl['half'] === 2 ? 2 : 1;
            if (isset($teams[$scorer])) {
                $teams[$scorer]['buckets_for'][$b]++;
                $teams[$scorer]['half_for'][$half]++;
                $kind = $gl['kind'];
                $teams[$scorer]['kinds'][$kind] = ($teams[$scorer]['kinds'][$kind] ?? 0) + 1;
            }
            if (isset($teams[$conceder])) {
                $teams[$conceder]['buckets_against'][$b]++;
                $teams[$conceder]['half_against'][$half]++;
            }
            $totals['buckets'][$b]++;
            $totals['half'][$half]++;
            $totals['kinds'][$gl['kind']] = ($totals['kinds'][$gl['kind']] ?? 0) + 1;
            if (!$gl['own'] && $gl['player']) {
                $goalsByPlayer[$gl['player']] = ($goalsByPlayer[$gl['player']] ?? 0) + 1;
            }
            if ($gl['half'] === 1 && $gl['minute'] > 0 && (!$records['fastest_goal'] || $gl['minute'] < $records['fastest_goal']['value'])) {
                $records['fastest_goal'] = ['value' => $gl['minute'], 'match' => $m, 'player' => $gl['own'] ? 0 : $gl['player']];
            }
        }
        foreach ($goalsByPlayer as $pid => $n) {
            if ($n >= 3) {
                $records['hattricks'][] = ['player' => $pid, 'goals' => $n, 'match' => $m];
            }
        }

        foreach ($events as $ev) {
            $info = event_info((string) $ev['olay_kodu']);
            $tid = (int) $ev['takim_id'];
            $type = $info['type'];
            if (isset($teams[$tid])) {
                if ($type === 'yellow') $teams[$tid]['yellow']++;
                if ($type === 'red') $teams[$tid]['red']++;
                if ($type === 'save') $teams[$tid]['saves']++;
                if ($type === 'chance') $teams[$tid]['chances']++;
                if ($type === 'block') $teams[$tid]['blocks']++;
                if ($type === 'foul') $teams[$tid]['fouls']++;
                if ($type === 'sub') $teams[$tid]['subs']++;
            }
            if ($type === 'yellow') $totals['yellow']++;
            if ($type === 'red') $totals['red']++;
            if ($type === 'save') $totals['saves']++;
            if ($type === 'chance') $totals['chances']++;

            // Oyuncu maç kaydı
            if ($type === 'sub') {
                foreach ([['oyuncu_giren_id', 'in'], ['oyuncu_cikan_id', 'out']] as [$k, $label]) {
                    $pid = (int) ($ev[$k] ?? 0);
                    if ($pid) {
                        $playerMatch[$pid][$mid]['sub_' . $label] = (int) $ev['dakika'];
                        $playerMatch[$pid][$mid]['team'] = $tid;
                    }
                }
                continue;
            }
            $pid = (int) ($ev['oyuncu_id'] ?? 0);
            if ($pid) {
                $key = $type === 'own-goal' ? 'own' : $type;
                $playerMatch[$pid][$mid][$key] = ($playerMatch[$pid][$mid][$key] ?? 0) + 1;
                $playerMatch[$pid][$mid]['team'] = $tid;
            }
        }
    }

    usort($records['hattricks'], static function ($x, $y) {
        return $y['goals'] <=> $x['goals'];
    });

    // Maç günlerine göre sıralama geçmişi
    $dates = [];
    foreach ($played as $m) {
        $dates[substr((string) $m['date'], 0, 10)] = true;
    }
    ksort($dates);
    $rankHistory = [];
    $running = [];
    foreach ($teams as $id => $t) {
        $running[$id] = ['pts' => 0, 'gd' => 0, 'gf' => 0, 'name' => $t['name']];
    }
    foreach (array_keys($dates) as $date) {
        foreach ($played as $m) {
            if (substr((string) $m['date'], 0, 10) !== $date) {
                continue;
            }
            $h = (int) $m['home_team_id'];
            $a = (int) $m['away_team_id'];
            $hs = (int) $m['first_team_score'];
            $as = (int) $m['second_team_score'];
            foreach ([[$h, $hs, $as], [$a, $as, $hs]] as [$tid, $f, $g]) {
                if (!isset($running[$tid])) {
                    continue;
                }
                $running[$tid]['pts'] += $f > $g ? 3 : ($f === $g ? 1 : 0);
                $running[$tid]['gd'] += $f - $g;
                $running[$tid]['gf'] += $f;
            }
        }
        $order = $running;
        uasort($order, static function ($x, $y) {
            return [$y['pts'], $y['gd'], $y['gf']] <=> [$x['pts'], $x['gd'], $x['gf']] ?: tr_compare($x['name'], $y['name']);
        });
        $rank = 0;
        foreach ($order as $tid => $row) {
            $rankHistory[$tid][$date] = ++$rank;
        }
    }

    return $model = [
        'played' => $played,
        'events' => $eventsByMatch,
        'teams' => $teams,
        'totals' => $totals,
        'records' => $records,
        'rank_history' => $rankHistory,
        'player_match' => $playerMatch,
        'dates' => array_keys($dates),
    ];
}

/** Takımın güncel serisi: ['type' => 'w'|'d'|'l'|'unbeaten', 'count' => n] */
function team_streak(array $results): array
{
    if (!$results) {
        return ['label' => '–', 'count' => 0];
    }
    $last = end($results)['res'];
    $count = 0;
    foreach (array_reverse($results) as $r) {
        if ($r['res'] !== $last) break;
        $count++;
    }
    $labels = ['w' => 'galibiyet', 'd' => 'beraberlik', 'l' => 'mağlubiyet'];
    return ['label' => $count . ' maçtır ' . $labels[$last], 'count' => $count, 'type' => $last];
}

const HIGHLIGHT_STATS = [
    'goal' => ['En çok gol', 'gol'],
    'assist' => ['En çok asist', 'asist'],
    'save' => ['En çok kurtarış', 'kurtarış'],
    'chance' => ['En çok pozisyon üreten', 'pozisyon'],
    'block' => ['En çok kritik blok', 'blok'],
    'duel' => ['En çok ikili mücadele', 'ikili'],
    'aerial' => ['En çok hava topu', 'hava topu'],
];

/**
 * Verilen maçlardaki öne çıkanlar: her istatistikte en yüksek değere sahip
 * oyuncu (doğrudan maç olaylarından; puanlama/formül yok).
 */
function highlights(array $matchIds): array
{
    $model = season_model();
    $ids = array_flip(array_map('intval', $matchIds));
    $totals = [];
    foreach ($model['player_match'] as $pid => $byMatch) {
        foreach ($byMatch as $mid => $c) {
            if (!isset($ids[$mid])) continue;
            foreach (array_keys(HIGHLIGHT_STATS) as $k) {
                if (!empty($c[$k])) {
                    $totals[$k][$pid]['value'] = ($totals[$k][$pid]['value'] ?? 0) + (int) $c[$k];
                    $totals[$k][$pid]['match'] = $mid;
                }
            }
        }
    }
    $out = [];
    foreach (HIGHLIGHT_STATS as $k => [$label, $unit]) {
        if (empty($totals[$k])) continue;
        $rows = $totals[$k];
        uasort($rows, static function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        $best = reset($rows);
        $pid = (int) key($rows);
        $ties = count(array_filter($rows, static function ($r) use ($best) {
            return $r['value'] === $best['value'];
        })) - 1;
        $out[] = ['key' => $k, 'label' => $label, 'unit' => $unit, 'player' => $pid, 'value' => $best['value'], 'match' => $best['match'], 'ties' => $ties];
    }
    return $out;
}

/** Bir oyuncunun bu sezondaki gol dakikaları. */
function player_goal_minutes(int $playerId): array
{
    $model = season_model();
    $out = [];
    foreach ($model['played'] as $m) {
        foreach (match_goals($m, $model['events'][(int) $m['id']] ?? []) as $g) {
            if (!$g['own'] && $g['player'] === $playerId) {
                $out[] = ['minute' => $g['minute'], 'half' => $g['half'], 'kind' => $g['kind'], 'match' => $m];
            }
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  Maç haberi (manşet)                                                */
/* ------------------------------------------------------------------ */

/**
 * Maçın haber başlığı, üst başlığı ve özeti. Panelde manşet/rapor girilmişse
 * o kullanılır; yoksa skor ve golcülerden otomatik haber metni oluşturulur.
 */
function match_story(array $m): array
{
    $home = (string) $m['first_team_name'];
    $away = (string) $m['second_team_name'];
    $hs = (int) $m['first_team_score'];
    $as = (int) $m['second_team_score'];
    $model = season_model();
    $events = $model['events'][(int) $m['id']] ?? [];
    $goals = match_goals($m, $events);

    $scorers = ['home' => [], 'away' => []];
    foreach ($goals as $g) {
        $name = $g['own'] ? 'k.k.' : player_name($g['player'], '');
        if ($name === '') continue;
        $scorers[$g['side']][$name] = ($scorers[$g['side']][$name] ?? 0) + 1;
    }

    $winner = $hs > $as ? $home : ($as > $hs ? $away : null);
    $loser = $hs > $as ? $away : ($as > $hs ? $home : null);
    $ws = max($hs, $as);
    $ls = min($hs, $as);
    $winSide = $hs > $as ? 'home' : 'away';

    $hattrick = null;
    foreach ($scorers as $side => $list) {
        foreach ($list as $name => $n) {
            if ($n >= 3 && $name !== 'k.k.' && (!$hattrick || $n > $hattrick['n'])) {
                $hattrick = ['name' => $name, 'n' => $n, 'side' => $side];
            }
        }
    }

    if (!$winner) {
        $kicker = 'Beraberlik';
        $headline = $hs === 0 ? $home . ' ile ' . $away . ' golsüz berabere' : $home . ' ile ' . $away . ' yenişemedi: ' . $hs . '-' . $as;
    } elseif ($hattrick && ($hattrick['side'] === $winSide)) {
        $kicker = $hattrick['n'] >= 4 ? $hattrick['n'] . ' gollü performans' : 'Hat-trick';
        $headline = $hattrick['name'] . ' sahneye çıktı: ' . $winner . ' ' . $ws . '-' . $ls . ' kazandı';
    } elseif ($ws - $ls >= 6) {
        $kicker = 'Farklı galibiyet';
        $headline = $winner . ' rakibine şans tanımadı: ' . $ws . '-' . $ls;
    } elseif ($hs + $as >= 9) {
        $kicker = 'Gol yağmuru';
        $headline = $home . ' ' . $hs . '-' . $as . ' ' . $away . ': ' . ($hs + $as) . ' gollü maçta kazanan ' . $winner;
    } elseif ($ws - $ls === 1) {
        $kicker = 'Kıl payı';
        $headline = $winner . ', ' . $loser . ' karşısında ' . $ws . '-' . $ls . ' galip geldi';
    } else {
        $kicker = 'Maç sonucu';
        $headline = $winner . ', ' . $loser . ' karşısında ' . $ws . '-' . $ls . ' kazandı';
    }

    $custom = media_value($m['post_manset'] ?? '') ?: media_value($m['match_title'] ?? '');
    // Panelde manşet, yalnızca "A vs B" kalıbından ibaret değilse kullanılır.
    if ($custom !== '' && !preg_match('/\bvs\.?\b/iu', $custom)) {
        $headline = $custom;
    }

    $report = media_value($m['post_rapor'] ?? '') ?: media_value($m['match_comment'] ?? '');
    if ($report === '') {
        $parts = [];
        $field = trim((string) ($m['match_field'] ?? ''));
        $intro = fmt_date($m) . ($field !== '' ? ' günü ' . $field . ' sahasında' : ' günü') . ' oynanan CCL CUP mücadelesinde ';
        if ($winner) {
            $parts[] = $intro . $winner . ', ' . $loser . ' karşısında ' . $ws . '-' . $ls . ' galip geldi.';
        } else {
            $parts[] = $intro . $home . ' ile ' . $away . ' ' . $hs . '-' . $as . ' berabere kaldı.';
        }
        foreach (['home' => $home, 'away' => $away] as $side => $team) {
            if (!$scorers[$side]) continue;
            $list = [];
            foreach ($scorers[$side] as $name => $n) {
                $list[] = $name === 'k.k.' ? 'kendi kalesine gol' : ($n > 1 ? $name . ' (' . $n . ')' : $name);
            }
            $parts[] = $team . ' adına gollerin sahibi: ' . implode(', ', $list) . '.';
        }
        $report = implode(' ', $parts);
    }

    return ['kicker' => $kicker, 'headline' => $headline, 'summary' => $report, 'scorers' => $scorers];
}

/* ------------------------------------------------------------------ */
/*  SVG grafikler (JS gerektirmez)                                     */
/* ------------------------------------------------------------------ */

/**
 * Dikey çubuk grafik. $series: [['label' => 'Attığı', 'values' => [...], 'class' => 'c-home'], ...]
 */
function svg_bar_chart(array $labels, array $series, string $title, int $height = 180): string
{
    $n = count($labels);
    $groups = max(1, $n);
    $max = 1;
    foreach ($series as $s) {
        foreach ($s['values'] as $v) $max = max($max, (int) $v);
    }
    $w = 560;
    $padL = 8; $padB = 26; $padT = 18;
    $plotH = $height - $padB - $padT;
    $gw = ($w - $padL * 2) / $groups;
    $bars = count($series);
    $bw = min(26, ($gw - 14) / max(1, $bars) - 2);
    $out = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-label="' . e($title) . '">';
    // ızgara
    for ($i = 0; $i <= 2; $i++) {
        $y = $padT + $plotH * $i / 2;
        $out .= '<line class="grid" x1="0" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '"/>';
    }
    foreach ($labels as $i => $label) {
        $cx = $padL + $gw * $i + $gw / 2;
        $startX = $cx - ($bars * ($bw + 2)) / 2;
        foreach ($series as $j => $s) {
            $v = (int) ($s['values'][$i] ?? 0);
            $bh = $v > 0 ? max(4, $plotH * $v / $max) : 0;
            $x = $startX + $j * ($bw + 2);
            $y = $padT + $plotH - $bh;
            if ($bh > 0) {
                $out .= '<path class="' . e($s['class']) . '" d="' . bar_path($x, $y, $bw, $bh) . '"><title>' . e($label . ' dk · ' . $s['label'] . ': ' . $v) . '</title></path>';
                $out .= '<text class="val" x="' . ($x + $bw / 2) . '" y="' . ($y - 4) . '" text-anchor="middle">' . $v . '</text>';
            }
        }
        $out .= '<text class="axis" x="' . $cx . '" y="' . ($height - 8) . '" text-anchor="middle">' . e($label) . '</text>';
    }
    $out .= '<line class="baseline" x1="0" x2="' . $w . '" y1="' . ($padT + $plotH) . '" y2="' . ($padT + $plotH) . '"/>';
    return $out . '</svg>';
}

/** Üstü yuvarlatılmış çubuk yolu. */
function bar_path(float $x, float $y, float $w, float $h): string
{
    $r = min(4, $w / 2, $h);
    return sprintf(
        'M%.1f,%.1fv%.1fq0,-%.1f %.1f,-%.1fh%.1fq%.1f,0 %.1f,%.1fv%.1fz',
        $x, $y + $h, -($h - $r), $r, $r, $r, $w - 2 * $r, $r, $r, $r, $h - $r
    );
}

/** Sıralama geçmişi çizgi grafiği (1 = zirve, yukarıda). */
function svg_rank_chart(array $history, int $teamCount, string $title): string
{
    $points = array_values($history);
    $dates = array_keys($history);
    $n = count($points);
    if ($n === 0) {
        return '';
    }
    $w = 560; $h = 200; $padX = 34; $padT = 16; $padB = 30;
    $plotW = $w - $padX * 2;
    $plotH = $h - $padT - $padB;
    $teamCount = max(2, $teamCount);
    $xy = [];
    foreach ($points as $i => $rank) {
        $x = $n === 1 ? $w / 2 : $padX + $plotW * $i / ($n - 1);
        $y = $padT + $plotH * ($rank - 1) / ($teamCount - 1);
        $xy[] = [$x, $y, $rank, $dates[$i]];
    }
    $out = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '">';
    foreach ([1, (int) ceil($teamCount / 2), $teamCount] as $r) {
        $y = $padT + $plotH * ($r - 1) / ($teamCount - 1);
        $out .= '<line class="grid" x1="' . $padX . '" x2="' . ($w - $padX) . '" y1="' . $y . '" y2="' . $y . '"/>';
        $out .= '<text class="axis" x="' . ($padX - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . $r . '.</text>';
    }
    if ($n > 1) {
        $d = '';
        foreach ($xy as $i => [$x, $y]) {
            $d .= ($i ? 'L' : 'M') . round($x, 1) . ',' . round($y, 1);
        }
        $out .= '<path class="line" d="' . $d . '"/>';
    }
    foreach ($xy as $i => [$x, $y, $rank, $date]) {
        $ts = strtotime($date . ' 12:00');
        $label = (int) date('j', $ts) . ' ' . TR_MONTHS_SHORT[(int) date('n', $ts)];
        $out .= '<circle class="dot" cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="6"><title>' . e($label . ': ' . $rank . '. sıra') . '</title></circle>';
        if ($i === $n - 1 || $n <= 8) {
            $out .= '<text class="val" x="' . round($x, 1) . '" y="' . round($y - 11, 1) . '" text-anchor="middle">' . $rank . '.</text>';
        }
        $out .= '<text class="axis" x="' . round($x, 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e($label) . '</text>';
    }
    return $out . '</svg>';
}

/** Halka grafik (gol türleri gibi parça-bütün ilişkileri için). */
function svg_donut(array $parts, string $title, string $center = ''): string
{
    $total = array_sum(array_column($parts, 'value'));
    $size = 180; $r = 70; $c = 2 * M_PI * $r;
    $out = '<svg class="chart donut" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="' . e($title) . '">';
    $out .= '<circle class="donut-track" cx="90" cy="90" r="' . $r . '"/>';
    $offset = 0;
    if ($total > 0) {
        foreach ($parts as $p) {
            if ($p['value'] <= 0) continue;
            $len = $c * $p['value'] / $total;
            $gap = count(array_filter($parts, static function ($x) { return $x['value'] > 0; })) > 1 ? 3 : 0;
            $out .= '<circle class="donut-seg ' . e($p['class']) . '" cx="90" cy="90" r="' . $r . '" stroke-dasharray="' . round(max(0, $len - $gap), 2) . ' ' . round($c, 2) . '" stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 90 90)"><title>' . e($p['label'] . ': ' . $p['value']) . '</title></circle>';
            $offset += $len;
        }
    }
    if ($center !== '') {
        $out .= '<text class="donut-num" x="90" y="92" text-anchor="middle">' . e($center) . '</text>';
        $out .= '<text class="donut-cap" x="90" y="112" text-anchor="middle">gol</text>';
    }
    return $out . '</svg>';
}
