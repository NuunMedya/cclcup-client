<?php
require __DIR__ . '/inc/bootstrap.php';

$id = qs_int('id');
$match = $id ? ccl_match($id) : null;
if (!$match || ($match['mac_durumu'] ?? '') === 'taslak') {
    not_found('Bu maç CCL CUP\'a ait değil ya da henüz yayınlanmadı.');
}

$homeId = (int) $match['home_team_id'];
$awayId = (int) $match['away_team_id'];
$home = (string) $match['first_team_name'];
$away = (string) $match['second_team_name'];
$hs = (int) $match['first_team_score'];
$as = (int) $match['second_team_score'];
$played = match_is_played($match);
$live = match_is_live($match);
$showScore = $played || $live;

$model = season_model();
$lineup = ccl_match_lineup($id);
$events = $live ? ccl_match_events($id) : ($model['events'][$id] ?? ($showScore ? ccl_match_events($id) : []));
usort($events, 'compare_events');
$playerStats = $showScore ? ccl_match_player_stats($id) : [];
$story = $showScore ? match_story($match) : null;
$awards = match_awards($match);
$stars = $showScore ? match_stars($id, 3) : [];
$cover = match_cover($match);
$gallery = match_gallery($match);
$videoId = youtube_id(match_video_url($match));
$interviewUrl = match_interview_url($match);
$interviewId = youtube_id($interviewUrl);
$possH = (int) ($match['first_team_percentage'] ?? 0);
$possA = (int) ($match['second_team_percentage'] ?? 0);

// Oyuncu bilgileri: kadro + sezon dizini
$people = [];
foreach (['home' => $homeId, 'away' => $awayId] as $side => $tid) {
    foreach ($lineup[$side] as $p) {
        $pid = (int) ($p['playerId'] ?? $p['oyuncu_id'] ?? $p['id'] ?? 0);
        if ($pid) {
            $people[$pid] = [
                'name' => (string) ($p['playerName'] ?? $p['name'] ?? ''),
                'img' => $p['playerImg'] ?? null,
                'pos' => (string) ($p['position'] ?? ''),
                'num' => (string) ($p['number'] ?? ''),
                'rating' => $p['puan'] ?? null,
                'role' => (string) ($p['role'] ?? 'starter'),
                'captain' => !empty($p['captain']),
                'side' => $side,
            ];
        }
    }
}
$index = ccl_player_index();
$pname = static function ($pid) use ($people, $index) {
    $pid = (int) $pid;
    if (!$pid) return '';
    if (isset($people[$pid]) && $people[$pid]['name'] !== '') return $people[$pid]['name'];
    return isset($index[$pid]) ? (string) $index[$pid]['playerName'] : 'Oyuncu';
};
$pimg = static function ($pid) use ($people, $index) {
    $pid = (int) $pid;
    return $people[$pid]['img'] ?? ($index[$pid]['playerImage'] ?? null);
};

