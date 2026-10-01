<?php
require __DIR__ . '/inc/bootstrap.php';

$scope = ccl_scope();
$matches = ccl_matches();
$standings = ccl_standings();
$model = season_model();
$totals = $model['totals'];
$records = $model['records'];

$live = array_values(array_filter($matches, 'match_is_live'));
$played = $model['played'];
$upcoming = array_values(array_filter($matches, 'match_is_upcoming'));
$next = array_slice($upcoming, 0, 6);

// Manşet: en son maçlar; aynı gün içinde fotoğraflı, özel başlıklı ve gollü maçlar öne
$newsScore = static function (array $m) {
    $s = 0;
    if (match_cover($m)) $s += 100;
    if (media_value($m['post_manset'] ?? '') !== '' && !preg_match('/\bvs\.?\b/iu', (string) $m['post_manset'])) $s += 50;
    $s += (int) $m['first_team_score'] + (int) $m['second_team_score'];
    $s += abs((int) $m['first_team_score'] - (int) $m['second_team_score']);
    return $s;
};
$headlines = $played;
usort($headlines, static function ($a, $b) use ($newsScore) {
    return [substr((string) $b['date'], 0, 10), $newsScore($b)] <=> [substr((string) $a['date'], 0, 10), $newsScore($a)];
});
$slides = array_slice($headlines, 0, 5);
$moreStories = array_slice($headlines, 5, 6);
$news = ccl_news(4);

$scorers = ccl_player_stats(['sort' => 'topScorers', 'limit' => 5])['players'];
$assists = ccl_player_stats(['sort' => 'mostAssists', 'limit' => 5])['players'];
$keepers = ccl_player_stats(['sort' => 'mostSaves', 'limit' => 5])['players'];
$lastDate = $model['dates'] ? end($model['dates']) : null;
$matchById = [];
$dayIds = [];
foreach ($played as $m) {
    $matchById[(int) $m['id']] = $m;
    if ($lastDate && substr((string) $m['date'], 0, 10) === $lastDate) {
        $dayIds[] = (int) $m['id'];
    }
}
$dayHighlights = $dayIds ? highlights($dayIds) : [];
$weekly = ccl_weekly_awards(1);
$teamCount = count(ccl_team_map());
$index = ccl_player_index();

$kindClasses = ['right' => 's1', 'left' => 's2', 'header' => 's3', 'penalty' => 's4', 'freekick' => 's5', 'long' => 's6', 'own' => 's7', 'other' => 's8'];
$kindLabels = GOAL_KIND_LABELS + ['own' => 'Kendi kalesine'];
$kindParts = [];
foreach ($kindClasses as $k => $cls) {
    if (!empty($totals['kinds'][$k])) {
        $kindParts[] = ['label' => $kindLabels[$k], 'value' => (int) $totals['kinds'][$k], 'class' => $cls];
    }
}

