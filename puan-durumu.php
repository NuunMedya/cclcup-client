<?php
require __DIR__ . '/inc/bootstrap.php';

$groupData = ccl_groups();
$groups = $groupData['groups'];
$showOverall = !$groups || ($groupData['settings']['show_overall_standings'] ?? true);

// Lig usulü ("grup") gruplar puan tablosu sekmesi; eleme etapları ("etap") kupa sekmesi olarak gösterilir.
$tableGroups = array_values(array_filter($groups, static function ($g) {
    return ($g['group_kind'] ?? 'grup') === 'grup';
}));
$cups = ccl_cups();

$selected = qs('grup') ?: ($showOverall ? 'genel' : (string) ($tableGroups[0]['id'] ?? 'genel'));
$selectedGroup = null;
$selectedCup = ccl_cup_find((string) $selected);
foreach ($tableGroups as $g) {
    if ((string) $g['id'] === (string) $selected) {
        $selectedGroup = $g;
    }
}
if ($selectedCup) {
    $selected = $selectedCup['key'];
} elseif (!$selectedGroup) {
    $selected = 'genel';
}

$rows = $selectedCup ? [] : ccl_standings($selectedGroup ? (int) $selectedGroup['id'] : null);

$page = ['title' => $selectedCup ? $selectedCup['name'] : 'Puan Durumu', 'nav' => 'standings'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="page-head">
  <div class="container">
    <h1><?= $selectedCup ? e($selectedCup['name']) : 'Puan Durumu' ?></h1>
    <p class="muted"><?= e(ccl_scope()['seasonName']) ?><?= $selectedCup ? ' · Eleme etabı' : '' ?></p>
  </div>
</section>

<section class="container section">
  <?php if ($tableGroups || $cups): ?>
    <div class="tabs scroll" role="tablist">
      <?php if ($showOverall): ?>
        <a role="tab" class="tab<?= $selected === 'genel' ? ' active' : '' ?>" href="puan-durumu.php?grup=genel">Genel</a>
      <?php endif; ?>
      <?php foreach ($tableGroups as $g): ?>
        <a role="tab" class="tab<?= (string) $selected === (string) $g['id'] ? ' active' : '' ?>" href="<?= e(url('puan-durumu.php', ['grup' => $g['id']])) ?>"><?= e($g['group_name']) ?></a>
      <?php endforeach; ?>
      <?php foreach ($cups as $c): ?>
        <a role="tab" class="tab tab-cup tone-<?= e($c['tone']) ?><?= $selected === $c['key'] ? ' active' : '' ?>" href="<?= e(url('puan-durumu.php', ['grup' => $c['key']])) ?>"><span class="tab-trophy" aria-hidden="true">🏆</span><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($selectedCup): ?>
    <div class="card cup-card tone-<?= e($selectedCup['tone']) ?>">
      <div class="section-head">
        <h2><span aria-hidden="true">🏆</span> Kupa Yolu</h2>
        <span class="muted small"><?= e(implode(' → ', array_map(static function ($s) { return $s['label']; }, $selectedCup['stages']))) ?></span>
      </div>
      <?= render_cup_bracket($selectedCup) ?>
      <div class="legend">
        <span><b class="lg-win"></b> Tur atlayan</span><span><b class="lg-out"></b> Elenen</span>
        <span><b>P</b> Penaltılarla / kurayla</span><span>Eşleşmeye tıklayınca maç detayı açılır</span>
      </div>
    </div>
    <?php foreach ($selectedCup['stages'] as $st) echo render_cup_stage_list($st, $selectedCup); ?>
  <?php else: ?>
  <div class="card">
    <?= render_standings_table($rows) ?>
    <div class="legend">
      <span><b>O</b> Oynanan</span><span><b>G</b> Galibiyet</span><span><b>B</b> Beraberlik</span>
      <span><b>M</b> Mağlubiyet</span><span><b>A</b> Atılan</span><span><b>Y</b> Yenilen</span>
      <span><b>AV</b> Averaj</span><span><b>P</b> Puan</span>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php';