// Olayları işle
$stat = [];
$statKeys = [
    'goal' => 'Gol', 'chance' => 'Pozisyon üretme', 'save' => 'Kurtarış', 'block' => 'Kritik blok',
    'duel' => 'Kazanılan ikili mücadele', 'aerial' => 'Kazanılan hava topu', 'assist' => 'Asist',
    'foul' => 'Faul', 'yellow' => 'Sarı kart', 'red' => 'Kırmızı kart', 'sub' => 'Oyuncu değişikliği',
];
foreach (array_keys($statKeys) as $k) {
    $stat[$k] = ['home' => [0, 0, 0], 'away' => [0, 0, 0]]; // [toplam, 1. devre, 2. devre]
}
$playerMarks = [];
$feed = [];
$halfScore = [1 => ['home' => 0, 'away' => 0], 2 => ['home' => 0, 'away' => 0]];
$maxMinute = 50;
foreach ($events as $ev) {
    $info = event_info((string) $ev['olay_kodu']);
    $tid = (int) $ev['takim_id'];
    $side = $tid === $homeId ? 'home' : ($tid === $awayId ? 'away' : null);
    if (!$side) continue;
    $type = $info['type'];
    $half = (int) ($ev['devre'] ?? 1) === 2 ? 2 : 1;
    $min = (int) $ev['dakika'];
    $maxMinute = max($maxMinute, $min);
    $countSide = $side;
    if ($type === 'own-goal') {
        $countSide = $side === 'home' ? 'away' : 'home';
        $stat['goal'][$countSide][0]++;
        $stat['goal'][$countSide][$half]++;
        $halfScore[$half][$countSide]++;
    } elseif (isset($stat[$type])) {
        $stat[$type][$side][0]++;
        $stat[$type][$side][$half]++;
        if ($type === 'goal') {
            $halfScore[$half][$side]++;
        }
    }
    $pid = (int) ($ev['oyuncu_id'] ?? 0);
    if ($type === 'sub') {
        $in = (int) ($ev['oyuncu_giren_id'] ?? 0);
        $out = (int) ($ev['oyuncu_cikan_id'] ?? 0);
        if ($in) $playerMarks[$in]['in'] = $min;
        if ($out) $playerMarks[$out]['out'] = $min;
    } elseif ($pid && in_array($type, ['goal', 'yellow', 'red', 'own-goal'], true)) {
        $playerMarks[$pid][$type] = ($playerMarks[$pid][$type] ?? 0) + 1;
    }
    $feed[] = ['ev' => $ev, 'info' => $info, 'side' => $side, 'scoreSide' => $countSide, 'half' => $half, 'min' => $min];
}
$hasEvents = (bool) $feed;

// Akışta skor ilerleyişi
$running = ['home' => 0, 'away' => 0];
foreach ($feed as $i => $f) {
    if ($f['info']['type'] === 'goal' || $f['info']['type'] === 'own-goal') {
        $running[$f['scoreSide']]++;
        $feed[$i]['score'] = $running['home'] . '-' . $running['away'];
    }
}

// Bu maçtan önceki son 5 maç
$formBefore = static function (int $teamId) use ($model, $match) {
    $out = [];
    $key = match_sort_key($match) . sprintf('%010d', (int) $match['id']);
    foreach ($model['teams'][$teamId]['results'] ?? [] as $r) {
        if (match_sort_key($r['match']) . sprintf('%010d', (int) $r['match']['id']) < $key) {
            $out[] = $r;
        }
    }
    return array_slice($out, -5);
};
$standings = ccl_standings();
$rankOf = [];
foreach ($standings as $i => $r) {
    $rankOf[(int) $r['team_id']] = ['rank' => $i + 1, 'row' => $r];
}
$h2h = array_values(array_filter(ccl_matches(), static function ($m) use ($homeId, $awayId, $id) {
    $ids = [(int) $m['home_team_id'], (int) $m['away_team_id']];
    return (int) $m['id'] !== $id && in_array($homeId, $ids, true) && in_array($awayId, $ids, true);
}));
$sameDay = array_values(array_filter(ccl_matches(), static function ($m) use ($match, $id) {
    return (int) $m['id'] !== $id && substr((string) $m['date'], 0, 10) === substr((string) $match['date'], 0, 10);
}));

// Oyuncu istatistik tablosu
$psBySide = ['home' => [], 'away' => []];
foreach ($playerStats as $p) {
    $tid = (int) $p['teamId'];
    $side = $tid === $homeId ? 'home' : ($tid === $awayId ? 'away' : null);
    if ($side) $psBySide[$side][] = $p;
}
foreach (['home', 'away'] as $side) {
    usort($psBySide[$side], static function ($a, $b) {
        return [$b['started'], $b['totalGoals'], $b['assists'], $b['saves']] <=> [$a['started'], $a['totalGoals'], $a['assists'], $a['saves']];
    });
}

$title = $home . ' ' . ($showScore ? $hs . '-' . $as : 'vs') . ' ' . $away;
$page = [
    'title' => $story ? $story['headline'] : $home . ' - ' . $away,
    'nav' => 'fixtures',
    'refresh' => $live ? 60 : 0,
    'description' => $story ? mb_substr($story['summary'], 0, 160) : $title . ' · ' . fmt_date($match) . ' · CCL CUP',
    'image' => $cover,
];
require __DIR__ . '/inc/header.php';
echo render_api_notice();

