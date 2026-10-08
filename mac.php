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
$streamUrl = match_video_url($match);
$mediaLinks = match_media_links($match);
$interviewUrl = match_interview_url($match);
$streamEmbed = $streamUrl !== '' ? video_embed($streamUrl, $live) : null;
$interviewEmbed = $interviewUrl !== '' && $interviewUrl !== $streamUrl ? video_embed($interviewUrl) : null;
$photoSet = match_photo_set($match);
$hasPhotos = $photoSet['items'] || $photoSet['embed'] !== '' || $photoSet['album'] !== '';
// Yayın bölümü: link girildiyse her zaman, girilmediyse maç önce/canlıyken yer tutucu.
$showBroadcast = $streamUrl !== '' || $interviewEmbed || !$played;
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

    <?php if (isset($awards['best_player']) || $mediaLinks): ?>
    <div class="mh-extras">
      <?php if (isset($awards['best_player'])): $mvp = $awards['best_player']; ?>
        <a class="mh-mvp" href="<?= $mvp['id'] ? e(player_url($mvp['id'])) : '#enler' ?>">
          <?= player_avatar($mvp['id'] ? $pimg($mvp['id']) : null, $mvp['name'], 'xs') ?>
          <span><small>Maçın Oyuncusu</small><b><?= e($mvp['name']) ?></b></span>
        </a>
      <?php endif; ?>
      <?php if ($mediaLinks): ?>
        <div class="mlinks mlinks-hero">
          <?php foreach ($mediaLinks as $l):
            // Gömülebilen içerik sayfadaki oynatıcıya / galeriye götürür.
            $href = $l['url']; $ext = $l['external'];
            if ($l['type'] === 'stream' && $streamEmbed) { $href = '#yayin'; $ext = false; }
            if ($l['type'] === 'interview' && $interviewEmbed) { $href = '#yayin'; $ext = false; }
            if ($l['type'] === 'photos' && ($photoSet['items'] || $photoSet['embed'] !== '')) { $href = '#fotograflar'; $ext = false; } ?>
            <a class="mlink mlink-<?= e($l['type']) ?><?= $l['type'] === 'stream' && $live ? ' is-live' : '' ?>" href="<?= e($href) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><span class="mlink-ico" aria-hidden="true"><?= $l['icon'] ?></span><?= e($l['label']) ?><?php if ($l['type'] === 'photos' && $photoSet['total']): ?> <small><?= (int) $photoSet['total'] ?></small><?php endif; ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</header>

<nav class="subnav" aria-label="Maç bölümleri">
  <div class="container subnav-inner">
    <?php if ($showBroadcast): ?><a href="#yayin"><?= $live ? 'Canlı Yayın' : ($streamUrl !== '' || !$interviewEmbed ? 'Maç Yayını' : 'Röportaj') ?></a><?php endif; ?>
    <?php if ($story): ?><a href="#haber">Maç Haberi</a><?php endif; ?>
    <?php if ($showScore && $awards): ?><a href="#enler">Maçın Enleri</a><?php endif; ?>
    <?php if ($hasEvents): ?><a href="#akis">Maç Akışı</a><a href="#istatistik">İstatistikler</a><?php endif; ?>
    <a href="#kadrolar">Kadrolar</a>
    <?php if ($playerStats): ?><a href="#oyuncular">Oyuncu Performansları</a><?php endif; ?>
    <a href="#karsilastirma">Karşılaştırma</a>
    <?php if ($hasPhotos): ?><a href="#fotograflar">Fotoğraflar</a><?php endif; ?>
  </div>
</nav>

<?php if ($showBroadcast):
  $mainIsStream = $streamUrl !== '';
  $teaser = spread_items($photoSet['items'], 4); ?>
