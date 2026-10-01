<?php
require __DIR__ . '/inc/bootstrap.php';

$rules = require __DIR__ . '/inc/rules.php';
$sections = $rules['sections'];
$part = $rules['participation'];

$page = [
    'title' => 'Turnuva Kuralları',
    'nav' => 'rules',
    'description' => 'CCL CUP turnuva kuralları ve katılım şartları: format, maç süresi, kadro, oyuncu değişikliği, disiplin ve itirazlar.',
];
require __DIR__ . '/inc/header.php';

$renderItems = static function (array $section) {
    $out = '<ol class="rule-items">';
    foreach ($section['items'] as [$letter, $text]) {
        if ($text === '@steps' && !empty($section['steps'])) {
            $out .= '<li class="rule-item rule-block"><span class="rule-letter">' . e($letter) . '</span><div class="rule-text">'
                . '<strong class="rule-sub">' . e($section['steps']['title']) . '</strong><ol class="steps">';
            foreach ($section['steps']['list'] as $i => $step) {
                $out .= '<li><span>' . ($i + 1) . '</span>' . e($step) . '</li>';
            }
            $out .= '</ol></div></li>';
            continue;
        }
        if ($text === '@table' && !empty($section['table'])) {
            $t = $section['table'];
            $out .= '<li class="rule-item rule-block"><span class="rule-letter"></span><div class="rule-text"><div class="table-wrap"><table class="table rule-table"><thead><tr>';
            foreach ($t['head'] as $h) $out .= '<th class="left">' . e($h) . '</th>';
            $out .= '</tr></thead><tbody>';
            foreach ($t['rows'] as $row) {
                $out .= '<tr><td class="left">' . e($row[0]) . '</td><td class="left"><b>' . e($row[1]) . '</b></td><td class="left muted">' . e($row[2]) . '</td></tr>';
            }
            $out .= '</tbody></table></div>';
            if (!empty($section['details'])) {
                $out .= '<details class="rule-details"><summary>' . e($section['details']['title']) . '</summary>';
                foreach ($section['details']['paragraphs'] as $p) $out .= '<p>' . $p . '</p>';
                $out .= '</details>';
            }
            $out .= '</div></li>';
            continue;
        }
        $out .= '<li class="rule-item' . ($letter === '' ? ' is-cont' : '') . '"><span class="rule-letter">' . e($letter) . '</span><div class="rule-text">' . $text . '</div></li>';
    }
    return $out . '</ol>';
};
?>
<section class="rules-hero">
  <div class="container rules-hero-inner">
    <img class="rules-logo" src="assets/img/logo-white.png" alt="Natura Dünyası CCL CUP" width="360" height="75">
    <h1>Turnuva Kuralları</h1>
    <p>Format, maç süresi, kadro, oyuncu nitelikleri, disiplin ve katılım şartları: CCL CUP'ta bilmeniz gereken her şey tek sayfada.</p>
    <div class="rules-tools">
      <label class="rules-search">
        <span aria-hidden="true">🔎</span>
        <input type="search" placeholder="Kurallarda ara… (ör. kaleci, sigorta, penaltı)" data-rule-search aria-label="Kurallarda ara">
      </label>
      <button type="button" class="btn btn-ghost-light" data-print>Yazdır / PDF</button>
    </div>
  </div>
</section>

<section class="container section">
  <div class="facts">
    <?php foreach ($rules['facts'] as [$icon, $value, $label]): ?>
      <div class="fact"><span class="fact-icon" aria-hidden="true"><?= $icon ?></span><b><?= e($value) ?></b><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section rules-layout">
  <aside class="rules-toc" aria-label="İçindekiler">
    <span class="toc-title">İçindekiler</span>
    <ol>
      <?php foreach ($sections as $i => $s): ?>
        <li><a href="#<?= e($s['id']) ?>"><span><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><?= e($s['title']) ?></a></li>
      <?php endforeach; ?>
      <li><a href="#katilim-sartlari"><span>★</span>Katılım Şartları</a></li>
    </ol>
  </aside>

  <div class="rules-body">
    <p class="rules-empty" data-rule-empty hidden>Aramanızla eşleşen kural bulunamadı.</p>
    <?php foreach ($sections as $i => $s): ?>
      <article class="rule-section" id="<?= e($s['id']) ?>" data-rule-section>
        <header class="rule-head">
          <span class="rule-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <span class="rule-icon" aria-hidden="true"><?= $s['icon'] ?></span>
          <div>
            <h2><?= e($s['title']) ?></h2>
            <p><?= e($s['summary']) ?></p>
          </div>
        </header>
        <?= $renderItems($s) ?>
      </article>
    <?php endforeach; ?>

    <article class="rule-section rule-section-accent" id="katilim-sartlari" data-rule-section>
      <header class="rule-head">
        <span class="rule-num">★</span>
        <span class="rule-icon" aria-hidden="true">📝</span>
        <div>
          <h2>Katılım Şartları</h2>
          <p><?= e($part['intro']) ?></p>
        </div>
      </header>
      <ol class="rule-items">
        <?php foreach ($part['items'] as $i => $text): ?>
          <li class="rule-item"><span class="rule-letter"><?= $i + 1 ?></span><div class="rule-text"><?= $text ?></div></li>
        <?php endforeach; ?>
        <li class="rule-item rule-block"><span class="rule-letter"><?= count($part['items']) + 1 ?></span><div class="rule-text">
          <strong class="rule-sub"><?= e($part['checklist_title']) ?></strong>
          <ul class="checklist">
            <?php foreach ($part['checklist'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
          </ul>
        </div></li>
      </ol>
    </article>

    <div class="rules-note">
      <p>Kurallar organizasyon komitesi tarafından güncellenebilir; değişiklikler sezon başlamadan önce tüm katılımcılara iletilir.</p>
      <a class="btn btn-ghost btn-sm" href="https://arsiv.cclcup.com" target="_blank" rel="noopener">Geçmiş sezonlar için arşiv ↗</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