$posOrder = ['KL' => 0, 'DF' => 1, 'OS' => 2, 'FV' => 3, '' => 4];
$marksHtml = static function (int $pid) use ($playerMarks) {
    $m = $playerMarks[$pid] ?? [];
    $out = str_repeat('<span class="mk mk-goal" title="Gol">⚽</span>', (int) ($m['goal'] ?? 0));
    $out .= str_repeat('<span class="mk mk-own" title="Kendi kalesine">⚽</span>', (int) ($m['own-goal'] ?? 0));
    $out .= str_repeat('<span class="mk card-icon card-yellow" title="Sarı kart"></span>', (int) ($m['yellow'] ?? 0));
    $out .= str_repeat('<span class="mk card-icon card-red" title="Kırmızı kart"></span>', (int) ($m['red'] ?? 0));
    if (isset($m['in'])) $out .= '<span class="mk mk-in" title="Oyuna girdi">▲' . (int) $m['in'] . "'</span>";
    if (isset($m['out'])) $out .= '<span class="mk mk-out" title="Oyundan çıktı">▼' . (int) $m['out'] . "'</span>";
    return $out;
};
?>

<article class="match-page">
<header class="mh<?= $live ? ' is-live' : '' ?><?= $cover ? ' has-photo' : '' ?>">
  <div class="mh-bg" aria-hidden="true">
    <?php if ($cover): ?><img src="<?= e($cover) ?>" alt=""><?php endif; ?>
  </div>
  <div class="container mh-inner">
    <div class="mh-meta">
      <?php if ($live): ?><span class="pill pill-live">● CANLI</span><?php elseif ($played): ?><span class="pill pill-glass">Maç sonucu</span><?php else: ?><span class="pill pill-glass">Fikstür</span><?php endif; ?>
      <span><?= e(fmt_date($match, true)) ?><?= fmt_time($match) ? ' · ' . e(fmt_time($match)) : '' ?></span>
      <?php if (!empty($match['match_field'])): ?><span>📍 <?= e($match['match_field']) ?></span><?php endif; ?>
      <span><?= e(ccl_scope()['seasonName']) ?></span>
    </div>

    <div class="mh-board">
      <a class="mh-team" href="<?= e(team_url($homeId)) ?>">
        <?= team_badge(team_logo($homeId), $home, 'xl') ?>
        <span class="mh-name"><?= e($home) ?></span>
        <?php if (isset($rankOf[$homeId])): ?><small><?= $rankOf[$homeId]['rank'] ?>. sırada</small><?php endif; ?>
      </a>
      <div class="mh-center">
        <?php if ($showScore): ?>
          <div class="mh-score"><span><?= $hs ?></span><i>:</i><span><?= $as ?></span></div>
          <?php if ($hasEvents && $played): ?><div class="mh-ht">İlk yarı <?= $halfScore[1]['home'] ?>-<?= $halfScore[1]['away'] ?></div><?php endif; ?>
        <?php else: ?>
          <div class="mh-kick"><?= e(fmt_time($match) ?: 'VS') ?></div>
          <div class="mh-ht countdown" data-countdown="<?= e(substr((string) $match['date'], 0, 10) . 'T' . (fmt_time($match) ?: '00:00') . ':00+03:00') ?>"></div>
        <?php endif; ?>
      </div>
      <a class="mh-team" href="<?= e(team_url($awayId)) ?>">
        <?= team_badge(team_logo($awayId), $away, 'xl') ?>
        <span class="mh-name"><?= e($away) ?></span>
        <?php if (isset($rankOf[$awayId])): ?><small><?= $rankOf[$awayId]['rank'] ?>. sırada</small><?php endif; ?>
      </a>
    </div>

    <?php if ($stat['goal']['home'][0] + $stat['goal']['away'][0] > 0): ?>
    <div class="mh-scorers">
      <ul>
        <?php foreach ($feed as $f): if ($f['scoreSide'] !== 'home' || !in_array($f['info']['type'], ['goal', 'own-goal'], true)) continue; ?>
          <li><?= e($pname($f['ev']['oyuncu_id'])) ?><?= $f['info']['type'] === 'own-goal' ? ' (k.k.)' : '' ?> <b><?= $f['min'] ?>'</b></li>
        <?php endforeach; ?>
      </ul>
      <span class="mh-ball" aria-hidden="true">⚽</span>
      <ul>
        <?php foreach ($feed as $f): if ($f['scoreSide'] !== 'away' || !in_array($f['info']['type'], ['goal', 'own-goal'], true)) continue; ?>
          <li><b><?= $f['min'] ?>'</b> <?= e($pname($f['ev']['oyuncu_id'])) ?><?= $f['info']['type'] === 'own-goal' ? ' (k.k.)' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</header>