<section class="container section" id="yayin">
  <div class="section-head">
    <h2><?php if ($live): ?><span class="dot-live"></span> Canlı Yayın<?php elseif ($mainIsStream || !$interviewEmbed): ?>Maç Yayını<?php else: ?>Röportaj<?php endif; ?></h2>
    <?php if ($streamUrl !== ''): ?><a class="more" href="<?= e($streamUrl) ?>" target="_blank" rel="noopener">Yayını yeni sekmede aç ↗</a><?php endif; ?>
  </div>
  <div class="bc<?= $interviewEmbed && $mainIsStream || $teaser || $photoSet['album'] !== '' ? '' : ' bc-single' ?>">
    <div class="bc-main">
      <?php if ($mainIsStream && $streamEmbed): ?>
        <?= render_embed($streamEmbed, $live ? 'Canlı yayın' : 'Maç yayını', $live ? 'live' : 'stream', false) ?>
      <?php elseif ($mainIsStream): ?>
        <a class="bc-empty bc-link" href="<?= e($streamUrl) ?>" target="_blank" rel="noopener"><span class="bc-ico">▶</span><strong><?= $live ? 'Canlı yayını izle' : 'Maç yayınını izle' ?></strong><small><?= e((string) parse_url($streamUrl, PHP_URL_HOST)) ?></small></a>
      <?php elseif ($interviewEmbed): ?>
        <?= render_embed($interviewEmbed, 'Röportaj', 'interview', false) ?>
      <?php else: ?>
        <div class="bc-empty"><span class="bc-ico">📺</span><strong><?= $live ? 'Canlı yayın bağlantısı bekleniyor' : 'Canlı yayın maç saatinde burada' ?></strong><small>Yayın bağlantısı eklendiğinde maçı bu sayfadan canlı izleyebilirsiniz.</small></div>
      <?php endif; ?>
    </div>
    <?php if ($interviewEmbed && $mainIsStream || $teaser || $photoSet['album'] !== ''): ?>
    <aside class="bc-side">
      <?php if ($interviewEmbed && $mainIsStream): ?>
        <div class="bc-card"><h3 class="bc-title">🎙 Röportaj</h3><?= render_embed($interviewEmbed, 'Röportaj', 'interview') ?></div>
      <?php endif; ?>
      <?php if ($teaser || $photoSet['album'] !== ''): ?>
        <a class="bc-card bc-photos" href="<?= $teaser ? '#fotograflar' : e($photoSet['album']) ?>"<?= $teaser ? '' : ' target="_blank" rel="noopener"' ?>>
          <h3 class="bc-title">📷 Maç Fotoğrafları<?php if ($photoSet['total']): ?> <small><?= (int) $photoSet['total'] ?></small><?php endif; ?></h3>
          <?php if ($teaser): ?>
            <span class="bc-mosaic"><?php foreach ($teaser as $ph): ?><img src="<?= e($ph['thumb']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php endforeach; ?></span>
          <?php endif; ?>
          <span class="bc-more"><?= $teaser ? 'Galeriyi aç ↓' : 'Albümü aç ↗' ?></span>
        </a>
      <?php endif; ?>
    </aside>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

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
<section class="container section" id="enler">
  <?php if ($awards):
    $psById = [];
    foreach ($playerStats as $ps) $psById[(int) $ps['playerId']] = $ps;
    $teamOf = static function (int $pid) use ($people, $index, $homeId, $awayId) {
        if (isset($people[$pid])) return $people[$pid]['side'] === 'home' ? $homeId : $awayId;
        return (int) ($index[$pid]['teamId'] ?? 0);
    };
    $teamChip = static function (int $tid) {
        $t = ccl_team_map()[$tid] ?? null;
        return $t ? '<span class="en-team">' . team_badge(team_logo($tid), (string) $t['name'], 'xs') . '<span>' . e($t['name']) . '</span></span>' : '';
    };
    $awardMeta = [
        'best_goalkeeper' => ['tag' => 'KL', 'cls' => 'pos-kl'], 'best_defender' => ['tag' => 'DF', 'cls' => 'pos-df'],
        'best_midfielder' => ['tag' => 'OS', 'cls' => 'pos-os'], 'best_forward' => ['tag' => 'FV', 'cls' => 'pos-fv'],
        'best_goal' => ['tag' => '⚽', 'cls' => 'en-moment'], 'best_save' => ['tag' => '🧤', 'cls' => 'en-moment'],
    ];
    $mvp = $awards['best_player'] ?? null; ?>
    <div class="section-head"><h2>Maçın Enleri</h2></div>
    <div class="enler<?= $mvp ? '' : ' enler-no-mvp' ?>">
      <?php if ($mvp):
        $mps = $mvp['id'] ? ($psById[$mvp['id']] ?? null) : null;
        $chips = [];
        if ($mps) {
            foreach (['totalGoals' => 'gol', 'assists' => 'asist', 'saves' => 'kurtarış', 'chancesCreated' => 'pozisyon', 'criticalBlocks' => 'blok'] as $f => $u) {
                if ((int) ($mps[$f] ?? 0) > 0) $chips[] = '<span><b>' . (int) $mps[$f] . '</b> ' . $u . '</span>';
            }
        } ?>
        <a class="en-mvp"<?= $mvp['id'] ? ' href="' . e(player_url($mvp['id'])) . '"' : '' ?>>
          <span class="en-mvp-photo"><?= player_avatar($mvp['id'] ? $pimg($mvp['id']) : null, $mvp['name'], 'card') ?></span>
          <span class="en-mvp-body">
            <span class="en-mvp-label"><i aria-hidden="true">★</i> Maçın Oyuncusu</span>
            <strong class="en-mvp-name"><?= e($mvp['name']) ?></strong>
            <?= $mvp['id'] ? $teamChip($teamOf($mvp['id'])) : '' ?>
            <?php if ($chips): ?><span class="en-mvp-stats"><?= implode('', array_slice($chips, 0, 3)) ?></span><?php endif; ?>
          </span>
        </a>
      <?php endif; ?>
      <?php if (count($awards) > ($mvp ? 1 : 0)): ?>
      <div class="en-list">
        <?php foreach ($awards as $key => $aw): if ($key === 'best_player') continue; $meta = $awardMeta[$key] ?? ['tag' => '★', 'cls' => 'en-moment']; ?>
          <a class="en-item"<?= $aw['id'] ? ' href="' . e(player_url($aw['id'])) . '"' : '' ?>>
            <span class="en-photo"><?= player_avatar($aw['id'] ? $pimg($aw['id']) : null, $aw['name'], 'lg') ?><i class="en-tag <?= e($meta['cls']) ?>"><?= e($meta['tag']) ?></i></span>
            <span class="en-txt">
              <small><?= e($aw['label']) ?></small>
              <strong><?= e($aw['name']) ?></strong>
              <?= $aw['id'] ? $teamChip($teamOf($aw['id'])) : '' ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
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

