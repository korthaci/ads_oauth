# PROMPT-20.3 — Mutate `RESOURCE_NOT_FOUND` Düzeltmesi ve Konum Belirsizliği Mesajının İyileştirilmesi

## 0. Bağlam

Gerçek testte iki ayrı bulgu:

**(A) Asıl bug — "Türkiye" konumuyla gerçek mutate denemesi:**

```json
"errorCode": {"mutateError": "RESOURCE_NOT_FOUND"},
"message": "Resource was not found.",
"trigger": {"int64Value": "-1"},
"location": {..."campaign_operation"."create"."campaign_budget"}
```

ve benzer hatalar `campaign_criterion_operation.create.campaign` (`-2` referansı) ve
`ad_group_operation` için de tekrarlanıyor.

Kök neden, `google_ads_kampanya_olustur()` (`php/baglayici/google-ads-baglayici.php`)
içinde **geçici (temp) kaynakları oluşturan operation'lara `resource_name` hiç
atanmamış olması**. Kod şu an:

```php
(new CampaignBudget())
    ->setAmountMicros(...)
    ->setDeliveryMethod(...)
    ->setExplicitlyShared(false)
// setResourceName(...) YOK
```

ve sonraki adımda:

```php
->setCampaignBudget($on_ek . '/campaignBudgets/-1')
```

diyerek `-1` temp ID'sini **referans veriyor**, ama bunu **tanımlayan** hiçbir yerde
`->setResourceName($on_ek . '/campaignBudgets/-1')` çağrısı yok. Google Ads API'de bir
`temp_id` (negatif ID) referans verilebilmesi için, onu oluşturan `create` operation'ının
ilgili kaynağına **açıkça** o `resource_name`'in atanmış olması gerekir. Aynı eksiklik
`Campaign` (`-2`) ve `AdGroup` (`-3`) için de var.

**(B) İkincil iyileştirme — "Ankara"/"İstanbul" belirsizlik hatası bilgi vermiyor:**

```
"Aynı adlı birden fazla Google Ads konumu bulundu; kampanya için konumu daha belirgin yazın."
```

Bu mesaj hiçbir aday listelemiyor, kullanıcı ne yazması gerektiğini bilemiyor. Google'ın
`GeoTargetConstant` nesnesinde `getTargetType()` ve `getCanonicalName()` alanları mevcut
(örn. `canonicalName` "Ankara,Ankara,Turkey" gibi daha ayırt edici bir değer içerir) —
bu bilgi zaten `suggestGeoTargetConstants` yanıtında geliyor, sadece kullanılmıyor.

## 1. Görev A — Temp Resource Name Düzeltmesi (asıl bug)

`google_ads_kampanya_olustur()` içinde:

1. Bütçe oluşturma nesnesine ekle:
   `->setResourceName($on_ek . '/campaignBudgets/-1')`
2. Kampanya oluşturma nesnesine ekle:
   `->setResourceName($on_ek . '/campaigns/-2')`
3. Reklam grubu oluşturma nesnesine ekle:
   `->setResourceName($on_ek . '/adGroups/-3')`

Bu üç satır dışında, her operation'ın referans verdiği temp ID'ler (`-1`, `-2`, `-3`)
zaten doğru sayılarla eşleşiyor — sadece tanımlama eksikti, referanslar değil. Başka bir
şey değiştirilmez.

## 2. Görev B — Konum Belirsizliği Mesajının İyileştirilmesi

1. `google_ads_konum_onerilerini_al()` içinde, `$oneriler`/`$tam_eslesenler` dizilerine
   `target_type` (`$sabit->getTargetType()`) ve mümkünse `canonical_name`
   (`$sabit->getCanonicalName()`) alanlarını da ekle (gerçek SDK'da bu getter'ların var
   olduğunu `vendor/` içinden doğrula — tahmin etme).
2. `count($tam_eslesenler) > 1` durumunda fırlatılan hataya, adayları listeleyen bir
   mesaj ekle, örn:
   `"Aynı adlı birden fazla Google Ads konumu bulundu: Ankara (Il), Ankara (İlçe). Lütfen
   'canonical_name' değerlerinden birine göre daha belirgin yazın (örn. tam il/ilçe adı)."`
   Tam `canonical_name` değerlerini örnek olarak göster (en fazla 5 tanesini), böylece
   kullanıcı hangi ayrımı yapması gerektiğini gerçekten görebilir.
3. Bu değişiklik yalnızca **hata mesajının içeriğini** zenginleştirir; hangi durumda
   hata fırlatıldığı (tam eşleşme sayısına göre karar) mantığı **değiştirilmez**.

## 3. Kesin Yasaklar

- Görev A dışında mutate akışının başka hiçbir kısmına dokunulmaz (bütçe/kampanya/kriter/
  reklam grubu/anahtar kelime/reklam sırası, alanları, `PAUSED` durumu aynı kalır).
- Konum eşleştirme mantığında (tam eşleşme zorunluluğu, sessiz best-match yasağı)
  herhangi bir gevşetme yapılmaz — yalnızca hata mesajı daha bilgilendirici olur.
- Gerçek mutate çağrısı bu promptta test amacıyla bile yapılmaz; ancak Görev A'nın
  doğrulanması için **gerçek bir mutate testi zorunludur** (bkz. §4) — bu testi Kort
  düşük bütçeli bir kampanyayla yapacak.

## 4. Doğrulama

- `php -l` çalıştırılır.
- Görev A: Kod incelemesiyle, her `-1`/`-2`/`-3` referansının artık karşılık gelen
  `create` operation'ında `setResourceName` ile tanımlandığı teyit edilir.
- Görev B: Sentetik/mock bir yanıtla (birden fazla aynı isimli sonuç) yeni hata
  mesajının adayları içerdiği doğrulanır.
- **Bu promptun gerçek başarı kriteri**, Kort'un canlı ortamda "Türkiye" (veya başka tek
  eşleşmeli bir konum) ile kampanya sihirbazını tekrar denemesi ve bu kez
  `RESOURCE_NOT_FOUND` hatası **almadan**, gerçek `PAUSED` bir kampanya oluşturmasıdır.
  Bunu DURUM.md'de Kort'a net şekilde hatırlat.

## 5. DURUM.md

Kısa not: hangi üç satırın eklendiği (Görev A), konum hata mesajının nasıl zenginleştiği
(Görev B), ve Kort için canlı test hatırlatması. Ayrı TODO listesi gerekmiyor (odaklı,
iki maddelik bir düzeltme).