<nav class="subnav" aria-label="Maç bölümleri">
  <div class="container subnav-inner">
    <?php if ($story): ?><a href="#haber">Maç Haberi</a><?php endif; ?>
    <?php if ($hasEvents): ?><a href="#akis">Maç Akışı</a><a href="#istatistik">İstatistikler</a><?php endif; ?>
    <a href="#kadrolar">Kadrolar</a>
    <?php if ($playerStats): ?><a href="#oyuncular">Oyuncu Performansları</a><?php endif; ?>
    <a href="#karsilastirma">Karşılaştırma</a>
    <?php if ($videoId || $interviewUrl || $gallery): ?><a href="#medya">Medya</a><?php endif; ?>
  </div>
</nav>

<?php if ($story): ?>
<section class="container section" id="haber">
  <div class="story-lead">
    <div class="story-lead-text">
      <span class="eyebrow"><?= e($story['kicker']) ?></span>
      <h1 class="headline"><?= e($story['headline']) ?></h1>
      <p class="lead-text"><?= nl2br(e($story['summary'])) ?></p>
      <?= share_links($story['headline']) ?>
    </div>
    <div class="story-lead-media"><?= render_cover($match, 'lg') ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($showScore && ($awards || $stars)): ?>
<section class="container section">
  <div class="section-head"><h2><?= $awards ? 'Maçın Enleri' : 'Maçın Yıldızları' ?></h2><?php if (!$awards): ?><span class="muted small">Maç istatistiklerine göre</span><?php endif; ?></div>
  <div class="award-grid">
    <?php if ($awards): foreach ($awards as $key => $aw): ?>
      <a class="award<?= $key === 'best_player' ? ' award-main' : '' ?>"<?= $aw['id'] ? ' href="' . e(player_url($aw['id'])) . '"' : '' ?>>
        <?= player_avatar($aw['id'] ? $pimg($aw['id']) : null, $aw['name'], $key === 'best_player' ? 'lg' : 'md') ?>
        <span class="award-label"><?= e($aw['label']) ?></span>
        <strong><?= e($aw['name']) ?></strong>
      </a>
    <?php endforeach; else: foreach ($stars as $i => $st): $c = $st['c']; ?>
      <a class="award<?= $i === 0 ? ' award-main' : '' ?>" href="<?= e(player_url($st['player'])) ?>">
        <?= player_avatar($pimg($st['player']), $pname($st['player']), $i === 0 ? 'lg' : 'md') ?>
        <span class="award-label"><?= $i === 0 ? 'Maçın Yıldızı' : ($i + 1) . '. sırada' ?></span>
        <strong><?= e($pname($st['player'])) ?></strong>
        <span class="award-stats">
          <?php foreach (['goal' => 'gol', 'assist' => 'asist', 'save' => 'kurtarış', 'chance' => 'pozisyon', 'block' => 'blok'] as $k => $lbl): if (!empty($c[$k])): ?>
            <span><b><?= (int) $c[$k] ?></b> <?= $lbl ?></span>
          <?php endif; endforeach; ?>
        </span>
      </a>
    <?php endforeach; endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($hasEvents): ?>
