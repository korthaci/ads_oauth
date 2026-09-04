# DURUM.md
> Bu dosya **canlıdır** — her ilerleme sonrası güncellenir. Yeni bir çalışma oturumuna
> başlarken (context sıfırlansa dahi) önce bu dosya okunur, sonra ARCHITECTURE.md, sonra
> ilgili prompt dosyası.
> **Güncelleme sorumluluğu kod yazıcı AI'ye (Cline/Requesty) aittir.** Claude bu dosyayı
> artık düzenlemez, sadece yeni prompt hazırlarken referans olarak okur.

**Son güncelleme:** 2026-09-04

**Proje adı:** ads_oauth *(eski adı "ads" idi — arama motorlarında/genel aramalarda "ads" kelimesi çok geçtiği için değiştirildi)*
**Proje yolu:** `c:/server/htdocs/ads_oauth/`
**GitHub reposu:** https://github.com/korthaci/ads_oauth (public)
**Kod yazıcı ortam:** VS Code + Cline + Requesty → model: OpenAI GPT Luna (düşük token tüketimi hedefleniyor, proje sonuna kadar yetsin diye bilinçli olarak Claude/OpenAI'nin üst modelleri seçilmedi)
**Workspace notu:** Kort'un VS Code workspace'ine Websistem projesi de referans amaçlı eklendi — amaç, `api/` dosyalarının JSON dönüş biçimini Kort'un zaten alışık olduğu Websistem konvansiyonuyla (`class_f/fonksiyon.php` genel fonksiyonlar, `class_f/class_.php` genel class'lar) tutarlı tutmak. AI'nin Websistem'in tamamını incelemesine gerek yok, sadece bu iki dosyadaki JSON döndürme kalıbını referans almalı.

---

## 1. Şu An Neredeyiz?

**Aşama:** PROMPT 00 (iskelet kurulum), PROMPT 01 (isimlendirme düzeltmesi + autoload kaldırma),
PROMPT 02 (eksik kalan isimlendirme düzeltmelerinin kontrolü), PROMPT 03 (Google öncelikli
temel altyapı), PROMPT 04 (Composer/vendor altyapısı) ve PROMPT 05 (Google Ads PHP client
geçişi) tamamlandı. `.env` dosyası oluşturuldu ve veritabanı bilgileriyle dolduruldu;
Google OAuth client ayarları `.env` üzerinden okunuyor; Google Cloud redirect ayarının gerçek
ortamda doğrulanması henüz yapılmadı.

**Sıradaki adım:** Google Cloud redirect ayarını ve gerçek kullanıcı callback'ini doğrulamak,
ardından bağlayıcı/servis katmanlarına geçmek. Google Ads PHP client
(`googleads/google-ads-php:^34.0`) kuruldu ve
`vendor/autoload.php` üzerinden `Google\Ads\GoogleAds\Lib\V20\GoogleAdsClient` yüklemesi
doğrulandı.

---

## 2. Tamamlanan Adımlar

- [x] Proje fikri ve gerekçesi netleştirildi (Google/Meta reklam kurulum karmaşıklığını basitleştirme).
- [x] Çalışma modeli belirlendi: SaaS/OAuth modeli (ajans modeli değil).
- [x] Rakip analizi yapıldı (Madgicx, Revealbot vb. — farklı segment, tehdit değil).
- [x] Google Ads API ve Meta Marketing API resmi dokümantasyon bağlantıları paylaşıldı.
- [x] Google'ın "hangi ürünü kullanmalıyım" tablosu incelendi, API yolu doğrulandı
      (not: Türkçe sayfadaki "kullanma" ifadesi çeviri hatasıydı, orijinali "Use the Google Ads API").
- [x] OAuth izin akışının adım adım nasıl işlediği netleştirildi.
- [x] Websistem (Kort'un mevcut CMS'i) incelendi, bu projenin **modül değil, bağımsız sistem**
      olması gerektiğine karar verildi (gerekçe: hedef kitle Websistem kullanmayabilir, composer
      bağımlılık çakışma riski, cron/worker doğası CMS modül yapısına uymuyor, güvenlik izolasyonu).
- [x] Dosya yapısı taslağı çıkarıldı (bkz. ARCHITECTURE.md, ~34 dosya, SQL hariç).
- [x] Çalışma yöntemi belirlendi: genel mimari dosyası + ihtiyaç oldukça tek tek prompt +
      bu durum takip dosyası.
