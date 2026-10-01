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

/** Liderlik listesi (gol krallığı vb.) */
function render_leader_list(array $players, string $field, string $unit, int $limit = 5): string
{
    $players = array_values(array_filter($players, static function ($p) use ($field) {
        return (float) ($p[$field] ?? 0) > 0;
    }));
    if (!$players) {
        return '<div class="empty-state small"><p>Henüz veri yok.</p></div>';
    }
    ob_start(); ?>
    <ol class="leaders">
      <?php foreach (array_slice($players, 0, $limit) as $i => $p): ?>
        <li class="<?= $i === 0 ? 'top' : '' ?>">
          <span class="leader-rank"><?= $i + 1 ?></span>
          <?= player_avatar($p['playerImage'] ?? null, (string) $p['playerName'], 'sm') ?>
          <span class="leader-info">
            <a href="<?= e(player_url((int) $p['playerId'])) ?>"><?= e($p['playerName']) ?></a>
            <small><?= e($p['teamName'] ?? '') ?></small>
          </span>
          <span class="leader-value"><strong><?= e(num($p[$field], fmod((float) $p[$field], 1.0) ? 2 : 0)) ?></strong><small><?= e($unit) ?></small></span>
        </li>
      <?php endforeach; ?>
    </ol>
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

/**
 * Maç kapağı: panelde yüklenmiş kapak fotoğrafı varsa o, yoksa iki takımın
 * logolarıyla oluşturulan tasarım kapak.
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
        return '<div class="cover cover-' . e($size) . ' cover-photo"><img src="' . e($photo) . '" alt="' . e($home . ' - ' . $away . ' maçından kare') . '" loading="lazy"></div>';
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
      <span class="cover-mark">CCL CUP</span>
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

/** İki takım karşılaştırma çubuğu. */
function compare_bar(string $label, $home, $away, string $suffix = ''): string
{
    $h = (float) $home;
    $a = (float) $away;
    $total = $h + $a;
    $pct = $total > 0 ? round($h / $total * 100) : 50;
    $hw = $h > $a ? ' is-lead' : '';
    $aw = $a > $h ? ' is-lead' : '';
    return '<div class="cmp"><div class="cmp-labels"><b class="' . trim($hw) . '">' . e(num($home, fmod($h, 1.0) ? 1 : 0) . $suffix) . '</b><span>' . e($label) . '</span><b class="' . trim($aw) . '">' . e(num($away, fmod($a, 1.0) ? 1 : 0) . $suffix) . '</b></div>'
        . '<div class="cmp-track"><span class="c-home" style="width:' . ($total > 0 ? $pct : 50) . '%"></span><span class="c-away" style="width:' . ($total > 0 ? 100 - $pct : 50) . '%"></span></div></div>';
}

/** Yatay tek seri çubuk (yüzdelik vb.). */
function meter_row(string $label, float $ratio, string $valueText, string $note = ''): string
{
    $ratio = max(0, min(1, $ratio));
    return '<div class="meter"><div class="meter-head"><span>' . e($label) . '</span><b>' . e($valueText) . '</b></div>'
        . '<div class="meter-track"><span style="width:' . round($ratio * 100, 1) . '%"></span></div>'
        . ($note !== '' ? '<small>' . e($note) . '</small>' : '') . '</div>';
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