<section class="container section" id="akis">
  <div class="card">
    <div class="section-head"><h2>Maç Akışı</h2>
      <div class="chips" role="group" aria-label="Olay filtresi">
        <button type="button" class="chip active" data-feed-filter="key">Önemli anlar</button>
        <button type="button" class="chip" data-feed-filter="all">Tüm olaylar</button>
      </div>
    </div>

    <div class="momentum" aria-label="Zaman çizelgesi">
      <div class="momentum-team"><?= team_badge(team_logo($homeId), $home, 'xs') ?></div>
      <div class="momentum-track">
        <?php foreach ($feed as $f): if (!$f['info']['key']) continue;
          $left = min(100, max(0, $f['min'] / max(1, $maxMinute) * 100)); ?>
          <span class="mo mo-<?= e($f['side']) ?> mo-<?= e($f['info']['type']) ?>" style="left: <?= round($left, 2) ?>%" title="<?= e($f['min'] . "' " . $f['info']['label'] . ' — ' . ($f['info']['type'] === 'sub' ? $pname($f['ev']['oyuncu_giren_id'] ?? 0) : $pname($f['ev']['oyuncu_id'] ?? 0))) ?>"><?= event_icon($f['info']['type']) ?></span>
        <?php endforeach; ?>
        <span class="mo-axis"><i>0'</i><i><?= (int) round($maxMinute / 2) ?>'</i><i><?= $maxMinute ?>'</i></span>
      </div>
      <div class="momentum-team"><?= team_badge(team_logo($awayId), $away, 'xs') ?></div>
    </div>

    <ol class="feed" data-feed>
      <?php $lastHalf = 0; foreach ($feed as $f):
        $ev = $f['ev'];
        if ($f['half'] !== $lastHalf):
          $lastHalf = $f['half']; ?>
          <li class="feed-divider"><span><?= $f['half'] === 1 ? 'İlk yarı' : 'İkinci yarı' ?></span></li>
        <?php endif;
        $type = $f['info']['type'];
        if ($type === 'sub') {
            $who = '<a href="' . e(player_url((int) $ev['oyuncu_giren_id'])) . '"><b>' . e($pname($ev['oyuncu_giren_id'] ?? 0)) . '</b></a> oyuna girdi, <a href="' . e(player_url((int) $ev['oyuncu_cikan_id'])) . '">' . e($pname($ev['oyuncu_cikan_id'] ?? 0)) . '</a> çıktı';
        } else {
            $who = (int) ($ev['oyuncu_id'] ?? 0) ? '<a href="' . e(player_url((int) $ev['oyuncu_id'])) . '"><b>' . e($pname($ev['oyuncu_id'])) . '</b></a>' : '<b>' . e($f['side'] === 'home' ? $home : $away) . '</b>';
        } ?>
        <li class="feed-item feed-<?= e($f['side']) ?> ev-type-<?= e($type) ?><?= $f['info']['key'] ? ' is-key' : ' is-minor' ?>">
          <span class="feed-min"><?= $f['min'] ?>'</span>
          <span class="feed-icon"><?= event_icon($type) ?></span>
          <span class="feed-text">
            <span class="feed-label"><?= e($f['info']['label']) ?><?= isset($f['score']) ? ' <b class="feed-score">' . e($f['score']) . '</b>' : '' ?></span>
            <span class="feed-who"><?= $who ?> <small><?= e($f['side'] === 'home' ? $home : $away) ?></small></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="container section" id="istatistik">
  <div class="card">
    <div class="section-head">
      <h2>Takım İstatistikleri</h2>
      <div class="chips" role="group" aria-label="Devre">
        <button type="button" class="chip active" data-half="0">Maç</button>
        <button type="button" class="chip" data-half="1">1. yarı</button>
        <button type="button" class="chip" data-half="2">2. yarı</button>
      </div>
    </div>
    <div class="cmp-head"><span><?= team_badge(team_logo($homeId), $home, 'xs') ?> <?= e($home) ?></span><span><?= e($away) ?> <?= team_badge(team_logo($awayId), $away, 'xs') ?></span></div>
    <?php foreach ([0, 1, 2] as $half): ?>
      <div class="cmp-group" data-half-panel="<?= $half ?>"<?= $half ? ' hidden' : '' ?>>
        <?php if ($half === 0 && $possH + $possA > 0) echo compare_bar('Topla oynama', $possH, $possA, '%'); ?>
        <?php foreach ($statKeys as $k => $label):
          $hv = $stat[$k]['home'][$half]; $av = $stat[$k]['away'][$half];
          if ($hv + $av === 0) continue;
          echo compare_bar($label, $hv, $av);
        endforeach; ?>
        <?php if ($half === 0 && $stat['chance']['home'][0] + $stat['chance']['away'][0] > 0):
          $convH = ratio($stat['goal']['home'][0], $stat['goal']['home'][0] + $stat['chance']['home'][0]);
          $convA = ratio($stat['goal']['away'][0], $stat['goal']['away'][0] + $stat['chance']['away'][0]);
          echo compare_bar('Gole çevrilen atak oranı', round($convH * 100), round($convA * 100), '%');
        endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="container section" id="kadrolar">
  <div class="section-head"><h2>Kadrolar</h2></div>
  <?php if (!$people): ?>
    <div class="empty-state"><p>Kadrolar henüz açıklanmadı.</p></div>
  <?php else: ?>
  <div class="pitch" aria-label="Saha dizilişi">
    <?php foreach (['home' => [$homeId, $home], 'away' => [$awayId, $away]] as $side => [$tid, $tname]):
      $rows = [];
      foreach ($people as $pid => $p) {
          if ($p['side'] !== $side || $p['role'] !== 'starter') continue;
          $rows[position_short($p['pos'])][] = (int) $pid;
      }
      uksort($rows, static function ($a, $b) use ($posOrder) { return ($posOrder[$a] ?? 4) <=> ($posOrder[$b] ?? 4); });
      if ($side === 'away') $rows = array_reverse($rows, true); ?>
      <div class="pitch-half pitch-<?= $side ?>">
        <span class="pitch-team"><?= team_badge(team_logo($tid), $tname, 'xs') ?> <?= e($tname) ?></span>
        <?php foreach ($rows as $pids): ?>
          <div class="pitch-row">
            <?php foreach ($pids as $pid): $p = $people[$pid];
              $parts = preg_split('/\s+/u', trim($p['name'])) ?: [$p['name']];
              $short = end($parts); ?>
              <a class="pp" href="<?= e(player_url($pid)) ?>" title="<?= e($p['name'] . ($p['pos'] ? ' · ' . $p['pos'] : '')) ?>">
                <span class="pp-shirt"><?= $p['num'] !== '' ? e($p['num']) : e(position_short($p['pos']) ?: '•') ?><?php if ($p['captain']): ?><i class="pp-c">C</i><?php endif; ?></span>
                <span class="pp-name"><?= e(mb_strtoupper($short)) ?></span>
                <span class="pp-marks"><?= $marksHtml($pid) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid-2 lineup-lists">
    <?php foreach (['home' => [$homeId, $home], 'away' => [$awayId, $away]] as $side => [$tid, $tname]):
      $starters = []; $bench = [];
      foreach ($people as $pid => $p) {
          if ($p['side'] !== $side) continue;
          if ($p['role'] === 'starter') $starters[$pid] = $p; else $bench[$pid] = $p;
      }
      $sortPos = static function ($a, $b) use ($posOrder) {
          return ($posOrder[position_short($a['pos'])] ?? 4) <=> ($posOrder[position_short($b['pos'])] ?? 4);
      };
      uasort($starters, $sortPos); uasort($bench, $sortPos); ?>
      <div class="card lineup">
        <div class="lineup-head"><?= team_badge(team_logo($tid), $tname, 'sm') ?><h3><?= e($tname) ?></h3></div>
        <?php foreach (['İlk 11' => $starters, 'Yedekler' => $bench] as $label => $group): if (!$group) continue; ?>
          <h4 class="lineup-title"><?= e($label) ?></h4>
          <ul class="lineup-list">
            <?php foreach ($group as $pid => $p): ?>
              <li>
                <span class="lineup-num"><?= e($p['num']) ?></span>
                <?= player_avatar($p['img'], $p['name'], 'xs') ?>
                <a href="<?= e(player_url($pid)) ?>"><?= e($p['name']) ?></a>
                <?php if ($p['captain']): ?><span class="captain" title="Kaptan">C</span><?php endif; ?>
                <span class="pp-marks"><?= $marksHtml($pid) ?></span>
                <?php if ($p['rating'] !== null && $p['rating'] !== '' && (float) $p['rating'] > 0): ?><span class="rating" title="Maç puanı"><?= e(num($p['rating'], 1)) ?></span><?php endif; ?>
                <span class="pos-tag pos-<?= e(strtolower(position_short($p['pos'])) ?: 'na') ?>"><?= e(position_short($p['pos']) ?: '–') ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($playerStats): ?>
