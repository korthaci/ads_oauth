# PROMPT-20.2 — `suggestGeoTargetConstants` Sonuçlarının Hiç Okunmaması

## 0. Bağlam

Namespace düzeltmesinden (PROMPT-20.1) sonra `Class not found` hatası gitti, ama gerçek
testte "Ankara" için bile `"Girilen konum bulunamadı; farklı yazmayı deneyin."` hatası
alındı — yani `$oneriler` dizisi boş kaldı.

`php/baglayici/google-ads-baglayici.php`, `google_ads_konum_onerilerini_al()` içinde
(satır ~820-828):

```php
$yanit = $client->getGeoTargetConstantServiceClient()
    ->suggestGeoTargetConstants($istek);

$oneriler = [];
...
foreach ($yanit as $oneri) {
```

Google'ın resmi `google-ads-php` SDK örnek kodları (`GetGeoTargetConstantsByNames.php`)
şunu gösteriyor:

```php
foreach ($response->getGeoTargetConstantSuggestions() as $geoTargetConstantSuggestion) {
```

Yani döngü, response nesnesinin **kendisi** üzerinde değil,
`->getGeoTargetConstantSuggestions()` getter'ının döndürdüğü liste üzerinde kurulmalı.
Mevcut kod `foreach ($yanit as $oneri)` yazdığı için (getter çağrılmadan doğrudan
response nesnesi üzerinde döngü), muhtemelen hiç eleman dönmüyor ve API'den gerçek
sonuç gelse bile hiç okunmuyor — "Ankara" gibi kesinlikle var olan bir konumun bile
bulunamamasının sebebi büyük ihtimalle bu.

## 1. Görev

1. `google_ads_konum_onerilerini_al()` içindeki `foreach ($yanit as $oneri)` satırını
   `foreach ($yanit->getGeoTargetConstantSuggestions() as $oneri)` olarak düzelt.
2. Bunu değiştirmeden önce, gerçek `google-ads-php` SDK'sının (yüklü versiyon,
   PROMPT-20.1'de belirlenmişti) `SuggestGeoTargetConstantsResponse` sınıfını
   (`vendor/googleads/google-ads-php/...` altında) açıp getter adının gerçekten
   `getGeoTargetConstantSuggestions()` olduğunu **doğrula** — tahmin etme.
3. Fonksiyonun geri kalanı (tam eşleşme mantığı, `$tarama_limiti`, hata mesajları)
   **değiştirilmez** — sadece döngünün kaynağı düzeltilir.

## 2. Kesin Yasaklar

- Yalnızca bu tek satırlık (ve gerekiyorsa `use` ekleme) düzeltme yapılır.
- Kampanya oluşturmanın geri kalan mutate adımlarına dokunulmaz.
- Gerçek mutate çağrısı bu promptta test amacıyla bile yapılmaz.

## 3. Doğrulama

- Gerçek, oturumlu bir istekle `google_ads_konum_onerilerini_al(..., 'Ankara')`
  çağrısının artık gerçek bir `geoTargetConstant` sonucu (`resource_name`, `name`)
  döndürdüğü doğrulanır — bu kez "bulunamadı" hatası **alınmamalı**.
- `php -l` çalıştırılır.
- Bu düzeltme sonrası, kampanya oluşturma akışının **tamamı** (Kort tarafından, düşük
  bütçeli gerçek test) yeniden denenmeli — DURUM.md'de net şekilde hatırlat.

## 4. DURUM.md

Kısa not: hangi satırın, neden düzeltildiği (getter çağrılmıyordu), gerçek SDK
kaynağından doğrulanan getter adı. Ayrı TODO listesi gerekmiyor (tek satırlık düzeltme).