- [x] PROMPT 00 uygulandı: klasör/dosya iskeleti oluşturuldu, gerçek mantık eklenmedi.
- [x] PROMPT 01 uygulandı: `php/` altındaki klasör ve dosya adları küçük harfe çevrildi;
      `composer.json` içindeki PSR-4 `autoload` bloğu kaldırıldı.
- [x] PROMPT 02 uygulandı: PROMPT 01'in devamı/düzeltmesi olarak `php/` altındaki tüm klasör
      ve dosya adlarının küçük harfli son hali doğrulandı; ek yeniden adlandırma gerekmedi.
- [x] PROMPT 03 uygulandı: `db/sema.sql` oluşturuldu, temel config/veritabanı/oturum/şifreleme
      mantığı yazıldı ve `.env.sample` içindeki `DB_NAME` değeri `ads_oauth` olarak ayarlandı.
- [x] Gerçek `.env` dosyası oluşturuldu ve veritabanı bilgileriyle dolduruldu.
- [x] PROMPT 04 uygulandı: İlk Composer/vendor altyapısı `google/apiclient` ile kuruldu;
      `vendor/` ve `vendor/autoload.php` oluşturuldu. Google OAuth akışı henüz yazılmadı.
- [x] PROMPT 05 uygulandı: `google/apiclient` kaldırıldı, `googleads/google-ads-php:^34.0`
      eklendi; Composer bağımlılıkları yeniden çözüldü, `composer.lock` ve `vendor/` güncellendi.
      Google Ads PHP client'ın `vendor/autoload.php` üzerinden yüklenmesi doğrulandı; PHP
      `8.2.12` olarak kontrol edildi. OAuth koduna ve DB dosyalarına dokunulmadı.
- [x] PROMPT 06 Revize tamamlandı: OAuth HTTP girişi yalnızca `api/index.php` üzerinden
      `oauth-baslat` ve `oauth-donus` action'larıyla çalışacak şekilde eklendi; yeni OAuth HTTP
      endpoint dosyası oluşturulmadı.

## 2.1. PROMPT-06 Revize Sonucu

- **PROMPT 06 Revize tamamlandı mı?** Evet. Değişen dosyalar:
  `api/index.php`, `php/oauth/google-oauth.php` ve bu durum kaydı olan `md/DURUM.md`.
  `api/oauth-baslat.php` ile `api/oauth-donus.php` mevcut iskelet dosyalarıdır; bu görevde
  oluşturulmadı, değiştirilmedi ve dispatch için kullanılmıyor.
- **OAuth başlangıç akışı:** Giriş yapılmış session'daki `sahip_no` kontrol edilir; 32 byte
  `random_bytes()` state üretilip session'a yazılır; Google Ads scope, `offline` erişim ve
  `consent` prompt ile authorization URL JSON response içinde döner.
- **OAuth callback akışı:** `sahip_no`, state ve code/error parametreleri kontrol edilir.
  Doğrulanan state token exchange'den önce session'dan silinir. Authorization code, mevcut
  Composer paketindeki `Google\Auth\OAuth2` ile token'a çevrilir ve refresh token zorunlu
  tutulur. Google Ads PHP client'ın mevcut `OAuth2TokenBuilder` sınıfı refresh-token
  credential'ının kurulabilirliğini doğrulamak için kullanılır; Google Ads API çağrısı yapılmaz.
- **State/CSRF durumu:** State session'da tutulur, `hash_equals()` ile karşılaştırılır,
  geçersiz state reddedilir ve doğrulanmış state tek kullanımlık olarak tüketilir.
- **Refresh token şifreleme durumu:** Refresh token yalnızca mevcut `sifrele()` mekanizmasıyla
  AES-256-CBC ciphertext'e dönüştürülür; access token kalıcı yazılmaz. Token response'a,
  HTML'e veya log'a eklenmez.
- **Database kayıt durumu:** Mevcut `baglanmis_hesaplar` tablosu kullanılır; `sahip_no` session
  kullanıcısından, `platform` `google` değerinden alınır. `harici_kimlik` NULL bırakılır;
  mevcut Google kaydı varsa güncellenir, yoksa eklenir. Gerçek DB insert/update testi aşağıdaki
  gerçek Google callback'i ve refresh token olmadığı için çalıştırılmadı; PDO veritabanı bağlantı
  kontrolü başarılıdır.
- **Local OAuth test sonucu:** PHP syntax kontrolü, Composer autoload ve `OAuth2TokenBuilder`
  sınıfı kontrolü başarılıdır. CLI davranış testinde authorization URL, scope, offline/consent,
  sabit redirect URI, session state, geçersiz state reddi, state tüketimi ve şifreleme round-trip'i
  doğrulandı. PHP built-in server üzerinden `api/index.php?islem=oauth-baslat` isteği JSON
  standardında unauthenticated hata döndürdü. Vendor dışı 27 PHP dosyasının lint kontrolü başarılıdır.
