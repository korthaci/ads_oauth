# PROMPT-20 — Kampanya Oluşturma Sihirbazı (Yayına Hazır Search Kampanyası)

## 0. Bağlam ve Kapsam

Bu, projenin ilk **gerçek mutate** (yazma/oluşturma) akışıdır — şimdiye kadarki tüm
promptlar (PROMPT-09 → PROMPT-19) yalnızca salt-okunur doğrulama/listelemeydi. Bu yüzden
güvenlik/geri dönülebilirlik önceliklidir (bkz. §3).

Amaç: Kullanıcının ARCHITECTURE.md §1.1'deki örnekteki gibi sade bilgiler girerek
(`"Ankara'da günde 500 TL bütçeyle bu hizmetimin reklamını yapmak istiyorum"`) **gerçekten
yayınlanabilir** bir Google Ads **Search (Arama)** kampanyası oluşturabilmesidir. Kapsam
şu anda yalnızca Search kampanyasıdır (Display/Video/Shopping bu promptta yok — ileride
ayrı bir konu).

"Yayına hazır" burada şu anlama gelir: kampanya, reklam grubu, anahtar kelimeler ve
reklam gerçekten oluşturulur ve Google'ın minimum gereksinimlerini karşılar — **ancak
kampanya varsayılan olarak `PAUSED` (duraklatılmış) durumda oluşturulur** (bkz. §3.1).
Kullanıcı, ayrı ve bilinçli bir "yayına al" adımıyla kampanyayı `ENABLED` yapar. Bu
promptta otomatik aktivasyon **yapılmaz**.

## 1. Kullanıcıdan Alınacak Sade Bilgiler (Sihirbaz Arayüzü)

ARCHITECTURE.md §1.1 "Minimum Kullanıcı Bilgisi İlkesi" gereği, yalnızca gerçekten
zorunlu ve insan tarafından kolay anlaşılır alanlar istenir:

1. **Web sitesi** (reklamın yönlendireceği URL — `https://` ile veya olmadan girilebilir,
   sistem normalize eder)
2. **Kampanya/iş adı** (kullanıcının kendi takibi için kısa bir isim — Google'a gönderilen
   `campaign.name` bu olur)
3. **Reklam başlıkları** (serbest metin, birden fazla satır/virgülle ayrılmış — sistem en
   az 3 tanesini kullanır, kullanıcı 3'ten az girerse hata verir; 15'ten fazlasını
   kabul etmez)
4. **Reklam açıklama metinleri** (serbest metin — en az 2 tane gerekir, en fazla 4)
5. **Anahtar kelimeler** (serbest metin, virgülle ayrılmış liste — en az 1 tane gerekir)
6. **Günlük bütçe** (TL, tam sayı veya ondalık — kullanıcı "günde 500 TL" der, sistem
   `budget_amount_micros` olarak çevirir: `TL * 1.000.000`)
7. **Hedef konum** (şehir/bölge/ülke adı, serbest metin — örn. "Ankara", "Türkiye")

Kullanıcıdan **istenmeyecek** olanlar (sistem tarafından türetilir/varsayılan
kullanılır — ARCHITECTURE.md §1.1 gereği):

- Dil hedefleme → varsayılan **Türkçe** (`language_constant` = `languageConstants/1017`)
  sabit kullanılır, kullanıcıya sorulmaz.
