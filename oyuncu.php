<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
if (!$id) {
    not_found();
}

$player = ccl_player($id);

/** Oyuncunun bu sezondaki istatistik satırını bulur; yoksa CCL CUP oyuncusu değildir. */
$findStats = static function (array $rows) use ($id) {
    foreach ($rows as $r) {
        if ((int) ($r['playerId'] ?? 0) === $id) {
            return $r;
        }
    }
    return null;
};

$stats = null;
if (!empty($player['team_id'])) {
    $stats = $findStats(ccl_player_stats(['teamId' => (int) $player['team_id'], 'limit' => 100])['players']);
}
if (!$stats && $player) {
    $stats = $findStats(ccl_player_stats(['search' => $player['player_name'] ?? '', 'limit' => 100])['players']);
}
if (!$stats) {
    not_found('Bu oyuncu CCL CUP\'ta yer almıyor.');
}

$name = (string) $stats['playerName'];
$img = $stats['playerImage'] ?? ($player['player_img'] ?? null);
$teamId = (int) ($stats['teamId'] ?? 0);
$teamName = (string) ($stats['teamName'] ?? '');

// Maç bazında katkılar: oyuncunun olaylarını sezon maçlarıyla eşleştir.
$seasonMatches = [];
foreach (ccl_matches() as $m) {
    $seasonMatches[(int) $m['id']] = $m;
}
$perMatch = [];
foreach (ccl_player_events($id) as $ev) {
    $mid = (int) ($ev['mac_id'] ?? 0);
    if (!isset($seasonMatches[$mid]) || (int) ($ev['oyuncu_id'] ?? 0) !== $id) {
        continue;
    }
    $info = event_info((string) ($ev['olay_kodu'] ?? ''));
    $perMatch[$mid] ??= ['goal' => 0, 'assist' => 0, 'yellow' => 0, 'red' => 0, 'save' => 0, 'events' => []];
    if (isset($perMatch[$mid][$info['type']])) {
        $perMatch[$mid][$info['type']]++;
    }
}
// En yeni maç üstte
uksort($perMatch, static fn($a, $b) => strcmp(match_sort_key($seasonMatches[$b]), match_sort_key($seasonMatches[$a])));

$cards = [
    ['Maç', $stats['matchesPlayed'] ?? 0],
    ['Gol', $stats['totalGoals'] ?? 0],
    ['Asist', $stats['assists'] ?? 0],
    ['Puan', $stats['totalPoints'] ?? 0],
];
$details = [
    'İlk 11' => $stats['started'] ?? 0,
    'Oyuna sonradan girdi' => $stats['enteredAsSubstitute'] ?? 0,
    'Kaptanlık' => $stats['captainAppearances'] ?? 0,
    'Sağ ayak gol' => $stats['rightFootGoals'] ?? 0,
    'Sol ayak gol' => $stats['leftFootGoals'] ?? 0,
    'Kafa gol' => $stats['headerGoals'] ?? 0,
    'Penaltı gol' => $stats['penaltyGoals'] ?? 0,
    'Serbest vuruş gol' => $stats['freeKickGoals'] ?? 0,
    'Uzaktan gol' => (int) ($stats['longRightGoals'] ?? 0) + (int) ($stats['longLeftGoals'] ?? 0),
    'Pozisyon üretme' => $stats['chancesCreated'] ?? 0,
    'Kurtarış' => $stats['saves'] ?? 0,
    'Kritik blok' => $stats['criticalBlocks'] ?? 0,
    'Kazanılan ikili' => $stats['duelsWon'] ?? 0,
    'Kazanılan hava topu' => $stats['aerialDuelsWon'] ?? 0,
    'Faul' => $stats['fouls'] ?? 0,
    'Sarı kart' => $stats['yellowCards'] ?? 0,
    'Kırmızı kart' => $stats['redCards'] ?? 0,
    'Maç başı gol' => num($stats['goalsPerMatch'] ?? 0, 2),
];

$page = ['title' => $name, 'nav' => 'stats', 'description' => $name . ' (' . $teamName . ') — CCL CUP istatistikleri.'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="profile-hero">
  <div class="container profile-inner">
    <?= player_avatar($img, $name, 'xxl') ?>
    <div class="profile-copy">
      <span class="eyebrow"><?= e($stats['position'] ?? 'Oyuncu') ?></span>
      <h1><?= e($name) ?></h1>
      <?php if ($teamId): ?>
        <a class="profile-team" href="<?= e(team_url($teamId)) ?>"><?= team_badge($stats['teamLogo'] ?? team_logo($teamId), $teamName, 'sm') ?> <?= e($teamName) ?></a>
      <?php endif; ?>
      <div class="profile-stats">
        <?php foreach ($cards as [$label, $value]): ?>
          <div><strong><?= e(num($value)) ?></strong><span><?= e($label) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="container section grid-main-side">
  <div class="card">
    <div class="section-head"><h2>Maç Katkıları</h2></div>
    <?php if (!$perMatch): ?>
      <div class="empty-state small"><p>Bu sezon için gol, asist ya da kart kaydı bulunmuyor.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th class="left">Maç</th><th class="hide-sm">Tarih</th><th title="Gol">G</th><th title="Asist">A</th><th title="Kart">Kart</th></tr></thead>
        <tbody>
        <?php foreach ($perMatch as $mid => $c): $m = $seasonMatches[$mid]; ?>
          <tr>
            <td class="left"><a href="<?= e(match_url($mid)) ?>"><?= e($m['first_team_name']) ?> <strong><?= match_is_played($m) ? (int) $m['first_team_score'] . '-' . (int) $m['second_team_score'] : 'vs' ?></strong> <?= e($m['second_team_name']) ?></a></td>
            <td class="hide-sm muted"><?= e(fmt_date($m)) ?></td>
            <td><?= $c['goal'] ? '<strong>' . $c['goal'] . '</strong>' : '–' ?></td>
            <td><?= $c['assist'] ?: '–' ?></td>
            <td><?= str_repeat(event_icon('yellow'), $c['yellow']) . str_repeat(event_icon('red'), $c['red']) ?: '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="section-head"><h3>Sezon Detayı</h3></div>
    <dl class="detail-list">
      <?php foreach ($details as $label => $value): ?>
        <div><dt><?= e($label) ?></dt><dd><?= e(is_string($value) ? $value : num($value)) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
