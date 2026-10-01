<?php
require __DIR__ . '/inc/bootstrap.php';

$sorts = [
    'mostValuable' => ['En değerli', 'totalPoints', 'Puan'],
    'topScorers'   => ['Gol', 'totalGoals', 'Gol'],
    'mostAssists'  => ['Asist', 'assists', 'Asist'],
    'mostMatches'  => ['Maç', 'matchesPlayed', 'Maç'],
    'goalsPerMatch'=> ['Maç başı gol', 'goalsPerMatch', 'G/M'],
    'mostSaves'    => ['Kurtarış', 'saves', 'Kurtarış'],
    'mostCards'    => ['Kart', 'totalCards', 'Kart'],
];
$sort = qs('sort', 'topScorers');
if (!isset($sorts[$sort])) {
    $sort = 'topScorers';
}
$teams = ccl_team_map();
uasort($teams, static fn($a, $b) => tr_compare($a['name'], $b['name']));
$teamId = qs_int('takim');
if ($teamId && !isset($teams[$teamId])) {
    $teamId = 0;
}
$search = mb_substr(qs('ara'), 0, 60);
$perPage = 50;
$pageNo = max(1, qs_int('sayfa'));

$result = ccl_player_stats([
    'sort' => $sort,
    'limit' => $perPage,
    'offset' => ($pageNo - 1) * $perPage,
    'teamId' => $teamId ?: null,
    'search' => $search ?: null,
]);
$players = $result['players'];
$total = $result['count'];
$pages = max(1, (int) ceil($total / $perPage));
[$sortLabel, $sortField, $sortShort] = $sorts[$sort];

$link = static fn(array $over = []) => url('istatistikler.php', array_merge([
    'sort' => $sort, 'takim' => $teamId ?: null, 'ara' => $search ?: null,
], $over));

$page = ['title' => 'Oyuncu İstatistikleri', 'nav' => 'stats'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="page-head">
  <div class="container">
    <h1>Oyuncu İstatistikleri</h1>
    <p class="muted"><?= $total ?> oyuncu · <?= e(ccl_scope()['seasonName']) ?></p>
  </div>
</section>

<section class="container section">
  <div class="tabs scroll" role="tablist">
    <?php foreach ($sorts as $key => [$label]): ?>
      <a role="tab" class="tab<?= $sort === $key ? ' active' : '' ?>" href="<?= e($link(['sort' => $key, 'sayfa' => null])) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="toolbar filter" method="get" action="istatistikler.php">
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <label class="sr-only" for="ara">Oyuncu ara</label>
    <input id="ara" type="search" name="ara" value="<?= e($search) ?>" placeholder="Oyuncu ara…">
    <label class="sr-only" for="takim">Takım</label>
    <select id="takim" name="takim" data-autosubmit>
      <option value="">Tüm takımlar</option>
      <?php foreach ($teams as $t): ?>
        <option value="<?= (int) $t['id'] ?>"<?= $teamId === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm" type="submit">Filtrele</button>
  </form>

  <div class="card">
    <?php if (!$players): ?>
      <div class="empty-state"><p>Kriterlere uyan oyuncu bulunamadı.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="table stats-table">
        <thead>
          <tr>
            <th class="pos">#</th>
            <th class="left">Oyuncu</th>
            <th class="left hide-sm">Takım</th>
            <th title="Maç">M</th>
            <th title="Gol">G</th>
            <th title="Asist">A</th>
            <th class="hide-sm" title="Sarı kart">SK</th>
            <th class="hide-sm" title="Kırmızı kart">KK</th>
            <?php if (!in_array($sortField, ['matchesPlayed', 'totalGoals', 'assists'], true)): ?>
              <th class="pts"><?= e($sortShort) ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($players as $i => $p): $rank = ($pageNo - 1) * $perPage + $i + 1; ?>
          <tr>
            <td class="pos"><span class="rank rank-<?= $rank ?>"><?= $rank ?></span></td>
            <td class="left">
              <a class="player-cell" href="<?= e(player_url((int) $p['playerId'])) ?>">
                <?= player_avatar($p['playerImage'] ?? null, $p['playerName'], 'xs') ?>
                <span><?= e($p['playerName']) ?><small class="show-sm"><?= e($p['teamName'] ?? '') ?></small></span>
              </a>
            </td>
            <td class="left hide-sm">
              <a class="team-cell" href="<?= e(team_url((int) $p['teamId'])) ?>"><?= team_badge($p['teamLogo'] ?? null, (string) ($p['teamName'] ?? ''), 'xs') ?><span><?= e($p['teamName'] ?? '') ?></span></a>
            </td>
            <td class="<?= $sortField === 'matchesPlayed' ? 'pts' : '' ?>"><?= (int) $p['matchesPlayed'] ?></td>
            <td class="<?= $sortField === 'totalGoals' ? 'pts' : '' ?>"><?= (int) $p['totalGoals'] ?></td>
            <td class="<?= $sortField === 'assists' ? 'pts' : '' ?>"><?= (int) $p['assists'] ?></td>
            <td class="hide-sm"><?= (int) $p['yellowCards'] ?></td>
            <td class="hide-sm"><?= (int) $p['redCards'] ?></td>
            <?php if (!in_array($sortField, ['matchesPlayed', 'totalGoals', 'assists'], true)):
              $v = $p[$sortField] ?? 0; ?>
              <td class="pts"><strong><?= e(num($v, is_float($v) && fmod((float) $v, 1.0) ? 2 : 0)) ?></strong></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pagination" aria-label="Sayfalar">
    <?php if ($pageNo > 1): ?><a class="btn btn-sm btn-ghost" href="<?= e($link(['sayfa' => $pageNo - 1])) ?>">← Önceki</a><?php endif; ?>
    <span><?= $pageNo ?> / <?= $pages ?></span>
    <?php if ($pageNo < $pages): ?><a class="btn btn-sm btn-ghost" href="<?= e($link(['sayfa' => $pageNo + 1])) ?>">Sonraki →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php';