- Eşleme türü (match type) → tüm anahtar kelimeler için varsayılan **Phrase Match**
  kullanılır (Broad Match'ten daha kontrollü, kullanıcı bütçesini korumaya yardımcı olur).
  Kullanıcıya gösterilmez.
- Teklif stratejisi (bidding strategy) → varsayılan **Maximize Clicks**
  (`maximize_clicks`) kullanılır, manuel CPC veya teklif tutarı sorulmaz.
- Ağ ayarları (network settings) → yalnızca Google Arama Ağı açık
  (`target_google_search: true`), Arama Ortakları ve Display Ağı **kapalı**
  (`target_search_network: false`, `target_content_network: false`,
  `target_partner_search_network: false`) — kapsam net tutulur, harcama sürprizleri
  önlenir.
- Kampanya durumu → yukarıda belirtildiği gibi her zaman `PAUSED` başlar.

## 2. Teknik Akış (Katman Sorumlulukları)

ARCHITECTURE.md §5'teki katman ayrımına sadık kalınır:

1. **`tema/panel/kampanya-sihirbazi.php`** — yalnızca görünüm; §1'deki 7 alanı içeren
   form. İstemci tarafı validasyon (zorunlu alanlar, min 3 başlık/2 açıklama/1 anahtar
   kelime) kullanıcı deneyimi için eklenebilir ama **güvenlik sınırı değildir** — asıl
   doğrulama backend'de tekrarlanır.
2. **`api/kampanya-olustur.php`** — HTTP giriş noktası, oturum kontrolü, `php/servis/kampanya-servisi.php`'ye
   yönlendirme, Websistem `{return, mesaj}` formatında JSON döner.
3. **`php/servis/kampanya-servisi.php`** — yeni `kampanya_olustur()` fonksiyonu:
   - Aktif bağlı hesabı okur, **`manager` kontrolünü tekrar yapar** (PROMPT-16 ile aynı
     mantık — bağlı hesap değişmiş olabilir, her seferinde taze doğrulanır).
   - Girdileri doğrular (§4).
   - Konum adını (`"Ankara"` gibi) gerçek bir `geoTargetConstant` kaynak adına çevirmek
     için `GeoTargetConstantService.suggestGeoTargetConstants()` çağrısını
     `php/baglayici/google-ads-baglayici.php`'ye ekler (bu da salt-okunur bir çağrıdır,
     mutate değildir).
   - `php/baglayici/google-ads-baglayici.php`'deki yeni mutate fonksiyonlarını sırasıyla
     çağırır (§2.1).
4. **`php/baglayici/google-ads-baglayici.php`** — gerçek Google Ads API mutate
   çağrılarını sarmalayan yeni fonksiyonlar (§2.1). SDK'ya özgü detaylar yalnızca burada
   yaşar.

### 2.1. Gerekli Mutate Adımları (sırasıyla, tek bir `kampanya_olustur()` çağrısı içinde)

Google Ads API'de bu genelde tek bir `CampaignService`/`GoogleAdsService.mutate()` batch
isteğiyle (birden fazla operation, `temp_id` ile birbirine bağlı) yapılabilir — mümkünse
bu şekilde tek atomik istekte yapılsın (kısmi başarısızlık riskini azaltır). Değilse,
adımlar sırayla yapılır ve bir adım başarısız olursa **önceki adımlarda oluşturulan
kaynaklar temizlenir/durumu açıkça raporlanır** (yarım kalmış kampanya production'da
sessizce kalmaz).

1. `CampaignBudget` oluştur (`amount_micros`, `delivery_method: STANDARD`,
   `explicitly_shared: false`).
2. `Campaign` oluştur (`advertising_channel_type: SEARCH`, `status: PAUSED`,
   yukarıdaki bütçe/bidding/network ayarlarıyla).
3. `CampaignCriterion` — konum hedefleme (adım 3'te bulunan `geoTargetConstant`).
4. `CampaignCriterion` — dil hedefleme (`languageConstants/1017`).
5. `AdGroup` oluştur (`campaign`, `status: ENABLED`, `type: SEARCH_STANDARD`,
   varsayılan bir `cpc_bid_micros` gerekmiyorsa Maximize Clicks zaten kampanya
   seviyesinde yönetir).
6. `AdGroupCriterion` — her anahtar kelime için (`KEYWORD`, `match_type: PHRASE`).
7. `AdGroupAd` — Responsive Search Ad (`headlines[]`, `descriptions[]`, `final_urls: [web sitesi]`).

## 3. Güvenlik ve Geri Dönülebilirlik

### 3.1. Neden `PAUSED`?

Bu sistemin ilk gerçek harcama riski taşıyan özelliğidir. Kampanya `ENABLED` olarak
oluşturulursa, bir kod hatası (yanlış bütçe birimi, yanlış hedefleme, vs.) doğrudan
gerçek parasal harcamaya yol açabilir. Bu yüzden:

- Kampanya **her zaman** `PAUSED` oluşturulur.
- `api/kampanya-olustur.php` yanıtı, kullanıcıya kampanyanın oluşturulduğunu ama
  **duraklatılmış** olduğunu ve ayrı bir "yayına al" adımı gerektiğini açıkça belirtir.
- Bu promptta bir "yayına al" (`status: ENABLED` yapan) endpoint'i **yazılmaz** — bu
  bilinçli olarak ayrı, sonraki bir prompt konusu (kullanıcı önce oluşturulan kampanyayı
  Google Ads arayüzünde veya panelde gözden geçirsin, sonra ayrı bir onayla aktive
  etsin).

### 3.2. Kesin Yasaklar

- Kampanya **hiçbir koşulda** `ENABLED` olarak oluşturulmaz.
- `budget_amount_micros` hesaplaması test edilmeden gerçek çağrıya geçilmez (§5'teki
  birim testi zorunlu — TL→micros çevrimi yanlışsa 500 TL yerine 500.000.000 TL bütçe
  gibi feci bir hataya yol açabilir).
- Manager hesabına (`manager: true`) hiçbir kampanya/mutate çağrısı yapılmaz — servis bu
  kontrolü PROMPT-16'daki gibi tekrarlar.
- `createCustomerClient` kullanılmaz (ARCHITECTURE.md §7.1).
- Kullanıcıya, §1'de listelenmeyen hiçbir teknik alan (bidding stratejisi, network
  ayarı, match type, dil) arayüzde gösterilmez.

