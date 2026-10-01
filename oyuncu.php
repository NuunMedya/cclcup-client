<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
if (!$id) {
    not_found();
}

$index = ccl_player_index();
$stats = $index[$id] ?? null;
$player = ccl_player($id);
if (!$stats && $player) {
    foreach (ccl_player_stats(['search' => $player['player_name'] ?? '', 'limit' => 100])['players'] as $r) {
        if ((int) $r['playerId'] === $id) {
            $stats = $r;
        }
    }
}
if (!$stats) {
    not_found('Bu oyuncu CCL CUP\'ta yer almıyor.');
}

$name = (string) $stats['playerName'];
$img = $stats['playerImage'] ?? ($player['player_img'] ?? null);
$teamId = (int) ($stats['teamId'] ?? 0);
$teamName = (string) ($stats['teamName'] ?? '');
$teamLogo = $stats['teamLogo'] ?? team_logo($teamId);
$posShort = position_short($stats['position'] ?? '');
$isKeeper = $posShort === 'KL';

$log = ccl_player_match_log($id);
$seasons = ccl_player_seasons($id);
$career = ccl_player_career($id);
$market = ccl_player_market_value($id);
$model = season_model();
$matchMap = [];
foreach (ccl_matches() as $m) {
    $matchMap[(int) $m['id']] = $m;
}
$eventLog = $model['player_match'][$id] ?? [];

// Lig içi sıralamalar ve yüzdelikler (en az 1 maç oynayanlar)
$pool = array_values(array_filter($index, static function ($p) {
    return (int) $p['matchesPlayed'] > 0;
}));
$rankIn = static function (string $field) use ($pool, $stats) {
    $mine = (float) ($stats[$field] ?? 0);
    $better = 0;
    foreach ($pool as $p) {
        if ((float) ($p[$field] ?? 0) > $mine) $better++;
    }
    return $better + 1;
};
$percentile = static function (string $field) use ($pool, $stats) {
    $mp = max(1, (int) $stats['matchesPlayed']);
    $mine = (float) ($stats[$field] ?? 0) / $mp;
    if (!$pool) return 0.0;
    $below = 0;
    foreach ($pool as $p) {
        $v = (float) ($p[$field] ?? 0) / max(1, (int) $p['matchesPlayed']);
        if ($v < $mine) $below++;
    }
    return $below / count($pool);
};
$perMatch = static function (string $field) use ($stats) {
    return ratio($stats[$field] ?? 0, max(1, (int) $stats['matchesPlayed']));
};

$profileRows = $isKeeper
    ? [['saves', 'Kurtarış'], ['criticalBlocks', 'Kritik blok'], ['assists', 'Asist'], ['chancesCreated', 'Pozisyon üretme'], ['aerialDuelsWon', 'Hava topu'], ['totalGoals', 'Gol']]
    : [['totalGoals', 'Gol'], ['assists', 'Asist'], ['chancesCreated', 'Pozisyon üretme'], ['criticalBlocks', 'Kritik blok'], ['duelsWon', 'İkili mücadele'], ['aerialDuelsWon', 'Hava topu']];

$goals = (int) $stats['totalGoals'];
$kinds = [
    ['label' => 'Sağ ayak', 'value' => (int) $stats['rightFootGoals'], 'class' => 's1'],
    ['label' => 'Sol ayak', 'value' => (int) $stats['leftFootGoals'], 'class' => 's2'],
    ['label' => 'Kafa', 'value' => (int) $stats['headerGoals'], 'class' => 's3'],
    ['label' => 'Penaltı', 'value' => (int) $stats['penaltyGoals'], 'class' => 's4'],
    ['label' => 'Serbest vuruş', 'value' => (int) $stats['freeKickGoals'], 'class' => 's5'],
    ['label' => 'Uzaktan', 'value' => (int) $stats['longRightGoals'] + (int) $stats['longLeftGoals'], 'class' => 's6'],
];
$kinds = array_values(array_filter($kinds, static function ($k) { return $k['value'] > 0; }));

