<?php
/**
 * Eleme etapları (Altın Kupa, Gümüş Kupa ...) — kupa yolu / eleme ağacı.
 *
 * elitlig panelindeki "Gruplar & Etaplar" kurgusu kullanılır:
 *  - group_kind "grup" lig usulü gruplardır (puan tablosu),
 *  - group_kind "etap" eleme turlarıdır (eşleşmeler).
 *
 * Etaplar "kupa"lara ayrılır: bir etabın kontenjanları başka bir etabın
 * eşleşme GALİPLERİNDEN geliyorsa (ör. Çeyrek Final ← Son 16 galipleri) iki
 * etap aynı kupanın ardışık turlarıdır. Kaynağı lig sıralaması, doğrudan takım
 * ya da başka bir etabın MAĞLUPLARI olan etap yeni bir kupanın ilk turudur
 * (ör. Son 16'da elenenlerden oluşan Gümüş Kupa).
 */

/** Etap adındaki tur ifadesi (Son 16, Çeyrek Final, Final ...). */
const CUP_STAGE_PATTERN = '/\s*[-–·:]?\s*((SON|LAST)\s*\d+(\s*TURU)?|ÇEYREK\s*F[İI]NAL|YARI\s*F[İI]NAL|F[İI]NAL|\d+\.\s*(ELEME\s*)?TURU?|ELEME\s*TURU|PLAY[\s-]?OFF|ÜÇÜNCÜLÜK(\s*MAÇI)?)\s*$/iu';

/** Turun kısa adı: kupa adı (ortak önek) çıkarılmış hâli. */
function cup_stage_label(string $name, string $cupName): string
{
    $name = trim($name);
    if ($cupName !== '' && mb_stripos($name, $cupName) === 0) {
        $rest = trim(mb_substr($name, mb_strlen($cupName)), " \t-–·:");
        if ($rest !== '') {
            return $rest;
        }
    }
    if (preg_match(CUP_STAGE_PATTERN, $name, $m)) {
        return trim($m[1]);
    }
    return $name;
}

/** Adların kelime bazında ortak öneki. */
function common_word_prefix(array $names): string
{
    $split = array_map(static function ($n) {
        return preg_split('/\s+/u', trim((string) $n)) ?: [];
    }, $names);
    if (!$split) {
        return '';
    }
    $prefix = [];
    foreach ($split[0] as $i => $word) {
        foreach ($split as $words) {
            if (!isset($words[$i]) || mb_strtoupper($words[$i]) !== mb_strtoupper($word)) {
                break 2;
            }
        }
        $prefix[] = $word;
    }
    return implode(' ', $prefix);
}

/**
 * Sezondaki kupalar.
 * Her kupa: key, name, root_id, stages[] (tur sırasıyla), champion.
 * Her tur: group, label, matchups (ağaç sırasına dizilmiş), legs2 (rövanşlar).
 */
