# PROMPT-21 — Konum Belirsizliği Çözümü, Hariç Tutulan Bölgeler, Yaklaşık Toplam Bütçe

## 0. Bağlam

PROMPT-20 serisi tamamlandı; kampanya oluşturma gerçek testte başarılı oldu (gerçek
kampanya ID `24268992914`, `PAUSED`). Şimdi üç eksik/ilerletilecek konu var, **bu sırayla
ele alınır** (A en öncelikli, B ve C ardından):

**(A) Konum belirsizliği kullanılamaz durumda:** "Ankara", "İstanbul" gibi hem il hem de
başka bir düzeyde (`Province`/`City` gibi) aynı isimle kayıtlı konumlar için sistem hata
veriyor ve kullanıcıdan `canonical_name` yazmasını istiyor — ama sistem kullanıcının
yazdığı metni **olduğu gibi** Google'a konum adı olarak gönderiyor
(`setLocationNames([$konum])`), bu yüzden `"Ankara,Turkiye"` gibi virgüllü bir canonical
name girse bile geçerli bir arama terimi olarak çalışmaz, yine "bulunamadı" hatası alır.
Kullanıcının serbest metinle bu belirsizliği çözmesi **mümkün değil** — gerçek bir seçim
arayüzü gerekiyor.

**(B) Hariç tutulan bölge desteği yok:** Kullanıcı "Türkiye ama Trabzon hariç" gibi bir
hedefleme yapamıyor. Google Ads API bunu **negatif konum kriteri**
(`CampaignCriterion.negative = true`) ile destekliyor — teknik olarak mümkün, sadece
sihirbazda ve serviste yok.

**(C) Toplam bütçe kavramı yok:** Google Ads Search kampanyaları yalnızca **günlük
bütçe** kabul eder (`STANDARD` teslim yöntemi, Google yoğun günlerde günlük bütçenin
~2 katına kadar harcayıp ayı ortalayabilir — kesin bir günlük tavan garantisi yoktur).
Gerçek bir "toplamda en fazla X TL harca" garantisi Google Ads API'de **yoktur**. En
yakın yaklaşım: kampanyaya bir **bitiş tarihi** (`campaign.end_date`) eklemek —
`toplam_butce / gunluk_butce` gün sonra kampanya biter. Bu **yaklaşık** bir kontroldür,
kesin değildir; kullanıcıya bu sınırlama açıkça belirtilmelidir.

## 1. Görev A — Konum Belirsizliği İçin Gerçek Seçim Arayüzü

### 1.1. Backend

1. `google_ads_konum_onerilerini_al()` / `google_ads_konum_yanitini_isle()` mevcut
   davranışını koru: tam eşleşme sayısı 0 → "bulunamadı", 1 → doğrudan kullan. Ancak
   **birden fazla tam eşleşme** durumunda artık **exception fırlatmak yerine**, çağıran
   koda adayların tam listesini (`resource_name`, `name`, `canonical_name`,
   `target_type` — her biri) döndür (fonksiyonun dönüş tipini/şeklini bu senaryo için
   genişlet, mevcut çağıranları bozmadan — örn. ayrı bir dönüş yapısı veya özel bir
   sonuç nesnesi ile).
2. `kampanya_servisi.php`'deki `kampanya_olustur()` akışında: konum çözümlemesi
   belirsizse, gerçek mutate'e **hiç geçilmeden**, `api/kampanya-olustur.php` üzerinden
   frontend'e şu şekilde bir yanıt dönülür:
   ```json
   {
     "return": 0,
     "mesaj": "Konum belirsiz, lütfen birini seçin.",
     "konum_secenekleri": [
       {"resource_name": "geoTargetConstants/...", "ad": "Ankara", "tip": "Province", "canonical_name": "Ankara,Turkiye"},
       {"resource_name": "geoTargetConstants/...", "ad": "Ankara", "tip": "City", "canonical_name": "Ankara,Ankara,Turkiye"}
     ]
   }
   ```
