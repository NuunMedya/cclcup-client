<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
if (!$id || !ccl_has_team($id)) {
    not_found('Bu takım CCL CUP\'ta yer almıyor.');
}

$team = ccl_team($id) ?: [];
$name = (string) ($team['team_name'] ?? ccl_team_map()[$id]['name']);
$logo = $team['logo'] ?? team_logo($id);
$details = $team['details'] ?? null;
if (is_string($details)) {
    $details = json_decode($details, true);
}
$details = is_array($details) ? $details : [];

$model = season_model();
$t = $model['teams'][$id];
$all = $t['all'];
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
$upcoming = array_values(array_filter($matches, 'match_is_upcoming'));
$results = $t['results'];
$streak = team_streak($results);

// Kapak: takımın en son kapak fotoğraflı maçı
$coverMatch = null;
foreach (array_reverse($results) as $r) {
    if (match_cover($r['match'])) {
        $coverMatch = $r['match'];
        break;
    }
}

$squad = ccl_player_stats(['teamId' => $id, 'limit' => 100, 'sort' => 'mostMatches'])['players'];
$posOrder = ['KL' => 0, 'DF' => 1, 'OS' => 2, 'FV' => 3, '' => 4];
usort($squad, static function ($a, $b) use ($posOrder) {
    $pa = $posOrder[position_short($a['position'] ?? '')] ?? 4;
    $pb = $posOrder[position_short($b['position'] ?? '')] ?? 4;
    return $pa <=> $pb ?: ($b['matchesPlayed'] <=> $a['matchesPlayed']) ?: tr_compare($a['playerName'], $b['playerName']);
});
// Maçın enleri: takımın oyuncularının panelden aldığı ödüller
$squadIds = array_flip(array_map('intval', array_column($squad, 'playerId')));
$teamAwards = [];
foreach ($results as $r) {
    foreach (match_awards($r['match']) as $key => $aw) {
        if (!$aw['id'] || !isset($squadIds[$aw['id']])) continue;
        $pid = $aw['id'];
        if (!isset($teamAwards[$pid])) {
            $teamAwards[$pid] = ['id' => $pid, 'name' => $aw['name'], 'total' => 0, 'mvp' => 0, 'labels' => []];
        }
        $teamAwards[$pid]['total']++;
        $teamAwards[$pid]['mvp'] += $key === 'best_player' ? 1 : 0;
        $teamAwards[$pid]['labels'][$aw['label']] = ($teamAwards[$pid]['labels'][$aw['label']] ?? 0) + 1;
    }
}
uasort($teamAwards, static function ($a, $b) {
    return [$b['mvp'], $b['total']] <=> [$a['mvp'], $a['total']] ?: tr_compare($a['name'], $b['name']);
});
$squadById = [];
foreach ($squad as $pl) $squadById[(int) $pl['playerId']] = $pl;

$leader = static function (array $squad, string $field) {
    $best = null;
    foreach ($squad as $p) {
        if ((float) ($p[$field] ?? 0) > 0 && (!$best || (float) $p[$field] > (float) $best[$field])) {
            $best = $p;
        }
    }
    return $best;
};
$leaders = [
    ['Golcü', 'totalGoals', 'gol', $leader($squad, 'totalGoals')],
    ['Asist', 'assists', 'asist', $leader($squad, 'assists')],
    ['Kurtarış', 'saves', 'kurtarış', $leader($squad, 'saves')],
    ['Pozisyon üretme', 'chancesCreated', 'pozisyon', $leader($squad, 'chancesCreated')],
];

$p = max(1, $all['p']);
$winPct = ratio($all['w'], $all['p']);

$kindParts = [];
$kindClasses = ['right' => 's1', 'left' => 's2', 'header' => 's3', 'penalty' => 's4', 'freekick' => 's5', 'long' => 's6', 'own' => 's7', 'other' => 's8'];
$kindLabels = GOAL_KIND_LABELS + ['own' => 'Rakip kendi kalesine'];
foreach ($kindClasses as $k => $cls) {
    if (!empty($t['kinds'][$k])) {
        $kindParts[] = ['label' => $kindLabels[$k], 'value' => (int) $t['kinds'][$k], 'class' => $cls];
    }
}
$goalTotal = array_sum(array_column($kindParts, 'value'));

