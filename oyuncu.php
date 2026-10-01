<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
if (!$id) {
    not_found();
}

// Yalnızca CCL CUP sezonundaki istatistikler kullanılır.
$index = ccl_player_index();
$stats = $index[$id] ?? null;
if (!$stats) {
    not_found('Bu oyuncu CCL CUP\'ta yer almıyor.');
}

$name = (string) $stats['playerName'];
$img = $stats['playerImage'] ?? null;
$teamId = (int) ($stats['teamId'] ?? 0);
$teamName = (string) ($stats['teamName'] ?? '');
$teamLogo = $stats['teamLogo'] ?? team_logo($teamId);
$posShort = position_short($stats['position'] ?? '');
$posLabel = trim((string) ($stats['position'] ?? '')) ?: 'Oyuncu';
$isKeeper = $posShort === 'KL';

$model = season_model();
$matchMap = [];
foreach (ccl_matches() as $m) {
    $matchMap[(int) $m['id']] = $m;
}
$eventLog = $model['player_match'][$id] ?? [];
$log = ccl_player_match_log($id);

// Maç günlüğü (yalnızca bu sezonun CCL CUP maçları)
$logRows = [];
foreach ($log['matches'] as $r) {
    if (isset($matchMap[(int) $r['matchId']])) {
        $logRows[(int) $r['matchId']] = $r;
    }
}
foreach ($eventLog as $mid => $c) {
    if (isset($logRows[$mid]) || !isset($matchMap[$mid]) || !match_is_played($matchMap[$mid])) continue;
    $m = $matchMap[$mid];
    $home = (int) $m['home_team_id'] === (int) ($c['team'] ?? 0);
    $gf = $home ? (int) $m['first_team_score'] : (int) $m['second_team_score'];
    $ga = $home ? (int) $m['second_team_score'] : (int) $m['first_team_score'];
    $logRows[$mid] = [
        'matchId' => $mid, 'isHome' => $home, 'opponentId' => $home ? (int) $m['away_team_id'] : (int) $m['home_team_id'],
        'opponentName' => $home ? $m['second_team_name'] : $m['first_team_name'], 'goalsFor' => $gf, 'goalsAgainst' => $ga,
        'result' => $gf > $ga ? 'G' : ($gf < $ga ? 'M' : 'B'), 'started' => !isset($c['sub_in']),
        'goals' => (int) ($c['goal'] ?? 0), 'assists' => (int) ($c['assist'] ?? 0), 'saves' => (int) ($c['save'] ?? 0),
        'chancesCreated' => (int) ($c['chance'] ?? 0), 'yellowCards' => (int) ($c['yellow'] ?? 0), 'redCards' => (int) ($c['red'] ?? 0),
    ];
}
uasort($logRows, static function ($a, $b) use ($matchMap) {
    return strcmp(match_sort_key($matchMap[(int) $b['matchId']]), match_sort_key($matchMap[(int) $a['matchId']]));
});
$wins = $draws = $losses = 0;
foreach ($logRows as $r) {
    if ($r['result'] === 'G') $wins++; elseif ($r['result'] === 'M') $losses++; else $draws++;
}

// Ligdeki sıralar (gerçek toplamlar üzerinden)
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
$rankCards = [];
foreach ([
    'totalGoals' => ['Gol krallığı', 'gol'], 'assists' => ['Asist sıralaması', 'asist'], 'saves' => ['Kurtarış sıralaması', 'kurtarış'],
    'chancesCreated' => ['Pozisyon üretme', 'pozisyon'], 'criticalBlocks' => ['Kritik blok', 'blok'], 'duelsWon' => ['İkili mücadele', 'ikili'],
] as $field => [$label, $unit]) {
    if ((int) ($stats[$field] ?? 0) > 0) {
        $rankCards[] = ['label' => $label, 'unit' => $unit, 'value' => (int) $stats[$field], 'rank' => $rankIn($field), 'of' => count($pool)];
    }
}

