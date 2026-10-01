<?php
require __DIR__ . '/inc/bootstrap.php';

$groupData = ccl_groups();
$groups = $groupData['groups'];
$showOverall = !$groups || ($groupData['settings']['show_overall_standings'] ?? true);

// Yalnızca puan tablosu olan (lig usulü "grup") gruplar sekme olarak gösterilir; "etap" eşleşmelerdir.
$tableGroups = array_values(array_filter($groups, static function ($g) {
    return ($g['group_kind'] ?? 'grup') === 'grup';
}));

$selected = qs('grup') ?: ($showOverall ? 'genel' : (string) ($tableGroups[0]['id'] ?? 'genel'));
$selectedGroup = null;
foreach ($tableGroups as $g) {
    if ((string) $g['id'] === (string) $selected) {
        $selectedGroup = $g;
    }
}
if (!$selectedGroup) {
    $selected = 'genel';
}

$rows = ccl_standings($selectedGroup ? (int) $selectedGroup['id'] : null);

$page = ['title' => 'Puan Durumu', 'nav' => 'standings'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="page-head">
  <div class="container">
    <h1>Puan Durumu</h1>
    <p class="muted"><?= e(ccl_scope()['seasonName']) ?></p>
  </div>
</section>

<section class="container section">
  <?php if ($tableGroups): ?>
    <div class="tabs scroll" role="tablist">
      <?php if ($showOverall): ?>
        <a role="tab" class="tab<?= $selected === 'genel' ? ' active' : '' ?>" href="puan-durumu.php?grup=genel">Genel</a>
      <?php endif; ?>
      <?php foreach ($tableGroups as $g): ?>
        <a role="tab" class="tab<?= (string) $selected === (string) $g['id'] ? ' active' : '' ?>" href="<?= e(url('puan-durumu.php', ['grup' => $g['id']])) ?>"><?= e($g['group_name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <?= render_standings_table($rows) ?>
    <div class="legend">
      <span><b>O</b> Oynanan</span><span><b>G</b> Galibiyet</span><span><b>B</b> Beraberlik</span>
      <span><b>M</b> Mağlubiyet</span><span><b>A</b> Atılan</span><span><b>Y</b> Yenilen</span>
      <span><b>AV</b> Averaj</span><span><b>P</b> Puan</span>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
