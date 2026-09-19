# PROMPT-20.1 — `LocationNames` Namespace Hatası Düzeltmesi

## 0. Bağlam

Kampanya sihirbazı gerçek testinde hata:

```
Class "Google\Ads\GoogleAds\V25\Services\LocationNames" not found
```

`GeoTargetConstantService::suggestGeoTargetConstants()` çağrısı, `google-ads-php` SDK'sında
`LocationNames`'i **`SuggestGeoTargetConstantsRequest` sınıfının içine iç içe (nested)**
tanımlanmış bir sınıf olarak bekler — yani doğru namespace:

```
Google\Ads\GoogleAds\V{yuklu-versiyon}\Services\SuggestGeoTargetConstantsRequest\LocationNames
```

`Services\LocationNames` (üst seviyede, `SuggestGeoTargetConstantsRequest\` segmenti
olmadan) diye bir sınıf mevcut değil — bu yüzden "Class not found" hatası alınıyor.

## 1. Görev

1. `composer.json` / `composer.lock` içinden `googleads/google-ads-php` paketinin
   gerçekte yüklü olan sürümünü (dolayısıyla API versiyonunu, örn. `V23`, `V24`) tespit
   et — kod içindeki `V25` varsayımının doğru olup olmadığını **tahmin etmeden**
   `vendor/googleads/google-ads-php` altındaki gerçek dizin/namespace yapısından
   doğrula.
2. `php/baglayici/google-ads-baglayici.php` içindeki hatalı `use` ifadesini
   (`Google\Ads\GoogleAds\V25\Services\LocationNames` veya benzeri) doğru, nested
   namespace'e düzelt:
   `Google\Ads\GoogleAds\V{gerçek-versiyon}\Services\SuggestGeoTargetConstantsRequest\LocationNames`
3. Dosyada başka yerlerde de (varsa) yanlış/tutarsız API versiyon numarası kullanılan
   `use` ifadeleri olup olmadığını kontrol et — hepsi gerçekte yüklü SDK versiyonuyla
   tutarlı olmalı.

## 2. Kesin Yasaklar

- Bu yalnızca bir **namespace/import düzeltmesidir**. `google_ads_konum_onerilerini_al()`
  fonksiyonunun mantığı (tam isim eşleşmesi zorunluluğu, sessiz best-match yok vb.)
  değiştirilmez.
- Kampanya oluşturma mantığının geri kalanına (bütçe/kampanya/kriter/reklam grubu/
  anahtar kelime/reklam mutate adımları) dokunulmaz.
- Gerçek bir mutate çağrısı bu düzeltmeyi doğrulamak için **kullanılmaz** — yalnızca
  salt-okunur `suggestGeoTargetConstants` çağrısı (örn. "Ankara" için) test edilir.

## 3. Doğrulama

- Gerçek (yerel veya production, oturumlu) bir istekle `google_ads_konum_onerilerini_al('Ankara')`
  benzeri bir çağrının artık "Class not found" hatası vermediği, gerçek bir
  `geoTargetConstant` sonucu döndürdüğü doğrulanır.
- `php -l` çalıştırılır.
- Bu düzeltme sonrası **kampanya oluşturma akışının tamamı** (Kort tarafından, düşük
  bütçeli gerçek bir test kampanyasıyla) yeniden denenmeli — bunu DURUM.md'de Kort'a
  net şekilde hatırlat.

## 4. DURUM.md

Kısa not: hangi namespace'in yanlış olduğu, gerçek SDK versiyonunun ne olduğu, hangi
satırın düzeltildiği. Ayrı TODO listesi gerekmiyor (tek satırlık düzeltme).
