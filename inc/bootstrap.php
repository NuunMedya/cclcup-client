<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/partials.php';

$GLOBALS['ccl_api_errors'] = [];

/** Sayfa bulunamadığında 404 şablonu gösterip çıkar. */
function not_found(string $message = 'Aradığınız sayfa bulunamadı.'): void
{
    http_response_code(404);
    $page = ['title' => 'Bulunamadı', 'nav' => ''];
    require __DIR__ . '/header.php';
    echo '<section class="container section"><div class="empty-state big">';
    echo '<div class="empty-icon">⚽</div><h1>Bulunamadı</h1><p>' . e($message) . '</p>';
    echo '<a class="btn" href="index.php">Ana sayfaya dön</a></div></section>';
    require __DIR__ . '/footer.php';
    exit;
}
