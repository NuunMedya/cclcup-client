<?php
/** Birden fazla sayfada kullanılan HTML parçaları. */

function render_match_card(array $m, string $variant = 'row'): string
{
    $id = (int) $m['id'];
    $homeId = (int) ($m['home_team_id'] ?? 0);
    $awayId = (int) ($m['away_team_id'] ?? 0);
    $home = (string) ($m['first_team_name'] ?? 'Ev sahibi');
    $away = (string) ($m['second_team_name'] ?? 'Deplasman');
    $played = match_is_played($m);
    $live = match_is_live($m);
    $hs = (int) ($m['first_team_score'] ?? 0);
    $as = (int) ($m['second_team_score'] ?? 0);
    $time = fmt_time($m);

    if ($live || $played) {
        $center = '<span class="score' . ($live ? ' score-live' : '') . '"><b>' . $hs . '</b><i>-</i><b>' . $as . '</b></span>';
    } else {
        $center = '<span class="kickoff">' . e($time ?: 'VS') . '</span>';
    }

    $status = $live
        ? '<span class="pill pill-live">CANLI</span>'
        : ($played ? '<span class="pill">MS</span>' : '<span class="pill pill-soon">' . e(fmt_date_short($m)['day'] . ' ' . fmt_date_short($m)['month']) . '</span>');

    $homeCls = $played && $hs > $as ? ' winner' : '';
    $awayCls = $played && $as > $hs ? ' winner' : '';

    ob_start(); ?>
    <a class="match-card match-<?= e($variant) ?><?= $live ? ' is-live' : '' ?>" href="<?= e(match_url($id)) ?>">
      <div class="match-meta">
        <?= $status ?>
        <span class="match-info"><?= e(fmt_date($m)) ?><?= $time ? ' · ' . e($time) : '' ?><?= !empty($m['match_field']) ? ' · ' . e($m['match_field']) : '' ?></span>
      </div>
      <div class="match-teams">
        <span class="team team-home<?= $homeCls ?>">
          <span class="team-name"><?= e($home) ?></span>
          <?= team_badge(team_logo($homeId), $home, 'sm') ?>
        </span>
        <?= $center ?>
        <span class="team team-away<?= $awayCls ?>">
          <?= team_badge(team_logo($awayId), $away, 'sm') ?>
          <span class="team-name"><?= e($away) ?></span>
        </span>
      </div>
    </a>
    <?php
    return (string) ob_get_clean();
}

/**
 * Puan durumu tablosu.
 * $highlightTeam: vurgulanacak takım id'si (takım sayfası için).
 */
function render_standings_table(array $rows, bool $compact = false, int $highlightTeam = 0, int $limit = 0): string
{
    if (!$rows) {
        return '<div class="empty-state"><p>Puan durumu henüz oluşmadı.</p></div>';
    }
    if ($limit > 0) {
        $rows = array_slice($rows, 0, $limit);
    }
    ob_start(); ?>
    <div class="table-wrap">
      <table class="table standings<?= $compact ? ' compact' : '' ?>">
        <thead>
          <tr>
            <th class="pos">#</th>
            <th class="left">Takım</th>
            <th title="Oynanan">O</th>
            <?php if (!$compact): ?>
            <th title="Galibiyet">G</th>
            <th title="Beraberlik">B</th>
            <th title="Mağlubiyet">M</th>
            <th class="hide-sm" title="Atılan gol">A</th>
            <th class="hide-sm" title="Yenilen gol">Y</th>
            <?php endif; ?>
            <th title="Averaj">AV</th>
            <th class="pts" title="Puan">P</th>
            <?php if (!$compact): ?><th class="hide-sm left">Form</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r):
            $tid = (int) ($r['team_id'] ?? 0);
            $gd = (int) ($r['goal_diff'] ?? 0);
            $pts = $r['display_points'] ?? $r['total_points'] ?? 0; ?>
          <tr class="<?= $tid === $highlightTeam ? 'highlight' : '' ?>">
            <td class="pos"><span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span></td>
            <td class="left">
              <a class="team-cell" href="<?= e(team_url($tid)) ?>">
                <?= team_badge($r['logo'] ?? null, (string) ($r['team_name'] ?? ''), 'xs') ?>
                <span><?= e($r['team_name'] ?? '') ?></span>
              </a>
            </td>
            <td><?= (int) ($r['played'] ?? 0) ?></td>
            <?php if (!$compact): ?>
            <td><?= (int) ($r['wins'] ?? 0) ?></td>
            <td><?= (int) ($r['draws'] ?? 0) ?></td>
            <td><?= (int) ($r['losses'] ?? 0) ?></td>
            <td class="hide-sm"><?= (int) ($r['goals_for'] ?? 0) ?></td>
            <td class="hide-sm"><?= (int) ($r['goals_against'] ?? 0) ?></td>
            <?php endif; ?>
            <td class="<?= $gd > 0 ? 'pos-val' : ($gd < 0 ? 'neg-val' : '') ?>"><?= $gd > 0 ? '+' . $gd : $gd ?></td>
            <td class="pts"><strong><?= e((string) $pts) ?></strong></td>
            <?php if (!$compact): ?><td class="hide-sm left form-cell"><?= form_badges($r['last5'] ?? '') ?></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

