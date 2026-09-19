# PROMPT-20.4 — Zorunlu `contains_eu_political_advertising` Alanının Eklenmesi

## 0. Bağlam

Gerçek mutate testinde ("Türkiye" konumu, tek eşleşme — PROMPT-20.3'ün konum
belirsizliği düzeltmesi doğru çalıştı, mutate'e ulaşıldı) yeni bir hata alındı:

```json
"errorCode": {"fieldError": "REQUIRED"},
"message": "The required field was not present.",
"location": {..."campaign_operation"."create"."contains_eu_political_advertising"}
```

Google, 3 Eylül 2025'ten itibaren Google Ads API üzerinden oluşturulan **her yeni
kampanyanın**, AB Siyasi Reklam Şeffaflık Yönetmeliği (TTPA) gereği,
`contains_eu_political_advertising` alanını (`EuPoliticalAdvertisingStatus` enum'u)
açıkça beyan etmesini zorunlu kıldı. Bu alan boş bırakılırsa `FieldError.REQUIRED`
hatası döner — tam olarak alınan hata bu.

Bu proje (Türkiye pazarına yönelik, siyasi olmayan reklamlar) için doğru değer:
**`DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING`**.

## 1. Görev

1. `php/baglayici/google-ads-baglayici.php` içinde gerekli `use` ifadesini ekle:
   `Google\Ads\GoogleAds\V{gerçek-versiyon}\Enums\EuPoliticalAdvertisingStatusEnum\EuPoliticalAdvertisingStatus`
   (gerçek namespace'i `vendor/` içinden PROMPT-20.1'deki gibi doğrula — tahmin etme;
   enum'un tam olarak hangi alt-namespace'te olduğu SDK versiyonuna göre değişebilir).
2. `google_ads_kampanya_olustur()` içindeki `Campaign` oluşturma nesnesine ekle:
   ```php
   ->setContainsEuPoliticalAdvertising(
       EuPoliticalAdvertisingStatus::DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING
   )
   ```
3. Bu değerin **sabit/hardcoded** olduğunu ve kullanıcıya arayüzde hiç sorulmadığını
   (ARCHITECTURE.md §1.1 ilkesiyle tutarlı — bu proje kapsamında siyasi reklam
   desteklenmiyor) DURUM.md'de açıkça belirt.

## 2. Kesin Yasaklar

- Yalnızca bu tek alan eklenir. Mutate akışının başka hiçbir kısmına dokunulmaz.
- Kullanıcıya bu alanla ilgili herhangi bir seçenek/soru eklenmez.
- Gerçek mutate çağrısı bu promptta test amacıyla bile yapılmaz; nihai doğrulama
  Kort'un canlı testiyle yapılacak.

## 3. Doğrulama

- `php -l` çalıştırılır.
- Enum sınıfının ve `DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING` sabitinin gerçekten
  `vendor/` içinde var olduğu doğrulanır (`class_exists`/sabit kontrolü ile, PROMPT-20.1
  desenindeki gibi).
- **Bu promptun gerçek başarı kriteri**: Kort'un canlı ortamda "Türkiye" ile kampanya
  sihirbazını tekrar denemesi ve bu kez hiçbir `REQUIRED`/`RESOURCE_NOT_FOUND` hatası
  almadan gerçek, `PAUSED` durumda bir kampanya oluşturmasıdır. DURUM.md'de bu net
  şekilde hatırlatılır.
- Eğer bu alan eklendikten sonra **başka bir eksik zorunlu alan** hatası çıkarsa (Google
  API'nin başka yeni zorunlu alanları da olabilir), bunun da aynı şekilde (tahmin
  etmeden, gerçek hata mesajından) teşhis edilip ayrı bir düzeltme promptuna konu
  olacağı DURUM.md'de not edilir — bu promptun kapsamı yalnızca bilinen bu tek alandır.

## 4. DURUM.md

Kısa not: hangi alanın eklendiği, neden (AB düzenlemesi), sabit değerin
`DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING` olarak seçilme gerekçesi, Kort için canlı
test hatırlatması.
