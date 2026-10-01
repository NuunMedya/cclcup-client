<?php
/**
 * CCL CUP — site ayarları.
 *
 * Tüm veriler elitlig-server'dan (elitlig.com'un kullandığı API) sunucu
 * tarafında çekilir. Tarayıcı API'ye hiç gitmez; bu yüzden elitlig-server'da
 * CORS ayarı yapmaya gerek yoktur.
 *
 * Ortam değişkenleriyle (ör. Apache SetEnv / hosting paneli) her ayar
 * ezilebilir; tanımlı değilse aşağıdaki varsayılanlar kullanılır.
 */

$env = static function (string $key, $default) {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

return [
    // elitlig-server adresi (sonunda / olmadan)
    'api_base'   => rtrim($env('CCL_API_BASE', 'https://elitlig-api-88a866b7a4da.herokuapp.com'), '/'),

    // elitlig.com'daki CCL CUP kapsamı: Ankara / CCL CUP / CCL 2026 GÜZ SEZONU
    'city_id'    => (int) $env('CCL_CITY_ID', 1),
    'league_id'  => (int) $env('CCL_LEAGUE_ID', 100),
    // Yeni sezon açıldığında yalnızca bu değeri değiştirmek yeterli.
    // 0 verilirse ligin en güncel (arşivlenmemiş) sezonu otomatik seçilir.
    'season_id'  => (int) $env('CCL_SEASON_ID', 193),

    // Logo ve fotoğrafların bulunduğu medya sunucusu. Bu adresteki görseller
    // ziyaretçiye img.php üzerinden kendi alan adımızla sunulur.
    'media_base' => rtrim($env('CCL_MEDIA_BASE', 'https://elitlig-space.fra1.digitaloceanspaces.com'), '/'),

    // API yanıtlarının dosya önbelleğinde tutulma süresi (saniye)
    'cache_ttl'  => (int) $env('CCL_CACHE_TTL', 60),
    'cache_dir'  => __DIR__ . '/cache',
    'timeout'    => (int) $env('CCL_API_TIMEOUT', 15),

    // Geçmiş sezonların bulunduğu arşiv sitesi
    'archive_url' => $env('CCL_ARCHIVE_URL', 'https://arsiv.cclcup.com'),

    // Site bilgileri
    'site_name'  => 'CCL CUP',
    'site_title' => 'Natura Dünyası CCL CUP — Kurumlar Arası Futbol Turnuvası',
    'site_url'   => rtrim($env('CCL_SITE_URL', 'https://cclcup.com'), '/'),

    // İletişim / sosyal medya (boş bırakılanlar gösterilmez)
    'contact' => [
        'phone'     => $env('CCL_PHONE', ''),
        'email'     => $env('CCL_EMAIL', ''),
        'instagram' => $env('CCL_INSTAGRAM', ''),
        'whatsapp'  => $env('CCL_WHATSAPP', ''),
        'address'   => $env('CCL_ADDRESS', 'Ankara'),
    ],
];
