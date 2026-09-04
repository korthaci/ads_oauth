# PROMPT 01 — İsimlendirme Düzeltmesi (Küçük Harf) + Autoload Stratejisi

> Bu görevde de MANTIK YAZMAYACAKSIN. Sadece yeniden adlandırma ve composer.json düzeltmesi
> yapacaksın. Henüz hiçbir dosyada gerçek kod olmadığı için (hepsi iskelet/docblock durumunda)
> bu işlem risksiz olmalı.

## 1. Klasör Yeniden Adlandırma (php/ altında)

| Eski | Yeni |
|---|---|
| `php/Oauth/` | `php/oauth/` |
| `php/Baglayici/` | `php/baglayici/` |
| `php/Servis/` | `php/servis/` |
| `php/Cron/` | `php/cron/` (zaten küçüktü, değişiklik yok) |

## 2. Dosya Yeniden Adlandırma

| Eski | Yeni |
|---|---|
| `php/Config.php` | `php/config.php` |
| `php/Veritabani.php` | `php/veritabani.php` |
| `php/Oturum.php` | `php/oturum.php` |
| `php/Sifreleme.php` | `php/sifreleme.php` |
| `php/oauth/GoogleOauth.php` | `php/oauth/google-oauth.php` |
| `php/oauth/MetaOauth.php` | `php/oauth/meta-oauth.php` |
| `php/baglayici/GoogleAdsBaglayici.php` | `php/baglayici/google-ads-baglayici.php` |
| `php/baglayici/MetaAdsBaglayici.php` | `php/baglayici/meta-ads-baglayici.php` |
| `php/servis/KampanyaServisi.php` | `php/servis/kampanya-servisi.php` |
| `php/servis/SenkronServisi.php` | `php/servis/senkron-servisi.php` |
| `php/servis/HesapServisi.php` | `php/servis/hesap-servisi.php` |
| `php/cron/senkron-calistir.php` | değişiklik yok |

Her dosyanın içeriği (şu anki docblock yorumu) aynı kalsın, sadece dosya/klasör adı değişsin.

## 3. composer.json Düzeltmesi

Mevcut `composer.json` içindeki şu PSR-4 autoload bloğunu:

```json
"autoload": {
    "psr-4": {
        "Ads\\": "php/"
    }
}
```

**tamamen kaldır.** Kendi class'larımız için Composer autoload kullanmıyoruz — bunun yerine
manuel `require_once` ile çalışacağız (bu, bir sonraki promptta `php/config.php` içine
eklenecek, bu promptta sadece composer.json'dan bu bloğu kaldırman yeterli).

`require` bloğu (`"php": ">=8.1"`) olduğu gibi kalsın.

## 4. Bitince

Sadece şunu raporla: hangi dosya/klasörleri yeniden adlandırdığını ve composer.json'da
neyi kaldırdığını liste halinde yaz. Başka açıklama/öneri ekleme.
