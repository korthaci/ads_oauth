# PROMPT 00 — İskelet Kurulum (Sadece Klasör/Dosya Yapısı)

> Bu görevde MANTIK YAZMAYACAKSIN. Sadece aşağıda listelenen klasör ve dosyaları,
> belirtilen içerikle birebir oluştur. Yorum yapma, ekleme yapma, "daha iyi olur" diye
> başka dosya/klasör önerme. Sadece aşağıdaki listeyi uygula.

## Proje Bilgisi
- Proje adı: `ads`
- Proje kök yolu: `c:/server/htdocs/ads/`
- Bu kök yol altında aşağıdaki yapıyı oluştur.

## Yapılacaklar (sırayla)

### 1. Kök dizin dosyaları
Şu 3 dosyayı kök dizinde oluştur:

**`index.php`**
```php
<?php
/**
 * ads - Ana giriş noktası
 * Görev: Oturum kontrolü yapar, giriş yapılmamışsa tema/giris.php'ye,
 * yapılmışsa tema/panel/anasayfa.php'ye yönlendirir.
 * DURUM: İskelet - henüz mantık yazılmadı.
 */
```

**`composer.json`**
```json
{
    "name": "kort/ads",
    "description": "Reklam kurulumunu basitletiren bagimsiz sistem",
    "type": "project",
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Ads\\": "php/"
        }
    }
}
```
*(Not: `googleads/google-ads-php` ve `facebook/php-business-sdk` paketleri daha sonraki
bir promptta eklenecek, şimdi ekleme.)*

**`.env.sample`**
```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_DEVELOPER_TOKEN=
META_APP_ID=
META_APP_SECRET=
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=
SIFRELEME_ANAHTARI=
```

### 2. `api/` klasörü
Şu boş dosyaları oluştur, her birine SADECE başta belirtilen docblock yorumunu yaz,
başka hiçbir kod yazma:

- `api/index.php` → yorum: "Genel router. Görev: Gelen istekleri ilgili api/ dosyasına yönlendirir. DURUM: İskelet."
- `api/oauth-baslat.php` → yorum: "Google/Meta OAuth akışını başlatır (redirect). DURUM: İskelet."
- `api/oauth-donus.php` → yorum: "OAuth callback, kodu token ile değiştirir. DURUM: İskelet."
- `api/hesap-sil.php` → yorum: "Bağlı hesabın bağlantısını kaldırır, JSON döner. DURUM: İskelet."
- `api/kampanya-olustur.php` → yorum: "Sihirbazdan gelen veriyle kampanya kurar, JSON döner. DURUM: İskelet."
- `api/kampanya-listele.php` → yorum: "Kullanıcının kampanyalarını/durumunu döner, JSON. DURUM: İskelet."
- `api/kampanya-durdur.php` → yorum: "Kampanyayı duraklat/devam ettir, JSON döner. DURUM: İskelet."
- `api/senkron-tetikle.php` → yorum: "Manuel senkron tetikleme (test/debug amaçlı). DURUM: İskelet."

### 3. `php/` klasörü ve alt klasörleri
Şu klasörleri oluştur: `php/Oauth/`, `php/Baglayici/`, `php/Servis/`, `php/Cron/`

Şu boş dosyaları oluştur, her birine sadece docblock yorumu yaz:

- `php/Config.php` → yorum: "Ortam degiskenlerini (.env) okur, sabitleri tanimlar. DURUM: Iskelet."
- `php/Veritabani.php` → yorum: "PDO baglanti sarmalayicisi. DURUM: Iskelet."
- `php/Oturum.php` → yorum: "Site sahibi auth/session yonetimi. DURUM: Iskelet."
- `php/Sifreleme.php` → yorum: "Token sifreleme/cozme islemleri. DURUM: Iskelet."
- `php/Oauth/GoogleOauth.php` → yorum: "Google OAuth akis mantigi. DURUM: Iskelet."
- `php/Oauth/MetaOauth.php` → yorum: "Meta OAuth akis mantigi. DURUM: Iskelet."
- `php/Baglayici/GoogleAdsBaglayici.php` → yorum: "Google Ads API cagrilarini sarmalar. DURUM: Iskelet."
- `php/Baglayici/MetaAdsBaglayici.php` → yorum: "Meta Marketing API cagrilarini sarmalar. DURUM: Iskelet."
- `php/Servis/KampanyaServisi.php` → yorum: "Sihirbaz girdisinden kampanya yapisi kurar. DURUM: Iskelet."
- `php/Servis/SenkronServisi.php` → yorum: "Durum/rapor verisini cekip DB'ye yazar. DURUM: Iskelet."
- `php/Servis/HesapServisi.php` → yorum: "Hesap baglama/baglanti kesme mantigi. DURUM: Iskelet."
- `php/Cron/senkron-calistir.php` → yorum: "Crontab'in cagiracagi giris scripti. DURUM: Iskelet."

### 4. `tema/` klasörü
Şu klasörleri oluştur: `tema/layout/`, `tema/panel/`

Şu boş dosyaları oluştur, sadece docblock/HTML yorumu yaz:

- `tema/layout/header.php` → yorum: "Ortak sayfa basligi. DURUM: Iskelet."
- `tema/layout/footer.php` → yorum: "Ortak sayfa altligi. DURUM: Iskelet."
- `tema/panel/anasayfa.php` → yorum: "Giris sonrasi genel durum ekrani. DURUM: Iskelet."
- `tema/panel/hesap-baglan.php` → yorum: "Hesap baglama butonu/ekrani. DURUM: Iskelet."
- `tema/panel/kampanya-sihirbazi.php` → yorum: "Basit kurulum formu. DURUM: Iskelet."
- `tema/giris.php` → yorum: "Site sahibi login ekrani. DURUM: Iskelet."

### 5. `assets/` klasörü
Şu boş klasörleri oluştur (içi boş kalabilir, dosya oluşturmana gerek yok):
`assets/js/`, `assets/css/`, `assets/img/`, `assets/font/`

### 6. `db/` klasörü
Şu klasörü oluştur: `db/` (içine şimdilik dosya koyma, boş kalsın — SQL şeması ayrı bir promptta gelecek)

## Bitince
Sadece şunu raporla: hangi dosya/klasörleri oluşturduğunu liste halinde yaz.
Başka hiçbir açıklama, öneri veya kod ekleme yapma.
