# ARCHITECTURE.md
> Bu dosya projenin **sabit mimari referansıdır**. Her yeni prompt öncesi bu dosya AI'ye verilmelidir.
> Bu dosya sık değişmez — değişiklik gerekirse önce insan (Kort) onayı alınır ve DURUM.md'ye not düşülür.

---

## 1. Proje Nedir?

Google Ads API ve Meta Marketing API'yi kullanarak, reklam vermek isteyen bireylerin/küçük
işletmelerin karşılaştığı kurulum karmaşıklığını basitleştiren bağımsız bir web sistemi.
Kullanıcı kendi Google Ads / Meta reklam hesabını OAuth ile bağlar, basit bir sihirbaz üzerinden
temel bilgileri girer (web sitesi, bütçe, hedef kitle), sistem arka planda kampanya kurulumunu
API üzerinden otomatik yapar ve durumu/metrikleri basit bir panelde gösterir.

**Bu sistem değildir:**
- Bir reklam ajansı aracı değildir (kullanıcı adına para akışına dahil olunmaz).
- Otomatik itiraz/dispute çözücü değildir (bu kısım destek şablonu üretmekle sınırlı).

---

## 1.1. Temel Ürün Felsefesi — Platform Karmaşıklığı Kullanıcıya Ait Değildir

Bu sistemin temel amacı, Google Ads ve Meta reklam platformlarının karmaşık yönetim arayüzlerini kullanıcıya yeniden sunmak değildir.

**Platform karmaşıklığı kullanıcıya değil, sisteme aittir.**

Kullanıcıdan yalnızca reklam oluşturmak için gerekli olan ve insan tarafından kolayca anlaşılabilecek bilgiler alınmalıdır. Kullanıcı, Google Ads veya Meta'nın teknik kampanya yapısını bilmek zorunda olmamalıdır.

Kullanıcı arayüzü mümkün olduğunca az sayıda ve anlaşılır kontrol üzerine kurulmalıdır. Bunlar ihtiyaca göre `input`, `textarea`, `select`, basit seçim bileşenleri, tarih/bütçe alanları ve gerektiğinde görsel yükleme gibi kontroller olabilir.

Örneğin kullanıcıdan şu tür bilgiler alınabilir:

* Web sitesi
* Reklam başlıkları
* Reklam metinleri
* Anahtar kelimeler
* Günlük/toplam bütçe
* Reklamın yayınlanacağı ülke, bölge veya şehir
* Hedef kitleyle ilgili basit bilgiler
* Platformun gerçekten gerektirdiği diğer temel bilgiler

Buna karşılık Google Ads veya Meta'nın aşağıdaki gibi teknik yapıları kullanıcı arayüzüne doğrudan yansıtılmamalıdır:

* Campaign / Campaign Budget
* Ad Group
* Ad / Asset yapıları
* Teknik bidding stratejileri
* Platforma özgü ID ve criterion yapıları
* API'ye özgü teknik parametreler
* Kullanıcının reklam vermesi için bilmesine gerek olmayan diğer platform ayarları

Platform API'si bir işlemi gerçekleştirmek için çok sayıda teknik parametre gerektiriyorsa öncelikli soru:

**“Bu ayarları kullanıcıya nasıl gösterebiliriz?” değil, “Bu teknik gereksinimi kullanıcıya göstermeden sistem içinde nasıl yönetebiliriz?” olmalıdır.**

Mümkün olduğunda teknik değerler sistem tarafından türetilmeli, güvenli varsayılanlar kullanılmalı veya birden fazla teknik seçenek kullanıcı açısından tek ve anlaşılır bir seçeneğe dönüştürülmelidir.

Örneğin kullanıcı:

> “Ankara'da günde 500 TL bütçeyle bu hizmetimin reklamını yapmak istiyorum.”

diyebilmelidir. Kullanıcının bu isteği gerçekleştirmek için Google Ads'in kampanya, reklam grubu, bütçe, location criterion, bidding veya benzeri teknik kavramlarını bilmesi beklenmemelidir.

Kullanıcı arayüzüne yeni bir teknik seçenek ancak şu durumda eklenmelidir:

1. İşlemi gerçekleştirmek için gerçekten gerekli olması,
2. Kullanıcı açısından anlaşılır şekilde ifade edilebilmesi,
3. Kullanıcı deneyimini gereksiz şekilde karmaşıklaştırmaması.

**Google Ads veya Meta'nın kendi arayüzündeki ayar sayısını veya karmaşıklığını taklit etmek bu projenin hedefi değildir.**

Bu prensip yalnızca frontend için değil, tüm sistem mimarisi için geçerlidir. Backend ve adapter katmanları, kullanıcıdan alınan sade bilgileri ilgili reklam platformunun gerektirdiği teknik yapılara dönüştürmekten sorumludur.

### Minimum Kullanıcı Bilgisi İlkesi

Bir reklamın oluşturulabilmesi için platform tarafından zorunlu tutulan her teknik alanın kullanıcı tarafından manuel olarak doldurulması şart değildir.

Öncelik sırası:

