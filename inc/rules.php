<?php
/**
 * CCL CUP turnuva kuralları ve katılım şartları.
 * Kaynak: arsiv.cclcup.com "Kurallar" ve "Katılım Şartları" sayfaları.
 * Metni güncellemek için yalnızca bu dosyayı düzenlemek yeterlidir.
 */

return [
    'facts' => [
        ['⚽', '2 × 25 dk', 'Maç süresi'],
        ['👥', '7 + 1', 'Sahada (kaleci dahil 8 kişi)'],
        ['📋', '8 – 15', 'Kadro (+5 yedek oyuncu)'],
        ['🔁', '5 + 1', 'Oyuncu değişikliği (kaleci)'],
        ['🎂', '18+', 'Yaş sınırı'],
        ['🚫', 'Yok', 'Ofsayt'],
    ],

    'sections' => [
        [
            'id' => 'genel-isleyis', 'icon' => '🏆', 'title' => 'Genel İşleyiş',
            'summary' => 'Kura ile belirlenen gruplarda tek maçlı lig usulü, ardından tek maçlı eleme.',
            'items' => [
                ['a', 'CCL CUP halı saha turnuvasına katılan takımlar kura sonucunda belirlenen <b>dört grupta tek maçlı lig usulü</b> mücadele eder. Kura torbaları belirlenirken katılımcıların öncelikle son 3 sezondaki başarıları, ardından organizasyona katılımları dikkate alınır.'],
                ['', 'Grup maçları sonunda <b>ilk dört sırayı alan takımlar ikinci tura</b> yükselir. İkinci tur eşleşmeleri ve kupa yolundaki yerler kura ile belirlenir; aynı gruptan gelen takımlar eşleşmez. Grubunu 1. bitirenler 4. bitirenlerle, 2. bitirenler 3. bitirenlerle eşleşir. Bu turdan itibaren <b>tek maçlı eleme</b> sistemi uygulanır.'],
                ['', 'Katılımcı sayısına göre sistem değişiklik gösterebilir. Bu değişiklikler kura çekimi öncesinde tüm katılımcılara iletilir.'],
                ['b', '@steps'],
                ['c', 'Eleme turlarında berabere biten maçlarda <b>uzatma oynanmaz, doğrudan penaltı atışlarına</b> geçilir. Beşer penaltı sonunda eşitlik sürerse eşitlik bozulana kadar seri penaltı atışları yapılır.'],
                ['d', 'Turnuva boyunca <b>iki hükmen mağlubiyet</b> alan takım turnuvadan ihraç edilir. Takımın ihraç öncesi aldığı skorlar aynen tescil edilir, kalan maçları aleyhine hükmen (3-0) mağlubiyet olarak işlenir.'],
                ['e', 'Her takım müsabaka başlangıç saatinde <b>en az 6 oyuncusuyla</b> sahada hazır olmalıdır; aksi hâlde hükmen mağlup sayılır. Organizasyon komitesi gerekli gördüğü durumlarda maç saatini ve yerini değiştirme hakkına sahiptir.'],
            ],
            'steps' => [
                'title' => 'Puan eşitliğinde sıralama (grup maçları)',
                'list' => [
                    'Eş puanlı takımların kendi aralarındaki maç sonuçları',
                    'Genel puan tablosundaki averaj',
                    'Atılan gol sayısı',
                    'Alınan hükmen mağlubiyetler, ardından grup maçlarındaki kart puanı (sarı kart 1, kırmızı kart 3 puan)',
                    'Eşitlik bozulmazsa kura',
                ],
            ],
        ],
        [
            'id' => 'oyun-suresi', 'icon' => '⏱️', 'title' => 'Oyun Süresi',
            'summary' => 'Müsabakalar 25 dakikalık iki devre hâlinde oynanır.',
            'items' => [
                ['', 'Müsabakalar <b>25 dakikalık iki devre</b> hâlinde oynanır. Hakemin takdirinde olmak kaydıyla oyun gereği olmayan duraklamalar (sakatlık, oyuncu değişikliği vb.) ilgili devrenin sonuna eklenir.'],
            ],
        ],
        [
            'id' => 'oyuncular', 'icon' => '👥', 'title' => 'Oyuncu Sayısı ve Nitelikleri',
            'summary' => 'Kadro 8–15 kişi, oyuncular kurum çalışanı ve en az iki aylık sigortalı olmalı.',
            'items' => [
                ['a', 'Kadrolar <b>en az 8, en fazla 15 kişiden</b> oluşur. Katılımcılar turnuva sırasında zorunlu sebeplerle kadroya eklenebilecek <b>5 yedek oyuncu</b> bildirebilir. Müsabakalar biri kaleci olmak üzere <b>8 kişilik</b> iki takım arasında oynanır.'],
                ['b', 'Kadrodaki oyuncular <b>18 yaşını doldurmuş</b> olmalıdır.'],
                ['c', 'Tüm oyuncular katılımcı şirket veya kurumun çalışanı ve turnuva başlangıç tarihi itibarıyla geriye dönük <b>en az iki aylık sigortalı</b> olmak zorundadır. <i>İstisna:</i> yalnızca 2 oyuncuyla sınırlı olmak üzere turnuva başlangıcından önce işe yeni girmiş sigortalı çalışanlar katılabilir. Tüm katılımcılar oyuncu sigorta dökümlerini ve sözleşmede belirtilen evrakları turnuva öncesinde organizasyona iletir.'],
                ['d', 'Grup şirketleri CCL CUP\'a birbirinden bağımsız ya da birlikte katılabilir. Birleşmeleri hâlinde çatı grubun adıyla yer alırlar; bağımsız katılırlarsa ayrı gruplara yerleştirilirler.'],
                ['e', 'İki şirket birleşerek katılabilir. Takım adı iki şirketin adından oluşabilir ya da yalnızca birinin adı kullanılabilir. Bu durumda kadrodan <b>en az 4 oyuncunun ikinci firmada</b> çalışıyor olması gerekir. <i>İstisna:</i> daha önce organizasyona katılmış ve en az çeyrek final görmüş firma takımları birleşerek katılamaz.'],
                ['f', '15 kişilik asil ve 5 kişilik yedek listede <b>stajyer ve yarı zamanlı</b> çalışanlar bulunamaz.'],
                ['g', 'Listelerde <b>1 taşeron firmanın çalışanları</b> (en fazla 5 kişi) yer alabilir; iki firma birleşerek katılıyorsa bu sayı 2 kişiyle sınırlıdır. Taşeron çalışanı mutlaka katılımcı firmada (ya da birleşen firmalardan birinde) görevli olmalı, sigortası taşeron ya da katılımcı firma tarafından yapılmış olmalıdır. Gerekirse iki firma arasındaki sözleşme organizasyona ibraz edilir.'],
                ['h', 'Profesyonel lisanslı çalışanlar kesinlikle oynatılamaz. Amatör lisanslı oyuncular için sınırlar aşağıdaki tablodadır.'],
                ['', '@table'],
                ['ı', 'Turnuva sırasında işten ayrılan ya da iş akdi feshedilen oyuncu, resmî çıkış tarihinden itibaren hiçbir maçta yer alamaz. Yerine, evrakları organizasyona ibraz edilmek kaydıyla, turnuva başında bildirilen 5 yedek oyuncudan biri kadroya eklenebilir.'],
                ['i', 'Takım kaptanları maç öncesinde kadroyu (asil ve yedek) müsabaka koordinatörüne bildirir. Kaptanlar maç öncesinde, sırasında ve sonrasında takımın yetkilisi ve sorumlusudur; yönetici ve teknik adam yokken tam yetkilidir.'],
                ['k', 'Takımlar önceden bildirmek ve kimlik ibraz etmek koşuluyla yedek kulübesinde <b>bir yönetici ve bir antrenör</b> (firma içinden ya da dışından) bulundurabilir. Bu kişiler dışında kimse devre arası dahil oyun alanına alınmaz.'],
                ['l', 'Kadrolarla ilgili usulsüzlükte (kaçak ya da cezalı oyuncu) usulsüzlük yapan takım aleyhine <b>3-0 hükmen mağlubiyet</b> kararı verilir.'],
            ],
            'table' => [
                'title' => 'Lisanslı oyuncular',
                'head' => ['Lisans durumu', 'Kadroda', 'Not'],
                'rows' => [
                    ['Profesyonel lisans', 'Oynayamaz', 'Lisans iptalinden 3 yıl sonra serbest oyuncu olarak katılabilir'],
                    ['Bölgesel Amatör Lig / Süper Amatör Lig (BAL-SAL)', 'En fazla 1', 'Vize yaptırılan sezonun takvim yılı boyunca BAL-SAL statüsünde sayılır'],
                    ['1. ve 2. küme aktif lisanslı amatör', 'En fazla 3', 'Lisans süresi bitince serbest oyuncu olarak katılabilir'],
                    ['Tüm amatörler toplamı', 'En fazla 3', 'Sahada aynı anda en fazla 2 amatör oyuncu'],
                ],
            ],
            'details' => [
                'title' => 'Lisans kuralları: ayrıntılar ve örnekler',
                'paragraphs' => [
                    'Bölgesel Amatör Lig ve Süper Amatör Lig lisansına sahip çalışanlar, vize yaptırdıkları sezonun takvim yılı içinde turnuvada BAL-SAL oyuncusu statüsündedir. Turnuvanın oynandığı takvim yılında aktif BAL-SAL lisansı yoksa diğer kategorilerde (amatör ya da serbest) değerlendirilir.',
                    '<b>Örnek:</b> Ahmet Şahin 10.09.2023\'te 2023-2024 sezonu için SAL ya da BAL\'da mücadele eden bir takımda vize yeniler ve sezon 2024 içinde biterse, 2024 yılındaki sezonlara BAL-SAL statüsünde katılır. Oynadığı lig sezonu Aralık 2023\'te biter ve 1. amatör ligde bir takıma transfer olursa, 2024 CCL CUP sezonlarına amatör statüsünde katılır. SAL ya da BAL\'da oynarken aynı takvim yılında 1. veya 2. küme takımına transfer olması BAL-SAL statüsünü bitirmez.',
                    'Profesyonel lisansa sahip çalışanlar lisans iptalinden <b>3 yıl sonra</b> kadroya dahil olabilir. <b>Örnek:</b> 10.09.2023\'te profesyonel bir takımda vize yenileyen oyuncunun lisans bitişi 10.09.2024\'tür; yeniden vize yenilemezse 10.09.2027\'den itibaren serbest oyuncu olarak katılabilir. SAL, BAL, 1. veya 2. küme takımlarına transfer olması ya da amatörlüğe dönüş vizesi alması bu bekleme süresini kısaltmaz.',
                    '1. ve 2. küme amatör oyuncular lisans süresinin bitiminden itibaren serbest oyuncu olarak yer alabilir. <b>Örnek:</b> 10.09.2023\'te amatör vize yenileyen oyuncunun lisansı 10.09.2024\'te biter; yeniden vize yenilemezse bu tarihten itibaren serbest oyuncu olarak katılabilir.',
                    'Kurum ya da şirketlere ait spor kulüpleri varsa, bu kulüplerde ikinci amatör küme oyuncusu olan çalışanlar kapsam dışında sayılır ve turnuvada yer alabilir.',
                ],
            ],
        ],
        [
            'id' => 'oyuncu-degisikligi', 'icon' => '🔁', 'title' => 'Oyuncu Değişikliği',
            'summary' => 'Her maçta 5 + 1 (kaleci) değişiklik hakkı; çıkan oyuncu tekrar giremez.',
            'items' => [
                ['a', 'Her takımın her maç için <b>5 + 1 (kaleci)</b> oyuncu değiştirme hakkı vardır.'],
                ['b', 'Değişiklik oyunun durduğu bir anda hakemin izniyle yapılır. Kaleci değişikliği yalnızca listede yedek kaleci olarak belirtilen oyuncuyla yapılabilir.'],
                ['c', 'Kaleci, oyunun durduğu bir anda hakeme haber vererek bir takım arkadaşıyla yer değiştirebilir; bu değişiklik haktan düşülür. Değişiklik hakkı biten takımın kalecisi oyuna devam edemezse sahadaki oyunculardan biri kaleye geçer.'],
                ['d', '<b>Çıkan oyuncu tekrar oyuna giremez.</b>'],
                ['e', 'Değişiklik sayısının ve sahadaki amatör oyuncu sayısının takibinden takım kaptanları ve kenar yönetimi sorumludur.'],
            ],
        ],
        [
            'id' => 'ekipman', 'icon' => '👟', 'title' => 'Giysi ve Ekipman',
            'summary' => 'Numaralı forma bütünlüğü, suni çime uygun ayakkabı, takı ve aksesuar yasağı.',
            'items' => [
                ['a', 'Takımlar, kaleci formaları farklı renkte olmak kaydıyla <b>numaralı forma bütünlüğü</b> içinde oynar. Forma bütünlüğü olmayan takımlar ilk hafta organizasyonun sağladığı yeleklerle oynar.'],
                ['b', 'İki takımın forma renkleri aynıysa kura ile belirlenen takım organizasyonun yelekleriyle oynar.'],
                ['c', 'Oyuncular suni çim için üretilmiş <b>AG tabanlı ya da halı saha ayakkabısı</b> giyer. Hakemin uygun bulmadığı ayakkabılar (vidalı ya da yüksek dişli krampon) kesinlikle kullanılamaz.'],
                ['d', 'Tekmelik ve tozluk kullanımı sakatlıkların önlenmesi için tavsiye edilir ancak zorunlu değildir; oyuncular bu konuda kendileri sorumludur.'],
                ['e', 'Oyuncular kendilerine ya da başkalarına zarar verebilecek <b>takı ve aksesuar</b> (gözlük, saat, yüzük, kolye, bileklik vb.) takamaz.'],
                ['f', 'Giysi ve ekipmanla ilgili karar yetkisi hakemindir.'],
                ['g', 'Maçlarda organizasyonun sağladığı profesyonel toplar kullanılır. Takımların getirdiği toplar rakibin onayıyla kullanılabilir; anlaşılamazsa organizasyon topları kullanılır.'],
            ],
        ],
        [
            'id' => 'hakemler', 'icon' => '🟨', 'title' => 'Hakemler',
            'summary' => 'Maçları TFF Ankara İl Hakem Kurulu\'nun atadığı lisanslı hakemler yönetir.',
            'items' => [
                ['a', 'Tüm maçlar <b>TFF Ankara İl Hakem Kurulu</b> tarafından atanan lisanslı hakemlerce yönetilir.'],
                ['b', 'Hakemlerin oyunla ilgili kararları <b>nihaidir</b>.'],
                ['c', 'Hakemler resmî görevli statüsündedir. Hakemlere yönelik fiilî ya da sözlü hakaret ve saldırılar <b>6222 sayılı Sporda Şiddetin Önlenmesine Dair Kanun</b> kapsamında değerlendirilir. Bu davranış bir takımın oyuncularının geneli tarafından yapılırsa ilgili katılımcı firma sorumlu tutulur ve hukuki işlemler firma üzerinden yürütülür.'],
            ],
        ],
        [
            'id' => 'teknik-ekip', 'icon' => '📣', 'title' => 'Teknik Ekip ve Yöneticiler',
            'summary' => 'Yalnızca bildirilmiş yönetici ve teknik direktör teknik alanda bulunabilir.',
            'items' => [
                ['a', 'Katılımda isimleri bildirilen takım yöneticisi ve teknik direktör soyunma odası, koridor ve oyun alanı gibi teknik alanlarda takımla birlikte bulunabilir. Tanımlı olmayan kişilerin bu alanlarda bulunması yasaktır. Maçtan önce bildirilip onay alınırsa firma/kurum yöneticileri maçı yedek kulübesinden izleyebilir.'],
                ['b', 'Bildirilen yönetici ve teknik direktör, yazılı bildirim yapılması ve evrakların hafta içi mesai saatlerinde tamamlanması koşuluyla <b>bir kez</b> değiştirilebilir. Maç günü değişiklik kabul edilmez.'],
                ['c', 'Teknik direktör ve yöneticilerin saha içinde ve dışında centilmenliğe aykırı davranışları hâlinde kendilerine ve/veya takımlarına Disiplin Kurulu tarafından ceza verilir.'],
            ],
        ],
        [
            'id' => 'seyirciler', 'icon' => '🏟️', 'title' => 'Seyirciler',
            'summary' => 'Takımlar seyircilerinin davranışlarından sorumludur; alkol yasaktır.',
            'items' => [
                ['a', 'Seyircilerin ve yedek oyuncuların hakem izni olmadan sahaya girmesi kesinlikle yasaktır.'],
                ['b', 'Takımlar kendilerini izlemeye gelen seyircilerin davranışlarından sorumludur. Gözlemci ya da hakem raporuyla bir takımın taraftarı olduğu anlaşılan kişinin koordinatöre, hakeme, rakip oyuncuya ya da taraftara karşı centilmenlik dışı tutumu ve saha kurallarına uymaması o takımın sorumluluğundadır. <b>Tribün ve saha çevresinde alkol kullanımı kesinlikle yasaktır.</b> Disiplin Kurulu takım ile taraftar arasında bağlantı olduğuna kanaat getirirse cezayı ilgili takıma verir. Katılımcı şirket, seyircilerinin etkinlik alanındaki eşya ve düzeneklere verdiği maddi zarardan sorumludur.'],
            ],
        ],
        [
            'id' => 'saglik', 'icon' => '🚑', 'title' => 'Sağlık',
            'summary' => 'Ambulans ve sağlık ekibi olmadan maç oynanmaz.',
            'items' => [
                ['a', 'Tüm maçlarda tesiste <b>ambulans ve sağlık personeli</b> hazır bulunur; sağlık ekibi gelmeden maç oynanmaz.'],
                ['b', 'Takımlar, organizasyona önceden bildirmek kaydıyla kendi sağlık personelini yedek kulübesinde bulundurabilir.'],
                ['c', 'Tüm oyuncular futbol oynamaya sağlık açısından elverişli olduklarını kabul eder. Maç sırasında oluşabilecek sakatlık, yaralanma ve diğer sağlık sorunlarından organizasyon komitesi sorumlu tutulamaz.'],
            ],
        ],
        [
            'id' => 'oyun-kurallari', 'icon' => '📖', 'title' => 'Oyun Kuralları',
            'summary' => 'Ofsayt hariç tüm futbol kuralları geçerli; taç elle kullanılır, VAR uygulanır.',
            'items' => [
                ['a', 'Maçlarda <b>ofsayt hariç</b> tüm futbol oyun kuralları geçerlidir.'],
                ['b', 'Taç kuralı uygulanır ve taç <b>elle</b> kullanılır.'],
                ['c', 'Baraj mesafesi oyun alanının ölçülerine göre organizasyon komitesince belirlenir ve hakem tarafından uygulanır.'],
                ['ç', '<b>VAR</b>, hakemin uygun gördüğü ve net görüntü alınan pozisyonlarda kullanılır. Reji personeli net pozisyonlarda hakemi uyarabilir. Oyuncu ve yöneticilerin VAR talepleri dikkate alınmaz. VAR kontrolü sırasında hiçbir oyuncu VAR alanına 10 metreden fazla yaklaşamaz.'],
            ],
        ],
        [
            'id' => 'disiplin', 'icon' => '🟥', 'title' => 'Disiplin ve İtirazlar',
            'summary' => 'Kırmızı kart cezaları disiplin kurulunca verilir; itirazlar 24 saat içinde yazılı yapılır.',
            'items' => [
                ['a', 'Maçlarda sarı ve kırmızı kart uygulanır.'],
                ['b', 'Kırmızı kart gören oyuncular, hakem raporu doğrultusunda Disiplin Kurulu\'nun verdiği ceza süresince maçlardan men edilir.'],
                ['c', 'Maç öncesinde, sırasında ve sonrasında disiplinsiz ve centilmenlik dışı davranışlar, kırmızı kart görülmese bile Disiplin Kurulu tarafından cezalandırılır.'],
                ['d', 'Takımlar ilan edilen maç saatinde sahada <b>en az 6 oyuncuyla</b> hazır olmalıdır; zamanında gelmeyen takımlar <b>3-0 hükmen mağlup</b> sayılır.'],
                ['e', 'Maç sırasında bir takımın oyuncu sayısı <b>5\'in altına</b> düşerse (sakatlık, kart vb.) maç tatil edilir; skor ve sonuçla ilgili karar Disiplin Kurulu\'nundur.'],
                ['f', 'Oynanan maçlara itirazlar, maçın oynandığı saatten itibaren <b>24 saat içinde e-posta</b> ile yapılır (info@sportfest.com.tr). Zamanında yapılmayan ve/veya sözlü itirazlar dikkate alınmaz. İtiraz rakip takım ya da usulsüzlüğü tespit eden organizasyon komitesi tarafından yapılabilir.'],
                ['g', 'İnceleme sonucunda usulsüzlük yaptığı tespit edilen takım (kaçak ya da cezalı oyuncu oynatmak vb.) <b>3-0 hükmen yenik</b> sayılır.'],
            ],
        ],
        [
            'id' => 'tatil', 'icon' => '⏸️', 'title' => 'Tatil Edilen Maçlar',
            'summary' => 'Hayati sakatlıkta skor dondurulur; disiplin/güvenlik nedenlerinde karar kurulundur.',
            'items' => [
                ['a', 'Bir maç, herhangi bir oyuncunun hayati sakatlığı nedeniyle (sakatlığın önemi hakemce onaylanmalıdır) tatil edilirse karşılaşma o anki skoruyla dondurulur ve Organizasyon Komitesi\'nin belirleyeceği bir günde tamamlanır.'],
                ['b', 'Disiplin ya da güvenlik gibi bir nedenle tatil edilen maçlarda karar yetkisi tamamen Disiplin Kurulu\'ndadır. Kurul maçın yeniden oynanmasına, kaldığı yerden devam etmesine, takımlardan birinin hükmen galibiyetine, o anki skorla tescile, her iki takımın ihracına ya da puan cezasına karar verebilir.'],
            ],
        ],
        [
            'id' => 'guvenlik', 'icon' => '🛡️', 'title' => 'Güvenlik',
            'summary' => 'Takımlar güvenlik konusunda kendileri sorumludur.',
            'items' => [
                ['a', 'Organizasyona katılan tüm takımlar güvenlik konusunda kendileri sorumludur.'],
                ['b', 'Katılımcıların kendi aralarında ya da tesislerle aralarında çıkabilecek anlaşmazlıklarda organizasyon taraf değildir ve sorumluluk kabul etmez.'],
                ['c', 'Organizasyon; güvenlikle ilgili konularda tedbir alınmaması ya da öngörülemeyen nedenlerle takımların ve oyuncuların uğrayabileceği maddi ve manevi zararlardan sorumlu tutulamaz.'],
            ],
        ],
        [
            'id' => 'odul', 'icon' => '🥇', 'title' => 'Ödül',
            'summary' => 'Ankara şampiyonu, Antalya\'daki şirketler arası futbol şampiyonasına katılır.',
            'items' => [
                ['', 'Organizasyon sonunda <b>Ankara şampiyonu</b> olan takım, Antalya\'da yapılacak şirketler arası futbol şampiyonasına katılma hakkı kazanır. Katılım bedeli (2 gece 3 gün) organizasyon tarafından karşılanır; ulaşım bedeli katılımcı firmaya aittir.'],
            ],
        ],
    ],

    'participation' => [
        'intro' => 'CCL CUP\'ta yer alacak şirket ve kurumlar için katılım şartları.',
        'items' => [
            'Tüm oyuncular katılımcı şirket ya da kurumun çalışanı ve turnuva başlangıcı itibarıyla geriye dönük <b>en az iki aylık sigortalı</b> olmalıdır. <i>İstisna:</i> en fazla 2 oyuncuyla sınırlı olmak üzere işe yeni girmiş sigortalı çalışanlar katılabilir; bu çalışanlar hiçbir kategoride aktif futbol lisanslı olamaz. <i>Özel durum:</i> askerlik görevinden dönüp başka bir firmada çalışmadan aynı firmada SGK\'lı olarak işe başlayan çalışan için geriye dönük sigorta aranmaz, ancak turnuva tarihi itibarıyla SGK kaydı zorunludur.',
            'Grup şirketleri birbirinden bağımsız ya da birlikte katılabilir. Birleşirlerse çatı grubun adıyla yer alırlar; bağımsız katılırlarsa ayrı gruplara yerleştirilirler.',
            'İki şirket birleşerek katılabilir; takım adı iki şirketin adından oluşabilir ya da yalnızca biri kullanılabilir. Kadrodaki oyuncuların iki firmadan birinde çalışıyor olması gerekir.',
            'Bir şirket, kadrosunda <b>franchise</b> işletmelerinin çalışanlarını oynatabilir; bu durumda franchise sözleşmesi kayıt aşamasında ibraz edilir.',
            '15 kişilik asil ve 5 kişilik yedek listede stajyer ve yarı zamanlı çalışanlar bulunamaz.',
            'Listelerde 1 taşeron firmanın çalışanları (en fazla 5 kişi) yer alabilir; iki firma birleşerek katılıyorsa bu sayı 2 kişidir. Taşeron çalışanları mutlaka katılımcı firmada görevli olmalıdır.',
            'Lisanslı oyuncu sınırları "Oyuncu Sayısı ve Nitelikleri" bölümündeki tablodaki gibidir.',
            'Organizasyon komitesi gerekli gördüğü takdirde tesisleri, maç yerlerini, tarih ve saatleri önceden haber vermeksizin değiştirebilir; bu durumda takımlar en kısa sürede bilgilendirilir.',
        ],
        'checklist_title' => 'Resmî katılımcı olmak için',
        'checklist' => [
            'Katkı payı ödemesinin bildirilen hesaba yapılması',
            'Katılım sözleşmesinin her sayfasının yetkili kişi tarafından ıslak imzalı ve kaşeli olarak elden ya da kargoyla teslimi',
            'Resmî takım listesinin yetkili imzalı ve kaşeli olarak elden ya da e-postayla (info@sportfest.com.tr) iletilmesi',
            'Her oyuncunun T.C. kimlik numaralı kimlik kartı ya da sürücü belgesi fotokopisi',
            'Her oyuncunun işe giriş bildirgesi',
            'Her oyuncunun son 2 aya ait SGK hizmet dökümü',
        ],
    ],
];