// Takımın maç yayınları ve fotoğraf albümleri (canlı maç önce, sonra yeniden eskiye)
$mediaMatches = array_merge(
    array_values(array_filter($matches, 'match_is_live')),
    array_reverse(array_map(static function ($r) { return $r['match']; }, $results))
);
$mediaWall = render_media_wall($mediaMatches);

$page = ['title' => $name, 'nav' => 'teams', 'description' => $name . ' — CCL CUP maçları, kadrosu, istatistikleri ve form durumu.', 'image' => $logo];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<header class="th<?= $coverMatch ? ' has-photo' : '' ?>">
  <div class="th-bg" aria-hidden="true">
    <?php if ($coverMatch): ?><img src="<?= e(media_url(match_cover($coverMatch), 1920)) ?>" alt=""><?php endif; ?>
    <?php if ($logo): ?><img class="th-watermark" src="<?= e(media_url($logo, 640)) ?>" alt=""><?php endif; ?>
  </div>
  <div class="container th-inner">
    <?= team_badge($logo, $name, 'xxl') ?>
    <div class="th-copy">
      <span class="eyebrow">CCL CUP · <?= e(ccl_scope()['seasonName']) ?></span>
      <h1><?= e($name) ?></h1>
      <?php if (!empty($details['motto'])): ?><p class="th-motto">“<?= e($details['motto']) ?>”</p><?php endif; ?>
      <div class="th-badges">
        <?php if ($rank): ?><span class="chip-lg"><b><?= $rank ?>.</b> sırada</span><?php endif; ?>
        <?php if ($row): ?><span class="chip-lg"><b><?= e((string) ($row['display_points'] ?? $row['total_points'] ?? 0)) ?></b> puan</span><?php endif; ?>
        <?php if ($streak['count']): ?><span class="chip-lg chip-<?= e($streak['type']) ?>"><?= e($streak['label']) ?></span><?php endif; ?>
        <?php if (!empty($team['founded_at'])): ?><span class="chip-lg">Kuruluş <b><?= e(substr((string) $team['founded_at'], 0, 4)) ?></b></span><?php endif; ?>
      </div>
      <?php if ($results): ?>
      <div class="form-strip" aria-label="Son maçlar">
        <?php foreach (array_slice($results, -6) as $r): $om = $r['match']; $opp = ccl_team_map()[$r['opp']] ?? ['name' => '', 'logo' => null]; ?>
          <a class="fs fs-<?= e($r['res']) ?>" href="<?= e(match_url((int) $om['id'])) ?>" title="<?= e($opp['name'] . ' · ' . fmt_date($om)) ?>">
            <?= team_badge($opp['logo'], $opp['name'], 'sm') ?>
            <b><?= $r['gf'] ?>-<?= $r['ga'] ?></b>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<nav class="subnav" aria-label="Takım bölümleri">
  <div class="container subnav-inner">
    <a href="#genel">Genel Bakış</a><?php if ($teamAwards): ?><a href="#oduller">Ödüller</a><?php endif; ?><a href="#maclar">Maçlar</a><?php if ($mediaWall): ?><a href="#medya">Yayınlar & Fotoğraflar</a><?php endif; ?><a href="#analiz">Gol Analizi</a><a href="#kadro">Kadro</a>
    <?php if (!empty($details['history']) || !empty($details['achievements'])): ?><a href="#hakkinda">Hakkında</a><?php endif; ?>
  </div>
</nav>