1. Kullanıcıdan temel ve anlaşılır bilgi alınır.
2. Sistem gerekli teknik değerleri mümkün olduğunca kendisi oluşturur veya türetir.
3. Platformun zorunlu tuttuğu ancak kullanıcı tarafından sağlanması gereken bir bilgi varsa kullanıcıya sade bir biçimde sorulur.
4. Kullanıcının bilmesine gerek olmayan teknik ayrıntılar arayüzde gösterilmez.

Bu nedenle proje geliştirilirken **“Google/Meta API bunu istiyor” tek başına bir UI alanı ekleme gerekçesi değildir.**

Her yeni kullanıcı alanı için, bu bilginin kullanıcıdan neden alınması gerektiği ayrıca değerlendirilmelidir.


## 2. Çalışma Modeli (Roller)

| Rol | Kim | Görev |
|---|---|---|
| Spec yazarı | Claude (bu sohbet) | Mimari kararları verir, `.md` prompt dosyalarını ve düzeltme promptlarını hazırlar |
| Kimlik/erişim sorumlusu | Kort | Google/Meta geliştirici hesapları, OAuth client_id/secret, API erişim başvuruları, `.env` doldurma |
| Kod yazıcı | VS Code + AI (Cline/Requesty) | Prompt dosyalarını okuyup gerçek kod dosyalarını yazar, **her görev sonunda `DURUM.md`'yi kendisi günceller** |

**Kritik kural:** Kod yazıcı AI, her oturuma başlarken önce `DURUM.md` dosyasını, sonra bu
`ARCHITECTURE.md` dosyasını, sonra o anki görev prompt dosyasını okumalıdır. Bu sıralama
context sıfırlansa bile kaldığı yerden devam edebilmeyi sağlar. Görev bittiğinde **DURUM.md'yi
güncellemek de kod yazıcı AI'nin sorumluluğudur** — Claude bu dosyayı artık düzenlemez, sadece
okur (yeni prompt hazırlarken mevcut durumu anlamak için).

