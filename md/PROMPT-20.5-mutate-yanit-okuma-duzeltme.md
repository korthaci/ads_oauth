# PROMPT-20.5 — Mutate Yanıtı Okuma Hatası: `getResults()` → `getMutateOperationResponses()`

## 0. Bağlam

PROMPT-20.4'ten sonra "Türkiye" ile gerçek testte **artık `REQUIRED`/`RESOURCE_NOT_FOUND`
hatası alınmadı** — istek Google'a ulaştı. Ancak yeni bir hata çıktı:

```
Call to undefined method Google\Ads\GoogleAds\V25\Services\MutateGoogleAdsResponse::getResults()
```

Bu hata **mutate isteği gönderildikten sonra**, yanıtı işlerken oluşuyor. Yani **gerçek
bir kampanya Google Ads hesabında oluşmuş olabilir** — sistemin kendisi bunu doğrulayıp
kullanıcıya göstermeden önce çöktü.

Google'ın resmi SDK örnek kodları (Java/C#/PHP, heterojen `mutate_operations` ile
gönderilen istekler için), yanıtı `getResults()` ile değil
**`getMutateOperationResponses()`** ile okuyor; her eleman bir `MutateOperationResponse`
olup, tipine göre `->getCampaignBudgetResult()`, `->getCampaignResult()`,
`->getCampaignCriterionResult()`, `->getAdGroupResult()`, `->getAdGroupCriterionResult()`,
`->getAdGroupAdResult()` gibi getter'lar sunar.

## 1. Görev A — ÖNCELİKLİ: Yarım Kalmış Kampanya Kontrolü

Kod düzeltmesine geçmeden önce, gerçek hesapta (`4150407743`) bu başarısız denemenin
**gerçekten bir kampanya oluşturup oluşturmadığını** salt-okunur bir sorguyla kontrol et
(PROMPT-16'daki `kampanya-listele` mantığı kullanılabilir, veya doğrudan
`GoogleAdsService.search` ile son 1 saat içinde oluşturulmuş `PAUSED` kampanyaları
sorgula). Bulgularını DURUM.md'ye gerçek verilerle (kampanya adı/ID/oluşturulma zamanı
varsa) yaz. Kort'a da ayrıca, hesapta beklenmedik bir "Türkiye"/test kampanyası olup
olmadığını ads.google.com arayüzünden görsel olarak teyit etmesini net şekilde hatırlat.

## 2. Görev B — Kod Düzeltmesi

1. `google_ads_kampanya_olustur()` içinde:
   ```php
   $sonuclar = $yanit->getResults();
   ```
   satırını:
   ```php
   $sonuclar = $yanit->getMutateOperationResponses();
   ```
   olarak düzelt.
2. Bu değişiklik sonrası, `$sonuclar[1]->getCampaignResult()` çağrısının hâlâ doğru
   çalıştığını doğrula — `MutateOperationResponse` sınıfının gerçekten
   `getCampaignResult()` metoduna sahip olduğunu **`vendor/` içinden** teyit et (tahmin
   etme; PROMPT-20.1/20.4 desenindeki gibi).
3. Kodun geri kalanı (operation sırası, index varsayımı `$sonuclar[1]` = kampanya
   operation'ı — bu, operation dizisindeki sıraya göre doğru olmalı, kontrol et)
   değiştirilmez, yalnızca gerekiyorsa bu index varsayımı da vendor'dan doğrulanan
   gerçek response yapısına göre teyit edilir.

## 3. Kesin Yasaklar

- Bu yalnızca yanıt okuma (response parsing) düzeltmesidir. Mutate isteğinin
  oluşturulma kısmına (operation'lar, alanlar, PAUSED durumu, EU political advertising
  alanı) dokunulmaz.
- Gerçek bir mutate çağrısı bu promptta test amacıyla **yapılmaz** — ama Görev A'daki
  salt-okunur kontrol zorunludur.

## 4. Doğrulama

- `php -l` çalıştırılır.
- `MutateOperationResponse` ve ilgili getter'ların gerçekten var olduğu vendor'dan
  doğrulanır.
- **Bu promptun gerçek başarı kriteri**: Kort'un canlı ortamda kampanya sihirbazını
  tekrar denemesi ve bu kez hem mutate isteğinin hem de yanıt okumasının başarılı
  olması — kullanıcıya gerçek kampanya ID'si ve "PAUSED" durumu net şekilde
  gösterilmesi. DURUM.md'de bu net şekilde hatırlatılır.
- Görev A'da bir yarım kalmış/yetim kampanya bulunursa, bunun ne olduğu (PAUSED
  olduğu için zararsız olsa da) DURUM.md'ye kayıt düşülür ve Kort'a bildirilir.

## 5. DURUM.md

Görev A'nın sonucu (yetim kampanya var mı yok mu) ve Görev B'deki değişiklik kısaca
not edilir. Ayrı TODO listesi gerekmiyor.
