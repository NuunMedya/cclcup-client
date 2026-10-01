<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
$match = $id ? ccl_match($id) : null;
if (!$match) {
    not_found('Bu maç CCL CUP\'a ait değil ya da henüz yayınlanmadı.');
}

$homeId = (int) $match['home_team_id'];
$awayId = (int) $match['away_team_id'];
$home = (string) $match['first_team_name'];
$away = (string) $match['second_team_name'];
$played = match_is_played($match);
$live = match_is_live($match);
$showScore = $played || $live;

$lineup = ccl_match_lineup($id);
$events = ($played || $live) ? ccl_match_events($id) : [];

// Oyuncu id => ad
$names = [];
foreach (['home', 'away'] as $side) {
    foreach ($lineup[$side] as $p) {
        $pid = (int) ($p['playerId'] ?? $p['oyuncu_id'] ?? $p['id'] ?? 0);
        if ($pid) {
            $names[$pid] = (string) ($p['playerName'] ?? $p['name'] ?? '');
        }
    }
}
$pname = static fn($pid) => $pid ? ($names[(int) $pid] ?? 'Oyuncu') : '';

usort($events, static fn($a, $b) => [(int) ($a['devre'] ?? 1), (int) ($a['dakika'] ?? 0), (int) $a['id']] <=> [(int) ($b['devre'] ?? 1), (int) ($b['dakika'] ?? 0), (int) $b['id']]);

// Gol atanlar (skor özeti) ve maç istatistikleri
$scorers = ['home' => [], 'away' => []];
$statKeys = [
    'goal' => 'Gol', 'chance' => 'Pozisyon', 'save' => 'Kurtarış', 'block' => 'Kritik blok',
    'duel' => 'İkili mücadele', 'aerial' => 'Hava topu', 'foul' => 'Faul', 'yellow' => 'Sarı kart', 'red' => 'Kırmızı kart',
];
$stats = array_fill_keys(array_keys($statKeys), ['home' => 0, 'away' => 0]);
$keyEvents = [];

foreach ($events as $ev) {
    $info = event_info((string) ($ev['olay_kodu'] ?? ''));
    $teamId = (int) ($ev['takim_id'] ?? 0);
    $side = $teamId === $homeId ? 'home' : ($teamId === $awayId ? 'away' : null);
    if (!$side) {
        continue;
    }
    $type = $info['type'];
    if ($type === 'own-goal') {
        // Kendi kalesine gol rakibin hanesine yazılır.
        $other = $side === 'home' ? 'away' : 'home';
        $scorers[$other][] = $pname($ev['oyuncu_id'] ?? 0) . ' (k.k.) ' . (int) $ev['dakika'] . "'";
    } elseif ($type === 'goal') {
        $scorers[$side][] = $pname($ev['oyuncu_id'] ?? 0) . ' ' . (int) $ev['dakika'] . "'";
    }
    if (isset($stats[$type])) {
        $stats[$type][$side]++;
    }
    if ($info['key']) {
        $keyEvents[] = ['ev' => $ev, 'info' => $info, 'side' => $side];
    }
}
$hasStats = array_sum(array_map(static fn($s) => $s['home'] + $s['away'], $stats)) > 0;

$video = (string) ($match['match_video'] ?? '');
$video = preg_match('#^https?://#i', $video) ? $video : '';

