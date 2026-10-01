<?php
/** @var array $page ['title' => string, 'nav' => string, 'description' => ?string] */
$cfg = ccl_config();
$scope = ccl_scope();
$pageTitle = !empty($page['title']) ? $page['title'] . ' | ' . $cfg['site_name'] : $cfg['site_title'];
$description = $page['description'] ?? ($cfg['site_name'] . ' ' . ($scope['seasonName'] ?: '') . ' fikstür, sonuçlar, puan durumu ve oyuncu istatistikleri.');
$nav = [
    'home'      => ['index.php', 'Ana Sayfa'],
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
  <meta property="og:site_name" content="<?= e($cfg['site_name']) ?>">
  <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body>
<a class="skip-link" href="#main">İçeriğe geç</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="index.php" aria-label="<?= e($cfg['site_name']) ?> ana sayfa">
      <img src="assets/img/logo.svg" alt="" width="44" height="44">
      <span class="brand-text">
        <strong>CCL <em>CUP</em></strong>
        <small><?= e($scope['seasonName'] ?: 'Kurumlar Arası Futbol Turnuvası') ?></small>
      </span>
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