<section class="container section" id="oyuncular">
  <div class="card">
    <div class="section-head"><h2>Oyuncu Performansları</h2></div>
    <div class="tabs tabs-inline" role="tablist">
      <button type="button" class="tab active" role="tab" aria-selected="true" data-tab-target="ps-home"><?= e($home) ?></button>
      <button type="button" class="tab" role="tab" aria-selected="false" data-tab-target="ps-away"><?= e($away) ?></button>
    </div>
    <?php foreach (['home', 'away'] as $side): ?>
    <div class="table-wrap" id="ps-<?= $side ?>" data-tab-panel<?= $side === 'away' ? ' hidden' : '' ?>>
      <table class="table">
        <thead><tr>
          <th class="left">Oyuncu</th><th>Mevki</th><th title="Gol">G</th><th title="Asist">A</th><th title="Pozisyon üretme">Poz</th>
          <th title="Kurtarış">Kur</th><th title="Kritik blok">Blk</th><th class="hide-sm" title="Kazanılan ikili mücadele">İkili</th><th class="hide-sm" title="Kazanılan hava topu">Hava</th><th class="hide-sm" title="Faul">Faul</th><th title="Kart">Kart</th><th title="Maç puanı">Puan</th>
        </tr></thead>
        <tbody>
        <?php foreach ($psBySide[$side] as $p): $pid = (int) $p['playerId']; ?>
          <tr class="<?= $p['started'] ? '' : 'is-sub' ?>">
            <td class="left"><a class="player-cell" href="<?= e(player_url($pid)) ?>"><?= player_avatar($p['playerImage'] ?? null, $p['playerName'], 'xs') ?><span><?= e($p['playerName']) ?><small class="show-sm"><?= $p['started'] ? 'İlk 11' : 'Yedek' ?></small></span></a></td>
            <td><span class="pos-tag pos-<?= e(strtolower(position_short($p['position'] ?? '')) ?: 'na') ?>"><?= e(position_short($p['position'] ?? '') ?: '–') ?></span></td>
            <td><?= $p['totalGoals'] ? '<strong>' . (int) $p['totalGoals'] . '</strong>' : '–' ?></td>
            <td><?= (int) $p['assists'] ?: '–' ?></td>
            <td><?= (int) $p['chancesCreated'] ?: '–' ?></td>
            <td><?= (int) $p['saves'] ?: '–' ?></td>
            <td><?= (int) $p['criticalBlocks'] ?: '–' ?></td>
            <td class="hide-sm"><?= (int) $p['duelsWon'] ?: '–' ?></td>
            <td class="hide-sm"><?= (int) $p['aerialDuelsWon'] ?: '–' ?></td>
            <td class="hide-sm"><?= (int) $p['fouls'] ?: '–' ?></td>
            <td><?= str_repeat(event_icon('yellow'), (int) $p['yellowCards']) . str_repeat(event_icon('red'), (int) $p['redCards']) ?: '–' ?></td>
            <td><?= (float) $p['totalPoints'] ? '<span class="rating">' . e(num($p['totalPoints'], 1)) . '</span>' : '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="container section" id="karsilastirma">
  <div class="section-head"><h2>Karşılaştırma</h2></div>
  <div class="grid-2">
    <?php foreach ([[$homeId, $home], [$awayId, $away]] as [$tid, $tname]):
      $t = $model['teams'][$tid] ?? null;
      $form = $formBefore($tid);
      $rk = $rankOf[$tid] ?? null; ?>
      <div class="card team-compare">
        <a class="tc-head" href="<?= e(team_url($tid)) ?>"><?= team_badge(team_logo($tid), $tname, 'md') ?><span><strong><?= e($tname) ?></strong><small><?= $rk ? $rk['rank'] . '. sıra · ' . e((string) ($rk['row']['display_points'] ?? $rk['row']['total_points'] ?? 0)) . ' puan' : '' ?></small></span></a>
        <?php if ($t && $t['all']['p']): ?>
        <div class="tiles tiles-4">
          <?= stat_tile('Maç', $t['all']['p']) ?>
          <?= stat_tile('G-B-M', $t['all']['w'] . '-' . $t['all']['d'] . '-' . $t['all']['l']) ?>
          <?= stat_tile('Attığı ort.', num(ratio($t['all']['gf'], $t['all']['p']), 1)) ?>
          <?= stat_tile('Yediği ort.', num(ratio($t['all']['ga'], $t['all']['p']), 1)) ?>
        </div>
        <?php endif; ?>
        <h4 class="mini-title">Bu maçtan önceki form</h4>
        <?php if (!$form): ?>
          <p class="muted small">Bu maç, takımın sezondaki ilk maçı.</p>
        <?php else: ?>
          <ul class="form-list">
            <?php foreach (array_reverse($form) as $r): $om = $r['match']; ?>
              <li><a href="<?= e(match_url((int) $om['id'])) ?>"><span class="form form-<?= ['w' => 'win', 'd' => 'draw', 'l' => 'loss'][$r['res']] ?>"><?= ['w' => 'G', 'd' => 'B', 'l' => 'M'][$r['res']] ?></span>
                <span class="fl-opp"><?= e(ccl_team_map()[$r['opp']]['name'] ?? '') ?></span><b><?= $r['gf'] ?>-<?= $r['ga'] ?></b><small><?= e(fmt_date($om)) ?></small></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($h2h): ?>
    <h3 class="mini-title">Bu sezonki diğer karşılaşmaları</h3>
    <div class="match-list"><?php foreach ($h2h as $m) echo render_match_card($m); ?></div>
  <?php endif; ?>