3. Sihirbaz formuna, kullanıcı bir seçenek işaretleyip formu **tekrar gönderdiğinde**
   backend'e iletilecek gizli bir alan eklenir: `hedef_konum_resource_name` (opsiyonel).
   Bu alan doluysa, `kampanya_olustur()` konum adı aramasını (suggestGeoTargetConstants)
   **atlar**, doğrudan bu `resource_name`'i kriter olarak kullanır — bu, "sessiz
   best-match yok" ilkesini korurken gerçek, kullanıcının bilinçli seçtiği bir değerdir.
4. Aynı isim tekrar değişirse (kullanıcı konum metnini değiştirip formu tekrar
   gönderirse) `hedef_konum_resource_name` geçersiz sayılmalı/yok sayılmalı — yalnızca
   son gönderilen `hedef_konum` metniyle tutarlıysa kullanılmalı (basit bir eşleşme
   kontrolü yeterli, aşırı mühendislik yapılmaz).

### 1.2. Frontend (`tema/panel/kampanya-sihirbazi.php`)

1. Backend `konum_secenekleri` içeren bir yanıt döndürdüğünde, form gönderilmeden önce
   engellenip, kullanıcıya adaylar arasından seçim yapması için basit bir radio-button
   listesi gösterilir (`ad` + `tip` + `canonical_name` görünür şekilde).
2. Kullanıcı bir seçenek işaretleyip "Kampanyayı Oluştur"a tekrar bastığında, form
   `hedef_konum_resource_name` gizli alanıyla birlikte tekrar gönderilir.
3. Sayfa yenilenmeden (mevcut fetch/JS akışı korunur), tüm bu adımlar tek sayfada olur.

## 2. Görev B — Hariç Tutulan Bölge(ler)

1. Sihirbaza **opsiyonel** bir alan eklenir: "Hariç tutulacak bölgeler (opsiyonel)" —
   serbest metin, virgül/satırla ayrılmış liste (mevcut
   `kampanya_metnini_listeye_ayir()` fonksiyonu tekrar kullanılır).
2. Her hariç tutulacak konum, **hedef konumla aynı çözümleme mantığından** geçer (§1.1):
   tam eşleşme yoksa hata, birden fazla tam eşleşme varsa aynı seçim mekanizması
   (§1.1) her hariç konum için de tetiklenir — kullanıcı birden fazla belirsiz hariç
   konum girerse, bunlar sırayla/birlikte çözümlenir (basit tutulur: ilk belirsiz olan
   için seçim istenir, o çözülünce varsa bir sonraki için tekrar istenir).
