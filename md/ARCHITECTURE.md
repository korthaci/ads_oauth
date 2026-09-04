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
│   ├── Config.php
│   ├── Veritabani.php
│   ├── Oturum.php
│   ├── Sifreleme.php
│   │
│   ├── Oauth/
│   │   ├── GoogleOauth.php
│   │   └── MetaOauth.php
│   │
│   ├── Baglayici/
│   │   ├── GoogleAdsBaglayici.php
│   │   └── MetaAdsBaglayici.php
│   │
│   ├── Servis/
│   │   ├── KampanyaServisi.php
│   │   ├── SenkronServisi.php
│   │   └── HesapServisi.php
│   │
│   └── Cron/
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

- Dosya/fonksiyon isimlendirmesi: Türkçe, eylem bazlı (`kampanya-olustur.php`, `hesap-sil.php`).
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

## 8. Değişiklik Notu

Bu dosyada mimari bir değişiklik yapılırsa, `DURUM.md` içindeki "Karar Günlüğü" bölümüne
tarih ve gerekçeyle birlikte not düşülür.
