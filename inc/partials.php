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
