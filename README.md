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
| `index.php` | Dönen **manşet** (maç haberleri, kapak fotoğrafları), skor bandı, sezon rakamları, sıradaki maç geri sayımı, maç gününün öne çıkanları, puan durumu, gol/asist/kurtarış liderleri, turnuva rekorları, gol dakika ve tür grafikleri, haber kartları |
| `haberler.php` | Tüm maç haberleri; panelden CCL CUP ligi için yazılan haberler (`?haber=`) |
| `fikstur.php` | Tüm maçlar gün gün; Tümü / Fikstür / Sonuçlar sekmeleri ve takım filtresi |
| `puan-durumu.php` | Detaylı puan tablosu (sezonda grup varsa grup sekmeleri) |
| `takimlar.php` | Takım kartları |
| `takim.php?id=` | Takım profili: son maçlar şeridi, G/B/M dağılımı, sıralama grafiği, iç saha/deplasman, rekorlar, takım liderleri, sonuç kartları, gol analizi, takımın golcüleri, mevkilere göre fotoğraflı kadro, tarihçe/başarılar |
| `mac.php?id=` | Maç detayı: kapak, otomatik ya da panelden girilen maç haberi, golcüler, ilk yarı skoru, maçın enleri ve öne çıkanları, sade maç akışı, maç/devre bazlı istatistikler, profil fotoğraflı saha dizilişi, kadrolar, oyuncu performans tabloları, form karşılaştırması, video/röportaj/galeri |
| `oyuncu.php?id=` | Oyuncu kartı, ligdeki sıraları, gol dakikaları haritası, gol türleri, maç maç performans kartları, ödüller, takım arkadaşları (yalnızca CCL CUP maçları) |
| `istatistikler.php` | Oyuncu sıralamaları (gol, asist, maç, kart, kurtarış…), arama, takım filtresi, sayfalama |

Yalnızca CCL CUP kapsamındaki maç/takım/oyuncular gösterilir; başka bir ligin
id'si verilirse sayfa 404 döner.

### Maç haberleri ve kapaklar

- **Kapak fotoğrafı:** elitlig panelinde maça yüklenen fotoğraf (`match_picture`).
  Fotoğraf yoksa iki takımın logosu ve skorla otomatik tasarım kapak çizilir.
- **Manşet / haber metni:** panelde girilen maç başlığı (`post_manset` / `match_title`)
  ve raporu (`post_rapor` / `match_comment`) kullanılır. Girilmemişse skor, golcüler
  ve maç olaylarından otomatik haber başlığı ve özeti oluşturulur.
- **Maçın enleri:** panelde seçilen en iyi oyuncu, kaleci, gol vb. (`post_enler`).
  Ayrıca maç olaylarından doğrudan "Maçın Öne Çıkanları" (en çok gol, kurtarış,
  pozisyon, blok) gösterilir; puanlama/formül kullanılmaz.
- **Video, röportaj, galeri:** `match_video`, `match_interview`, `match_images`.

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
- `GET /mac-olaylari?mac_ids=` — sezonun tüm maç olayları (analizler bundan hesaplanır)
- `GET /api/players/statistics?matchId=` — maç içi oyuncu istatistikleri
- `GET /oyuncular/:id/match-log?cityId&leagueId&seasonId` — oyuncunun bu sezonki maç günlüğü
- `GET /api/weekly-awards/public`, `/api/news` — yalnızca CCL CUP kapsamındakiler, varsa gösterilir

Sitede yalnızca gerçek maç verileri gösterilir: piyasa değeri, puan/derecelendirme
ve oyuncuların CCL CUP dışındaki maçları kullanılmaz.

## Görseller ve logo

- Site logosu: `assets/img/logo-white.png` (koyu zeminler) ve `assets/img/logo-color.png`
  (açık zeminler), kökteki "yatay natura dünyası ccl cup logo" dosyalarından üretildi.
- Takım logoları ve oyuncu/maç fotoğrafları `img.php` üzerinden kendi alan adımızla
  sunulur, `cache/img` altında saklanır ve GD varsa küçültülür (ör. logo 223 KB → 10 KB).
  Kaynak adres `config.php` → `media_base`.

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

1. PHP 7.1+ (8.x önerilir) ve `curl` eklentisi (yoksa `file_get_contents` kullanılır).
   `mbstring` yoksa yedek fonksiyonlar devreye girer.
2. Dosyaları `cclcup.com`'un kök dizinine yükleyin.
3. `cache/` klasörünün web sunucusu tarafından yazılabilir olduğundan emin olun
   (`chmod 775 cache`). Yazılamazsa site çalışır ama her istekte API'ye gider.
4. Apache kullanılıyorsa `.htaccess` dosyaları `inc/` ve `cache/` klasörlerini
   dışarıya kapatır. Nginx'te aynı kuralı elle ekleyin:
   `location ~ ^/(inc|cache)/ { deny all; }`

Site açılmıyorsa (500 hatası) `/kontrol.php` sayfasını açın: PHP sürümünü,
eklentileri, `cache/` iznini, elitlig-server bağlantısını kontrol eder ve ana
sayfanın verdiği hatayı gösterir.

Yerelde denemek için:

```bash
php -S localhost:8080
```

## Dayanıklılık

- API yanıtları `cache/` altında saklanır. API'ye ulaşılamazsa (ör. Heroku
  uyanırken) süresi dolmuş önbellek gösterilir; hiç veri yoksa sayfada kısa bir
  uyarı çıkar.
- Yüklenemeyen logo/fotoğraflar otomatik olarak baş harf rozetine dönüşür.
