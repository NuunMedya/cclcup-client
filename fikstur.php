<?php
require __DIR__ . '/inc/bootstrap.php';

$matches = ccl_matches();
$teams = ccl_team_map();
uasort($teams, 'compare_team_names');

$tab = qs('tab', 'tumu');
if (!in_array($tab, ['tumu', 'fikstur', 'sonuclar'], true)) {
    $tab = 'tumu';
}
$teamFilter = qs_int('takim');
if ($teamFilter && !isset($teams[$teamFilter])) {
    $teamFilter = 0;
}

$playedCount = count(array_filter($matches, 'match_is_played'));
$upcomingCount = count(array_filter($matches, 'match_is_upcoming'));

$list = array_filter($matches, static function ($m) use ($tab, $teamFilter) {
    if ($teamFilter && (int) $m['home_team_id'] !== $teamFilter && (int) $m['away_team_id'] !== $teamFilter) {
        return false;
    }
    if ($tab === 'fikstur') return !match_is_played($m);
    if ($tab === 'sonuclar') return match_is_played($m) || match_is_live($m);
    return true;
});

$byDate = [];
foreach ($list as $m) {
    $byDate[substr((string) ($m['date'] ?? ''), 0, 10) ?: 'tbd'][] = $m;
}
// Sonuçlarda en yeni gün üstte
if ($tab === 'sonuclar') {
    krsort($byDate);
}

$tabs = ['tumu' => ['Tümü', count($matches)], 'sonuclar' => ['Sonuçlar', $playedCount], 'fikstur' => ['Fikstür', $upcomingCount]];

$page = ['title' => 'Fikstür ve Sonuçlar', 'nav' => 'fixtures', 'description' => 'CCL CUP fikstürü ve maç sonuçları.'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="fx-head">
  <div class="container">
    <span class="eyebrow"><?= e(ccl_scope()['seasonName']) ?></span>
    <h1>Fikstür</h1>
    <div class="fx-controls">
      <nav class="seg seg-lg" aria-label="Maç filtresi">
        <?php foreach ($tabs as $k => [$label, $count]): ?>
          <a class="<?= $tab === $k ? 'active' : '' ?>" href="<?= e(url('fikstur.php', ['tab' => $k, 'takim' => $teamFilter ?: null])) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <small><?= $count ?></small></a>
        <?php endforeach; ?>
      </nav>
      <form class="fx-filter" method="get" action="fikstur.php">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <label class="sr-only" for="takim">Takım</label>
        <select id="takim" name="takim" data-autosubmit>
          <option value="">Tüm takımlar</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int) $t['id'] ?>"<?= $teamFilter === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm" type="submit">Filtrele</button></noscript>
        <?php if ($teamFilter): ?><a class="fx-clear" href="<?= e(url('fikstur.php', ['tab' => $tab])) ?>">Temizle ✕</a><?php endif; ?>
      </form>
    </div>
  </div>
</section>

<section class="container section fx">
  <?php if (!$byDate): ?>
    <div class="fx-empty">
      <span aria-hidden="true">📅</span>
      <p><?= $tab === 'fikstur' ? 'Planlanmış maç bulunmuyor. Yeni fikstür açıklandığında burada yer alacak.' : 'Gösterilecek maç bulunamadı.' ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($byDate as $date => $dayMatches):
    $first = $dayMatches[0];
    $d = fmt_date_short($first);
    $dayGoals = 0;
    $dayPlayed = 0;
    foreach ($dayMatches as $m) {
        if (match_is_played($m)) {
            $dayPlayed++;
            $dayGoals += (int) $m['first_team_score'] + (int) $m['second_team_score'];
        }
    } ?>
    <div class="fx-day">
      <div class="fx-date">
        <?php if ($date === 'tbd'): ?>
          <b>—</b><span>Tarihi belirlenecek</span>
        <?php else: ?>
          <b><?= e(ltrim($d['day'], '0')) ?></b>
          <span><strong><?= e(TR_MONTHS[(int) date('n', (int) match_ts($first))]) ?></strong><?= e($d['weekday']) ?></span>
        <?php endif; ?>
        <small><?= count($dayMatches) ?> maç<?= $dayPlayed ? ' · ' . $dayGoals . ' gol' : '' ?></small>
      </div>
      <ul class="fx-list">
        <?php foreach ($dayMatches as $m):
          $mid = (int) $m['id'];
          $hId = (int) $m['home_team_id'];
          $aId = (int) $m['away_team_id'];
          $played = match_is_played($m);
          $live = match_is_live($m);
          $hs = (int) $m['first_team_score'];
          $as = (int) $m['second_team_score'];
          $hCls = $played && $hs < $as ? ' is-lose' : ($played && $hs > $as ? ' is-win' : '');
          $aCls = $played && $as < $hs ? ' is-lose' : ($played && $as > $hs ? ' is-win' : '');
          $focus = $teamFilter && ($teamFilter === $hId || $teamFilter === $aId); ?>
          <li>
            <a class="fx-row<?= $live ? ' is-live' : '' ?><?= $focus ? ' is-focus' : '' ?>" href="<?= e(match_url($mid)) ?>">
              <span class="fx-time">
                <?php if ($live): ?><span class="dot-live"></span> Canlı
                <?php elseif ($played): ?>MS
                <?php else: ?><?= e(fmt_time($m) ?: '—') ?><?php endif; ?>
              </span>
              <span class="fx-team fx-home<?= $hCls ?>"><span class="fx-name"><?= e($m['first_team_name']) ?></span><?= team_badge(team_logo($hId), (string) $m['first_team_name'], 'xs') ?></span>
              <span class="fx-score">
                <?php if ($played || $live): ?><b><?= $hs ?></b><i>–</i><b><?= $as ?></b>
                <?php else: ?><span class="fx-vs"><?= e(fmt_time($m) ?: 'vs') ?></span><?php endif; ?>
              </span>
              <span class="fx-team fx-away<?= $aCls ?>"><?= team_badge(team_logo($aId), (string) $m['second_team_name'], 'xs') ?><span class="fx-name"><?= e($m['second_team_name']) ?></span></span>
              <span class="fx-venue"><?= !empty($m['match_field']) ? e($m['match_field']) : '' ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</section>
<?php require __DIR__ . '/inc/footer.php';