3. Çözümlenen her hariç konum, mutate isteğine ek bir `CampaignCriterion` olarak,
   `->setNegative(true)` ile eklenir (hedef konum kriterinin yanına, aynı atomik mutate
   içinde — §2.1'deki mevcut mutate akışı genişletilir, yeniden yazılmaz).
4. Hedef konumla **aynı** bir yer hariç tutulmaya çalışılırsa (örn. hedef "Türkiye",
   hariç de "Türkiye") kullanıcıya backend'de anlamlı bir hata verilir, sessizce
   yok sayılmaz.

## 3. Görev C — Yaklaşık Toplam Bütçe (bitiş tarihi ile)

1. Sihirbaza **opsiyonel** bir alan eklenir: "Toplam bütçe (TL, opsiyonel)".
2. Doldurulursa: `bitis_tarihi = bugün + floor(toplam_butce / gunluk_butce)` gün
   hesaplanır (asgari 1 gün; `gunluk_butce`'den küçük bir `toplam_butce` girilirse
   kullanıcıya net bir doğrulama hatası verilir, sessizce 0/1 güne yuvarlanmaz).
3. Kampanya mutate isteğine `->setEndDate($bitis_tarihi)` (Google'ın beklediği
   `YYYY-MM-DD` formatında) eklenir — yalnızca bu alan doldurulmuşsa; boşsa kampanya
   şu anki gibi bitiş tarihsiz (süresiz) oluşturulur.
4. Sihirbaz arayüzünde bu alanın yanına **açık bir uyarı metni** eklenir: *"Bu, kesin
   bir toplam harcama garantisi değildir — Google bazı günlerde günlük bütçenin biraz
   üzerinde harcayıp ayı ortalayabilir; bu alan yalnızca kampanyanın belirli bir tarihte
   otomatik olarak durmasını sağlar."*

## 4. Kesin Yasaklar (tüm görevler için)

- Kampanya hâlâ **her zaman `PAUSED`** oluşturulur — bu promptta hiçbir şekilde
  değiştirilmez.
- Manager hesap kontrolü (mutate öncesi taze doğrulama) korunur.
- `partial_failure` kapalı, atomik mutate mantığı korunur — yeni kriterler (hariç
  konum) bu tek isteğin içine eklenir, ayrı bir mutate çağrısı **yapılmaz**.
- Hiçbir yeni alan kullanıcıya "gizli" varsayılanları (dil, match type, bidding,
  network) değiştirmez — yalnızca §1-3'te tanımlanan üç konu ele alınır.
- "Sessiz best-match" hâlâ yasak: konum belirsizliğinde kullanıcı **gerçekten**
  seçim yapmadan hiçbir aday otomatik seçilmez.

## 5. Doğrulama

- `php -l` tüm değişen/yeni dosyalarda.
- Sentetik testler: (a) tek eşleşme → doğrudan geçer, (b) çoklu eşleşme →
  `konum_secenekleri` dönüyor, mutate'e geçilmiyor, (c) `hedef_konum_resource_name`
  ile gönderilen istek suggest çağrısını atlayıp doğrudan o kaynağı kullanıyor,
  (d) hariç konum aynı hedef konumla çakışınca hata, (e) toplam bütçe < günlük bütçe
  → doğrulama hatası, (f) toplam bütçe verilince `end_date` doğru hesaplanıyor
  (birim testi, örn. `günlük 100 TL, toplam 1000 TL → 10 gün sonrası`).
- **Gerçek canlı test zorunlu** (Kort tarafından): (1) "Ankara" ile seçim ekranının
  gerçekten çıktığı ve bir seçenek seçince kampanyanın o konumla oluştuğu, (2) "Türkiye"
  hedef + "Trabzon" hariç ile gerçek bir test kampanyası, (3) toplam bütçe alanı
  doldurulmuş bir test kampanyasının `end_date`'inin Google Ads arayüzünde doğru
  göründüğü. Üçü de **PAUSED** kalmalı, hiçbiri `ENABLED` yapılmamalı.

## 6. DURUM.md — TODO Listesi

```markdown
## PROMPT-21 TODO

- [ ] Konum çözümleme fonksiyonu çoklu eşleşmede adayları döndürecek şekilde genişletildi
- [ ] api/kampanya-olustur.php konum_secenekleri yanıtını destekliyor
- [ ] Sihirbaz formu: belirsizlikte seçim ekranı + hedef_konum_resource_name akışı
- [ ] Hariç tutulan bölge(ler) alanı eklendi, aynı çözümleme mantığından geçiyor
- [ ] Hariç konum mutate'e negative=true kriteri olarak ekleniyor
- [ ] Hedef ile hariç konum çakışması reddediliyor
- [ ] Toplam bütçe alanı + end_date hesaplama birim testi geçti
- [ ] Toplam bütçe < günlük bütçe doğrulaması eklendi
- [ ] Sihirbazda toplam bütçe için uyarı metni eklendi
- [ ] php -l tüm dosyalarda geçti
- [ ] Deploy listesi verildi
- [ ] Kort için üç canlı test senaryosu (Ankara seçim, Trabzon hariç, toplam bütçe) DURUM.md'ye net yazıldı
- [ ] DURUM.md güncellendi
```

Tamamlanamayan madde varsa nedeni yazılır, tahminle işaretlenmez. Bu promptun sırası
(önce A, sonra B, sonra C) korunur; A tamamlanmadan B/C'ye geçilse bile, DURUM.md'de
hangisinin gerçekten bitip hangisinin bitmediği net ayrılır.
