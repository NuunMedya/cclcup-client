<?php
require __DIR__ . '/inc/bootstrap.php';

$standings = ccl_standings();
$rowsById = [];
foreach ($standings as $i => $r) {
    $rowsById[(int) $r['team_id']] = $r + ['rank' => $i + 1];
}
$teams = ccl_team_map();
uasort($teams, 'compare_team_names');

$page = ['title' => 'Takımlar', 'nav' => 'teams'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="page-head">
  <div class="container">
    <h1>Takımlar</h1>
    <p class="muted"><?= count($teams) ?> kurum takımı</p>
  </div>
</section>

<section class="container section">
  <?php if (!$teams): ?>
    <div class="empty-state big"><p>Takım bilgisi bulunamadı.</p></div>
  <?php else: ?>
  <div class="team-grid">
    <?php foreach ($teams as $t): $r = $rowsById[$t['id']] ?? null; ?>
      <a class="team-card" href="<?= e(team_url($t['id'])) ?>">
        <?= team_badge($t['logo'], $t['name'], 'xl') ?>
        <h3><?= e($t['name']) ?></h3>
        <?php if ($r): ?>
          <div class="team-card-stats">
            <span><b><?= (int) $r['rank'] ?>.</b> sıra</span>
            <span><b><?= e((string) ($r['display_points'] ?? $r['total_points'] ?? 0)) ?></b> puan</span>
            <span><b><?= (int) ($r['played'] ?? 0) ?></b> maç</span>
          </div>
          <div class="form-row"><?= form_badges($r['last5'] ?? '') ?></div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php';