$page = [
    'title' => $home . ' - ' . $away,
    'nav' => 'fixtures',
    'refresh' => $live ? 60 : 0,
    'description' => $home . ' ' . ($showScore ? $match['first_team_score'] . '-' . $match['second_team_score'] : 'vs') . ' ' . $away . ' · ' . fmt_date($match) . ' · CCL CUP',
];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="match-hero<?= $live ? ' is-live' : '' ?>">
  <div class="container">
    <div class="match-hero-meta">
      <?php if ($live): ?><span class="pill pill-live">CANLI</span><?php elseif ($played): ?><span class="pill">Maç sonucu</span><?php else: ?><span class="pill pill-soon">Fikstür</span><?php endif; ?>
      <span><?= e(fmt_date($match, true)) ?><?= fmt_time($match) ? ' · ' . e(fmt_time($match)) : '' ?></span>
      <?php if (!empty($match['match_field'])): ?><span>📍 <?= e($match['match_field']) ?></span><?php endif; ?>
    </div>
    <div class="scoreboard">
      <a class="sb-team" href="<?= e(team_url($homeId)) ?>">
        <?= team_badge(team_logo($homeId), $home, 'xl') ?>
        <span><?= e($home) ?></span>
      </a>
      <div class="sb-center">
        <?php if ($showScore): ?>
          <span class="sb-score"><?= (int) $match['first_team_score'] ?><i>-</i><?= (int) $match['second_team_score'] ?></span>
        <?php else: ?>
          <span class="sb-time"><?= e(fmt_time($match) ?: 'VS') ?></span>
        <?php endif; ?>
      </div>
      <a class="sb-team" href="<?= e(team_url($awayId)) ?>">
        <?= team_badge(team_logo($awayId), $away, 'xl') ?>
        <span><?= e($away) ?></span>
      </a>
    </div>
    <?php if ($scorers['home'] || $scorers['away']): ?>
    <div class="sb-scorers">
      <ul><?php foreach ($scorers['home'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul>
      <span class="sb-ball" aria-hidden="true">⚽</span>
      <ul><?php foreach ($scorers['away'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <?php if ($video): ?>
      <div class="center"><a class="btn" href="<?= e($video) ?>" target="_blank" rel="noopener">▶ Maç videosunu izle</a></div>
    <?php endif; ?>
  </div>
</section>

<section class="container section grid-2">
  <div class="card">
    <div class="section-head"><h2>Maç Akışı</h2></div>
    <?php if (!$keyEvents): ?>
      <div class="empty-state small"><p><?= $showScore ? 'Bu maç için olay kaydı bulunmuyor.' : 'Maç başladığında olaylar burada yer alacak.' ?></p></div>
    <?php else: ?>
      <ol class="timeline">
        <?php foreach ($keyEvents as $k):
          $ev = $k['ev'];
          if ($k['info']['type'] === 'sub') {
              $text = '<b>' . e($pname($ev['oyuncu_giren_id'] ?? 0)) . '</b> girdi, ' . e($pname($ev['oyuncu_cikan_id'] ?? 0)) . ' çıktı';
          } else {
              $text = '<b>' . e($pname($ev['oyuncu_id'] ?? 0)) . '</b> <small>' . e($k['info']['label']) . '</small>';
          } ?>
          <li class="tl-<?= e($k['side']) ?> tl-<?= e($k['info']['type']) ?>">
            <span class="tl-min"><?= (int) $ev['dakika'] ?>'</span>
            <span class="tl-body"><?= event_icon($k['info']['type']) ?> <span><?= $text ?></span></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="section-head"><h2>İstatistikler</h2></div>
    <?php if (!$hasStats): ?>
      <div class="empty-state small"><p>İstatistik bulunmuyor.</p></div>
    <?php else: ?>
      <div class="stat-bars">
        <div class="stat-bars-head"><span><?= e($home) ?></span><span><?= e($away) ?></span></div>
        <?php foreach ($statKeys as $key => $label):
          $h = $stats[$key]['home']; $a = $stats[$key]['away'];
          if ($h + $a === 0) continue;
          $pct = round($h / ($h + $a) * 100); ?>
          <div class="stat-bar">
            <div class="stat-bar-labels"><b><?= $h ?></b><span><?= e($label) ?></span><b><?= $a ?></b></div>
            <div class="stat-bar-track"><span class="home" style="width: <?= $pct ?>%"></span><span class="away" style="width: <?= 100 - $pct ?>%"></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="container section">
  <div class="section-head"><h2>Kadrolar</h2></div>
  <?php if (!$lineup['home'] && !$lineup['away']): ?>
    <div class="empty-state"><p>Kadrolar henüz açıklanmadı.</p></div>
  <?php else: ?>
  <div class="grid-2">
    <?php foreach (['home' => [$homeId, $home], 'away' => [$awayId, $away]] as $side => [$tid, $tname]):
      $players = $lineup[$side];
      $starters = array_filter($players, static fn($p) => ($p['role'] ?? 'starter') === 'starter');
      $subs = array_filter($players, static fn($p) => ($p['role'] ?? 'starter') !== 'starter'); ?>
      <div class="card lineup">
        <div class="lineup-head"><?= team_badge(team_logo($tid), $tname, 'sm') ?><h3><?= e($tname) ?></h3></div>
        <?php foreach (['İlk 11' => $starters, 'Yedekler' => $subs] as $title => $group): if (!$group) continue; ?>
          <h4 class="lineup-title"><?= e($title) ?></h4>
          <ul class="lineup-list">
            <?php foreach ($group as $p):
              $pid = (int) ($p['playerId'] ?? $p['oyuncu_id'] ?? 0);
              $pn = (string) ($p['playerName'] ?? $p['name'] ?? ''); ?>
              <li>
                <span class="lineup-num"><?= e($p['number'] ?? '') ?: '' ?></span>
                <?= player_avatar($p['playerImg'] ?? null, $pn, 'xs') ?>
                <?php if ($pid): ?>
                  <a href="<?= e(player_url($pid)) ?>"><?= e($pn) ?></a>
                <?php else: ?>
                  <span><?= e($pn) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['captain'])): ?><span class="captain" title="Kaptan">C</span><?php endif; ?>
                <span class="pos-tag pos-<?= e(strtolower(position_short($p['position'] ?? '')) ?: 'na') ?>"><?= e(position_short($p['position'] ?? '') ?: '–') ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/inc/footer.php';