function ccl_cups(): array
{
    static $cups = null;
    if ($cups !== null) {
        return $cups;
    }
    $cups = [];
    $stages = [];
    foreach (ccl_groups()['groups'] as $g) {
        if (($g['group_kind'] ?? 'grup') === 'etap' && ($g['show_in_bracket'] ?? true) !== false) {
            $stages[(int) $g['id']] = $g;
        }
    }
    if (!$stages) {
        return $cups;
    }

    // Etabın bir önceki turu: kontenjanlarının galibini aldığı tek etap.
    $parentOf = [];
    foreach ($stages as $id => $g) {
        $sources = [];
        foreach ($g['slots'] ?? [] as $slot) {
            if (($slot['source_type'] ?? '') === 'MATCH_WINNER' && isset($stages[(int) $slot['source_group_id']])) {
                $sources[(int) $slot['source_group_id']] = true;
            } else {
                $sources = [];
                break;
            }
        }
        $parentOf[$id] = count($sources) === 1 ? (int) key($sources) : 0;
    }

    $rootOf = static function (int $id) use ($parentOf): int {
        $seen = [];
        while (!empty($parentOf[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = $parentOf[$id];
        }
        return $id;
    };

    $byRoot = [];
    foreach ($stages as $id => $g) {
        $byRoot[$rootOf($id)][] = $g;
    }

    $sortStages = static function ($a, $b) {
        return [(int) ($a['depth'] ?? 0), (int) ($a['group_priority'] ?? 999), (int) $a['id']]
            <=> [(int) ($b['depth'] ?? 0), (int) ($b['group_priority'] ?? 999), (int) $b['id']];
    };

    foreach ($byRoot as $rootId => $list) {
        usort($list, $sortStages);
        $names = array_column($list, 'group_name');
        $cupName = count($names) > 1 ? common_word_prefix($names) : '';
        if ($cupName === '') {
            $cupName = trim((string) preg_replace(CUP_STAGE_PATTERN, '', (string) $names[0]));
        }
        $displayName = $cupName !== '' ? $cupName : 'Eleme Etabı';

        $built = [];
        foreach ($list as $g) {
            $legs2 = [];
            foreach ($g['schedule'] ?? [] as $round) {
                foreach ($round['meetings'] ?? [] as $mt) {
                    if ((int) ($mt['leg'] ?? 1) === 2) {
                        $legs2[(int) $mt['away_slot_id'] . ':' . (int) $mt['home_slot_id']] = $mt;
                    }
                }
            }
            $built[] = [
                'group'    => $g,
                'label'    => cup_stage_label((string) $g['group_name'], $cupName),
                'matchups' => array_values($g['matchups'] ?? []),
                'legs2'    => $legs2,
            ];
        }
        $built = cup_order_matchups($built);

        $last = end($built);
        $champion = null;
        if ($last && count($last['matchups']) === 1) {
            $fm = $last['matchups'][0];
            $wid = (int) ($fm['winner_team_id'] ?? 0);
            if ($wid) {
                foreach (['home', 'away'] as $side) {
                    if ((int) ($fm[$side]['team']['team_id'] ?? 0) === $wid) {
                        $champion = $fm[$side]['team'];
                    }
                }
            }
        }

        $cups[] = [
            'key'      => 'kupa-' . $rootId,
            'root_id'  => (int) $rootId,
            'name'     => $displayName,
            'tone'     => cup_tone($displayName),
            'stages'   => $built,
            'champion' => $champion,
            'group_ids' => array_map(static function ($s) { return (int) $s['group']['id']; }, $built),
        ];
    }

    usort($cups, static function ($a, $b) {
        $pa = (int) ($a['stages'][0]['group']['group_priority'] ?? 999);
        $pb = (int) ($b['stages'][0]['group']['group_priority'] ?? 999);
        return [$pa, $a['root_id']] <=> [$pb, $b['root_id']];
    });
    return $cups;
}

/** Kupa rengi: adında altın/gümüş/bronz geçiyorsa ona göre. */
function cup_tone(string $name): string
{
    $n = mb_strtoupper($name);
    if (mb_strpos($n, 'ALTIN') !== false || mb_strpos($n, 'GOLD') !== false) return 'gold';
    if (mb_strpos($n, 'GÜMÜŞ') !== false || mb_strpos($n, 'GUMUS') !== false || mb_strpos($n, 'SILVER') !== false) return 'silver';
    if (mb_strpos($n, 'BRONZ') !== false) return 'bronze';
    return 'gold';
}

/**
 * Eşleşmeleri ağaç sırasına dizer: son turdan geriye doğru, her eşleşmenin
 * iki tarafını besleyen önceki tur eşleşmeleri alt alta gelir. Böylece
 * bağlantı çizgileri kesişmez (1-16, 8-9 ... sırası).
 */
function cup_order_matchups(array $stages): array
{
    for ($i = count($stages) - 2; $i >= 0; $i--) {
        $next = $stages[$i + 1];
        $prevId = (int) $stages[$i]['group']['id'];
        $slotById = [];
        foreach ($next['group']['slots'] ?? [] as $slot) {
            $slotById[(int) $slot['id']] = $slot;
        }
        $byIndex = [];
        foreach ($stages[$i]['matchups'] as $m) {
            $byIndex[(int) $m['index']] = $m;
        }
        $ordered = [];
        foreach ($next['matchups'] as $nm) {
            foreach (['home_slot_id', 'away_slot_id'] as $k) {
                $slot = $slotById[(int) ($nm[$k] ?? 0)] ?? null;
                if ($slot && ($slot['source_type'] ?? '') === 'MATCH_WINNER' && (int) $slot['source_group_id'] === $prevId) {
                    $idx = (int) $slot['source_matchup'];
                    if (isset($byIndex[$idx])) {
                        $ordered[] = $byIndex[$idx];
                        unset($byIndex[$idx]);
                    }
                }
            }
        }
        $stages[$i]['matchups'] = array_merge($ordered, array_values($byIndex));
    }
    return $stages;
}

/** Kupa, anahtar ya da içindeki bir etabın id'si ile bulunur. */
function ccl_cup_find(string $key): ?array
{
    foreach (ccl_cups() as $cup) {
        if ($cup['key'] === $key || (ctype_digit($key) && in_array((int) $key, $cup['group_ids'], true))) {
            return $cup;
        }
    }
    return null;
}

/** Sezon maçları id'ye göre (canlı/oynandı bilgisi için). */
function ccl_matches_by_id(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (ccl_matches() as $m) {
            $map[(int) $m['id']] = $m;
        }
    }
    return $map;
}

/**
 * Eşleşmenin bir ayağının görüntülenecek hâli:
 * id, played, live, home/away skor (ilk maçın ev sahibine göre), tarih metni.
 */
function cup_leg_info(?array $meetingMatch, int $homeTeamId = 0): ?array
{
    if (!$meetingMatch || empty($meetingMatch['id'])) {
        return null;
    }
    $id = (int) $meetingMatch['id'];
    $full = ccl_matches_by_id()[$id] ?? null;
    if ($full) {
        $played = match_is_played($full);
        $live = match_is_live($full);
        $hs = (int) ($full['first_team_score'] ?? 0);
        $as = (int) ($full['second_team_score'] ?? 0);
        $when = trim(fmt_date($full) . (fmt_time($full) ? ' · ' . fmt_time($full) : ''));
        $venue = (string) ($full['match_field'] ?? '');
    } else {
        $played = ($meetingMatch['status'] ?? '') === 'yayinlanmis' && $meetingMatch['home_score'] !== null;
        $live = false;
        $hs = (int) ($meetingMatch['home_score'] ?? 0);
        $as = (int) ($meetingMatch['away_score'] ?? 0);
        $fake = ['date' => $meetingMatch['date'] ?? '', 'time' => $meetingMatch['time'] ?? ''];
        $when = trim(fmt_date($fake) . (fmt_time($fake) ? ' · ' . fmt_time($fake) : ''));
        $venue = (string) ($meetingMatch['venue'] ?? '');
    }
    // Skor eşleşmenin ev sahibine göre: maç ters kaydedildiyse çevrilir.
    $matchHome = (int) ($full['home_team_id'] ?? $meetingMatch['home_team_id'] ?? 0);
    if ($homeTeamId && $matchHome && $matchHome !== $homeTeamId) {
        [$hs, $as] = [$as, $hs];
    }
    return ['id' => $id, 'played' => $played, 'live' => $live, 'home' => $hs, 'away' => $as, 'when' => $when, 'venue' => $venue];
}

/** Henüz belli olmayan tarafın kısa açıklaması. */
function cup_pending_label(array $side, array $cup): string
{
    $label = (string) ($side['label'] ?? '');
    if (preg_match('/^(.*)\s+(\d+)\.\s*eşleşme\s+(galibi|mağlubu)$/iu', $label, $m)) {
        $stageName = trim($m[1]);
        foreach ($cup['stages'] as $st) {
            if (mb_strtoupper((string) $st['group']['group_name']) === mb_strtoupper($stageName)) {
                $stageName = $st['label'];
            }
        }
        $stageName = (string) preg_replace(['/ÇEYREK\s*F[İI]NAL/iu', '/YARI\s*F[İI]NAL/iu'], ['Ç. Final', 'Y. Final'], $stageName);
        return $stageName . ' ' . $m[2] . '. ' . mb_strtolower($m[3]);
    }
    return $label !== '' ? $label : 'Belirlenecek';
}

/** Eşleşme kutusu (ağaçta ve tur listelerinde ortak). */
function render_cup_match(array $mu, array $stage, array $cup, bool $compact = false): string
{
    $homeTid = (int) ($mu['home']['team']['team_id'] ?? 0);
    $leg1 = cup_leg_info($mu['match'] ?? null, $homeTid);
    $leg2 = null;
    $double = !empty($stage['group']['double_round']);
    if ($double) {
        $rev = $stage['legs2'][(int) $mu['home_slot_id'] . ':' . (int) $mu['away_slot_id']] ?? null;
        $leg2 = $rev ? cup_leg_info($rev['match'] ?? null, $homeTid) : null;
    }
    $winner = (int) ($mu['winner_team_id'] ?? 0);
    $decided = (string) ($mu['decided_by'] ?? '');
    $live = ($leg1 && $leg1['live']) || ($leg2 && $leg2['live']);
    $anyScore = ($leg1 && ($leg1['played'] || $leg1['live'])) || ($leg2 && ($leg2['played'] || $leg2['live']));
    $link = $leg2 && ($leg2['live'] || ($leg1 && $leg1['played'])) ? $leg2['id'] : ($leg1['id'] ?? 0);

    $score = static function (string $side) use ($leg1, $leg2, $mu, $double) {
        if ($double && !empty($mu['aggregate'])) {
            return (int) $mu['aggregate'][$side];
        }
        $v = null;
        foreach ([$leg1, $leg2] as $l) {
            if ($l && ($l['played'] || $l['live'])) {
                $v = (int) $v + (int) $l[$side];
            }
        }
        return $v;
    };

    $rows = '';
    foreach (['home', 'away'] as $side) {
        $team = $mu[$side]['team'] ?? null;
        $tid = (int) ($team['team_id'] ?? 0);
        $isWin = $winner && $tid === $winner;
        $isOut = $winner && $tid && $tid !== $winner;
        $sc = $anyScore ? $score($side) : null;
        $name = $team ? (string) $team['team_name'] : cup_pending_label($mu[$side] ?? [], $cup);
        $rows .= '<span class="bm-row' . ($isWin ? ' is-win' : '') . ($isOut ? ' is-out' : '') . ($team ? '' : ' is-tbd') . '">'
            . ($team ? team_badge($team['logo'] ?? team_logo($tid), $name, 'xs') : '<span class="bm-q" aria-hidden="true">?</span>')
            . '<span class="bm-name" title="' . e($name) . '">' . e($name) . '</span>'
            . ($isWin && $decided === 'manual' ? '<span class="bm-tag" title="Penaltılarla / kurayla">P</span>' : '')
            . '<span class="bm-score">' . ($sc === null ? '' : (int) $sc) . '</span>'
            . '</span>';
    }

    $meta = '';
    if ($live) {
        $meta = '<span class="pill pill-live">CANLI</span>';
    } elseif ($leg1 && !$leg1['played']) {
        $meta = e($leg1['when'] ?: 'Tarih belli değil');
    } elseif ($leg2 && !$leg2['played']) {
        $meta = 'Rövanş: ' . e($leg2['when'] ?: 'tarih belli değil');
    } elseif ($leg1) {
        $meta = 'MS' . ($double && $leg2 ? ' · ' . $leg1['home'] . '-' . $leg1['away'] . ', ' . $leg2['home'] . '-' . $leg2['away'] : '');
    } else {
        $meta = 'Maç programı bekleniyor';
    }
    if (!$compact && $leg1 && !$leg1['played'] && !$live && $leg1['venue'] !== '') {
        $meta .= ' · ' . e($leg1['venue']);
    }

    $tag = $link ? 'a' : 'div';
    $href = $link ? ' href="' . e(match_url((int) $link)) . '"' : '';
    return '<' . $tag . ' class="bm' . ($live ? ' is-live' : '') . ($winner ? ' is-done' : '') . '"' . $href . '>'
        . $rows
        . '<span class="bm-meta">' . $meta . '</span>'
        . '</' . $tag . '>';
}

/**
 * Kupa yolu (eleme ağacı). Turlar soldan sağa, sonunda şampiyon kutusu.
 * Eşleşme sayısı her turda yarıya iniyorsa bağlantı çizgileri çizilir.
 */
function render_cup_bracket(array $cup, bool $compact = false): string
{
    $stages = $cup['stages'];
    $n = count($stages);
    ob_start(); ?>
    <div class="bracket-scroll">
      <div class="bracket tone-<?= e($cup['tone']) ?><?= $compact ? ' is-compact' : '' ?>">
        <?php foreach ($stages as $i => $st):
            $count = count($st['matchups']);
            $nextCount = isset($stages[$i + 1]) ? count($stages[$i + 1]['matchups']) : 0;
            $joins = $nextCount > 0 && $count === $nextCount * 2;
            $fed = $i > 0 && count($stages[$i - 1]['matchups']) === $count * 2; ?>
          <div class="br-col<?= $joins ? ' joins' : '' ?><?= $fed ? ' fed' : '' ?>">
            <div class="br-head"><?= e($st['label']) ?></div>
            <div class="br-body">
              <?php if ($joins): foreach (array_chunk($st['matchups'], 2) as $pair): ?>
                <div class="br-pair">
                  <?php foreach ($pair as $mu): ?><div class="br-cell"><?= render_cup_match($mu, $st, $cup, true) ?></div><?php endforeach; ?>
                </div>
              <?php endforeach; else: foreach ($st['matchups'] as $mu): ?>
                <div class="br-cell"><?= render_cup_match($mu, $st, $cup, true) ?></div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($n && count(end($stages)['matchups']) === 1): $ch = $cup['champion']; ?>
          <div class="br-col br-champ fed">
            <div class="br-head">Şampiyon</div>
            <div class="br-body"><div class="br-cell">
              <div class="champ<?= $ch ? ' is-set' : '' ?>">
                <span class="champ-cup" aria-hidden="true">🏆</span>
                <?php if ($ch): ?>
                  <a href="<?= e(team_url((int) $ch['team_id'])) ?>"><?= team_badge($ch['logo'] ?? null, (string) $ch['team_name'], 'md') ?><strong><?= e($ch['team_name']) ?></strong></a>
                <?php else: ?>
                  <strong>?</strong><small>Finalden sonra belli olacak</small>
                <?php endif; ?>
                <span class="champ-name"><?= e($cup['name']) ?></span>
              </div>
            </div></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/** Bir turun eşleşmeleri liste hâlinde (tarih, saha, skor). */
function render_cup_stage_list(array $st, array $cup): string
{
    $g = $st['group'];
    $done = 0;
    foreach ($st['matchups'] as $mu) {
        if (!empty($mu['winner_team_id'])) $done++;
    }
    $total = count($st['matchups']);
    // Liste görünümünde eşleşmeler panel sırasıyla (1. maç, 2. maç ...).
    $list = $st['matchups'];
    usort($list, static function ($a, $b) { return (int) $a['index'] <=> (int) $b['index']; });
    ob_start(); ?>
    <div class="card stage-card">
      <div class="section-head">
        <h3><?= e($st['label']) ?></h3>
        <span class="muted small"><?= $total ?> eşleşme<?= !empty($g['double_round']) ? ' · rövanşlı' : '' ?> · <?= $done === $total && $total ? 'tamamlandı' : $done . '/' . $total . ' sonuçlandı' ?></span>
      </div>
      <div class="stage-grid">
        <?php foreach ($list as $mu): ?>
          <div class="stage-item"><span class="stage-no"><?= (int) $mu['index'] ?>. maç</span><?= render_cup_match($mu, $st, $cup) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
