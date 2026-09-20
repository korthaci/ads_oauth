# PROMPT-21.1 — Yanlış Dil Sabiti Düzeltmesi (`languageConstants/1017` Türkçe Değil)

## 0. Bağlam

Gerçek bir test kampanyasında ("Türkiye + Trabzon hariç" testi, kampanya ID
`24265218474`), Google Ads arayüzünde kampanyanın **Diller** ayarı beklenmedik şekilde
**"Çince (basitleştirilmiş)"** çıktı. PROMPT-20'de dil hedeflemesi için sabit
`languageConstants/1017` kullanılmıştı ("varsayılan Türkçe" varsayımıyla) — ama gerçek
sonuç bu ID'nin **Türkçe olmadığını**, muhtemelen Çince (Basitleştirilmiş) olduğunu
gösteriyor. Bu, kullanıcıya hiç sorulmayan, sessizce uygulanan bir varsayılan olduğu için
**ciddi bir hata** — şu ana kadar oluşturulan gerçek kampanyaların hepsi muhtemelen
yanlış dille hedeflenmiş durumda.

## 1. Görev A — Doğru ID'nin Gerçek API'den Doğrulanması (tahmin yok)

1. Salt-okunur bir `GoogleAdsService.search()` GAQL sorgusu çalıştır:
   ```sql
   SELECT language_constant.id, language_constant.code, language_constant.name,
          language_constant.resource_name
   FROM language_constant
   WHERE language_constant.code = 'tr'
   ```
   (Bu sorgu `customer` gerektirmez ama SDK'nın `GoogleAdsServiceClient::search()`
   çağrısı için geçerli bir `customer_id` ile çalıştırılması gerekebilir — mevcut bağlı
   hesabın ID'si kullanılabilir, bu salt-okunur bir sorgudur.)
2. Dönen gerçek `id`/`resource_name` değerini not al — bu, doğru Türkçe dil sabiti
   olacak. Aynı sorguyla, mevcut yanlış kullanılan `1017`'nin gerçekte hangi dile ait
   olduğunu da (`language_constant.id = 1017` filtresiyle) doğrulayıp DURUM.md'ye not
   düş (teyit amaçlı, meraktan değil — ileride benzer bir karışıklık tekrarlanmasın diye).

## 2. Görev B — Kod Düzeltmesi

1. `php/baglayici/google-ads-baglayici.php` içinde sabit dil ID'sinin kullanıldığı
   **tüm** yerleri bul (`grep` ile — yalnızca tek bir yerde olmayabilir, örn. sabit bir
   `const`/değişken olarak tanımlanmış olabilir, onu da kontrol et).
2. Değeri, Görev A'da gerçek API'den doğrulanan doğru Türkçe ID'siyle değiştir.
3. Kod içinde bu sabitin yanına, hangi GAQL sorgusuyla doğrulandığını belirten kısa bir
   yorum ekle (ileride tekrar şüpheye düşülmesin diye).

## 3. Kesin Yasaklar

- Yalnızca bu tek sabit değer düzeltilir. Mutate akışının başka hiçbir kısmına
  dokunulmaz.
- Değer hâlâ **sabit/hardcoded** kalır, kullanıcıya arayüzde dil seçimi
  **eklenmez** (ARCHITECTURE.md §1.1 ilkesiyle tutarlı — bu proje kapsamında dil hâlâ
  sistem tarafından türetilir).
- Zaten oluşturulmuş, yanlış dille hedeflenmiş test kampanyalarına (`24268992914`,
  `24276234886`, `24265218474` vb.) otomatik bir düzeltme/güncelleme **yapılmaz** — bu
  promptun kapsamı yalnızca **yeni oluşturulacak** kampanyalar içindir. Kort isterse bu
  eski test kampanyalarının dilini Google Ads arayüzünden elle düzeltebilir veya
  `REMOVED` yapabilir (kendi kararı).

## 4. Doğrulama

- `php -l` çalıştırılır.
- Görev A'daki GAQL sorgusunun gerçek sonucu (doğru ID, ve `1017`'nin gerçekte ne
  olduğu) DURUM.md'ye **gerçek verilerle** yazılır.
- **Bu promptun gerçek başarı kriteri**: Kort'un canlı ortamda yeni bir test kampanyası
  oluşturması ve Google Ads arayüzünde **Diller** ayarının artık doğru şekilde
  **Türkçe** göründüğünü doğrulamasıdır. DURUM.md'de bu net şekilde hatırlatılır.
  Kampanya yine `PAUSED` kalmalı.

## 5. DURUM.md

Kısa not: GAQL sorgusunun sonucu (eski yanlış ID neydi, yeni doğru ID ne), hangi
satır(lar)ın değiştiği, Kort için canlı test hatırlatması, eski yanlış-dilli test
kampanyaları hakkında bilgilendirme notu. Ayrı TODO listesi gerekmiyor.
