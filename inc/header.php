<?php
/** @var array $page ['title' => string, 'nav' => string, 'description' => ?string] */
$cfg = ccl_config();
$scope = ccl_scope();
$pageTitle = !empty($page['title']) ? $page['title'] . ' | ' . $cfg['site_name'] : $cfg['site_title'];
$description = $page['description'] ?? ($cfg['site_name'] . ' ' . ($scope['seasonName'] ?: '') . ' fikstür, sonuçlar, puan durumu ve oyuncu istatistikleri.');
$nav = [
    'home'      => ['index.php', 'Ana Sayfa'],
    'news'      => ['haberler.php', 'Haberler'],
    'fixtures'  => ['fikstur.php', 'Fikstür & Sonuçlar'],
    'standings' => ['puan-durumu.php', 'Puan Durumu'],
    'teams'     => ['takimlar.php', 'Takımlar'],
    'stats'     => ['istatistikler.php', 'İstatistikler'],
];
$active = $page['nav'] ?? '';
?><!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <meta name="theme-color" content="#0a1230">
  <?php if (!empty($page['refresh'])): ?><meta http-equiv="refresh" content="<?= (int) $page['refresh'] ?>"><?php endif; ?>
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:type" content="website">
  <?php
    $ogImage = media_url($page['image'] ?? '') ?: 'assets/img/logo-color.png';
    if (!preg_match('#^https?://#i', $ogImage)) $ogImage = $cfg['site_url'] . '/' . ltrim($ogImage, '/');
  ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <?php if (!empty($page['image'])): ?><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
  <meta property="og:site_name" content="<?= e($cfg['site_name']) ?>">
  <link rel="icon" href="assets/img/favicon.png" type="image/png">
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body>
<a class="skip-link" href="#main">İçeriğe geç</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="index.php" aria-label="<?= e($cfg['site_name']) ?> ana sayfa">
      <img class="brand-logo" src="assets/img/logo-white.png" alt="Natura Dünyası CCL CUP" width="232" height="48">
      <?php if ($scope['seasonName']): ?><span class="brand-season"><?= e($scope['seasonName']) ?></span><?php endif; ?>
    </a>
    <button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" aria-label="Menüyü aç">
      <span></span><span></span><span></span>
    </button>
    <nav id="site-nav" class="site-nav" aria-label="Ana menü">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e($href) ?>"<?= $active === $key ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main id="main">