// Maç günlüğü: API'den gelen kayıt + olaylardan ek istatistikler
$logRows = $log['matches'];
if (!$logRows) {
    foreach ($eventLog as $mid => $c) {
        if (!isset($matchMap[$mid])) continue;
        $m = $matchMap[$mid];
        $home = (int) $m['home_team_id'] === (int) ($c['team'] ?? 0);
        $gf = $home ? (int) $m['first_team_score'] : (int) $m['second_team_score'];
        $ga = $home ? (int) $m['second_team_score'] : (int) $m['first_team_score'];
        $logRows[] = [
            'matchId' => $mid, 'isHome' => $home, 'opponentId' => $home ? (int) $m['away_team_id'] : (int) $m['home_team_id'],
            'opponentName' => $home ? $m['second_team_name'] : $m['first_team_name'], 'goalsFor' => $gf, 'goalsAgainst' => $ga,
            'result' => $gf > $ga ? 'G' : ($gf < $ga ? 'M' : 'B'), 'started' => !isset($c['sub_in']), 'goals' => (int) ($c['goal'] ?? 0),
            'assists' => (int) ($c['assist'] ?? 0), 'saves' => (int) ($c['save'] ?? 0), 'chancesCreated' => (int) ($c['chance'] ?? 0),
            'yellowCards' => (int) ($c['yellow'] ?? 0), 'redCards' => (int) ($c['red'] ?? 0), 'points' => 0,
        ];
    }
}
usort($logRows, static function ($a, $b) use ($matchMap) {
    $ka = isset($matchMap[(int) $a['matchId']]) ? match_sort_key($matchMap[(int) $a['matchId']]) : '';
    $kb = isset($matchMap[(int) $b['matchId']]) ? match_sort_key($matchMap[(int) $b['matchId']]) : '';
    return strcmp($kb, $ka);
});

// Maçın enleri ödülleri ve istatistiksel yıldızlıklar
$awardsWon = [];
$starCount = 0;
foreach ($model['played'] as $m) {
    foreach (match_awards($m) as $aw) {
        if ($aw['id'] === $id) {
            $awardsWon[] = ['label' => $aw['label'], 'match' => $m];
        }
    }
    $s = match_stars((int) $m['id'], 1);
    if ($s && $s[0]['player'] === $id) {
        $starCount++;
    }
}

$teammates = array_values(array_filter($index, static function ($p) use ($teamId, $id) {
    return (int) $p['teamId'] === $teamId && (int) $p['playerId'] !== $id;
}));
usort($teammates, static function ($a, $b) {
    return [$b['totalGoals'] + $b['assists'], $b['matchesPlayed']] <=> [$a['totalGoals'] + $a['assists'], $a['matchesPlayed']];
});

$careerTotals = [
    'Sezon' => count($seasons),
    'Maç' => $career['oynadigi_mac_sayisi'] ?? array_sum(array_column($seasons, 'matches')),
    'Gol' => $career['toplam_gol'] ?? array_sum(array_column($seasons, 'goals')),
    'Asist' => $career['asist'] ?? null,
];

