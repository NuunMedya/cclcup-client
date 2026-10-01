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
$highlights = $showScore ? highlights([$id]) : [];
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
    <?php if ($cover): ?><img src="<?= e(media_url($cover, 1920)) ?>" alt=""><?php endif; ?>
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

<?php if ($showScore && ($awards || $highlights)): ?>
<section class="container section">
  <?php if ($awards): ?>
    <div class="section-head"><h2>Maçın Enleri</h2></div>
    <div class="award-grid">
      <?php foreach ($awards as $key => $aw): ?>
        <a class="award<?= $key === 'best_player' ? ' award-main' : '' ?>"<?= $aw['id'] ? ' href="' . e(player_url($aw['id'])) . '"' : '' ?>>
          <?= player_avatar($aw['id'] ? $pimg($aw['id']) : null, $aw['name'], $key === 'best_player' ? 'lg' : 'md') ?>
          <span class="award-label"><?= e($aw['label']) ?></span>
          <strong><?= e($aw['name']) ?></strong>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($highlights): ?>
    <div class="section-head<?= $awards ? ' section-gap' : '' ?>"><h2>Maçın Öne Çıkanları</h2></div>
    <div class="hl-grid">
      <?php foreach ($highlights as $h): ?>
        <a class="hl" href="<?= e(player_url($h['player'])) ?>">
          <?= player_avatar($pimg($h['player']), $pname($h['player']), 'md') ?>
          <span class="hl-text"><small><?= e($h['label']) ?></small><strong><?= e($pname($h['player'])) ?></strong></span>
          <span class="hl-val"><b><?= (int) $h['value'] ?></b><small><?= e($h['unit']) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($hasEvents): ?>
<section class="container section" id="akis">
  <div class="grid-feed">
    <div class="card card-flat">
      <div class="section-head">
        <h2>Maç Akışı</h2>
        <div class="seg" role="group" aria-label="Olay filtresi">
          <button type="button" class="active" data-feed-filter="key">Önemli</button>
          <button type="button" data-feed-filter="all">Tümü</button>
        </div>
      </div>
      <div class="tl-head"><span><?= team_badge(team_logo($homeId), $home, 'xs') ?> <?= e($home) ?></span><span><?= e($away) ?> <?= team_badge(team_logo($awayId), $away, 'xs') ?></span></div>
      <ol class="tl" data-feed>
        <?php
        $lastHalf = 0;
        $htShown = false;
        foreach ($feed as $f):
          $ev = $f['ev'];
          if ($f['half'] !== $lastHalf):
            if ($lastHalf === 1): ?>
              <li class="tl-mark"><span>Devre arası · <?= $halfScore[1]['home'] ?>-<?= $halfScore[1]['away'] ?></span></li>
            <?php elseif ($lastHalf === 0): ?>
              <li class="tl-mark"><span>Başlama düdüğü</span></li>
            <?php endif;
            $lastHalf = $f['half'];
          endif;
          $type = $f['info']['type'];
          $isGoal = $type === 'goal' || $type === 'own-goal';
          if ($type === 'sub') {
              $main = '<a href="' . e(player_url((int) $ev['oyuncu_giren_id'])) . '">' . e($pname($ev['oyuncu_giren_id'] ?? 0)) . '</a>';
              $sub = '<span class="tl-out">↓ ' . e($pname($ev['oyuncu_cikan_id'] ?? 0)) . '</span>';
          } else {
              $main = (int) ($ev['oyuncu_id'] ?? 0) ? '<a href="' . e(player_url((int) $ev['oyuncu_id'])) . '">' . e($pname($ev['oyuncu_id'])) . '</a>' : e($f['side'] === 'home' ? $home : $away);
              $sub = '<span>' . e($f['info']['label']) . '</span>';
          }
          $content = '<span class="tl-ico">' . event_icon($type) . '</span><span class="tl-txt"><span class="tl-name">' . $main . '</span>' . $sub . '</span>'
              . ($isGoal && isset($f['score']) ? '<span class="tl-score">' . e($f['score']) . '</span>' : ''); ?>
          <li class="tl-row tl-<?= e($f['side']) ?> tl-t-<?= e($type) ?><?= $f['info']['key'] ? ' is-key' : ' is-minor' ?>">
            <div class="tl-cell tl-left"><?= $f['side'] === 'home' ? $content : '' ?></div>
            <div class="tl-min"><?= $f['min'] ?>'</div>
            <div class="tl-cell tl-right"><?= $f['side'] === 'away' ? $content : '' ?></div>
          </li>
        <?php endforeach; ?>
        <?php if ($played): ?><li class="tl-mark tl-end"><span>Maç sonu · <?= $hs ?>-<?= $as ?></span></li><?php endif; ?>
      </ol>
    </div>

    <div class="card card-flat" id="istatistik">
      <div class="section-head">
        <h2>İstatistikler</h2>
        <div class="seg" role="group" aria-label="Devre">
          <button type="button" class="active" data-half="0">Maç</button>
          <button type="button" data-half="1">1. Y</button>
          <button type="button" data-half="2">2. Y</button>
        </div>
      </div>
      <div class="tl-head"><span><?= team_badge(team_logo($homeId), $home, 'xs') ?></span><span><?= team_badge(team_logo($awayId), $away, 'xs') ?></span></div>
      <?php foreach ([0, 1, 2] as $half): ?>
        <div class="st-list" data-half-panel="<?= $half ?>"<?= $half ? ' hidden' : '' ?>>
          <?php if ($half === 0 && $possH + $possA > 0) echo stat_row('Topla oynama', $possH, $possA, '%'); ?>
          <?php $any = false; foreach ($statKeys as $k => $label):
            $hv = $stat[$k]['home'][$half]; $av = $stat[$k]['away'][$half];
            if ($hv + $av === 0) continue;
            $any = true;
            echo stat_row($label, $hv, $av);
          endforeach; ?>
          <?php if (!$any): ?><p class="muted small">Bu devrede kayıtlı olay yok.</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
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
                <span class="pp-photo"><?= player_avatar($pimg($pid), $p['name'], 'pitch') ?><?php if ($p['num'] !== ''): ?><i class="pp-num"><?= e($p['num']) ?></i><?php endif; ?><?php if ($p['captain']): ?><i class="pp-c">C</i><?php endif; ?></span>
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
                <?= player_avatar($pimg($pid), $p['name'], 'xs') ?>
                <a href="<?= e(player_url($pid)) ?>"><?= e($p['name']) ?></a>
                <?php if ($p['captain']): ?><span class="captain" title="Kaptan">C</span><?php endif; ?>
                <span class="pp-marks"><?= $marksHtml($pid) ?></span>
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
          <th title="Kurtarış">Kur</th><th title="Kritik blok">Blk</th><th class="hide-sm" title="Kazanılan ikili mücadele">İkili</th><th class="hide-sm" title="Kazanılan hava topu">Hava</th><th class="hide-sm" title="Faul">Faul</th><th title="Kart">Kart</th>
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
        <?php foreach ($images as $img): ?><a href="<?= e(media_url($img)) ?>" target="_blank" rel="noopener"><img src="<?= e(media_url($img, 640)) ?>" alt="Maçtan kare" loading="lazy"></a><?php endforeach; ?>
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