$page = ['title' => '', 'nav' => 'home'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>

<?php if ($played || $live): ?>
<div class="ticker" aria-label="Son skorlar">
  <div class="ticker-track">
    <?php foreach (array_merge($live, array_slice(array_reverse($played), 0, 12)) as $m): ?>
      <a class="tick<?= match_is_live($m) ? ' is-live' : '' ?>" href="<?= e(match_url((int) $m['id'])) ?>">
        <?php if (match_is_live($m)): ?><span class="dot-live"></span><?php endif; ?>
        <span><?= e($m['first_team_name']) ?></span>
        <b><?= (int) $m['first_team_score'] ?>-<?= (int) $m['second_team_score'] ?></b>
        <span><?= e($m['second_team_name']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($slides): ?>
<section class="headline-area" aria-label="Manşet">
  <div class="slider" data-slider>
    <?php foreach ($slides as $i => $m): $story = match_story($m); $photo = match_cover($m); ?>
      <article class="slide<?= $i === 0 ? ' active' : '' ?><?= $photo ? ' has-photo' : '' ?>" data-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
        <div class="slide-bg" aria-hidden="true"><?php if ($photo): ?><img src="<?= e(media_url($photo, 320)) ?>" alt="" loading="lazy"><?php endif; ?></div>
        <div class="container slide-inner">
          <a class="slide-copy" href="<?= e(match_url((int) $m['id'])) ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>">
            <span class="slide-kicker"><?= e($story['kicker']) ?> · <?= e(fmt_date($m)) ?></span>
            <h2 class="slide-title"><?= e($story['headline']) ?></h2>
            <p class="slide-summary"><?= e(mb_substr($story['summary'], 0, 200)) ?><?= mb_strlen_safe($story['summary']) > 200 ? '…' : '' ?></p>
            <span class="slide-cta">Maç detayları →</span>
          </a>
          <a class="slide-media" href="<?= e(match_url((int) $m['id'])) ?>" tabindex="-1" aria-hidden="true"><?= render_cover($m, 'hero') ?></a>
        </div>
      </article>
    <?php endforeach; ?>
    <div class="container slider-nav">
      <ol class="slider-tabs" role="tablist" aria-label="Manşet haberleri">
        <?php foreach ($slides as $i => $m): $story = match_story($m); ?>
          <li><button type="button" role="tab" class="<?= $i === 0 ? 'active' : '' ?>" data-slide-to="<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
            <span class="st-num"><?= $i + 1 ?></span><span class="st-text"><?= e($story['headline']) ?></span><span class="st-bar"><i></i></span>
          </button></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</section>
<?php else: ?>
<section class="hero">
  <div class="hero-bg" aria-hidden="true"></div>
  <div class="container hero-inner">
    <div class="hero-copy">
      <span class="eyebrow"><?= e($scope['seasonName'] ?: 'Yeni Sezon') ?></span>
      <h1>CCL <span>CUP</span></h1>
      <p class="lead">Ankara'nın kurumlarını sahada buluşturan futbol turnuvası. Maçlar başladığında haberler burada yer alacak.</p>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="container section">
  <div class="season-band">
    <div class="sb-brand">
      <span class="eyebrow"><?= e($scope['seasonName']) ?></span>
      <strong>Sezon<br>Rakamları</strong>
    </div>
    <div class="sb-nums">
      <div><b><?= $teamCount ?></b><span>Takım</span></div>
      <div><b><?= $totals['matches'] ?></b><span>Maç</span></div>
      <div><b><?= $totals['goals'] ?></b><span>Gol</span></div>
      <div><b><?= $totals['matches'] ? num($totals['goals'] / $totals['matches'], 1) : '0' ?></b><span>Maç başı gol</span></div>
      <div><b><?= $totals['saves'] ?></b><span>Kurtarış</span></div>
      <div><b><?= $totals['yellow'] ?><i class="card-icon card-yellow"></i> <?= $totals['red'] ?><i class="card-icon card-red"></i></b><span>Kart</span></div>
    </div>
  </div>
</section>

<?php if ($live): ?>
<section class="container section">
  <div class="section-head"><h2><span class="dot-live"></span> Şu An Oynanıyor</h2></div>
  <div class="match-list"><?php foreach ($live as $m) echo render_match_card($m); ?></div>
</section>
<?php endif; ?>

<?php if ($upcoming): $nm = $upcoming[0]; ?>
<section class="container section">
  <a class="next-match" href="<?= e(match_url((int) $nm['id'])) ?>">
    <span class="nm-label">Sıradaki maç</span>
    <span class="nm-team"><?= team_badge(team_logo((int) $nm['home_team_id']), $nm['first_team_name'], 'lg') ?><b><?= e($nm['first_team_name']) ?></b></span>
    <span class="nm-center">
      <span class="nm-time"><?= e(fmt_time($nm) ?: 'VS') ?></span>
      <span class="nm-date"><?= e(fmt_date($nm, true)) ?><?= !empty($nm['match_field']) ? ' · ' . e($nm['match_field']) : '' ?></span>
      <span class="countdown" data-countdown="<?= e(substr((string) $nm['date'], 0, 10) . 'T' . (fmt_time($nm) ?: '00:00') . ':00+03:00') ?>"></span>
    </span>
    <span class="nm-team"><?= team_badge(team_logo((int) $nm['away_team_id']), $nm['second_team_name'], 'lg') ?><b><?= e($nm['second_team_name']) ?></b></span>
  </a>
</section>
<?php endif; ?>

<?php if ($dayHighlights): ?>
<section class="container section">
  <div class="section-head"><h2>Maç Gününün Öne Çıkanları</h2><span class="muted small"><?= e(fmt_date(['date' => $lastDate])) ?></span></div>
  <div class="star-row">
    <?php foreach ($dayHighlights as $i => $h): $pl = $index[$h['player']] ?? null; if (!$pl) continue; $sm = $matchById[$h['match']] ?? null; ?>
      <a class="star-card<?= $i === 0 ? ' star-top' : '' ?>" href="<?= e(player_url($h['player'])) ?>">
        <span class="star-label"><?= e($h['label']) ?></span>
        <?= player_avatar($pl['playerImage'] ?? null, $pl['playerName'], 'lg') ?>
        <strong><?= e($pl['playerName']) ?></strong>
        <small><?= e($pl['teamName']) ?></small>
        <span class="star-value"><b><?= (int) $h['value'] ?></b> <?= e($h['unit']) ?></span>
        <?php if ($sm): ?><span class="star-match"><?= e($sm['first_team_name']) ?> <?= (int) $sm['first_team_score'] ?>-<?= (int) $sm['second_team_score'] ?> <?= e($sm['second_team_name']) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($weekly && !empty($weekly[0]['items'])): $set = $weekly[0]; ?>
<section class="container section">
  <div class="section-head"><h2><?= e($set['title'] ?: 'Haftanın Takımı') ?></h2></div>
  <div class="star-row">
    <?php foreach ($set['items'] as $it): if (empty($it['player_name'])) continue; ?>
      <a class="star-card" href="<?= e(player_url((int) $it['player_id'])) ?>">
        <?= player_avatar($it['player_img'] ?? null, $it['player_name'], 'lg') ?>
        <strong><?= e($it['player_name']) ?></strong>
        <small><?= e($it['player_team_name'] ?? '') ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="container section grid-main-side">
  <div class="card">
    <div class="section-head">
      <h2>Puan Durumu</h2>
      <a class="more" href="puan-durumu.php">Detaylı tablo →</a>
    </div>
    <?= render_standings_table($standings, true, 0, 10) ?>
  </div>
  <div class="card">
    <div class="section-head"><h2>Fikstür</h2><a class="more" href="fikstur.php?tab=fikstur">Tümü →</a></div>
    <?php if ($next): ?>
      <div class="match-list compact-list"><?php foreach ($next as $m) echo render_match_card($m, 'mini'); ?></div>
    <?php else: ?>
      <div class="empty-state small"><p>Yeni fikstür açıklandığında burada yer alacak.</p></div>
    <?php endif; ?>
    <div class="section-head section-gap"><h3>Son Sonuçlar</h3><a class="more" href="fikstur.php?tab=sonuclar">Tümü →</a></div>
    <div class="match-list compact-list"><?php foreach (array_slice(array_reverse($played), 0, 5) as $m) echo render_match_card($m, 'mini'); ?></div>
  </div>
</section>

<section class="container section">
  <div class="section-head"><h2>Liderler</h2><a class="more" href="istatistikler.php">Tüm istatistikler →</a></div>
  <div class="leader-board">
    <?php foreach ([['Gol Krallığı', $scorers, 'totalGoals', 'gol', 'topScorers'], ['Asist Liderleri', $assists, 'assists', 'asist', 'mostAssists'], ['Kurtarış Liderleri', $keepers, 'saves', 'kurtarış', 'mostSaves']] as [$title, $list, $field, $unit, $sort]):
      $list = array_values(array_filter($list, static function ($p) use ($field) { return (float) $p[$field] > 0; }));
      $top = $list[0] ?? null; ?>
      <div class="card lb">
        <div class="section-head"><h3><?= e($title) ?></h3><a class="more" href="istatistikler.php?sort=<?= e($sort) ?>">Tümü →</a></div>
        <?php if (!$top): ?>
          <div class="empty-state small"><p>Henüz veri yok.</p></div>
        <?php else: ?>
          <a class="lb-top" href="<?= e(player_url((int) $top['playerId'])) ?>">
            <?= player_avatar($top['playerImage'] ?? null, $top['playerName'], 'xl') ?>
            <span class="lb-top-info"><span class="lb-crown">1</span><strong><?= e($top['playerName']) ?></strong><small><?= team_badge($top['teamLogo'] ?? null, (string) $top['teamName'], 'xs') ?> <?= e($top['teamName']) ?></small></span>
            <span class="lb-top-val"><b><?= e(num($top[$field])) ?></b><small><?= e($unit) ?></small></span>
          </a>
          <ol class="leaders" start="2">
            <?php foreach (array_slice($list, 1, 4) as $i => $p): ?>
              <li>
                <span class="leader-rank"><?= $i + 2 ?></span>
                <?= player_avatar($p['playerImage'] ?? null, (string) $p['playerName'], 'sm') ?>
                <span class="leader-info"><a href="<?= e(player_url((int) $p['playerId'])) ?>"><?= e($p['playerName']) ?></a><small><?= e($p['teamName'] ?? '') ?></small></span>
                <span class="leader-value"><strong><?= e(num($p[$field])) ?></strong><small><?= e($unit) ?></small></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($totals['matches']): ?>
<section class="container section">
  <div class="section-head"><h2>Turnuva Rekorları</h2></div>
  <div class="record-grid">
    <?php if ($records['biggest_win']): $rm = $records['biggest_win']['match']; ?>
      <a class="record" href="<?= e(match_url((int) $rm['id'])) ?>"><span class="record-icon">💥</span><span class="record-label">En farklı skor</span><strong><?= (int) $rm['first_team_score'] ?>-<?= (int) $rm['second_team_score'] ?></strong><small><?= e($rm['first_team_name'] . ' - ' . $rm['second_team_name']) ?></small></a>
    <?php endif; ?>
    <?php if ($records['highest_scoring']): $rm = $records['highest_scoring']['match']; ?>
      <a class="record" href="<?= e(match_url((int) $rm['id'])) ?>"><span class="record-icon">🔥</span><span class="record-label">En gollü maç</span><strong><?= (int) $records['highest_scoring']['value'] ?> gol</strong><small><?= e($rm['first_team_name'] . ' ' . (int) $rm['first_team_score'] . '-' . (int) $rm['second_team_score'] . ' ' . $rm['second_team_name']) ?></small></a>
    <?php endif; ?>
    <?php if ($records['fastest_goal']): $rf = $records['fastest_goal']; $rm = $rf['match']; ?>
      <a class="record" href="<?= e(match_url((int) $rm['id'])) ?>"><span class="record-icon">⚡</span><span class="record-label">En hızlı gol</span><strong><?= (int) $rf['value'] ?>. dakika</strong><small><?= e(($rf['player'] ? player_name($rf['player']) . ' · ' : '') . $rm['first_team_name'] . ' - ' . $rm['second_team_name']) ?></small></a>
    <?php endif; ?>
    <?php foreach (array_slice($records['hattricks'], 0, 3) as $ht): $rm = $ht['match']; ?>
      <a class="record" href="<?= e(match_url((int) $rm['id'])) ?>"><span class="record-icon">🎩</span><span class="record-label"><?= $ht['goals'] >= 4 ? $ht['goals'] . ' gol' : 'Hat-trick' ?></span><strong><?= e(player_name($ht['player'])) ?></strong><small><?= e($rm['first_team_name'] . ' ' . (int) $rm['first_team_score'] . '-' . (int) $rm['second_team_score'] . ' ' . $rm['second_team_name']) ?></small></a>
    <?php endforeach; ?>
    <a class="record" href="fikstur.php?tab=sonuclar"><span class="record-icon">🏠</span><span class="record-label">Ev sahibi / Beraberlik / Deplasman</span><strong><?= $totals['home_wins'] ?> / <?= $totals['draws'] ?> / <?= $totals['away_wins'] ?></strong><small>galibiyet dağılımı</small></a>
  </div>
</section>

<section class="container section grid-main-side">
  <div class="card">
    <div class="section-head"><h3>Gollerin Dakikalara Dağılımı</h3><span class="muted small"><?= $totals['half'][1] ?> gol ilk yarıda, <?= $totals['half'][2] ?> gol ikinci yarıda</span></div>
    <?= svg_bar_chart(GOAL_BUCKETS, [['label' => 'Gol', 'values' => $totals['buckets'], 'class' => 'c-home']], 'Turnuvada dakika aralıklarına göre goller') ?>
  </div>
  <div class="card">
    <div class="section-head"><h3>Gol Türleri</h3></div>
    <?php if ($kindParts): ?>
    <div class="donut-wrap">
      <?= svg_donut($kindParts, 'Turnuvadaki gol türleri', (string) array_sum(array_column($kindParts, 'value'))) ?>
      <ul class="donut-legend"><?php foreach ($kindParts as $kp): ?><li><span class="lg <?= e($kp['class']) ?>"></span><?= e($kp['label']) ?><b><?= $kp['value'] ?></b></li><?php endforeach; ?></ul>
    </div>
    <?php else: ?><div class="empty-state small"><p>Henüz gol yok.</p></div><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($moreStories || $news): ?>
<section class="container section">
  <div class="section-head"><h2>Maç Haberleri</h2><a class="more" href="haberler.php">Tüm haberler →</a></div>
  <div class="story-grid">
    <?php foreach ($news as $n): ?>
      <a class="story story-card" href="<?= e(url('haberler.php', ['haber' => $n['id']])) ?>">
        <?php if (!empty($n['cover_image_url'])): ?><div class="cover cover-md cover-photo"><img src="<?= e(media_url($n['cover_image_url'])) ?>" alt="" loading="lazy"></div><?php endif; ?>
        <span class="story-body"><span class="story-kicker"><?= e($n['category_label'] ?? 'Haber') ?></span><span class="story-title"><?= e($n['title']) ?></span><span class="story-summary"><?= e($n['summary'] ?? '') ?></span></span>
      </a>
    <?php endforeach; ?>
    <?php foreach ($moreStories as $m) echo render_story_card($m); ?>
  </div>
</section>
<?php endif; ?>

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
