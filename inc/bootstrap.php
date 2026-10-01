<?php
require_once __DIR__ . '/compat.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/partials.php';

$GLOBALS['ccl_api_errors'] = [];

// Ölümcül bir hata olursa tarayıcıya boş 500 yerine anlaşılır bir mesaj göster.
register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    error_log('CCL CUP: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px">'
        . '<h1>Bir sorun oluştu</h1><p>Sayfa şu anda görüntülenemiyor. Lütfen biraz sonra tekrar deneyin.</p>'
        . '<p style="color:#7b84a0;font-size:.9em">Site yöneticisi: ayrıntı için <code>/kontrol.php</code> sayfasını açın.</p></div>';
});

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