**Kod inceleme/düzeltme akışı:** Claude (spec yazarı) mevcut kodu **doğrudan düzenlemez.**
Repo'dan ilgili dosyayı okur, sorunu/eksiği tespit eder, kod yazıcı AI'ye yönelik **düzeltme
promptu** yazar (örn. "`GoogleOauth.php` dosyasında `refresh_token` boş geldiğinde hata
fırlatmıyor, şu satırdan sonra şu kontrolü ekle: ..."). Kod yazıcı AI bu promptu uygulayıp
dosyayı günceller. Bu sayede kod stili ve konvansiyonları tek bir AI üzerinden tutarlı kalır,
Claude sadece yönlendirici/denetleyici rolünde kalır.

---

## 3. Teknoloji Yığını

- **Backend:** Native PHP (framework yok), Composer ile bağımlılık yönetimi
- **Veritabanı:** MySQL (Kort'un mevcut altyapısıyla uyumlu)
- **Frontend:** Vanilla HTML/CSS/JavaScript (framework yok)
- **Üçüncü parti SDK'lar:**
  - `googleads/google-ads-php` (Google Ads API)
  - `facebook/php-business-sdk` (Meta Marketing API)
- **Cron:** Sunucu crontab ile tetiklenen bağımsız PHP script'i (HTTP isteğinden ayrı)

---

## 4. Dosya Yapısı (Hedef)

```
ads_oauth/
├── index.php
├── composer.json
├── .env.sample
│
├── api/
│   ├── index.php
│   ├── oauth-baslat.php
│   ├── oauth-donus.php
│   ├── hesap-sil.php
│   ├── kampanya-olustur.php
│   ├── kampanya-listele.php
│   ├── kampanya-durdur.php
│   └── senkron-tetikle.php
│
├── php/
│   ├── config.php
│   ├── veritabani.php
│   ├── oturum.php
│   ├── sifreleme.php
│   │
│   ├── oauth/
│   │   ├── google-oauth.php
│   │   └── meta-oauth.php
│   │
│   ├── baglayici/
│   │   ├── google-ads-baglayici.php
│   │   └── meta-ads-baglayici.php
│   │
│   ├── servis/
│   │   ├── kampanya-servisi.php
│   │   ├── senkron-servisi.php
│   │   └── hesap-servisi.php
│   │
│   └── cron/
│       └── senkron-calistir.php
│
├── tema/
│   ├── layout/
│   │   ├── header.php
│   │   └── footer.php
│   ├── panel/
│   │   ├── anasayfa.php
│   │   ├── hesap-baglan.php
│   │   └── kampanya-sihirbazi.php
│   └── giris.php
│
└── assets/
    ├── js/
    ├── css/
    ├── img/
    └── font/
```

*(SQL şema dosyası ayrı tutulur, bu ağaca dahil değildir — `db/sema.sql` gibi bağımsız bir dosyada olacak.)*

---

## 5. Katman Sorumlulukları

- **`api/`** → Sadece HTTP giriş noktaları. İş mantığı burada yazılmaz, `Servis/` katmanını çağırır, JSON döner.
- **`php/Oauth/`** → Google/Meta OAuth akışının teknik detayları (authorization URL üretme, token değişimi).
- **`php/Baglayici/`** → Google Ads API / Meta Marketing API çağrılarını sarmalayan adapter katmanı. SDK değişikliklerinin etkisi sadece burada kalmalı.
- **`php/Servis/`** → İş mantığı. `Baglayici/` ve `Oauth/` katmanlarını kullanır, `api/` dosyalarına temiz veri döner.
- **`php/Cron/`** → HTTP isteğinden bağımsız, crontab ile tetiklenen tek giriş dosyası.
- **`tema/`** → Sadece görünüm. İş mantığı içermez.

---

## 6. Genel Konvansiyonlar

- Dosya/fonksiyon isimlendirmesi: Türkçe, eylem bazlı, **tamamen küçük harf, tire ile ayrılmış**
  (`kampanya-olustur.php`, `hesap-sil.php`, `google-oauth.php`). Class dosyaları da bu kurala
  uyar — class'ın kendisi PHP konvansiyonuna göre PascalCase yazılabilir (`class GoogleOauth`),
  ama dosya adı küçük harf kalır (`google-oauth.php`).
- **Autoload stratejisi:** Kendi class'larımız için Composer PSR-4 autoload **kullanılmaz**
  (dosya adı küçük harf + class adı PascalCase uyuşmazlığı, Linux sunucuda case-sensitivity
  sorunu yaratır). Bunun yerine Websistem'deki gibi merkezi bir `require_once` zinciri kullanılır
  (örn. `php/config.php` üstünde gerekli dosyaları manuel include eder). Composer/PSR-4,
  sadece üçüncü parti paketler (`google-ads-php`, `facebook-business-sdk`) için kullanılır.
- `api/` altındaki her dosya JSON döner. **Websistem konvansiyonuyla uyumlu format** (Kort'un alışık olduğu):
  `echo json_encode(['return' => 1, 'mesaj' => 'İşlem başarılı']);` (başarı, `return: 1`)
  `echo json_encode(['return' => 0, 'mesaj' => 'Hata açıklaması']);` (hata, `return: 0`)
  Ek veri döndürmek gerektiğinde aynı diziye ekstra anahtar eklenir (örn. `['return' => 1, 'mesaj' => '...', 'kampanya_id' => 123]`), ayrı bir "veri" objesi içine sarılmaz.
- Her `api/` dosyasının başında doğrudan erişim engeli olmalı: dosya sadece `api/index.php`
  üzerinden çağrılabilmeli, tarayıcıdan direkt istekle açılamamalı (Websistem'deki
  `if (!defined('otoban')) exit;` benzeri bir koruma — bu projede kullanılacak sabit adı
  ayrı bir promptta netleştirilecek).
- Hassas veriler (refresh token) `Sifreleme.php` üzerinden şifreli saklanır, asla düz metin değil.
- Her yeni dosya, hangi katmana ait olduğunu ve hangi dosyaları çağırdığını üstte kısa bir yorum ile belirtir.
- `.env` dosyasında: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_DEVELOPER_TOKEN`, `META_APP_ID`, `META_APP_SECRET`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SIFRELEME_ANAHTARI`.

---

## 7. Veri Modeli (Taslak — SQL dosyası ayrı hazırlanacak)

- `site_sahipleri` — sistemi kullanan kişi/işletme kaydı
- `baglanmis_hesaplar` — hangi kullanıcının hangi Google/Meta hesabına bağlı olduğu, şifreli token'lar
- `kampanyalar` — oluşturulan kampanyaların yerel kaydı, platform tarafı kampanya ID'si
- `senkron_kayitlari` — her senkron çalışmasının log'u (ne zaman, hangi kampanya, sonuç)

*(Detaylı kolon şeması SQL dosyası hazırlanırken netleştirilecek — bu bölüm sadece kavramsal.)*

---

## Proje Kapsamı ve Meta Entegrasyonu

Bu projenin mevcut geliştirme kapsamı Google Ads entegrasyonudur.

Google Ads tarafı tamamlandığında `ads_oauth` projesi tamamlanmış kabul edilir ve
`DURUM.md` içinde proje durumu **BİTTİ** olarak işaretlenir.

Meta entegrasyonu bu projenin tamamlanma kriteri değildir.

Meta entegrasyonu daha sonraki ayrı bir geliştirme fazı/konusu olarak ele alınacaktır.

Meta geliştirmesine bu proje tamamlanmadan başlanmaz.

Meta için gerekli OAuth, API, veri modeli, servisler, arayüzler ve entegrasyon
kararları bu aşamada uygulanmaz.

Mevcut Google Ads mimarisi, Meta entegrasyonu gelecek diye gereksiz şekilde
genellenmez veya soyutlanmaz.

---

## 8. Değişiklik Notu

Bu dosyada mimari bir değişiklik yapılırsa, `DURUM.md` içindeki "Karar Günlüğü" bölümüne
tarih ve gerekçeyle birlikte not düşülür.