- **Hata/engel:** Gerçek Google authorization code callback'i ve refresh token alışverişi,
  Google Cloud'da aktif kullanıcı onayı/redirect ayarı olmadan çalıştırılamaz. Kullanılan ve
  Google Cloud OAuth client üzerinde birebir Authorized redirect URI olarak tanımlanması gereken
  URI şudur: `http://localhost/ads_oauth/api/index.php?islem=oauth-donus`. Mevcut çalışma
  ortamında gerçek Google authorization code bulunmadığı için gerçek `baglanmis_hesaplar` kaydı ve
  veritabanındaki gerçek ciphertext doğrulanamadı; PDO bağlantısı başarılıdır. Google Cloud ayarı
  bu çalışma kapsamında değiştirilmedi.
- **Sonraki adım:** Google Cloud Authorized redirect URI'yi yukarıdaki değerle doğrula, giriş
  yapılmış local session ile gerçek OAuth akışını tamamla, DB'deki `refresh_token_sifreli`
  değerini plaintext olmayan ciphertext olarak doğrula; ardından bağlayıcı ve servis katmanına
  geç.

- **PROMPT-07 durumu: Tamamlandı.** Minimum site sahibi kayıt/giriş altyapısı oluşturuldu.
  `site_sahipleri` tablosu kullanıldı; yeni tablo veya şema değişikliği yapılmadı. E-posta
  normalize/doğrulama, duplicate e-posta mesajı, `password_hash()` / `password_verify()`,
  mevcut session fonksiyonları, login sonrası `session_regenerate_id(true)`, logout ve
  oturum kontrolü eklendi. Kayıt ve giriş API action'ları `api/index.php` üzerinden JSON
  standardında çalışıyor; kök `index.php` ve mevcut tema dosyaları gerçek browser form akışını
  sağlıyor.
- **PROMPT-07 değişen dosyalar:** `index.php`, `api/index.php`,
  `php/servis/kullanici-servisi.php`, `tema/giris.php`, `tema/panel/anasayfa.php`,
  `tema/layout/header.php`, `tema/layout/footer.php`, `md/DURUM.md`.
- **PROMPT-07 test sonucu:** PHP syntax/lint, Composer autoload, PDO veritabanı bağlantısı,
  kayıt, normalize edilmiş e-posta, plaintext olmayan password hash, başarılı login,
  başarısız login için genel hata, duplicate e-posta, login sonrası session ID yenileme,
  `sahip_no` session kontrolü, logout, logout sonrası session temizliği ve login olmadan
  Google OAuth başlangıcının reddedilmesi doğrulandı. Giriş yapılmış HTTP session ile OAuth
  başlangıcının authorization URL üretmesi ve sabit redirect URI'yi kullanması da doğrulandı.
- **PROMPT-07 başarısız test:** Yok. Geçici test kullanıcısı ve test dosyaları temizlendi;
  şifre, hash veya credential bu dosyaya yazılmadı.
- **PROMPT-07 sonraki adım:** Gerçek login olmuş browser session ile Google consent ekranını
  tamamlamak, callback ve şifreli `baglanmis_hesaplar.refresh_token_sifreli` kaydını doğrulamak.

## 3. Bekleyen / Henüz Yapılmayanlar

- [ ] Google Ads API Developer Token başvurusu (Kort tarafında yapılacak).
- [ ] Google OAuth anahtarlarının (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) alınması.
- [ ] Meta Marketing API App Review süreci (Kort tarafında yapılacak).
- [ ] Meta OAuth anahtarlarının (`META_APP_ID`, `META_APP_SECRET`) alınması.
- [x] Google OAuth akışı (`php/oauth/google-oauth.php`) ve `api/index.php` dispatch'i tamamlandı;
      Meta OAuth hâlâ yazılmadı.
- [ ] Baglayici (adapter) katmanı prompt dosyalarının yazılması.
- [ ] Servis katmanı prompt dosyalarının yazılması.
- [ ] Cron/senkron mekanizmasının prompt dosyasının yazılması.
- [ ] Panel/tema dosyalarının prompt dosyalarının yazılması.

## 4. Karar Günlüğü (Önemli Mimari Kararlar ve Gerekçeleri)