// Gol dakikaları ve türleri
$goalMinutes = player_goal_minutes($id);
$goals = (int) $stats['totalGoals'];
$kinds = array_values(array_filter([
    ['label' => 'Sağ ayak', 'value' => (int) $stats['rightFootGoals'], 'class' => 's1', 'key' => 'right'],
    ['label' => 'Sol ayak', 'value' => (int) $stats['leftFootGoals'], 'class' => 's2', 'key' => 'left'],
    ['label' => 'Kafa', 'value' => (int) $stats['headerGoals'], 'class' => 's3', 'key' => 'header'],
    ['label' => 'Penaltı', 'value' => (int) $stats['penaltyGoals'], 'class' => 's4', 'key' => 'penalty'],
    ['label' => 'Serbest vuruş', 'value' => (int) $stats['freeKickGoals'], 'class' => 's5', 'key' => 'freekick'],
    ['label' => 'Uzaktan', 'value' => (int) $stats['longRightGoals'] + (int) $stats['longLeftGoals'], 'class' => 's6', 'key' => 'long'],
], static function ($k) { return $k['value'] > 0; }));
$kindClass = [];
foreach ($kinds as $k) $kindClass[$k['key']] = $k['class'];

// Maçın enleri ödülleri (panelden)
$awardsWon = [];
foreach ($model['played'] as $m) {
    foreach (match_awards($m) as $aw) {
        if ($aw['id'] === $id) {
            $awardsWon[] = ['label' => $aw['label'], 'match' => $m];
        }
    }
}

$teammates = array_values(array_filter($index, static function ($p) use ($teamId, $id) {
    return (int) $p['teamId'] === $teamId && (int) $p['playerId'] !== $id;
}));
usort($teammates, static function ($a, $b) {
    return [$b['totalGoals'] + $b['assists'], $b['matchesPlayed']] <=> [$a['totalGoals'] + $a['assists'], $a['matchesPlayed']];
});

$cardStats = $isKeeper
    ? [['MAÇ', $stats['matchesPlayed']], ['KURT', $stats['saves']], ['BLOK', $stats['criticalBlocks']], ['GOL', $goals], ['ASİST', $stats['assists']], ['POZ', $stats['chancesCreated']]]
    : [['MAÇ', $stats['matchesPlayed']], ['GOL', $goals], ['ASİST', $stats['assists']], ['POZ', $stats['chancesCreated']], ['BLOK', $stats['criticalBlocks']], ['İKİLİ', $stats['duelsWon']]];

$page = ['title' => $name, 'nav' => 'stats', 'description' => $name . ' (' . $teamName . ') — CCL CUP istatistikleri ve maç maç performansı.', 'image' => $img];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<header class="ph2">
  <div class="ph2-bg" aria-hidden="true"><?php if ($teamLogo): ?><img src="<?= e(media_url($teamLogo, 640)) ?>" alt=""><?php endif; ?></div>
  <div class="container ph2-inner">
    <div class="pcard">
      <div class="pcard-top">
        <span class="pcard-pos"><?= e($posShort ?: '—') ?></span>
        <?php if ($teamLogo): ?><span class="pcard-team"><?= team_badge($teamLogo, $teamName, 'sm') ?></span><?php endif; ?>
      </div>
      <div class="pcard-photo"><?= player_avatar($img, $name, 'card') ?></div>
      <div class="pcard-name"><?= e($name) ?></div>
      <div class="pcard-stats">
        <?php foreach ($cardStats as [$label, $v]): ?><div><b><?= (int) $v ?></b><span><?= e($label) ?></span></div><?php endforeach; ?>
      </div>
      <img class="pcard-brand" src="assets/img/logo-white.png" alt="" loading="lazy">
    </div>

    <div class="ph2-copy">
      <span class="eyebrow"><?= e($posLabel) ?> · <?= e(ccl_scope()['seasonName']) ?></span>
      <h1><?= e($name) ?></h1>
      <?php if ($teamId): ?>
        <a class="profile-team" href="<?= e(team_url($teamId)) ?>"><?= team_badge($teamLogo, $teamName, 'sm') ?> <?= e($teamName) ?></a>
      <?php endif; ?>
      <div class="big-nums">
        <div><b><?= (int) $stats['matchesPlayed'] ?></b><span>Maç</span></div>
        <div class="accent"><b><?= $goals ?></b><span>Gol</span></div>
        <div><b><?= (int) $stats['assists'] ?></b><span>Asist</span></div>
        <?php if ($isKeeper || (int) $stats['saves'] > 0): ?><div><b><?= (int) $stats['saves'] ?></b><span>Kurtarış</span></div><?php endif; ?>
      </div>
      <?php if ($logRows): ?>
        <div class="wdl" title="Oynadığı maçlarda takımının sonuçları">
          <?php $tot = max(1, count($logRows)); ?>
          <span class="wdl-w" style="flex: <?= $wins ?>"><?= $wins ? $wins . ' G' : '' ?></span>
          <span class="wdl-d" style="flex: <?= $draws ?>"><?= $draws ? $draws . ' B' : '' ?></span>
          <span class="wdl-l" style="flex: <?= $losses ?>"><?= $losses ? $losses . ' M' : '' ?></span>
        </div>
        <small class="ph2-note">Oynadığı <?= count($logRows) ?> maçta takımının sonuçları</small>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($rankCards): ?>