<section class="container section" id="genel">
  <div class="tiles tiles-6">
    <?= stat_tile('Oynanan', $all['p'], $all['w'] . 'G · ' . $all['d'] . 'B · ' . $all['l'] . 'M') ?>
    <?= stat_tile('Galibiyet oranı', pct($winPct), $all['p'] ? $all['w'] . ' / ' . $all['p'] . ' maç' : '') ?>
    <?= stat_tile('Attığı gol', $all['gf'], 'Maç başı ' . num(ratio($all['gf'], $all['p']), 1)) ?>
    <?= stat_tile('Yediği gol', $all['ga'], 'Maç başı ' . num(ratio($all['ga'], $all['p']), 1)) ?>
    <?= stat_tile('Gol yemediği maç', $t['cs'], $all['p'] ? pct(ratio($t['cs'], $all['p'])) . ' oranında' : '') ?>
    <?= stat_tile('İlk golü attığı', $t['first_goal'], $t['first_goal'] ? $t['first_goal_wins'] . ' tanesini kazandı' : '') ?>
  </div>

  <?php if ($all['p']): ?>
  <div class="wdl wdl-lg section-gap" title="Galibiyet / beraberlik / mağlubiyet">
    <span class="wdl-w" style="flex: <?= $all['w'] ?>"><?= $all['w'] ? $all['w'] . ' galibiyet' : '' ?></span>
    <span class="wdl-d" style="flex: <?= $all['d'] ?>"><?= $all['d'] ? $all['d'] . ' beraberlik' : '' ?></span>
    <span class="wdl-l" style="flex: <?= $all['l'] ?>"><?= $all['l'] ? $all['l'] . ' mağlubiyet' : '' ?></span>
  </div>
  <?php endif; ?>

  <div class="grid-main-side section-gap">
    <div class="card">
      <div class="section-head"><h3>Sıralama Grafiği</h3><span class="muted small">Maç günlerine göre</span></div>
      <?php if (!empty($model['rank_history'][$id])): ?>
        <?= svg_rank_chart($model['rank_history'][$id], count(ccl_team_map()), $name . ' sıralama geçmişi') ?>
      <?php else: ?>
        <div class="empty-state small"><p>Grafik ilk maçtan sonra oluşacak.</p></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="section-head"><h3>İç Saha / Deplasman</h3></div>
      <table class="table split-table">
        <thead><tr><th class="left"></th><th>O</th><th>G</th><th>B</th><th>M</th><th>A:Y</th></tr></thead>
        <tbody>
          <?php foreach (['home' => 'Ev sahibi', 'away' => 'Deplasman', 'all' => 'Toplam'] as $k => $label): $r = $t[$k]; ?>
            <tr<?= $k === 'all' ? ' class="total"' : '' ?>><td class="left"><?= e($label) ?></td><td><?= $r['p'] ?></td><td><?= $r['w'] ?></td><td><?= $r['d'] ?></td><td><?= $r['l'] ?></td><td><?= $r['gf'] ?>:<?= $r['ga'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="records">
        <?php if ($t['best_win']): $bm = $t['best_win']['match']; ?>
          <a href="<?= e(match_url((int) $bm['id'])) ?>"><span>En farklı galibiyet</span><b><?= e($bm['first_team_name']) ?> <?= (int) $bm['first_team_score'] ?>-<?= (int) $bm['second_team_score'] ?> <?= e($bm['second_team_name']) ?></b></a>
        <?php endif; ?>
        <?php if ($t['worst_loss']): $wm = $t['worst_loss']['match']; ?>
          <a href="<?= e(match_url((int) $wm['id'])) ?>"><span>En farklı mağlubiyet</span><b><?= e($wm['first_team_name']) ?> <?= (int) $wm['first_team_score'] ?>-<?= (int) $wm['second_team_score'] ?> <?= e($wm['second_team_name']) ?></b></a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="leader-cards section-gap">
    <?php foreach ($leaders as [$label, $field, $unit, $pl]): if (!$pl) continue; ?>
      <a class="leader-card" href="<?= e(player_url((int) $pl['playerId'])) ?>">
        <?= player_avatar($pl['playerImage'] ?? null, $pl['playerName'], 'lg') ?>
        <span class="lc-label"><?= e($label) ?></span>
        <strong class="lc-name"><?= e($pl['playerName']) ?></strong>
        <span class="lc-value"><b><?= e(num($pl[$field])) ?></b> <?= e($unit) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($teamAwards): ?>
  <div class="card section-gap" id="oduller">
    <div class="section-head"><h3>Maçın Enleri Ödülleri</h3><span class="muted small"><?= array_sum(array_column($teamAwards, 'total')) ?> ödül</span></div>
    <div class="taward-grid">
      <?php foreach ($teamAwards as $ta): ?>
        <a class="taward" href="<?= e(player_url($ta['id'])) ?>">
          <?= player_avatar($squadById[$ta['id']]['playerImage'] ?? null, $ta['name'], 'md') ?>
          <span class="taward-text">
            <strong><?= e($ta['name']) ?></strong>
            <span class="taward-chips">
              <?php foreach ($ta['labels'] as $label => $n): ?><span class="mc mc-award"><?= e($label) ?><?= $n > 1 ? ' <b>×' . $n . '</b>' : '' ?></span><?php endforeach; ?>
            </span>
          </span>
          <b class="taward-count" title="Toplam ödül">🏅 <?= $ta['total'] ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<section class="container section" id="maclar">
  <div class="grid-2">
    <div>
      <div class="section-head"><h2>Sonuçlar</h2><span class="muted small"><?= count($results) ?> maç</span></div>
      <?php if (!$results): ?>
        <div class="empty-state"><p>Henüz oynanmış maç yok.</p></div>
      <?php else: ?>
        <div class="rcards">
          <?php foreach (array_reverse($results) as $r): $m = $r['match'];
            $mAwards = match_awards($m);
            $mvp = $mAwards['best_player'] ?? null;
            $links = render_media_links($m, 'sm'); ?>
            <div class="rcard-wrap<?= $mvp || $links ? ' has-foot' : '' ?>">
            <a class="rcard rcard-<?= e($r['res']) ?>" href="<?= e(match_url((int) $m['id'])) ?>">
              <span class="rcard-top"><span class="form form-<?= ['w' => 'win', 'd' => 'draw', 'l' => 'loss'][$r['res']] ?>"><?= ['w' => 'G', 'd' => 'B', 'l' => 'M'][$r['res']] ?></span><?= e(fmt_date($m)) ?></span>
              <span class="rcard-body">
                <span class="rcard-team"><?= team_badge(team_logo((int) $m['home_team_id']), $m['first_team_name'], 'md') ?><small><?= e($m['first_team_name']) ?></small></span>
                <b class="rcard-score"><?= (int) $m['first_team_score'] ?><i>-</i><?= (int) $m['second_team_score'] ?></b>
                <span class="rcard-team"><?= team_badge(team_logo((int) $m['away_team_id']), $m['second_team_name'], 'md') ?><small><?= e($m['second_team_name']) ?></small></span>
              </span>
            </a>
            <?php if ($mvp || $links): ?>
              <div class="rcard-foot">
                <?php if ($mvp): ?><a class="rcard-mvp" href="<?= $mvp['id'] ? e(player_url($mvp['id'])) : e(match_url((int) $m['id'])) . '#enler' ?>" title="Maçın Oyuncusu">⭐ <?= e($mvp['name']) ?></a><?php endif; ?>
                <?= $links ?>
              </div>
            <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <div class="section-head"><h2>Fikstür</h2></div>
      <?php if ($upcoming): ?>
        <div class="match-list"><?php foreach ($upcoming as $m) echo render_match_card($m) . render_media_links($m, 'sm'); ?></div>
      <?php else: ?>
        <div class="empty-state"><p>Planlanmış maç yok. Yeni fikstür açıklandığında burada görünecek.</p></div>
      <?php endif; ?>
      <div class="card section-gap">
        <div class="section-head"><h3>Puan Durumu</h3><a class="more" href="puan-durumu.php">Tümü →</a></div>
        <?php
          $around = $standings;
          if ($rank > 0 && count($standings) > 7) {
              $start = max(0, min(count($standings) - 7, $rank - 4));
              $around = array_slice($standings, $start, 7, true);
          }
          echo render_standings_table($around, true, $id);
        ?>
      </div>
    </div>
  </div>
</section>

<?php if ($mediaWall): ?>
<section class="container section" id="medya">
  <div class="section-head"><h2>Yayınlar & Fotoğraflar</h2><span class="muted small">Takımın maçlarından</span></div>
  <?= $mediaWall ?>
</section>
<?php endif; ?>

<section class="container section" id="analiz">
  <div class="section-head"><h2>Gol Analizi</h2></div>
  <div class="grid-main-side">
    <div class="card">
      <div class="section-head"><h3>Dakikalara Göre Goller</h3>
        <div class="legend-inline"><span class="lg lg-home"></span>Attığı <span class="lg lg-away"></span>Yediği</div>
      </div>
      <?php if ($all['gf'] + $all['ga'] > 0): ?>
        <?= svg_bar_chart(GOAL_BUCKETS, [
            ['label' => 'Attığı', 'values' => $t['buckets_for'], 'class' => 'c-home'],
            ['label' => 'Yediği', 'values' => $t['buckets_against'], 'class' => 'c-away'],
        ], 'Dakika aralıklarına göre atılan ve yenilen goller') ?>
        <div class="half-split">
          <div><span>1. yarı</span><b><?= $t['half_for'][1] ?></b> attı · <b><?= $t['half_against'][1] ?></b> yedi</div>
          <div><span>2. yarı</span><b><?= $t['half_for'][2] ?></b> attı · <b><?= $t['half_against'][2] ?></b> yedi</div>
        </div>
      <?php else: ?>
        <div class="empty-state small"><p>Henüz gol kaydı yok.</p></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="section-head"><h3>Gol Türleri</h3></div>
      <?php if ($goalTotal): ?>
        <div class="donut-wrap">
          <?= svg_donut($kindParts, 'Gol türleri dağılımı', (string) $goalTotal) ?>
          <ul class="donut-legend">
            <?php foreach ($kindParts as $kp): ?><li><span class="lg <?= e($kp['class']) ?>"></span><?= e($kp['label']) ?><b><?= $kp['value'] ?></b></li><?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <div class="empty-state small"><p>Gol türü verisi yok.</p></div>
      <?php endif; ?>
    </div>
  </div>
  <?php
    $scorers = array_values(array_filter($squad, static function ($p) { return (int) $p['totalGoals'] > 0; }));
    usort($scorers, static function ($a, $b) { return $b['totalGoals'] <=> $a['totalGoals']; });
    if ($scorers): $maxG = max(1, (int) $scorers[0]['totalGoals']); ?>
  <div class="card section-gap">
    <div class="section-head"><h3>Takımın Golcüleri</h3><span class="muted small"><?= $all['gf'] ?> golün dağılımı</span></div>
    <div class="hbars">
      <?php foreach (array_slice($scorers, 0, 8) as $sc): ?>
        <a class="hbar" href="<?= e(player_url((int) $sc['playerId'])) ?>">
          <?= player_avatar($sc['playerImage'] ?? null, $sc['playerName'], 'sm') ?>
          <span class="hbar-name"><?= e($sc['playerName']) ?></span>
          <span class="hbar-track"><i style="width: <?= round((int) $sc['totalGoals'] / $maxG * 100, 1) ?>%"></i></span>
          <b><?= (int) $sc['totalGoals'] ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="tiles tiles-6 section-gap">
    <?= stat_tile('Pozisyon üretme', $t['chances'], 'Maç başı ' . num(ratio($t['chances'], $all['p']), 1)) ?>
    <?= stat_tile('Kurtarış', $t['saves'], 'Maç başı ' . num(ratio($t['saves'], $all['p']), 1)) ?>
    <?= stat_tile('Kritik blok', $t['blocks'], 'Maç başı ' . num(ratio($t['blocks'], $all['p']), 1)) ?>
    <?= stat_tile('Gol atamadığı', $t['fts'], 'maç') ?>
    <?= stat_tile('Sarı kart', $t['yellow'], $t['fouls'] ? $t['fouls'] . ' faul' : '') ?>
    <?= stat_tile('Kırmızı kart', $t['red'], $t['subs'] ? $t['subs'] . ' oyuncu değişikliği' : '') ?>
  </div>
</section>

<section class="container section" id="kadro">
  <div class="card">
    <div class="section-head"><h2>Kadro</h2><span class="muted small"><?= count($squad) ?> oyuncu</span></div>
    <?php if (!$squad): ?>
      <div class="empty-state small"><p>Kadro bilgisi henüz girilmedi.</p></div>
    <?php else: ?>
    <?php
      $groups = ['KL' => 'Kaleciler', 'DF' => 'Defans', 'OS' => 'Orta Saha', 'FV' => 'Forvet', '' => 'Diğer'];
      $byPos = [];
      foreach ($squad as $pl) {
          $k = position_short($pl['position'] ?? '');
          $byPos[isset($groups[$k]) ? $k : ''][] = $pl;
      }
      foreach ($groups as $k => $label): if (empty($byPos[$k])) continue; ?>
      <h4 class="squad-title"><span class="pos-tag pos-<?= e(strtolower($k) ?: 'na') ?>"><?= e($k ?: '–') ?></span> <?= e($label) ?> <small><?= count($byPos[$k]) ?></small></h4>
      <div class="squad-grid">
        <?php foreach ($byPos[$k] as $pl): ?>
          <a class="squad-card" href="<?= e(player_url((int) $pl['playerId'])) ?>">
            <span class="squad-photo"><?= player_avatar($pl['playerImage'] ?? null, $pl['playerName'], 'squad') ?></span>
            <?php if (isset($teamAwards[(int) $pl['playerId']])): ?><span class="squad-award" title="Maçın enleri ödülü">🏅 <?= $teamAwards[(int) $pl['playerId']]['total'] ?></span><?php endif; ?>
            <strong><?= e($pl['playerName']) ?></strong>
            <span class="squad-stats"><span><b><?= (int) $pl['matchesPlayed'] ?></b> maç</span><span><b><?= (int) $pl['totalGoals'] ?></b> gol</span><?php if ((int) $pl['saves']): ?><span><b><?= (int) $pl['saves'] ?></b> kurt.</span><?php else: ?><span><b><?= (int) $pl['assists'] ?></b> asist</span><?php endif; ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <details class="details-table">
      <summary>Ayrıntılı kadro istatistikleri</summary>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th class="left">Oyuncu</th><th>Mevki</th><th title="Maç">M</th><th title="İlk 11">İ11</th><th title="Gol">G</th><th title="Asist">A</th><th title="Pozisyon">Poz</th><th title="Kurtarış">Kur</th><th title="Blok">Blk</th><th title="Sarı kart">SK</th><th title="Kırmızı kart">KK</th></tr></thead>
          <tbody>
          <?php foreach ($squad as $pl): ?>
            <tr>
              <td class="left"><a class="player-cell" href="<?= e(player_url((int) $pl['playerId'])) ?>"><?= player_avatar($pl['playerImage'] ?? null, $pl['playerName'], 'xs') ?><span><?= e($pl['playerName']) ?></span></a></td>
              <td><span class="pos-tag pos-<?= e(strtolower(position_short($pl['position'] ?? '')) ?: 'na') ?>"><?= e(position_short($pl['position'] ?? '') ?: '–') ?></span></td>
              <td><?= (int) $pl['matchesPlayed'] ?></td><td><?= (int) $pl['started'] ?></td>
              <td><strong><?= (int) $pl['totalGoals'] ?></strong></td><td><?= (int) $pl['assists'] ?></td>
              <td><?= (int) $pl['chancesCreated'] ?></td><td><?= (int) $pl['saves'] ?></td><td><?= (int) $pl['criticalBlocks'] ?></td>
              <td><?= (int) $pl['yellowCards'] ?></td><td><?= (int) $pl['redCards'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($details['history']) || !empty($details['achievements']) || !empty($details['social'])): ?>
<section class="container section" id="hakkinda">
  <div class="grid-2">
    <?php if (!empty($details['history'])): ?>
      <div class="card"><div class="section-head"><h3>Tarihçe</h3></div><p class="prose"><?= nl2br(e($details['history'])) ?></p></div>
    <?php endif; ?>
    <div class="stack">
      <?php if (!empty($details['achievements']) && is_array($details['achievements'])): ?>
        <div class="card"><div class="section-head"><h3>Başarılar</h3></div>
          <ul class="achievements">
            <?php foreach ($details['achievements'] as $a): if (!is_array($a) || empty($a['title'])) continue; ?>
              <li><span class="trophy">🏆</span><span><b><?= e($a['title']) ?></b><small><?= e(trim(($a['season'] ?? '') . ' ' . (!empty($a['rank']) ? '· ' . $a['rank'] : ''))) ?></small></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if (!empty($details['social']) && is_array($details['social'])): ?>
        <div class="card"><div class="section-head"><h3>Sosyal Medya</h3></div>
          <div class="social-links">
            <?php foreach (['instagram' => 'Instagram', 'x' => 'X', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'website' => 'Web sitesi'] as $k => $label):
              $v = trim((string) ($details['social'][$k] ?? '')); if ($v === '' || !preg_match('#^https?://#i', $v)) continue; ?>
              <a class="btn btn-ghost btn-sm" href="<?= e($v) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php';