function render_api_notice(): string
{
    if (empty($GLOBALS['ccl_api_errors'])) {
        return '';
    }
    return '<div class="container"><div class="notice">Bazı veriler şu anda yüklenemedi. Lütfen birkaç dakika sonra sayfayı yenileyin.</div></div>';
}

/** Kapak üstündeki skor şeridi (logo · takım · skor · takım · logo). */
function cover_scorebar(array $m, bool $withScore = true): string
{
    $homeId = (int) $m['home_team_id'];
    $awayId = (int) $m['away_team_id'];
    $home = (string) $m['first_team_name'];
    $away = (string) $m['second_team_name'];
    $showScore = $withScore && (match_is_played($m) || match_is_live($m));
    $center = $showScore
        ? '<b>' . (int) $m['first_team_score'] . '</b><i>-</i><b>' . (int) $m['second_team_score'] . '</b>'
        : '<b class="vs">' . e(fmt_time($m) ?: 'VS') . '</b>';
    return '<span class="scorebar"><span class="sbar-team">' . team_badge(team_logo($homeId), $home, 'xs') . '<span>' . e($home) . '</span></span>'
        . '<span class="sbar-score">' . $center . '</span>'
        . '<span class="sbar-team sbar-away"><span>' . e($away) . '</span>' . team_badge(team_logo($awayId), $away, 'xs') . '</span></span>';
}

/**
 * Maç kapağı: panelde yüklenmiş fotoğraf varsa kırpılmadan (bulanık zemin
 * üzerinde) gösterilir ve logolar/skor fotoğrafın üstüne yerleşir; fotoğraf
 * yoksa iki takımın logolarıyla tasarım kapak çizilir.
 */
function render_cover(array $m, string $size = 'md', bool $withScore = true): string
{
    $photo = match_cover($m);
    $homeId = (int) $m['home_team_id'];
    $awayId = (int) $m['away_team_id'];
    $home = (string) $m['first_team_name'];
    $away = (string) $m['second_team_name'];
    $showScore = $withScore && (match_is_played($m) || match_is_live($m));

    if ($photo) {
        return '<div class="cover cover-' . e($size) . ' cover-photo">'
            . '<img class="cover-blur" src="' . e(media_url($photo, 320)) . '" alt="" aria-hidden="true" loading="lazy">'
            . '<img class="cover-img" src="' . e(media_url($photo, $size === 'hero' || $size === 'lg' ? 1280 : 640)) . '" alt="' . e($home . ' - ' . $away . ' maçından kare') . '" loading="lazy">'
            . cover_scorebar($m, $withScore) . '</div>';
    }
    $seed = ($homeId * 7 + $awayId * 13) % 4;
    ob_start(); ?>
    <div class="cover cover-<?= e($size) ?> cover-gen cover-v<?= $seed ?>" aria-hidden="true">
      <span class="cover-logo cover-logo-home"><?= team_badge(team_logo($homeId), $home, 'cover') ?></span>
      <span class="cover-center">
        <?php if ($showScore): ?>
          <b><?= (int) $m['first_team_score'] ?></b><i></i><b><?= (int) $m['second_team_score'] ?></b>
        <?php else: ?>
          <b class="vs">VS</b>
        <?php endif; ?>
      </span>
      <span class="cover-logo cover-logo-away"><?= team_badge(team_logo($awayId), $away, 'cover') ?></span>
      <span class="cover-names"><span><?= e($home) ?></span><span><?= e($away) ?></span></span>
      <img class="cover-mark" src="assets/img/logo-white.png" alt="" loading="lazy">
    </div>
    <?php
    return (string) ob_get_clean();
}