<section class="container section">
  <div class="section-head"><h2>Ligdeki Yeri</h2><span class="muted small"><?= count($pool) ?> oyuncu arasında</span></div>
  <div class="rank-grid">
    <?php foreach ($rankCards as $rc): ?>
      <div class="rank-card<?= $rc['rank'] <= 3 ? ' is-top' : '' ?>">
        <span class="rank-pos"><?= $rc['rank'] ?><small>.</small></span>
        <span class="rank-info"><strong><?= e($rc['label']) ?></strong><small><?= $rc['value'] ?> <?= e($rc['unit']) ?></small></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($goals > 0): ?>
<section class="container section">
  <div class="grid-main-side">
    <div class="card">
      <div class="section-head"><h3>Gollerinin Dakikaları</h3><span class="muted small"><?= count($goalMinutes) ?> gol</span></div>
      <div class="minute-map">
        <div class="mm-track">
          <span class="mm-half" style="left: 50%"></span>
          <?php foreach ($goalMinutes as $g):
            $left = min(100, max(0, $g['minute'] / 50 * 100)); ?>
            <a class="mm-goal <?= e($kindClass[$g['kind']] ?? 's8') ?>" style="left: <?= round($left, 2) ?>%" href="<?= e(match_url((int) $g['match']['id'])) ?>" title="<?= e($g['minute'] . "' · " . (GOAL_KIND_LABELS[$g['kind']] ?? 'Gol') . ' · ' . $g['match']['first_team_name'] . ' - ' . $g['match']['second_team_name']) ?>"><?= $g['minute'] ?>'</a>
          <?php endforeach; ?>
        </div>
        <div class="mm-axis"><span>0'</span><span>10'</span><span>20'</span><span>30'</span><span>40'</span><span>50'</span></div>
      </div>
      <?php
        $buckets = array_fill(0, 6, 0);
        foreach ($goalMinutes as $g) $buckets[goal_bucket($g['minute'])]++;
        echo svg_bar_chart(GOAL_BUCKETS, [['label' => 'Gol', 'values' => $buckets, 'class' => 'c-home']], 'Dakika aralıklarına göre golleri', 150);
      ?>
    </div>
    <div class="card">
      <div class="section-head"><h3>Gol Türleri</h3></div>
      <div class="donut-wrap">
        <?= svg_donut($kinds, 'Gol türleri', (string) $goals) ?>
        <ul class="donut-legend">
          <?php foreach ($kinds as $k): ?><li><span class="lg <?= e($k['class']) ?>"></span><?= e($k['label']) ?><b><?= $k['value'] ?></b></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="section-head"><h2>Maç Maç</h2><span class="muted small"><?= count($logRows) ?> maç</span></div>
  <?php if (!$logRows): ?>
    <div class="empty-state"><p>Bu sezon henüz maç kaydı yok.</p></div>
  <?php else: ?>
  <div class="mlog">
    <?php foreach ($logRows as $mid => $r):
      $m = $matchMap[$mid];
      $c = $eventLog[$mid] ?? [];
      $res = ['G' => 'win', 'B' => 'draw', 'M' => 'loss'][$r['result']] ?? 'draw';
      $role = !empty($r['started']) ? 'İlk 11' : 'Yedek';
      if (isset($c['sub_in'])) $role = (int) $c['sub_in'] . "' oyuna girdi";
      if (isset($c['sub_out'])) $role .= ' · ' . (int) $c['sub_out'] . "' çıktı"; ?>
      <a class="mlog-card mlog-<?= $res ?>" href="<?= e(match_url($mid)) ?>">
        <span class="mlog-date"><?= e(fmt_date_short($m)['day'] . ' ' . fmt_date_short($m)['month']) ?></span>
        <span class="mlog-vs">
          <?= team_badge(team_logo((int) $r['opponentId']), (string) $r['opponentName'], 'md') ?>
          <span><small><?= !empty($r['isHome']) ? 'İç saha' : 'Deplasman' ?></small><strong><?= e($r['opponentName']) ?></strong></span>
        </span>
        <span class="mlog-res"><span class="form form-<?= $res ?>"><?= e($r['result']) ?></span><b><?= (int) $r['goalsFor'] ?>-<?= (int) $r['goalsAgainst'] ?></b></span>
        <span class="mlog-contrib">
          <?php
            $bits = [];
            if ((int) $r['goals']) $bits[] = '<span class="mc mc-goal">' . str_repeat('⚽', min(5, (int) $r['goals'])) . ((int) $r['goals'] > 5 ? ' ×' . (int) $r['goals'] : '') . '</span>';
            if ((int) $r['assists']) $bits[] = '<span class="mc"><b>' . (int) $r['assists'] . '</b> asist</span>';
            if ((int) $r['saves']) $bits[] = '<span class="mc"><b>' . (int) $r['saves'] . '</b> kurtarış</span>';
            if ((int) ($r['chancesCreated'] ?? 0)) $bits[] = '<span class="mc"><b>' . (int) $r['chancesCreated'] . '</b> pozisyon</span>';
            if (!empty($c['block'])) $bits[] = '<span class="mc"><b>' . (int) $c['block'] . '</b> blok</span>';
            if ((int) $r['yellowCards']) $bits[] = str_repeat(event_icon('yellow'), (int) $r['yellowCards']);
            if ((int) $r['redCards']) $bits[] = str_repeat(event_icon('red'), (int) $r['redCards']);
            echo $bits ? implode('', $bits) : '<span class="muted small">—</span>';
          ?>
        </span>
        <span class="mlog-role"><?= e($role) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="container section">
  <div class="tiles tiles-6">
    <?= stat_tile('İlk 11', (int) $stats['started'], (int) $stats['enteredAsSubstitute'] . ' kez sonradan girdi') ?>
    <?= stat_tile('Maç başı gol', num($stats['goalsPerMatch'], 2)) ?>
    <?= stat_tile('Hava topu', (int) $stats['aerialDuelsWon']) ?>
    <?= stat_tile('Faul', (int) $stats['fouls']) ?>
    <?= stat_tile('Sarı kart', (int) $stats['yellowCards']) ?>
    <?= stat_tile('Kırmızı kart', (int) $stats['redCards']) ?>
  </div>