| Tarih | Karar | Gerekçe |
|---|---|---|
| 2026-09-03 | Ajans modeli değil, SaaS/OAuth modeli seçildi | Hukuki/mali yük istenmiyor, kullanıcı kendi hesabını bağlar |
| 2026-09-03 | Websistem modülü değil, bağımsız sistem | Hedef kitle CMS'ten bağımsız, composer/cron/güvenlik izolasyonu gerekiyor |
| 2026-09-03 | Türkçe eylem-bazlı dosya isimlendirmesi korundu | Kort'un mevcut proje konvansiyonuyla tutarlılık |
| 2026-09-03 | Claude kodu doğrudan düzenlemez, düzeltme promptu yazar | Kod stili tutarlılığı, tek AI üzerinden kod yazımı korunur |
| 2026-09-04 | api/ JSON formatı `{return, mesaj}` olarak belirlendi | Websistem `php/class_f/` ve `api/` koduna bakılarak netleştirildi (Kort'un alışık olduğu format) |
| 2026-09-04 | `php/` altındaki klasör ve dosya adları küçük harfe çevrildi | Dosya yolu tutarlılığı ve platformlar arası adlandırma uyumu |
| 2026-09-04 | Kendi sınıfları için Composer PSR-4 autoload kullanılmamasına karar verildi | Manuel `require_once` stratejisi kullanılacak |
| 2026-09-04 | PROMPT 02 ile `php/` altındaki tüm adlandırmaların küçük harfli son hali doğrulandı | PROMPT 01'in devamı/düzeltmesi; ek yeniden adlandırma gerekmedi |
| 2026-09-04 | Nihai hedef çoklu platform olsa da geliştirme sıralı ilerleyecek; önce Google Ads altyapısı tamamlanacak | Meta daha sonra eklenecek, bu aşamada yalnızca `google` platform değeri kullanılacak |
| 2026-09-04 | DB engine olarak InnoDB seçildi | Foreign key desteği ve şifreli token'ların transactional bütünlüğü gerekiyor |
| 2026-09-04 | Gerçek `.env` dosyası DB bilgileriyle oluşturuldu | Yerel veritabanı bağlantı ayarları hazırlandı; hassas değerler repora yazılmayacak |
| 2026-09-04 | Google ve Meta anahtarları henüz alınmadı; Google Ads PHP client olarak `googleads/google-ads-php:^34.0` seçildi ve `google/apiclient` kaldırıldı | Google Ads API entegrasyonu için resmi PHP client kullanılacak; OAuth ve gerçek API bağlantısı sonraki aşamaya bırakıldı |

## 5. Açık Sorular / Netleşmemiş Noktalar

- Kampanya sihirbazında kaç adım/soru olacak henüz detaylandırılmadı.
- Senkron cron'unun çalışma sıklığı (15dk/30dk/saatlik) henüz kararlaştırılmadı.

## 6. Prompt Dosyaları İzleme Tablosu

| # | Dosya | Durum | Not |
|---|---|---|---|
| 00 | `md/00-iskelet-kurulum.md` | Tamamlandı | Sadece klasör/dosya + docblock oluşturuldu, mantık eklenmedi |
| 01 | `md/01-isimlendirme-duzeltme.md` | Tamamlandı | `php/` altındaki adlar küçültüldü, Composer PSR-4 `autoload` bloğu kaldırıldı |
| 02 | PROMPT 01 devamı/düzeltmesi | Tamamlandı | `php/` altındaki tüm klasör ve dosya adlarının küçük harfli son hali doğrulandı; ek yeniden adlandırma gerekmedi |
| 03 | `md/03-temel-altyapi.md` | Tamamlandı | İlk SQL şeması ve Google öncelikli temel altyapı fonksiyonları eklendi |
| 04 | PROMPT-04 — Composer / Vendor Altyapısının Kurulması | Tamamlandı | İlk Composer/vendor altyapısı `google/apiclient` ile kuruldu; OAuth akışı henüz yazılmadı |
| 05 | PROMPT-05 — Google Ads PHP Client geçişi | Tamamlandı | `google/apiclient` kaldırıldı; `googleads/google-ads-php:^34.0` eklendi, `composer.lock`/`vendor/` güncellendi ve `GoogleAdsClient` autoload doğrulandı |
| 07 | PROMPT-07 — Minimum Site Kullanıcısı Login/Register | Tamamlandı | `site_sahipleri` üzerinde register/login/logout, session fixation önleme, password hashing ve browser test akışı eklendi; Google OAuth yeniden yazılmadı |

*(Her yeni prompt dosyası oluşturulduğunda bu tabloya satır eklenir: numara, dosya adı, durum
[Bekliyor / AI'ye verildi / Tamamlandı / Revizyon gerekli], kısa not.)*