## 4. Girdi Doğrulama (backend, zorunlu)

- Web sitesi: geçerli bir URL formatına sahip olmalı (şema eksikse `https://` eklenir).
- Kampanya adı: boş olamaz, makul bir uzunluk sınırı (örn. 255 karakter).
- Başlıklar: en az 3, en fazla 15 adet; her biri **30 karakteri** geçemez (Google Ads
  Responsive Search Ad headline sınırı).
- Açıklamalar: en az 2, en fazla 4 adet; her biri **90 karakteri** geçemez.
- Anahtar kelimeler: en az 1 adet, her biri boş olamaz, makul bir üst sınır (örn. 20).
- Günlük bütçe: pozitif sayı, makul bir alt sınır (Google Ads TRY için minimum günlük
  bütçe gereksinimini gerçek API/dokümantasyondan doğrula — tahmin etme, gerekiyorsa
  API'nin döndürdüğü hatayı kullanıcıya olduğu gibi ilet).
- Konum: `suggestGeoTargetConstants` sonucu boşsa (hiç eşleşme yoksa), kullanıcıya net
  "bu konum bulunamadı, farklı yazmayı deneyin" mesajı döner — tahmini/en yakın eşleşme
  sessizce seçilmez.

## 5. Doğrulama (Görev Öncesi/Sonrası)

- TL→micros çevrim fonksiyonu için birim testi yazılır (örn. `500 TL → 500000000`,
  `12.50 TL → 12500000`).
- Gerçek bir mutate çağrısı yapılmadan önce, bağlı hesabın gerçekten `manager: false`
  olduğu PROMPT-16'daki gibi taze sorgulanır; `manager: true` ise **hiçbir mutate
  denenmeden** net hata döner.
- Gerçek API testi (Kort'un canlı oturumuyla) **küçük, düşük bütçeli** bir test
  kampanyasıyla yapılmalı — bu promptun DURUM.md notunda Kort'a açıkça hatırlatılır.
- Test sonrası oluşan gerçek kampanya, PAUSED durumda kaldığı ve gerçek harcama
  başlamadığı doğrulanır (Google Ads arayüzünden görsel kontrol Kort tarafından yapılır).
- `php -l` tüm değişen/yeni dosyalarda çalıştırılır.

## 6. DURUM.md — TODO Listesi

```markdown
## PROMPT-20 TODO

- [ ] Sihirbaz formu (7 alan) tema/panel/kampanya-sihirbazi.php'ye eklendi
- [ ] api/kampanya-olustur.php gerçek işlevine kavuştu
- [ ] kampanya-servisi.php: kampanya_olustur() + manager tekrar-doğrulama eklendi
- [ ] google-ads-baglayici.php: budget/campaign/criterion/ad-group/keyword/ad mutate
      fonksiyonları eklendi
- [ ] GeoTargetConstant konum çözümleme eklendi (salt-okunur)
- [ ] TL→micros çevrimi için birim testi yazıldı ve geçti
- [ ] Kampanyanın her zaman PAUSED oluşturulduğu koddan doğrulandı
- [ ] Manager hesaba mutate denenmediği doğrulandı
- [ ] Girdi doğrulama kuralları (başlık/açıklama uzunluk, min/max sayılar) uygulandı
- [ ] php -l tüm dosyalarda geçti
- [ ] Deploy listesi verildi
- [ ] Kort'a canlı düşük bütçeli test talimatı DURUM.md'de not edildi
- [ ] DURUM.md güncellendi
```

Tamamlanamayan madde varsa nedeni yazılır, tahminle işaretlenmez. Bu, projenin ilk gerçek
mutate özelliği olduğundan, TODO listesindeki güvenlik maddeleri (PAUSED, manager
kontrolü, micros çevrimi) **hiçbir şekilde atlanmadan** işaretlenmelidir.