</section>

<?php if ($awardsWon || $teammates): ?>
<section class="container section">
  <div class="grid-main-side">
    <?php if ($teammates): ?>
      <div class="card"><div class="section-head"><h3>Takım Arkadaşları</h3><a class="more" href="<?= e(team_url($teamId)) ?>">Kadro →</a></div>
        <div class="mate-grid">
          <?php foreach (array_slice($teammates, 0, 8) as $tm): ?>
            <a href="<?= e(player_url((int) $tm['playerId'])) ?>"><?= player_avatar($tm['playerImage'] ?? null, $tm['playerName'], 'md') ?><span><?= e($tm['playerName']) ?></span><small><?= (int) $tm['totalGoals'] ?> gol · <?= (int) $tm['matchesPlayed'] ?> maç</small></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($awardsWon): ?>
      <div class="card"><div class="section-head"><h3>Ödüller</h3></div>
        <ul class="achievements">
          <?php foreach ($awardsWon as $a): ?>
            <li><span class="trophy">🏅</span><a href="<?= e(match_url((int) $a['match']['id'])) ?>"><b><?= e($a['label']) ?></b><small><?= e($a['match']['first_team_name'] . ' - ' . $a['match']['second_team_name'] . ' · ' . fmt_date($a['match'])) ?></small></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php';