</section>

<?php if ($videoId || $interviewUrl || $gallery): ?>
<section class="container section" id="medya">
  <div class="section-head"><h2>Medya</h2></div>
  <div class="media-grid">
    <?php if ($videoId): ?>
      <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($videoId) ?>" title="Maç videosu" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe><span class="video-cap">Maç özeti</span></div>
    <?php elseif (match_video_url($match)): ?>
      <a class="btn" href="<?= e(match_video_url($match)) ?>" target="_blank" rel="noopener">▶ Maç videosunu izle</a>
    <?php endif; ?>
    <?php if ($interviewId): ?>
      <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($interviewId) ?>" title="Röportaj" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe><span class="video-cap">Röportaj</span></div>
    <?php elseif ($interviewUrl): ?>
      <a class="btn btn-ghost" href="<?= e($interviewUrl) ?>" target="_blank" rel="noopener">🎙 Röportajı izle</a>
    <?php endif; ?>
  </div>
  <?php if ($gallery):
    $images = array_values(array_filter($gallery, 'is_image_url'));
    $links = array_values(array_diff($gallery, $images)); ?>
    <?php if ($images): ?>
      <div class="gallery">
        <?php foreach ($images as $img): ?><a href="<?= e($img) ?>" target="_blank" rel="noopener"><img src="<?= e($img) ?>" alt="Maçtan kare" loading="lazy"></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php foreach ($links as $l): ?><a class="btn btn-ghost" href="<?= e($l) ?>" target="_blank" rel="noopener">📷 Maç fotoğraflarının tamamı</a><?php endforeach; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($sameDay): ?>
<section class="container section">
  <div class="section-head"><h2>Aynı Gün Oynanan Maçlar</h2><a class="more" href="fikstur.php">Tüm fikstür →</a></div>
  <div class="story-grid">
    <?php foreach (array_slice($sameDay, 0, 6) as $m) echo match_is_played($m) ? render_story_card($m) : render_match_card($m); ?>
  </div>
</section>
<?php endif; ?>
</article>
<?php require __DIR__ . '/inc/footer.php';