$page = ['title' => $name, 'nav' => 'stats', 'description' => $name . ' (' . $teamName . ') — CCL CUP istatistikleri, maç maç performansı ve kariyeri.', 'image' => $img];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<header class="ph">
  <div class="ph-bg" aria-hidden="true"><?php if ($teamLogo): ?><img src="<?= e($teamLogo) ?>" alt=""><?php endif; ?></div>
  <div class="container ph-inner">
    <div class="ph-photo"><?= player_avatar($img, $name, 'hero') ?><?php if ($posShort): ?><span class="ph-pos pos-<?= e(strtolower($posShort)) ?>"><?= e($stats['position']) ?></span><?php endif; ?></div>
    <div class="ph-copy">
      <span class="eyebrow">CCL CUP Oyuncusu</span>
      <h1><?= e($name) ?></h1>
      <?php if ($teamId): ?>
        <a class="profile-team" href="<?= e(team_url($teamId)) ?>"><?= team_badge($teamLogo, $teamName, 'sm') ?> <?= e($teamName) ?></a>
      <?php endif; ?>
      <div class="ph-stats">
        <div><strong><?= (int) $stats['matchesPlayed'] ?></strong><span>Maç</span></div>
        <div><strong><?= $goals ?></strong><span>Gol</span></div>
        <div><strong><?= (int) $stats['assists'] ?></strong><span>Asist</span></div>
        <?php if ($isKeeper || (int) $stats['saves'] > 0): ?><div><strong><?= (int) $stats['saves'] ?></strong><span>Kurtarış</span></div><?php endif; ?>
        <div><strong><?= $goals + (int) $stats['assists'] ?></strong><span>Gol katkısı</span></div>
        <?php if ((float) $stats['totalPoints'] > 0): ?><div><strong><?= e(num($stats['averagePoints'], 1)) ?></strong><span>Ort. puan</span></div><?php endif; ?>
        <?php if ($market): ?><div><strong><?= e(num($market['currentValue'])) ?></strong><span>Piyasa değeri (<?= e($market['currency'] ?? 'ETL') ?>)</span></div><?php endif; ?>
      </div>
      <div class="th-badges">
        <?php if ($goals > 0): ?><span class="chip-lg">Gol krallığında <b><?= $rankIn('totalGoals') ?>.</b></span><?php endif; ?>
        <?php if ((int) $stats['assists'] > 0): ?><span class="chip-lg">Asistte <b><?= $rankIn('assists') ?>.</b></span><?php endif; ?>
        <?php if ((int) $stats['saves'] > 0): ?><span class="chip-lg">Kurtarışta <b><?= $rankIn('saves') ?>.</b></span><?php endif; ?>
        <?php if ($starCount): ?><span class="chip-lg chip-w"><b><?= $starCount ?></b> kez maçın yıldızı</span><?php endif; ?>
        <?php if ((int) $stats['captainAppearances'] > 0): ?><span class="chip-lg"><b><?= (int) $stats['captainAppearances'] ?></b> maç kaptan</span><?php endif; ?>
      </div>
    </div>
  </div>
</header>

<section class="container section">
  <div class="grid-main-side">
    <div class="card">
      <div class="section-head"><h2>Oyuncu Profili</h2><span class="muted small">Maç başı değerler, lig oyuncularıyla karşılaştırma</span></div>
      <div class="meters">
        <?php foreach ($profileRows as [$field, $label]):
          $pctl = $percentile($field); ?>
          <?= meter_row($label, $pctl, num($perMatch($field), 2) . ' / maç', 'Toplam ' . num($stats[$field] ?? 0) . ' · yüzdelik dilim ' . (int) round($pctl * 100)) ?>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="section-head"><h3>Gol Türleri</h3></div>
      <?php if ($kinds): ?>
        <div class="donut-wrap">
          <?= svg_donut($kinds, 'Gol türleri', (string) $goals) ?>
          <ul class="donut-legend">
            <?php foreach ($kinds as $k): ?><li><span class="lg <?= e($k['class']) ?>"></span><?= e($k['label']) ?><b><?= $k['value'] ?></b></li><?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <div class="empty-state small"><p>Bu sezon henüz gol yok.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="container section">
  <div class="tiles tiles-6">
    <?= stat_tile('İlk 11', (int) $stats['started'], (int) $stats['enteredAsSubstitute'] . ' kez sonradan girdi') ?>
    <?= stat_tile('Maç başı gol', num($stats['goalsPerMatch'], 2)) ?>
    <?= stat_tile('Pozisyon üretme', (int) $stats['chancesCreated']) ?>
    <?= stat_tile('Kritik blok', (int) $stats['criticalBlocks']) ?>
    <?= stat_tile('Faul', (int) $stats['fouls']) ?>
    <?= stat_tile('Kart', (int) $stats['yellowCards'] . ' / ' . (int) $stats['redCards'], 'sarı / kırmızı') ?>
  </div>
</section>

