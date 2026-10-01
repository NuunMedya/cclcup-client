<?php
require __DIR__ . '/inc/bootstrap.php';

$scope = ccl_scope();
$matches = ccl_matches();
$standings = ccl_standings();
$scorers = ccl_player_stats(['sort' => 'topScorers', 'limit' => 5])['players'];
$assists = ccl_player_stats(['sort' => 'mostAssists', 'limit' => 5])['players'];
$valuable = ccl_player_stats(['sort' => 'mostValuable', 'limit' => 5])['players'];

$live = array_values(array_filter($matches, 'match_is_live'));
$played = array_values(array_filter($matches, 'match_is_played'));
$upcoming = array_values(array_filter($matches, static fn($m) => !match_is_played($m) && !match_is_live($m)));
$recent = array_reverse(array_slice($played, -6));
$next = array_slice($upcoming, 0, 6);

$featured = $live[0] ?? $upcoming[0] ?? ($played ? $played[count($played) - 1] : null);

$totalGoals = 0;
foreach ($played as $m) {
    $totalGoals += (int) $m['first_team_score'] + (int) $m['second_team_score'];
}
$teamCount = count(ccl_team_map());

$page = ['title' => '', 'nav' => 'home'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>

<section class="hero">
  <div class="hero-bg" aria-hidden="true"></div>
  <div class="container hero-inner">
    <div class="hero-copy">
      <span class="eyebrow"><?= e($scope['seasonName'] ?: 'Yeni Sezon') ?></span>
      <h1>CCL <span>CUP</span></h1>
      <p class="lead">Ankara'nın kurumlarını sahada buluşturan futbol turnuvası. Fikstür, sonuçlar, puan durumu ve oyuncu istatistikleri tek yerde.</p>
      <div class="hero-stats">
        <div><strong><?= $teamCount ?></strong><span>Takım</span></div>
        <div><strong><?= count($played) ?></strong><span>Oynanan maç</span></div>
        <div><strong><?= $totalGoals ?></strong><span>Gol</span></div>
        <div><strong><?= count($played) ? number_format($totalGoals / count($played), 1, ',', '') : '0' ?></strong><span>Maç başı gol</span></div>
      </div>
    </div>

    <?php if ($featured): ?>
    <div class="hero-match">
      <span class="hero-match-label">
        <?= match_is_live($featured) ? '<span class="dot-live"></span> Şu an oynanıyor' : (match_is_played($featured) ? 'Son maç' : 'Sıradaki maç') ?>
      </span>
      <?php
        $hId = (int) $featured['home_team_id'];
        $aId = (int) $featured['away_team_id'];
        $showScore = match_is_played($featured) || match_is_live($featured);
      ?>
      <a class="featured" href="<?= e(match_url((int) $featured['id'])) ?>">
        <div class="featured-team">
          <?= team_badge(team_logo($hId), $featured['first_team_name'], 'lg') ?>
          <span><?= e($featured['first_team_name']) ?></span>
        </div>
        <div class="featured-center">
          <?php if ($showScore): ?>
            <span class="featured-score"><?= (int) $featured['first_team_score'] ?><i>:</i><?= (int) $featured['second_team_score'] ?></span>
          <?php else: ?>
            <span class="featured-time"><?= e(fmt_time($featured) ?: 'VS') ?></span>
          <?php endif; ?>
          <small><?= e(fmt_date($featured, true)) ?></small>
          <?php if (!empty($featured['match_field'])): ?><small><?= e($featured['match_field']) ?></small><?php endif; ?>
        </div>
        <div class="featured-team">
          <?= team_badge(team_logo($aId), $featured['second_team_name'], 'lg') ?>
          <span><?= e($featured['second_team_name']) ?></span>
        </div>
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($live): ?>
<section class="container section">
  <div class="section-head"><h2><span class="dot-live"></span> Canlı Maçlar</h2></div>
  <div class="match-list">
    <?php foreach ($live as $m) echo render_match_card($m); ?>
  </div>
</section>
<?php endif; ?>

<section class="container section grid-2">
  <div>
    <div class="section-head">
      <h2>Son Sonuçlar</h2>
      <a class="more" href="fikstur.php?tab=sonuclar">Tümü →</a>
    </div>
    <div class="match-list">
      <?php if ($recent): foreach ($recent as $m) echo render_match_card($m); else: ?>
        <div class="empty-state"><p>Henüz oynanmış maç yok.</p></div>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <div class="section-head">
      <h2>Fikstür</h2>
      <a class="more" href="fikstur.php?tab=fikstur">Tümü →</a>
    </div>
    <div class="match-list">
      <?php if ($next): foreach ($next as $m) echo render_match_card($m); else: ?>
        <div class="empty-state"><p>Planlanmış maç bulunmuyor. Yeni fikstür açıklandığında burada yer alacak.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="container section grid-main-side">
  <div class="card">
    <div class="section-head">
      <h2>Puan Durumu</h2>
      <a class="more" href="puan-durumu.php">Detaylı tablo →</a>
    </div>
    <?= render_standings_table($standings, true, 0, 10) ?>
  </div>
  <div class="stack">
    <div class="card">
      <div class="section-head"><h3>Gol Krallığı</h3><a class="more" href="istatistikler.php?sort=topScorers">Tümü →</a></div>
      <?= render_leader_list($scorers, 'totalGoals', 'gol') ?>
    </div>
    <div class="card">
      <div class="section-head"><h3>Asist Liderleri</h3><a class="more" href="istatistikler.php?sort=mostAssists">Tümü →</a></div>
      <?= render_leader_list($assists, 'assists', 'asist') ?>
    </div>
    <div class="card">
      <div class="section-head"><h3>En Değerli Oyuncular</h3><a class="more" href="istatistikler.php?sort=mostValuable">Tümü →</a></div>
      <?= render_leader_list($valuable, 'totalPoints', 'puan') ?>
    </div>
  </div>
</section>

<?php $teams = ccl_team_map(); if ($teams): ?>
<section class="container section">
  <div class="section-head"><h2>Takımlar</h2><a class="more" href="takimlar.php">Tümü →</a></div>
  <div class="team-strip">
    <?php foreach ($teams as $t): ?>
      <a href="<?= e(team_url($t['id'])) ?>" title="<?= e($t['name']) ?>">
        <?= team_badge($t['logo'], $t['name'], 'md') ?>
        <span><?= e($t['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php';