/** Haber kartı (manşet altı / haber listesi). */
function render_story_card(array $m, string $variant = 'card'): string
{
    $story = match_story($m);
    ob_start(); ?>
    <a class="story story-<?= e($variant) ?>" href="<?= e(match_url((int) $m['id'])) ?>">
      <?= render_cover($m, $variant === 'card' ? 'md' : 'sm') ?>
      <span class="story-body">
        <span class="story-kicker"><?= e($story['kicker']) ?> · <?= e(fmt_date($m)) ?></span>
        <span class="story-title"><?= e($story['headline']) ?></span>
        <?php if ($variant === 'card'): ?>
          <span class="story-summary"><?= e(mb_substr($story['summary'], 0, 150)) ?><?= mb_strlen_safe($story['summary']) > 150 ? '…' : '' ?></span>
        <?php endif; ?>
      </span>
    </a>
    <?php
    return (string) ob_get_clean();
}

/** Büyük istatistik kutusu. */
function stat_tile(string $label, $value, string $sub = '', string $class = ''): string
{
    return '<div class="tile ' . e($class) . '"><span class="tile-label">' . e($label) . '</span><strong class="tile-value">' . e((string) $value) . '</strong>'
        . ($sub !== '' ? '<span class="tile-sub">' . e($sub) . '</span>' : '') . '</div>';
}

/** Paylaşım bağlantıları. */
function share_links(string $title): string
{
    $url = ccl_config('site_url') . ($_SERVER['REQUEST_URI'] ?? '/');
    $text = rawurlencode($title . ' ' . $url);
    return '<div class="share"><span>Paylaş</span>'
        . '<a href="https://wa.me/?text=' . $text . '" target="_blank" rel="noopener" aria-label="WhatsApp ile paylaş">WhatsApp</a>'
        . '<a href="https://twitter.com/intent/tweet?text=' . $text . '" target="_blank" rel="noopener" aria-label="X ile paylaş">X</a>'
        . '<button type="button" data-copy="' . e($url) . '">Bağlantıyı kopyala</button></div>';
}

/** Minimal istatistik satırı: değerler kenarlarda, çubuklar ortadan dışa doğru. */
function stat_row(string $label, $home, $away, string $suffix = ''): string
{
    $h = (float) $home;
    $a = (float) $away;
    $max = max($h, $a, 1);
    $hw = round($h / $max * 100, 1);
    $aw = round($a / $max * 100, 1);
    $fmt = static function ($v) use ($suffix) {
        return e(num($v, fmod((float) $v, 1.0) ? 1 : 0) . $suffix);
    };
    return '<div class="st">'
        . '<b class="st-v' . ($h > $a ? ' is-lead' : '') . '">' . $fmt($home) . '</b>'
        . '<div class="st-mid"><span class="st-label">' . e($label) . '</span>'
        . '<span class="st-bars"><span class="stb stb-h"><i style="width:' . $hw . '%"></i></span><span class="stb stb-a"><i style="width:' . $aw . '%"></i></span></span></div>'
        . '<b class="st-v' . ($a > $h ? ' is-lead' : '') . '">' . $fmt($away) . '</b>'
        . '</div>';
}