<?php if ($hasPhotos): ?>
<section class="container section" id="fotograflar">
  <div class="section-head">
    <h2>Maç Fotoğrafları</h2>
    <?php if ($photoSet['album'] !== ''): ?><a class="more" href="<?= e($photoSet['album']) ?>" target="_blank" rel="noopener"><?= $photoSet['total'] ? (int) $photoSet['total'] . ' fotoğraf · ' : '' ?>Albümün tamamı ↗</a><?php endif; ?>
  </div>
  <?php if ($photoSet['items']): ?>
    <div class="pgal" data-lightbox>
      <?php foreach ($photoSet['items'] as $i => $ph): ?>
        <a class="pgal-item<?= $i >= 12 ? ' is-more' : '' ?>" href="<?= e($ph['full']) ?>" data-lb-item<?= $i >= 12 ? ' hidden' : '' ?>><img referrerpolicy="no-referrer" src="<?= e($ph['thumb']) ?>" alt="<?= e($home . ' - ' . $away . ' maçından kare' . ($ph['name'] !== '' ? ' (' . $ph['name'] . ')' : '')) ?>" loading="lazy"></a>
      <?php endforeach; ?>
    </div>
    <?php if (count($photoSet['items']) > 12): ?><div class="pgal-actions"><button type="button" class="btn btn-ghost btn-sm" data-pgal-more>Daha fazla fotoğraf göster</button></div><?php endif; ?>
  <?php elseif ($photoSet['embed'] !== ''): ?>
    <div class="pgal-embed"><iframe src="<?= e($photoSet['embed']) ?>" title="Maç fotoğrafları" loading="lazy"></iframe></div>
  <?php else: ?>
    <a class="bc-empty bc-link bc-short" href="<?= e($photoSet['album']) ?>" target="_blank" rel="noopener"><span class="bc-ico">📷</span><strong>Maç fotoğraflarını görüntüle</strong><small><?= e((string) parse_url($photoSet['album'], PHP_URL_HOST)) ?></small></a>
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
