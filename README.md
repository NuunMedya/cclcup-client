# cclcup.com — CCL CUP web sitesi

CCL CUP organizasyonuna özel, düz **PHP + HTML + CSS** ile yazılmış web sitesi.
Framework, veritabanı veya derleme adımı yoktur; dosyaları PHP destekleyen
herhangi bir hostinge yüklemek yeterlidir.

Tüm veriler **elitlig-server**'dan (elitlig.com'un kullandığı API) canlı olarak
alınır. Maçlar, skorlar, kadrolar ve istatistikler elitlig yönetim panelinden
girildiği anda bu sitede de görünür (en fazla `cache_ttl` saniye gecikmeyle).

## Sayfalar

| Sayfa | İçerik |
|---|---|
| `index.php` | Öne çıkan/sıradaki maç, son sonuçlar, fikstür, puan durumu, gol/asist/değer liderleri, takımlar |
| `fikstur.php` | Tüm maçlar gün gün; Tümü / Fikstür / Sonuçlar sekmeleri ve takım filtresi |
| `puan-durumu.php` | Detaylı puan tablosu (sezonda grup varsa grup sekmeleri) |
| `takimlar.php` | Takım kartları |
| `takim.php?id=` | Takım profili: sıralama, form, maçlar, kadro ve takım golcüleri |
| `mac.php?id=` | Maç detayı: skor, golcüler, maç akışı, istatistikler, kadrolar (canlı maçta 60 sn'de bir yenilenir) |
| `oyuncu.php?id=` | Oyuncu profili: sezon istatistikleri ve maç katkıları |
| `istatistikler.php` | Oyuncu sıralamaları (gol, asist, maç, kart, kurtarış…), arama, takım filtresi, sayfalama |

Yalnızca CCL CUP kapsamındaki maç/takım/oyuncular gösterilir; başka bir ligin
id'si verilirse sayfa 404 döner.

## Kullanılan elitlig-server uçları

Hepsi herkese açık `GET` uçlarıdır; istekler PHP tarafında (sunucudan sunucuya)
yapıldığı için elitlig-server'da CORS ayarı değiştirmeye gerek yoktur.

- `GET /api/meta/seasons?cityId&leagueId` — sezon adı
- `GET /maclar?league_id&season_id` — maç listesi
- `GET /maclar/:id`, `/maclar/:id/kadro`, `/api/maclar/:id/olaylar` — maç detayı
- `GET /api/standings?cityId&leagueId&seasonId[&groupId]` — puan durumu
- `GET /api/season-groups/season/:seasonId` — gruplar
- `GET /api/players/statistics?cityId&leagueId&seasonId&sort&teamId&search` — oyuncu istatistikleri
- `GET /takimlar/:id`, `/oyuncular/:id`, `/mac-olaylari?oyuncu_id` — takım/oyuncu detayları

## Ayarlar (`config.php`)

| Ayar | Ortam değişkeni | Varsayılan |
|---|---|---|
| API adresi | `CCL_API_BASE` | `https://elitlig-api-88a866b7a4da.herokuapp.com` |
| Şehir | `CCL_CITY_ID` | `1` (Ankara) |
| Lig | `CCL_LEAGUE_ID` | `100` (CCL CUP) |
| Sezon | `CCL_SEASON_ID` | `193` (CCL 2026 GÜZ SEZONU) — `0` verilirse ligin güncel sezonu otomatik seçilir |
| Önbellek süresi (sn) | `CCL_CACHE_TTL` | `60` |
| İletişim | `CCL_PHONE`, `CCL_EMAIL`, `CCL_INSTAGRAM`, `CCL_WHATSAPP`, `CCL_ADDRESS` | boş (boşlar gösterilmez) |

**Yeni sezon açıldığında** elitlig panelinde CCL CUP ligine sezonu ekleyip
`season_id` değerini güncellemek (ya da `0` yapmak) yeterlidir.

## Kurulum

1. PHP 7.4+ (8.x önerilir) ve `curl` eklentisi (yoksa `file_get_contents` kullanılır).
2. Dosyaları `cclcup.com`'un kök dizinine yükleyin.
3. `cache/` klasörünün web sunucusu tarafından yazılabilir olduğundan emin olun
   (`chmod 775 cache`). Yazılamazsa site çalışır ama her istekte API'ye gider.
4. Apache kullanılıyorsa `.htaccess` dosyaları `inc/` ve `cache/` klasörlerini
   dışarıya kapatır. Nginx'te aynı kuralı elle ekleyin:
   `location ~ ^/(inc|cache)/ { deny all; }`

Yerelde denemek için:

```bash
php -S localhost:8080
```

## Dayanıklılık

- API yanıtları `cache/` altında saklanır. API'ye ulaşılamazsa (ör. Heroku
  uyanırken) süresi dolmuş önbellek gösterilir; hiç veri yoksa sayfada kısa bir
  uyarı çıkar.
- Yüklenemeyen logo/fotoğraflar otomatik olarak baş harf rozetine dönüşür.