<section class="container section">
  <div class="card">
    <div class="section-head"><h2>Maç Maç Performans</h2><span class="muted small"><?= count($logRows) ?> maç</span></div>
    <?php if (!$logRows): ?>
      <div class="empty-state small"><p>Bu sezon için maç kaydı bulunmuyor.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th class="left">Maç</th><th class="hide-sm">Tarih</th><th>Sonuç</th><th title="İlk 11 / yedek">Rol</th><th title="Gol">G</th><th title="Asist">A</th><th title="Pozisyon">Poz</th><th title="Kurtarış">Kur</th><th>Kart</th><th title="Maç puanı">Puan</th></tr></thead>
        <tbody>
        <?php foreach ($logRows as $r):
          $mid = (int) $r['matchId'];
          $m = $matchMap[$mid] ?? null;
          $c = $eventLog[$mid] ?? [];
          $res = ['G' => 'win', 'B' => 'draw', 'M' => 'loss'][$r['result']] ?? 'draw';
          $role = !empty($r['started']) ? 'İlk 11' : 'Yedek';
          if (isset($c['sub_in'])) $role = (int) $c['sub_in'] . "' girdi";
          if (isset($c['sub_out'])) $role .= ' · ' . (int) $c['sub_out'] . "' çıktı"; ?>
          <tr>
            <td class="left"><a class="team-cell" href="<?= e(match_url($mid)) ?>"><?= team_badge(team_logo((int) $r['opponentId']), (string) $r['opponentName'], 'xs') ?><span><?= !empty($r['isHome']) ? '' : '@ ' ?><?= e($r['opponentName']) ?></span></a></td>
            <td class="hide-sm muted"><?= $m ? e(fmt_date($m)) : '' ?></td>
            <td><span class="form form-<?= $res ?>"><?= e($r['result']) ?></span> <b><?= (int) $r['goalsFor'] ?>-<?= (int) $r['goalsAgainst'] ?></b></td>
            <td class="muted small"><?= e($role) ?></td>
            <td><?= (int) $r['goals'] ? '<strong>' . (int) $r['goals'] . '</strong>' : '–' ?></td>
            <td><?= (int) $r['assists'] ?: '–' ?></td>
            <td><?= (int) ($r['chancesCreated'] ?? ($c['chance'] ?? 0)) ?: '–' ?></td>
            <td><?= (int) $r['saves'] ?: '–' ?></td>
            <td><?= str_repeat(event_icon('yellow'), (int) $r['yellowCards']) . str_repeat(event_icon('red'), (int) $r['redCards']) ?: '–' ?></td>
            <td><?= (float) $r['points'] ? '<span class="rating">' . e(num($r['points'], 1)) . '</span>' : '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="container section">
  <div class="grid-2">
    <div class="card">
      <div class="section-head"><h3>ElitLig Kariyeri</h3><span class="muted small">Tüm lig ve sezonlar</span></div>
      <div class="tiles tiles-4">
        <?php foreach ($careerTotals as $label => $v): if ($v === null) continue; ?><?= stat_tile($label, num($v)) ?><?php endforeach; ?>
      </div>
      <?php if ($seasons): ?>
      <div class="table-wrap section-gap">
        <table class="table">
          <thead><tr><th class="left">Sezon</th><th>M</th><th>G</th><th>G-B-M</th><th>SK</th></tr></thead>
          <tbody>
            <?php foreach ($seasons as $s): ?>
              <tr<?= (int) $s['season_id'] === ccl_scope()['seasonId'] ? ' class="highlight"' : '' ?>>
                <td class="left"><?= e($s['season_label']) ?></td><td><?= (int) $s['matches'] ?></td><td><strong><?= (int) $s['goals'] ?></strong></td>
                <td><?= (int) $s['wins'] ?>-<?= (int) $s['draws'] ?>-<?= (int) $s['losses'] ?></td><td><?= (int) $s['yellow_cards'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <div class="stack">
      <?php if ($awardsWon): ?>
        <div class="card"><div class="section-head"><h3>Ödüller</h3></div>
          <ul class="achievements">
            <?php foreach ($awardsWon as $a): ?>
              <li><span class="trophy">🏅</span><a href="<?= e(match_url((int) $a['match']['id'])) ?>"><b><?= e($a['label']) ?></b><small><?= e($a['match']['first_team_name'] . ' - ' . $a['match']['second_team_name'] . ' · ' . fmt_date($a['match'])) ?></small></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if ($teammates): ?>
        <div class="card"><div class="section-head"><h3>Takım Arkadaşları</h3><a class="more" href="<?= e(team_url($teamId)) ?>">Kadro →</a></div>
          <div class="mate-grid">
            <?php foreach (array_slice($teammates, 0, 8) as $tm): ?>
              <a href="<?= e(player_url((int) $tm['playerId'])) ?>"><?= player_avatar($tm['playerImage'] ?? null, $tm['playerName'], 'md') ?><span><?= e($tm['playerName']) ?></span><small><?= (int) $tm['totalGoals'] ?> gol · <?= (int) $tm['assists'] ?> asist</small></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
