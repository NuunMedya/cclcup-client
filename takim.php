<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
if (!$id || !ccl_has_team($id)) {
    not_found('Bu takım CCL CUP\'ta yer almıyor.');
}

$team = ccl_team($id) ?? [];
$name = (string) ($team['team_name'] ?? ccl_team_map()[$id]['name']);
$logo = $team['logo'] ?? team_logo($id);

$standings = ccl_standings();
$row = null;
$rank = 0;
foreach ($standings as $i => $r) {
    if ((int) $r['team_id'] === $id) {
        $row = $r;
        $rank = $i + 1;
    }
}

$matches = array_values(array_filter(ccl_matches(), static function ($m) use ($id) {
    return (int) $m['home_team_id'] === $id || (int) $m['away_team_id'] === $id;
}));
$played = array_values(array_filter($matches, 'match_is_played'));
$upcoming = array_values(array_filter($matches, static function ($m) {
    return !match_is_played($m);
}));

$squad = ccl_player_stats(['teamId' => $id, 'limit' => 100, 'sort' => 'mostMatches'])['players'];
usort($squad, static function ($a, $b) {
    $order = ['KL' => 0, 'DF' => 1, 'OS' => 2, 'FV' => 3];
    $pa = $order[position_short($a['position'] ?? '')] ?? 4;
    $pb = $order[position_short($b['position'] ?? '')] ?? 4;
    return $pa <=> $pb ?: ($b['matchesPlayed'] <=> $a['matchesPlayed']) ?: tr_compare($a['playerName'], $b['playerName']);
});
$topScorers = $squad;
usort($topScorers, static function ($a, $b) {
    return $b['totalGoals'] <=> $a['totalGoals'];
});

$page = ['title' => $name, 'nav' => 'teams', 'description' => $name . ' — CCL CUP maçları, kadrosu ve istatistikleri.'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="profile-hero">
  <div class="container profile-inner">
    <?= team_badge($logo, $name, 'xxl') ?>
    <div class="profile-copy">
      <span class="eyebrow">CCL CUP Takımı</span>
      <h1><?= e($name) ?></h1>
      <?php if ($row): ?>
      <div class="profile-stats">
        <div><strong><?= $rank ?>.</strong><span>Sıralama</span></div>
        <div><strong><?= e((string) ($row['display_points'] ?? $row['total_points'] ?? 0)) ?></strong><span>Puan</span></div>
        <div><strong><?= (int) $row['played'] ?></strong><span>Maç</span></div>
        <div><strong><?= (int) $row['wins'] ?>-<?= (int) $row['draws'] ?>-<?= (int) $row['losses'] ?></strong><span>G-B-M</span></div>
        <div><strong><?= (int) $row['goals_for'] ?>:<?= (int) $row['goals_against'] ?></strong><span>Gol</span></div>
      </div>
      <div class="form-row"><span class="muted">Form</span> <?= form_badges($row['last5'] ?? '') ?></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="container section grid-main-side">
  <div class="stack">
    <?php if ($upcoming): ?>
    <div>
      <div class="section-head"><h2>Sıradaki Maçlar</h2></div>
      <div class="match-list"><?php foreach ($upcoming as $m) echo render_match_card($m); ?></div>
    </div>
    <?php endif; ?>
    <div>
      <div class="section-head"><h2>Sonuçlar</h2></div>
      <div class="match-list">
        <?php if ($played): foreach (array_reverse($played) as $m) echo render_match_card($m); else: ?>
          <div class="empty-state"><p>Henüz oynanmış maç yok.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="section-head"><h2>Kadro</h2><span class="muted"><?= count($squad) ?> oyuncu</span></div>
      <?php if (!$squad): ?>
        <div class="empty-state small"><p>Kadro bilgisi henüz girilmedi.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th class="left">Oyuncu</th><th>Mevki</th><th title="Maç">M</th><th title="Gol">G</th><th title="Asist">A</th><th class="hide-sm" title="Sarı kart">SK</th><th class="hide-sm" title="Kırmızı kart">KK</th></tr></thead>
          <tbody>
          <?php foreach ($squad as $p): ?>
            <tr>
              <td class="left"><a class="player-cell" href="<?= e(player_url((int) $p['playerId'])) ?>"><?= player_avatar($p['playerImage'] ?? null, $p['playerName'], 'xs') ?><span><?= e($p['playerName']) ?></span></a></td>
              <td><span class="pos-tag pos-<?= e(strtolower(position_short($p['position'] ?? '')) ?: 'na') ?>"><?= e(position_short($p['position'] ?? '') ?: '–') ?></span></td>
              <td><?= (int) $p['matchesPlayed'] ?></td>
              <td><strong><?= (int) $p['totalGoals'] ?></strong></td>
              <td><?= (int) $p['assists'] ?></td>
              <td class="hide-sm"><?= (int) $p['yellowCards'] ?></td>
              <td class="hide-sm"><?= (int) $p['redCards'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="section-head"><h3>Takımın Golcüleri</h3></div>
      <?= render_leader_list($topScorers, 'totalGoals', 'gol') ?>
    </div>
    <div class="card">
      <div class="section-head"><h3>Puan Durumu</h3><a class="more" href="puan-durumu.php">Tümü →</a></div>
      <?= render_standings_table($standings, true, $id) ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
