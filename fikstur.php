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

$list = array_filter($matches, static function ($m) use ($tab, $teamFilter) {
    if ($teamFilter && (int) $m['home_team_id'] !== $teamFilter && (int) $m['away_team_id'] !== $teamFilter) {
        return false;
    }
    if ($tab === 'fikstur') return !match_is_played($m);
    if ($tab === 'sonuclar') return match_is_played($m) || match_is_live($m);
    return true;
});
// Sonuçlarda en yeni üstte.
if ($tab === 'sonuclar') {
    $list = array_reverse($list);
}

$byDate = [];
foreach ($list as $m) {
    $byDate[substr((string) ($m['date'] ?? ''), 0, 10) ?: 'tbd'][] = $m;
}

$page = ['title' => 'Fikstür & Sonuçlar', 'nav' => 'fixtures'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="page-head">
  <div class="container">
    <h1>Fikstür &amp; Sonuçlar</h1>
    <p class="muted"><?= count($matches) ?> maç · <?= count(array_filter($matches, 'match_is_played')) ?> oynandı</p>
  </div>
</section>

<section class="container section">
  <div class="toolbar">
    <div class="tabs" role="tablist">
      <?php foreach (['tumu' => 'Tümü', 'fikstur' => 'Fikstür', 'sonuclar' => 'Sonuçlar'] as $k => $label): ?>
        <a role="tab" aria-selected="<?= $tab === $k ? 'true' : 'false' ?>" class="tab<?= $tab === $k ? ' active' : '' ?>" href="<?= e(url('fikstur.php', ['tab' => $k, 'takim' => $teamFilter ?: null])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <form class="filter" method="get" action="fikstur.php">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <label class="sr-only" for="takim">Takım</label>
      <select id="takim" name="takim" data-autosubmit>
        <option value="">Tüm takımlar</option>
        <?php foreach ($teams as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $teamFilter === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm" type="submit">Filtrele</button></noscript>
    </form>
  </div>

  <?php if (!$byDate): ?>
    <div class="empty-state big">
      <div class="empty-icon">📅</div>
      <p><?= $tab === 'fikstur' ? 'Planlanmış maç bulunmuyor.' : 'Gösterilecek maç bulunamadı.' ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($byDate as $date => $dayMatches): $first = $dayMatches[0]; ?>
    <div class="day-group">
      <h3 class="day-title">
        <?php if ($date === 'tbd'): ?>Tarihi belirlenecek<?php else: ?>
          <?= e(fmt_date($first, true)) ?>
        <?php endif; ?>
        <span class="count"><?= count($dayMatches) ?> maç</span>
      </h3>
      <div class="match-list">
        <?php foreach ($dayMatches as $m) echo render_match_card($m); ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<?php require __DIR__ . '/inc/footer.php';
