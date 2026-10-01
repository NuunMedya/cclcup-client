<?php
require __DIR__ . '/inc/bootstrap.php';

// Tek haber (panelden yazılan CCL CUP haberleri)
$newsId = qs('haber');
if ($newsId !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $newsId)) {
    $data = api_try('/api/news/' . rawurlencode($newsId), [], 300, []);
    $item = $data['item'] ?? null;
    if (!$item || (int) ($item['league_id'] ?? 0) !== ccl_scope()['leagueId']) {
        not_found('Haber bulunamadı.');
    }
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a>';
    $content = strip_tags((string) ($item['content'] ?? ''), $allowed);
    // Bağlantılarda yalnızca http(s) adreslerine izin ver, olay özniteliklerini temizle.
    $content = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content);
    $content = preg_replace('/href\s*=\s*("|\')\s*(?!https?:)[^"\']*\1/i', 'href="#"', $content);
    $page = ['title' => $item['title'], 'nav' => 'news', 'description' => $item['summary'] ?? '', 'image' => $item['cover_image_url'] ?? ''];
    require __DIR__ . '/inc/header.php'; ?>
    <article class="container section article">
      <span class="eyebrow"><?= e($item['category_label'] ?? 'Haber') ?></span>
      <h1 class="headline"><?= e($item['title']) ?></h1>
      <?php if (!empty($item['summary'])): ?><p class="lead-text"><?= e($item['summary']) ?></p><?php endif; ?>
      <?php if (!empty($item['cover_image_url'])): ?><img class="article-cover" src="<?= e($item['cover_image_url']) ?>" alt=""><?php endif; ?>
      <div class="prose"><?= $content ?></div>
      <?= share_links($item['title']) ?>
    </article>
    <?php require __DIR__ . '/inc/footer.php';
    exit;
}

$model = season_model();
$stories = array_reverse($model['played']);
$news = ccl_news(20);

$page = ['title' => 'Haberler', 'nav' => 'news', 'description' => 'CCL CUP maç haberleri, sonuçlar ve turnuvadan gelişmeler.'];
require __DIR__ . '/inc/header.php';
echo render_api_notice();
?>
<section class="page-head">
  <div class="container">
    <h1>Haberler</h1>
    <p class="muted"><?= count($stories) + count($news) ?> haber · <?= e(ccl_scope()['seasonName']) ?></p>
  </div>
</section>

<section class="container section">
  <?php if (!$stories && !$news): ?>
    <div class="empty-state big"><div class="empty-icon">📰</div><p>Maçlar oynandıkça haberler burada yer alacak.</p></div>
  <?php endif; ?>

  <?php if ($stories): $lead = $stories[0]; ?>
    <div class="story-feature"><?= render_story_card($lead, 'card') ?></div>
  <?php endif; ?>

  <div class="story-grid section-gap">
    <?php foreach ($news as $n): ?>
      <a class="story story-card" href="<?= e(url('haberler.php', ['haber' => $n['id']])) ?>">
        <?php if (!empty($n['cover_image_url'])): ?><div class="cover cover-md cover-photo"><img src="<?= e($n['cover_image_url']) ?>" alt="" loading="lazy"></div><?php endif; ?>
        <span class="story-body"><span class="story-kicker"><?= e($n['category_label'] ?? 'Haber') ?></span><span class="story-title"><?= e($n['title']) ?></span><span class="story-summary"><?= e($n['summary'] ?? '') ?></span></span>
      </a>
    <?php endforeach; ?>
    <?php foreach (array_slice($stories, 1) as $m) echo render_story_card($m); ?>
  </div>
</section>
<?php require __DIR__ . '/inc/footer.php';
