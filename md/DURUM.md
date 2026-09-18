# ads_oauth

Yeni bir sohbete başlarken önce bu sırayla oku:
1. DURUM.md — şu an neredeyiz, hangi promptlar tamamlandı
2. ARCHITECTURE.md — sabit mimari/kararlar
3. md/GOOGLE-HESAP-KURULUM-REHBERI.md — Google Cloud/Ads hesap kurulum süreci (kod değil, referans)

GitHub: https://github.com/korthaci/ads_oauth

> Bu dosya **canlıdır** — her ilerleme sonrası güncellenir. Yeni bir çalışma oturumuna
> başlarken (context sıfırlansa dahi) önce bu dosya okunur, sonra ARCHITECTURE.md, sonra
> ilgili prompt dosyası.
> **Güncelleme sorumluluğu kod yazıcı AI'ye (Cline/Requesty) aittir.** Claude bu dosyayı
> artık düzenlemez, sadece yeni prompt hazırlarken referans olarak okur.

**Son güncelleme:** 2026-09-18

**Proje durumu:** **AKTİF** — Kapsam netleşti (kişisel kullanım + davetli 1-2 kişi),
Manager üzerinden hesap oluşturma yaklaşımı terk edildi, kullanıcı kendi hesabını
kendisi bağlayacak şekilde ilerliyor. Google Ads API erişim seviyesi şu anda **BASIC**
(günlük 15.000 işlem, production ve test hesapları için); OAuth Brand Verification ve
sensitive scope (`adwords`) OAuth app verification tamamlandı. Google Ads OAuth ve
Manager/CustomerClient read-only keşfi tamamlandı. Meta entegrasyonu bu projenin
kapsamı değildir.

**Proje adı:** ads_oauth
**Proje yolu:** `c:/server/htdocs/ads_oauth/`
**GitHub reposu:** https://github.com/korthaci/ads_oauth (public)
**Kod yazıcı ortam:** VS Code + Cline + Requesty → model: OpenAI GPT Luna (düşük token tüketimi hedefleniyor, proje sonuna kadar yetsin diye bilinçli olarak Claude/OpenAI'nin üst modelleri seçilmedi)
**Workspace notu:** Kort'un VS Code workspace'ine Websistem projesi de referans amaçlı eklendi — amaç, `api/` dosyalarının JSON dönüş biçimini Kort'un zaten alışık olduğu Websistem konvansiyonuyla (`class_f/fonksiyon.php` genel fonksiyonlar, `class_f/class_.php` genel class'lar) tutarlı tutmak. AI'nin Websistem'in tamamını incelemesine gerek yok, sadece bu iki dosyadaki JSON döndürme kalıbını referans almalı.

---

## 1. Şu An Neredeyiz?

**Aşama:** PROMPT 00 (iskelet kurulum), PROMPT 01 (isimlendirme düzeltmesi + autoload kaldırma),
PROMPT 02 (eksik kalan isimlendirme düzeltmelerinin kontrolü), PROMPT 03 (Google öncelikli
temel altyapı), PROMPT 04 (Composer/vendor altyapısı), PROMPT 05 (Google Ads PHP client
geçişi), PROMPT 07 (site sahibi login/register) ve PROMPT-08 (Google Ads API ilk bağlantı ve
hesap keşfi endpoint'i) ve PROMPT-09 (Manager altındaki CustomerClient müşteri keşfi ve
proje kapsam sınırı) uygulandı. PROMPT-10 (Google Ads kampanya listeleme geçişi) ve
PROMPT-11 (Google Ads test müşteri hesabı geçişi) gerçek non-manager müşteri hesabı
bulunamadığı için beklemede bırakıldı. PROMPT-12 kapsamında Manager
`9530538405` altında `createCustomerClient` ile yeni müşteri oluşturma çağrısı tek kez
gerçekleştirildi; çağrı başarısız oldu ve gerçek bir non-manager müşteri hesabı
oluşturulamadı. PROMPT-14 kapsamında kalıcı ve güvenli API exception loglama altyapısı
uygulandı. PROMPT-15 kapsamında canlı tablo ön koşulu doğrulanarak `createCustomerClient`
çağrısı tam bir kez gerçek API'ye gönderildi; çağrı `PERMISSION_DENIED` ile başarısız oldu,
hesap oluşturulmadı ve güvenli hata kaydı kalıcı olarak yazıldı. 2026-09-17 kararına göre
`createCustomerClient` ile Manager altında otomatik hesap oluşturma yaklaşımı terk edildi
(ARCHITECTURE.md §7.1); proje durumu **AKTİF** olarak ilerliyor.

PROMPT-08 kapsamında SDK v34.0.0 içindeki mevcut `V25` API sınıfları kullanıldı. `.env` içindeki
`GOOGLE_DEVELOPER_TOKEN` mevcut ve gerçek API çağrısında çalıştı. `listAccessibleCustomers()`
gerçek çağrısı 2 customer resource name döndürdü. Bunlardan `customers/9530538405` temel
bilgileriyle keşfedilip `baglanmis_hesaplar` tablosuna kaydedildi; `customers/4150407743`
`PERMISSION_DENIED` / `CUSTOMER_NOT_ENABLED` nedeniyle atlandı. Kaydedilen hesap sayısı: **1**.

Gerçek API yanıtında Developer Token erişim seviyesi (Basic/Standard) bilgisi bulunmadığı için
erişim seviyesi tahmin edilmedi ve **doğrulanamadı**. Bu keşif çağrısında `login-customer-id`
eklenmeden hem erişilebilir müşteri listesi hem de geçerli müşterinin temel bilgileri alındı;
bu nedenle bu çağrı için gerekmedi. Manager hesabı sonucu `customer.manager = true` olarak
alındı, ancak manager hesabı hiyerarşisi için varsayımsal bir login customer ID eklenmedi.

İkinci gerçek endpoint çalıştırmasında duplicate kontrolü doğrulandı: kayıt sayısı 1 kaldı,
OAuth kaydının `no=2` değeri ve şifreli refresh token doluluğu korundu. Kampanya, reklam grubu,
reklam, bütçe, teklif, anahtar kelime veya UI işlemi yapılmadı.

**Sıradaki adım:** Google Ads API erişimi (Basic Access) tarafında engel kalmadı.
Sıradaki adım: proje sahibinin kendi Google Ads hesabını (gerçek, var olan bir hesap)
OAuth ile bağlaması ve bu gerçek hesap üzerinden kampanya listeleme/oluşturma
akışının test edilmesi. Google Ads API Basic Access başvurusu 2026-09-05'te
yapılmış ve erişim BASIC seviyesine yükselmiştir (bkz. Karar Günlüğü 2026-09-18);
referans: `md/GOOGLE-HESAP-KURULUM-REHBERI.md` — Google Cloud/Ads hesap ve erişim
seviyesi kurulum sürecinin kaydı, kod değişikliği içermez.

### PROMPT-12 sonucu — `createCustomerClient` kontrollü API testi

- **Durum:** Başarısız; proje durumu **BEKLEMEDE** olarak korunuyor.
- Manager Customer ID: `9530538405`.
- Google Ads V25 `CustomerService.createCustomerClient` çağrısı, istenen tek seferlik
  `descriptive_name=ads_oauth Test`, `currency_code=TRY` ve `time_zone=Europe/Istanbul`
  alanlarıyla gerçek API'ye **tam bir kez** gönderildi.
- API çağrısı hesap oluşturmadan başarısız oldu. SDK'nin dışarı verdiği gerçek exception sınıfı
  `Google\\ApiCore\\ApiException` oldu. Bu çalışmada `GoogleAdsException` içindeki yapılandırılmış
  Google Ads hata ayrıntısı alınamadı; gerçek Ads hata kodu ve mesajı mevcut sonuçtan elde
  edilemedi. Hata kodu `PERMISSION_DENIED`, `CUSTOMER_NOT_ELIGIBLE`, `AUTHORIZATION_ERROR`,
  1.000 USD harcama/uygunluk şartı veya başka bir değer olarak **tahmin edilmedi**.
- Hesap oluşturma başarısız olduğu için yeni müşteri resource name/customer ID oluşmadı;
  oluşturulan hesap üzerinde read-only `CustomerClient` doğrulaması yapılmadı.
- Çağrı sonrasında güvenli snapshot kontrolü: `baglanmis_hesaplar` kayıt sayısı önce **1**,
  sonra **1**; OAuth kaydı değişmedi; refresh-token hash’i değişmedi. Refresh token ve
  Developer Token çıktıya/log'a yazılmadı.
- `baglanmis_hesaplar` tablosuna kayıt eklenmedi; DB şeması değiştirilmedi. Kampanya, reklam,
  bütçe, ad group, keyword, ödeme veya başka bir API işlemi yapılmadı.
- Geçici teknik test dosyası API çağrısından sonra silindi. Kalıcı servis, endpoint veya
  hesap oluşturma özelliği eklenmedi.
- **Sonraki geliştirmeye etkisi:** Gerçek ve yetkili bir non-manager müşteri hesabı
  erişilebilir olmadan kampanya listeleme/geliştirmesine geçilmeyecek. Bu test sonucu
  tekrar `createCustomerClient` çağrısı yapılmayacak; Google Ads tarafındaki yetki/uygunluk
  durumu ayrıca netleşmeden farklı parametre veya ödeme işlemi denenmeyecek.

### PROMPT-15 sonucu — `createCustomerClient` kontrollü gerçek çağrı ve kalıcı log

- **Durum:** Başarısız; gerçek çağrı tam **1 kez** yapıldı; proje durumu **BEKLEMEDE** olarak
  korunuyor.
- Çağrı öncesi canlı ön koşul doğrulaması başarılı oldu: `ads_oauth` veritabanında
  `api_hata_kayitlari` tablosu mevcuttu.
- Manager Customer ID: `9530538405`. İstek alanları: `descriptive_name=ads_oauth Test`,
  `currency_code=TRY`, `time_zone=Europe/Istanbul`.
- Çağrı öncesi snapshot: `baglanmis_hesaplar` **1**, `api_hata_kayitlari` **0**.
- Gerçek sonuç: hesap oluşturulmadan `PERMISSION_DENIED` ile başarısız oldu; yeni customer
  resource name/customer ID dönmedi. Başarılı senaryodaki ek işlemler yapılmadı; yeni hesap
  keşfi/doğrulaması, kampanya veya başka mutate çağrısı yapılmadı.
- `api_hata_kayitlari` içindeki gerçek ve güvenli satır:

  ```text
  no=1 | api_cagrisi=createCustomerClient | exception_sinifi=Google\ApiCore\ApiException | status=PERMISSION_DENIED | kod=7 | mesaj=The caller does not have permission | hatalar=NULL | request_id=NULL | kayit_tarihi=2026-09-05 22:05:48
  ```

- Satırda credential, token, request/response gövdesi veya hassas metadata bulunmuyor.
  `hatalar` ve `request_id` gerçek sonuçta mevcut değildi; bu nedenle `NULL` kaldı.
- Çağrı sonrası doğrulama: `baglanmis_hesaplar` **1** olarak değişmedi; `api_hata_kayitlari`
  **1** oldu. Aktif OAuth kaydı (`no=2`, Manager `9530538405`) ve refresh token doluluğu
  değişmedi; token değeri loglanmadı. Geçici çağrı scripti silindi.
- **Net sonraki adım:** Yeni API/mutate çağrısı yapılmadan önce Manager hesabının Google Ads
  tarafındaki müşteri oluşturma yetkisi, developer token erişimi ve hesap uygunluğu yetkili
  hesap yöneticisiyle kontrol edilmelidir. `PERMISSION_DENIED` giderilmeden farklı parametreyle
  tekrar deneme yapılmamalıdır. Yetki/uygunluk doğrulanırsa yeni çağrı için ayrıca onay alınmalıdır.
- **Not (2026-09-17):** Bu yaklaşım tamamen terk edildi (ARCHITECTURE.md §7.1); `createCustomerClient`
  yeniden denenmeyecek. Kullanıcı kendi Google Ads hesabını kendisi açar, sistem yalnızca OAuth ile
  bağlar. Kampanya listeleme, kullanıcının gerçek/var olan hesabıyla yeni bir promptta test edilecek.

### PROMPT-13 sonucu — `createCustomerClient` hata teşhisi

- **Kesin sınıflandırma:** **Durum 3 — hata bilgisi hâlâ elde edilemiyor.** PROMPT-12'de
  gerçek çağrının tam bir kez yapıldığı ve `Google\\ApiCore\\ApiException` ile başarısız olduğu
  doğrulanabiliyor; ancak bu sınıf adı tek başına Google Ads hata nedenini belirlemez.
- PROMPT-12'ye ait geçici test dosyası, ham exception dökümü veya ayrı bir güvenli log çalışma
  ağacında ve erişilebilir Git geçmişinde bulunmadı. `md/DURUM.md` içinde yalnızca exception
  sınıfı kaydedilmiş; gerçek `status`, numeric `code`, exception `message`, metadata veya
  request ID saklanmamış.
- SDK v34.0.0 / Google Ads API V25 incelemesine göre `GoogleAdsException`,
  `Google\\ApiCore\\ApiException` sınıfından türemektedir. Google Ads yapısal hata bilgisi
  yalnızca `GoogleAdsException` üzerinden `getGoogleAdsFailure()` ile; request ID ise
  `getRequestId()` ile alınabilir. Genel `ApiException` için güvenli accessor'lar
  `getStatus()`, `getCode()`, `getMessage()`, `getMetadata()` ve `getBasicMessage()`'dir.
- Credential içermeyen sentetik exception testi bu accessor'ların SDK'da çalıştığını doğruladı;
  testte gerçek Google API çağrısı, OAuth işlemi veya mutate yapılmadı. Bu test PROMPT-12'nin
  gerçek status/code/message/request ID değerlerini üretmez.
- Mevcut bağlayıcı, exception'ı `Throwable` olarak yakalayıp güvenli kategori mesajına sarıyor;
  PROMPT-12 sırasında ham exception alanları kalıcı olarak kaydedilmediği için geçmiş çağrının
  gerçek Ads hata kodu ve mesajı sonradan çıkarılamaz.
- **Gerçek hata kodu:** Elde edilemedi. `PERMISSION_DENIED`, `CUSTOMER_NOT_ELIGIBLE`,
  `AUTHORIZATION_ERROR`, transport/gRPC status, authentication veya 1.000 USD uygunluk şartı
  sonucu olarak yazılmadı ve tahmin edilmedi.
- **Güvenli hata mesajı:** PROMPT-12'nin gerçek güvenli hata mesajı mevcut kayıtta yoktur;
  bu nedenle yeni bir mesaj uydurulmadı. Gerçek API mesajı credential içeriyorsa rapora
  kopyalanmamalıdır.
- **Request ID:** Elde edilemedi. `GoogleAdsException`/failure nesnesi veya metadata mevcut
  olmadığından request ID geriye dönük okunamaz.
- Bu prompt kapsamında `createCustomerClient` tekrar çağrılmadı; yeni müşteri hesabı, kampanya,
  reklam, bütçe, ad group veya keyword oluşturulmadı. DB, OAuth kaydı ve refresh token
  değiştirilmedi; Meta işlemi yapılmadı.
- **Sonraki teknik adım:** Google Ads Manager/developer token tarafındaki yetki ve uygunluk
  durumu manuel olarak netleştirilmeli. Herhangi bir gelecekteki izinli API teşhisinde, çağrı
  yapılmadan önce yalnızca allowlist edilmiş exception class/status/code, credential içermeyen
  sanitize edilmiş message ve varsa request ID güvenli şekilde alınmalıdır. Bu promptta
  `createCustomerClient` yeniden çalıştırılmayacaktır.

### PROMPT-14 sonucu — `createCustomerClient` hatasını kalıcı ve güvenli yakalama altyapısı

- **Durum:** Tamamlandı; gerçek Google Ads API/mutate çağrısı yapılmadı.
- `php/baglayici/google-ads-baglayici.php` içine `google_ads_hata_ayristir()` ve
  `google_ads_hata_kaydi_yaz()` eklendi. `GoogleAdsException` için `getGoogleAdsFailure()` içindeki
  tüm dolu V25 error-code oneof alt tipleri dinamik olarak alt tip adı + enum adı biçiminde çıkarılıyor;
  `getRequestId()`, status ve numeric code ayrıca saklanıyor. Genel `ApiException` için yalnızca
  status, code ve `getBasicMessage()` kullanılıyor.
- Hata mesajı kayda alınmadan önce e-posta, Authorization/Bearer değeri, refresh/access/developer
  token, client secret/ID, API key, password ve yapılandırmadaki Google değerleri `[redacted]` ile
  maskeleniyor. Metadata, credential, token, Authorization header ve tam request/response body
  okunmuyor veya kaydedilmiyor. Yapısal Google Ads hataları yalnızca sanitize edilmiş
  `error_code` ve `message` alanları olarak JSON kaydediliyor.
- Yeni `api_hata_kayitlari` tablosu üzerinden tarih/saat, API çağrısı, exception sınıfı,
  status/code, sanitize edilmiş mesaj, yapılandırılmış Ads hata listesi ve request ID kalıcı olarak
  saklanıyor. Log insert'i yalnızca exception catch akışlarında çalışıyor; normal başarılı akışa
  ekstra DB işlemi eklenmedi. Log yazma başarısız olursa kullanıcıya dönen genel JSON bozulmuyor.
- Mevcut Google Ads catch noktaları `GoogleAdsClientBuilder::build`, `CustomerService::listAccessibleCustomers`,
  `GoogleAdsService::search` ve Manager `customer_client` search hatalarında yeni log mekanizmasını
  çağıracak şekilde güncellendi. `api/index.php` beklenmeyen exception'lar için son güvenlik ağı olarak
  aynı sanitize edilmiş logger'ı kullanıyor; mevcut `{return, mesaj}` JSON biçimi korunuyor.
- **DB şema değişikliği vardır:** `db/sema.sql` dosyasına ayrı `api_hata_kayitlari` tablosu eklendi;
  mevcut `baglanmis_hesaplar`, OAuth kayıtları ve `senkron_kayitlari` değiştirilmedi. Bu tablo için
  üretim veritabanında ayrıca şema uygulanmalıdır. Bu oturumda canlı DB'ye şema uygulama/doğrulama
  işlemi tamamlanamadı; bir sonraki gerçek API çağrısından önce `db/sema.sql` içindeki yeni tablo
  uygulanmalıdır. Logger, tablo henüz yoksa kullanıcı response'unu bozmayacak şekilde hatayı yutar.
- **Değişen dosyalar:** `php/baglayici/google-ads-baglayici.php`, `api/index.php`,
  `db/sema.sql` ve bu durum kaydı olan `md/DURUM.md`.
- Credential içermeyen sentetik V25 `GoogleAdsException` ve genel `ApiException` testi; extraction,
  redaction, iki log INSERT'i ve transaction rollback ile başarılı oldu. Geçici test dosyası workspace'e
  eklenmedi ve test kaydı kalıcı bırakılmadı.
- Bu promptta `createCustomerClient` veya başka bir mutate çağrısı tekrarlanmadı; mevcut DB/OAuth/
  refresh token kayıtları değiştirilmedi, kampanya/reklam/bütçe/ad group/keyword ve Meta işlemi yapılmadı.

### PROMPT-09 sonucu

- Google Ads Manager Account `9530538405` üzerinden gerçek ve read-only
  `GoogleAdsService.search()` çağrısı yapıldı. V25 GAQL sorgusu `CustomerClient` için
  `id`, `descriptive_name`, `manager`, `status`, `currency_code`, `time_zone` ve `level`
  alanlarını istedi.
- SDK V25'in `withLoginCustomerId()` mekanizmasıyla mevcut Manager hesabı `9530538405`
  kullanılarak yapılan çağrı başarılı oldu. Aynı sorgu `login-customer-id` gönderilmeden de
  gerçek API'de başarılı oldu ve 1 kayıt döndürdü; bu nedenle bu akışta `login-customer-id`
  zorunlu değildir. Her iki denemede de farklı veya varsayımsal bir customer ID kullanılmadı.
- Gerçek API sonucu: **1** `CustomerClient` kaydı bulundu — `9530538405`, `ads_oauth`,
  `manager=true`, `status=ENABLED`, `currency_code=TRY`, `time_zone=Europe/Istanbul`,
  `level=0`.
- Manager → child customer ilişkisi mevcut `baglanmis_hesaplar` şemasında parent alanı
  bulunmadığı için DB'ye yeni ilişki/alan yazılmadı. Şema değiştirilmedi. Keşif sonucu yalnızca
  kontrollü JSON response olarak döndürüldü.
- Gerçek çağrı hatası oluşmadı. Önceki PROMPT-08 bulgusu olan `4150407743` hesabı
  `CUSTOMER_NOT_ENABLED` nedeniyle hâlâ ayrı bir Google Ads etkinlik/yetki konusudur.
- Endpoint oturum koruması doğrulandı: oturumsuz istek `{"return":0,"mesaj":"Oturum gerekli."}`
  döndürdü. Oturumlu endpoint başarıyla çalıştı.
- Çağrı öncesi/sonrası DB kontrolünde mevcut kayıt sayısı **1** kaldı; `no=2`,
  `harici_kimlik=9530538405`, aktif durum ve şifreli refresh token korundu. Refresh token
  müşteri sonuçlarına kopyalanmadı ve response/log'a yazılmadı.
- Bu promptta kampanya, reklam, bütçe, teklif, hedefleme veya başka bir mutate çağrısı
  yapılmadı. Meta OAuth/API/veri modeli/servis/arayüz uygulanmadı.
- `ads_oauth` projesinin mevcut geliştirme kapsamı Google Ads'tir. Meta entegrasyonu bu
  projenin tamamlanma kriteri değildir; ayrı bir geliştirme fazı/konusu olarak ele alınacaktır.

### PROMPT-10 sonucu

- **Durum:** Beklemede; gerçek bir non-manager müşteri hesabı bulunmadı.
- Gerçek V25 read-only kontrolünde erişilebilir hesap sayısı **1** oldu: Manager
  `9530538405` (`ads_oauth`, `manager=true`).
- Manager `9530538405` için `CustomerClient` sorgusu **1** kayıt döndürdü; gerçek
  `manager=false` child/non-manager hesap sayısı **0** oldu.
- Bu nedenle kampanya listeleme için kullanılabilecek gerçek bir customer ID yoktur.
  Kampanya GAQL sorgusu, `google-kampanyalari` endpoint'i ve yeni kampanya servisi
  oluşturulmadı. Sahte customer ID/kampanya üretilmedi ve API sonucu başarı gibi
  gösterilmedi.
- Manager hesabında kampanya varmış varsayılmadı; Google Ads hesap oluşturma/linkleme
  işlemi denenmedi.
- Oturumlu mevcut müşteri keşif endpoint'i başarıyla çalıştı; oturumsuz istek
  `{"return":0,"mesaj":"Oturum gerekli."}` döndürdü.
- Çağrı öncesi/sonrası DB kayıt sayısı **1** kaldı; OAuth kaydı `no=2`,
  `harici_kimlik=9530538405`, aktif durumu ve refresh token hash'i değişmedi.
- Kampanya, reklam grubu, reklam, bütçe, teklif, keyword veya herhangi bir mutate
  çağrısı yapılmadı. DB şeması, UI ve Meta kodu değiştirilmedi.
- Doğrulama sonuçları: vendor dışı 28 PHP dosyasında lint başarılı, Composer validation
  başarılı, `git diff --check` başarılı ve Google Ads mutate/destructive SQL taramaları
  temiz.

### PROMPT-11 sonucu

- **Durum:** Beklemede; kod tarafında yeni geliştirme yapılmadı.
- Gerçek V25 salt-okunur API doğrulamasında `listAccessibleCustomers()` sonucu yine 2
  customer resource name oldu: `customers/4150407743` ve `customers/9530538405`.
  `4150407743` hesabının `CUSTOMER_NOT_ENABLED` durumu korunmaktadır.
- Manager `9530538405` için gerçek `CustomerClient` sorgusu 1 kayıt döndürdü. Bu kayıt
  Manager hesabının kendisidir: `manager=true`, `status=ENABLED`, `level=0`.
  Gerçek `manager=false` child/non-manager müşteri hesabı sayısı: **0**.
- Google Ads ürün fonksiyonlarının gerçek API ile test edilebilmesi için erişilebilir ve
  yetkili bir **non-manager müşteri hesabı** gereklidir. Manager Account `9530538405`
  tek başına kampanya işlemleri için test müşterisi olarak kullanılmayacaktır.
- Bu nedenle Google Ads kampanya geliştirmesine geçilmedi; yeni endpoint, kampanya
  servisi, kampanya GAQL sorgusu veya campaign API çağrısı eklenmedi. Kampanya oluşturma,
  bütçe, ad group, reklam, keyword ve herhangi bir mutate işlemi yapılmadı.
- Mevcut sistem değiştirilmedi: DB şeması ve `kampanyalar` tablosu korunmuştur; DB'ye
  yeni kayıt yazılmadı, mevcut `baglanmis_hesaplar` kaydı korunmuştur. Oturumlu/oturumsuz
  endpoint kontrolleri yapıldı; oturumsuz erişim reddedildi.
- Çağrı öncesi/sonrası `baglanmis_hesaplar` kayıt sayısı **1** kaldı. OAuth kaydı `no=2`,
  Manager ID `9530538405`, aktif durumu ve refresh-token hash'i değişmedi. Gerçek token
  veya secret çıktıya yazılmadı.
- Meta tarafında hiçbir çalışma yapılmadı; Meta bu projenin tamamlanma kriteri değildir
  ve ayrı bir geliştirme fazı olarak korunmaktadır.
- Sonraki kodlama adımı, gerçek ve yetkili non-manager müşteri hesabı erişilebilir
  olduğunda başlayacaktır: (1) müşteri hesabını doğrulamak, (2) yalnızca bu hesap için
  kampanyaları salt-okunur listelemek, (3) sonrasında kontrollü mutate işlemlerine
  geçmek.

---

## 2. Tamamlanan Adımlar

- [x] Proje fikri ve gerekçesi netleştirildi (Google/Meta reklam kurulum karmaşıklığını basitleştirme).
- [x] Çalışma modeli belirlendi: SaaS/OAuth modeli (ajans modeli değil).
- [x] Rakip analizi yapıldı (Madgicx, Revealbot vb. — farklı segment, tehdit değil).
- [x] Google Ads API ve Meta Marketing API resmi dokümantasyon bağlantıları paylaşıldı.
- [x] Google'ın "hangi ürünü kullanmalıyım" tablosu incelendi, API yolu doğrulandı
      (not: Türkçe sayfadaki "kullanma" ifadesi çeviri hatasıydı, orijinali "Use the Google Ads API").
- [x] OAuth izin akışının adım adım nasıl işlediği netleştirildi.
- [x] Websistem (Kort'un mevcut CMS'i) incelendi, bu projenin **modül değil, bağımsız sistem**
      olması gerektiğine karar verildi (gerekçe: hedef kitle Websistem kullanmayabilir, composer
      bağımlılık çakışma riski, cron/worker doğası CMS modül yapısına uymuyor, güvenlik izolasyonu).
- [x] Dosya yapısı taslağı çıkarıldı (bkz. ARCHITECTURE.md, ~34 dosya, SQL hariç).
- [x] Çalışma yöntemi belirlendi: genel mimari dosyası + ihtiyaç oldukça tek tek prompt +
      bu durum takip dosyası.
- [x] PROMPT 00 uygulandı: klasör/dosya iskeleti oluşturuldu, gerçek mantık eklenmedi.
- [x] PROMPT 01 uygulandı: `php/` altındaki klasör ve dosya adları küçük harfe çevrildi;
      `composer.json` içindeki PSR-4 `autoload` bloğu kaldırıldı.
- [x] PROMPT 02 uygulandı: PROMPT 01'in devamı/düzeltmesi olarak `php/` altındaki tüm klasör
      ve dosya adlarının küçük harfli son hali doğrulandı; ek yeniden adlandırma gerekmedi.
- [x] PROMPT 03 uygulandı: `db/sema.sql` oluşturuldu, temel config/veritabanı/oturum/şifreleme
      mantığı yazıldı ve `.env.sample` içindeki `DB_NAME` değeri `ads_oauth` olarak ayarlandı.
- [x] PROMPT-14 uygulandı: Google Ads/API exception'ları credential/body kaydetmeden ayrıştıran,
      sanitize eden ve `api_hata_kayitlari` tablosuna kalıcı yazan güvenli loglama altyapısı eklendi;
      sentetik GoogleAdsException/ApiException extraction ve rollback testi geçti.
- [x] Gerçek `.env` dosyası oluşturuldu ve veritabanı bilgileriyle dolduruldu.
- [x] PROMPT 04 uygulandı: İlk Composer/vendor altyapısı `google/apiclient` ile kuruldu;
      `vendor/` ve `vendor/autoload.php` oluşturuldu. Google OAuth akışı henüz yazılmadı.
- [x] PROMPT 05 uygulandı: `google/apiclient` kaldırıldı, `googleads/google-ads-php:^34.0`
      eklendi; Composer bağımlılıkları yeniden çözüldü, `composer.lock` ve `vendor/` güncellendi.
      Google Ads PHP client'ın `vendor/autoload.php` üzerinden yüklenmesi doğrulandı; PHP
      `8.2.12` olarak kontrol edildi. OAuth koduna ve DB dosyalarına dokunulmadı.
- [x] PROMPT 06 Revize tamamlandı: OAuth HTTP girişi yalnızca `api/index.php` üzerinden
      `oauth-baslat` ve `oauth-donus` action'larıyla çalışacak şekilde eklendi; yeni OAuth HTTP
      endpoint dosyası oluşturulmadı.

## 2.1. PROMPT-06 Revize Sonucu

- **PROMPT 06 Revize tamamlandı mı?** Evet. Değişen dosyalar:
  `api/index.php`, `php/oauth/google-oauth.php` ve bu durum kaydı olan `md/DURUM.md`.
  `api/oauth-baslat.php` ile `api/oauth-donus.php` mevcut iskelet dosyalarıdır; bu görevde
  oluşturulmadı, değiştirilmedi ve dispatch için kullanılmıyor.
- **OAuth başlangıç akışı:** Giriş yapılmış session'daki `sahip_no` kontrol edilir; 32 byte
  `random_bytes()` state üretilip session'a yazılır; Google Ads scope, `offline` erişim ve
  `consent` prompt ile authorization URL JSON response içinde döner.
- **OAuth callback akışı:** `sahip_no`, state ve code/error parametreleri kontrol edilir.
  Doğrulanan state token exchange'den önce session'dan silinir. Authorization code, mevcut
  Composer paketindeki `Google\Auth\OAuth2` ile token'a çevrilir ve refresh token zorunlu
  tutulur. Google Ads PHP client'ın mevcut `OAuth2TokenBuilder` sınıfı refresh-token
  credential'ının kurulabilirliğini doğrulamak için kullanılır; Google Ads API çağrısı yapılmaz.
- **State/CSRF durumu:** State session'da tutulur, `hash_equals()` ile karşılaştırılır,
  geçersiz state reddedilir ve doğrulanmış state tek kullanımlık olarak tüketilir.
- **Refresh token şifreleme durumu:** Refresh token yalnızca mevcut `sifrele()` mekanizmasıyla
  AES-256-CBC ciphertext'e dönüştürülür; access token kalıcı yazılmaz. Token response'a,
  HTML'e veya log'a eklenmez.
- **Database kayıt durumu:** Mevcut `baglanmis_hesaplar` tablosu kullanılır; `sahip_no` session
  kullanıcısından, `platform` `google` değerinden alınır. `harici_kimlik` NULL bırakılır;
  mevcut Google kaydı varsa güncellenir, yoksa eklenir. Gerçek DB insert/update testi aşağıdaki
  gerçek Google callback'i ve refresh token olmadığı için çalıştırılmadı; PDO veritabanı bağlantısı
  başarılıdır.
- **Local OAuth test sonucu:** PHP syntax kontrolü, Composer autoload ve `OAuth2TokenBuilder`
  sınıfı kontrolü başarılıdır. Authorization URL, scope, offline/consent, config'ten alınan
  redirect URI, session state, geçersiz state reddi, state tüketimi ve şifreleme round-trip'i
  doğrulandı. PHP built-in server üzerinden `api/index.php?islem=oauth-baslat` isteği JSON
  standardında unauthenticated hata döndürdü. Vendor dışı PHP dosyalarının lint kontrolü başarılıdır.
- **Teşhis sonucu:** Önceki kodda OAuth redirect URI
  `http://localhost/ads_oauth/api/index.php?islem=oauth-donus` olarak hardcoded'dı. Bu nedenle
  production isteği bu URI ile başlatıldığında Google callback'i production'a dönemez ve
  `redirect_uri_mismatch` ile uygulamaya hiç ulaşmayabilir. Canlı kontrolünde `https://n0n1.tr/`
  WebSistem ana sayfası, `https://n0n1.tr/ads_oauth/` ve beklenen API yolu ise 404 döndürdü;
  dolayısıyla n0n1.tr için çalışır callback yolu henüz doğrulanmış değildir.
- **Uygulanan düzeltme:** `GOOGLE_OAUTH_REDIRECT_URI` artık `.env` içinde zorunlu bir ayardır;
  authorization URL ve token exchange aynı ayarı kullanır. URI HTTPS olmalı, yalnızca localhost
  geliştirmesinde HTTP'ye izin verilir ve callback query'si `islem=oauth-donus` içermelidir.
  Yerel `.env` mevcut localhost callback'iyle dolduruldu; production `.env` ise uygulamanın
  gerçekten yayınlandığı, Google Cloud'da birebir Authorized redirect URI olarak tanımlanmış
  callback adresiyle doldurulmalıdır. Gerçek authorization code, refresh token veya DB ciphertext
  bu teşhiste elde edilmedi.
- **Sonraki adım:** Uygulamayı canlıda gerçek bir HTTPS path altında yayınla, aynı callback URI'yi
  production `.env` ve Google Cloud Authorized redirect URIs'e birebir ekle, ardından giriş yapılmış
  browser session ile gerçek OAuth akışını tamamla; bunun sonrasında `baglanmis_hesaplar`
  kaydındaki ciphertext doğrulanabilir.

- **PROMPT-07 durumu: Tamamlandı.** Minimum site sahibi kayıt/giriş altyapısı oluşturuldu.
  `site_sahipleri` tablosu kullanıldı; yeni tablo veya şema değişikliği yapılmadı. E-posta
  normalize/doğrulama, duplicate e-posta mesajı, `password_hash()` / `password_verify()`,
  mevcut session fonksiyonları, login sonrası `session_regenerate_id(true)`, logout ve
  oturum kontrolü eklendi. Kayıt ve giriş API action'ları `api/index.php` üzerinden JSON
  standardında çalışıyor; kök `index.php` ve mevcut tema dosyaları gerçek browser form akışını
  sağlıyor.
- **PROMPT-07 değişen dosyalar:** `index.php`, `api/index.php`,
  `php/servis/kullanici-servisi.php`, `tema/giris.php`, `tema/panel/anasayfa.php`,
  `tema/layout/header.php`, `tema/layout/footer.php`, `md/DURUM.md`.
- **PROMPT-07 test sonucu:** PHP syntax/lint, Composer autoload, PDO veritabanı bağlantısı,
  kayıt, normalize edilmiş e-posta, plaintext olmayan password hash, başarılı login,
  başarısız login için genel hata, duplicate e-posta, login sonrası session ID yenileme,
  `sahip_no` session kontrolü, logout, logout sonrası session temizliği ve login olmadan
  Google OAuth başlangıcının reddedilmesi doğrulandı. Giriş yapılmış HTTP session ile OAuth
  başlangıcının authorization URL üretmesi ve sabit redirect URI'yi kullanması da doğrulandı.
- **PROMPT-07 başarısız test:** Yok. Geçici test kullanıcısı ve test dosyaları temizlendi;
  şifre, hash veya credential bu dosyaya yazılmadı.
- **PROMPT-07 sonraki adım:** Gerçek login olmuş browser session ile Google consent ekranını
  tamamlamak, callback ve şifreli `baglanmis_hesaplar.refresh_token_sifreli` kaydını doğrulamak.

## 3. Bekleyen / Henüz Yapılmayanlar

- [ ] Google Ads API Developer Token başvurusu (Kort tarafında yapılacak).
- [ ] Google OAuth anahtarlarının (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) alınması.
- [ ] Meta Marketing API App Review süreci (Kort tarafında yapılacak).
- [ ] Meta OAuth anahtarlarının (`META_APP_ID`, `META_APP_SECRET`) alınması.
- [x] Google OAuth akışı (`php/oauth/google-oauth.php`) ve `api/index.php` dispatch'i tamamlandı;
      Meta OAuth hâlâ yazılmadı.
- [ ] Baglayici (adapter) katmanı prompt dosyalarının yazılması.
- [ ] Servis katmanı prompt dosyalarının yazılması.
- [ ] Cron/senkron mekanizmasının prompt dosyasının yazılması.
- [ ] Panel/tema dosyalarının prompt dosyalarının yazılması.

## 4. Karar Günlüğü (Önemli Mimari Kararlar ve Gerekçeleri)

| Tarih | Karar | Gerekçe |
|---|---|---|
| 2026-09-03 | Ajans modeli değil, SaaS/OAuth modeli seçildi | Hukuki/mali yük istenmiyor, kullanıcı kendi hesabını bağlar |
| 2026-09-03 | Websistem modülü değil, bağımsız sistem | Hedef kitle CMS'ten bağımsız, composer/cron/güvenlik izolasyonu gerekiyor |
| 2026-09-03 | Türkçe eylem-bazlı dosya isimlendirmesi korundu | Kort'un mevcut proje konvansiyonuyla tutarlılık |
| 2026-09-03 | Claude kodu doğrudan düzenlemez, düzeltme promptu yazar | Kod stili tutarlılığı, tek AI üzerinden kod yazımı korunur |
| 2026-09-04 | api/ JSON formatı `{return, mesaj}` olarak belirlendi | Websistem `php/class_f/` ve `api/` koduna bakılarak netleştirildi (Kort'un alışık olduğu format) |
| 2026-09-04 | `php/` altındaki klasör ve dosya adları küçük harfe çevrildi | Dosya yolu tutarlılığı ve platformlar arası adlandırma uyumu |
| 2026-09-04 | Kendi sınıfları için Composer PSR-4 autoload kullanılmamasına karar verildi | Manuel `require_once` stratejisi kullanılacak |
| 2026-09-04 | PROMPT 02 ile `php/` altındaki tüm adlandırmaların küçük harfli son hali doğrulandı | PROMPT 01'in devamı/düzeltmesi; ek yeniden adlandırma gerekmedi |
| 2026-09-04 | Nihai hedef çoklu platform olsa da geliştirme sıralı ilerleyecek; önce Google Ads altyapısı tamamlanacak | Meta daha sonra eklenecek, bu aşamada yalnızca `google` platform değeri kullanılacak |
| 2026-09-04 | DB engine olarak InnoDB seçildi | Foreign key desteği ve şifreli token'ların transactional bütünlüğü gerekiyor |
| 2026-09-04 | Gerçek `.env` dosyası DB bilgileriyle oluşturuldu | Yerel veritabanı bağlantı ayarları hazırlandı; hassas değerler repora yazılmayacak |
| 2026-09-05 | `ads_oauth` projesinin mevcut geliştirme kapsamı Google Ads ile sınırlandırıldı; Meta ayrı faza bırakıldı | Google Ads tamamlandığında proje BİTTİ kabul edilecek; Meta OAuth/API/veri modeli/servis/arayüz ve ortak soyutlama bu aşamada uygulanmayacak |
| 2026-09-04 | Google ve Meta anahtarları henüz alınmadı; Google Ads PHP client olarak `googleads/google-ads-php:^34.0` seçildi ve `google/apiclient` kaldırıldı | Google Ads API entegrasyonu için resmi PHP client kullanılacak; OAuth ve gerçek API bağlantısı sonraki aşamaya bırakıldı |
| 2026-09-05 | PROMPT-10 kapsamında gerçek non-manager müşteri hesabı bulunmadığı için kampanya listeleme durduruldu | Manager hesabında kampanya varmış varsayılmayacak; sahte customer ID/kampanya üretilemeyecek, child hesap erişilebilir olduğunda yeniden değerlendirilecek |
| 2026-09-17 | Sistemin gerçek kapsamı (kişisel kullanım + davetli 1-2 kişi) netleşti; `createCustomerClient` ile Manager altında otomatik hesap oluşturma yaklaşımı terk edildi | Her kullanıcı kendi Google Ads hesabını manuel açacak, sistem sadece OAuth ile bağlayacak; PROMPT-10–15 hattı kapatıldı, kampanya listeleme artık kullanıcının kendi (gerçek, var olan) hesabıyla test edilecek |
| 2026-09-18 | Google Ads API erişim seviyesi Basic'e yükseltildi (Brand Verification + sensitive scope OAuth app verification tamamlandı, resmi Cloud Console > Google Ads API Overview sayfasından başvuruldu) | Explorer seviyesindeki (2.880 günlük production işlem) kısıtlama kalktı; günlük limit 15.000'e çıktı. Bu, önceki PERMISSION_DENIED / createCustomerClient tıkanıklığıyla ilgisizdir — o yaklaşım zaten ayrı bir kararla terk edilmişti |

## 5. Açık Sorular / Netleşmemiş Noktalar

- Kampanya sihirbazında kaç adım/soru olacak henüz detaylandırılmadı.
- Senkron cron'unun çalışma sıklığı (15dk/30dk/saatlik) henüz kararlaştırılmadı.

## 6. Prompt Dosyaları İzleme Tablosu

| # | Dosya | Durum | Not |
|---|---|---|---|
| 00 | `md/00-iskelet-kurulum.md` | Tamamlandı | Sadece klasör/dosya + docblock oluşturuldu, mantık eklenmedi |
| 01 | `md/01-isimlendirme-duzeltme.md` | Tamamlandı | `php/` altındaki adlar küçültüldü, Composer PSR-4 `autoload` bloğu kaldırıldı |
| 02 | PROMPT 01 devamı/düzeltmesi | Tamamlandı | `php/` altındaki tüm klasör ve dosya adlarının küçük harfli son hali doğrulandı; ek yeniden adlandırma gerekmedi |
| 03 | `md/03-temel-altyapi.md` | Tamamlandı | İlk SQL şeması ve Google öncelikli temel altyapı fonksiyonları eklendi |
| 04 | PROMPT-04 — Composer / Vendor Altyapısının Kurulması | Tamamlandı | İlk Composer/vendor altyapısı `google/apiclient` ile kuruldu; OAuth akışı henüz yazılmadı |
| 05 | PROMPT-05 — Google Ads PHP Client geçişi | Tamamlandı | `google/apiclient` kaldırıldı; `googleads/google-ads-php:^34.0` eklendi, `composer.lock`/`vendor/` güncellendi ve `GoogleAdsClient` autoload doğrulandı |
| 07 | PROMPT-07 — Minimum Site Kullanıcısı Login/Register | Tamamlandı | `site_sahipleri` üzerinde register/login/logout, session fixation önleme, password hashing ve browser test akışı eklendi; Google OAuth yeniden yazılmadı |
| 08 | PROMPT-08 — Google Ads API İlk Bağlantı ve Hesap Keşfi | Tamamlandı, 1 hesap kaydedildi | `google-hesap-kesfet` action'ı gerçek `listAccessibleCustomers()` ve V25 müşteri temel bilgi sorgusuyla çalıştı. 2 kaynak bulundu; `9530538405` kaydedildi, `4150407743` `CUSTOMER_NOT_ENABLED` nedeniyle atlandı. Developer Token mevcut; erişim seviyesi API yanıtından doğrulanamadı. |
| 08.1 | PROMPT-08.1 — OAuth Kaydı Veri Güvenliği Düzeltmesi | Uygulandı, canlı kayıt doğrulaması yapılamadı | Test cleanup için repo içinde silme mekanizması bulunmadı; hesap keşfi placeholder seçimi artık yalnızca dolu şifreli refresh token taşıyan gerçek OAuth kaydını kullanıyor. Google Ads API/OAuth tekrar çalıştırılmadı. |
| 09 | PROMPT-09 — Google Ads Müşteri Hesabı Keşfi ve Meta Sınırının Sabitlenmesi | Tamamlandı | Manager `9530538405` üzerinden V25 `CustomerClient` read-only keşfi gerçek API'de başarılı oldu; 1 kayıt bulundu. `baglanmis_hesaplar` şeması değiştirilmedi. Meta ayrı faz olarak kapsam dışı sabitlendi. |
| 10 | PROMPT-10 — Google Ads Kampanya Listeleme | Beklemede — gerçek non-manager müşteri hesabı yok (Terk edildi — bkz. 2026-09-17 karar notu, ARCHITECTURE.md §7.1) | Gerçek V25 kontrolünde yalnızca Manager `9530538405` ve 0 child/non-manager hesap bulundu. Kampanya endpoint'i/servisi yazılmadı; sahte veri, mutate, DB/UI/Meta değişikliği yapılmadı. |
| 11 | PROMPT-11 — Google Ads Test Müşteri Hesabı Geçişi | Beklemede — gerçek non-manager müşteri hesabı yok (Terk edildi — bkz. 2026-09-17 karar notu, ARCHITECTURE.md §7.1) | Read-only `listAccessibleCustomers()` ve Manager `9530538405` `CustomerClient` sonucu tekrar doğrulandı; 0 `manager=false` child hesabı bulundu. Kod geliştirilmedi, mevcut OAuth/DB/token korundu. |
| 12 | PROMPT-12 — Google Ads `createCustomerClient` kontrollü hesap oluşturma testi | Başarısız — proje beklemede (Terk edildi — bkz. 2026-09-17 karar notu, ARCHITECTURE.md §7.1) | Gerçek çağrı tam 1 kez gönderildi; `Google\\ApiCore\\ApiException` oluştu, yapılandırılmış Ads hata kodu/mesajı elde edilemedi ve tahmin edilmedi. Hesap oluşturulmadı, CustomerClient doğrulaması yapılmadı; DB/OAuth/token değişmedi, geçici test dosyası silindi. |
| 13 | PROMPT-13 — Google Ads `createCustomerClient` hata teşhisi | Durum 3 — gerçek hata bilgisi elde edilemedi | PROMPT-12'nin ham exception/log kaydı bulunmadığından status, code, güvenli message ve request ID geriye dönük çıkarılamadı. SDK V25 exception accessor'ları credential içermeyen sentetik testle doğrulandı; API/mutate tekrarlanmadı ve kalıcı kod değişikliği yapılmadı. |
| 14 | PROMPT-14 — `createCustomerClient` hatasını kalıcı ve güvenli yakalama | Tamamlandı — gerçek çağrı yapılmadı | `GoogleAdsException`/`ApiException` allowlist extraction, mesaj redaction, ayrı `api_hata_kayitlari` tablosuna kalıcı log ve mevcut catch entegrasyonu eklendi. `db/sema.sql` değişti; sentetik extraction/DB rollback testi geçti. |
| 15 | PROMPT-15 — `createCustomerClient` kontrollü gerçek çağrı ve kalıcı log | Başarısız — gerçek çağrı tam 1 kez yapıldı (Terk edildi — bkz. 2026-09-17 karar notu, ARCHITECTURE.md §7.1) | Canlı `api_hata_kayitlari` tablosu doğrulandı; Manager `9530538405` çağrısı `PERMISSION_DENIED` / `kod=7` ile başarısız oldu. `api_hata_kayitlari` satırı kalıcı yazıldı; `baglanmis_hesaplar` ve OAuth kaydı değişmedi, geçici script silindi. |
| 16 | OAuth genel hata teşhisi ve redirect URI düzeltmesi | Tamamlandı — production callback yolu beklemede | Hardcoded `localhost` redirect URI doğrulandı; n0n1.tr kökü WebSistem, `/ads_oauth/` ve beklenen API yolu 404. Redirect URI `.env` zorunlu ayara alındı; HTTPS/callback doğrulaması eklendi. Gerçek Google callback/token testi yapılmadı. |

### PROMPT-08 gerçek API test sonucu ve veri durumu

- Endpoint eklendi: `/api/index.php?islem=google-hesap-kesfet`.
- Oturumsuz test sonucu: `{"return":0,"mesaj":"Oturum gerekli."}`.
- Oturumlu gerçek test sonucu: `{"return":1,"mesaj":"Google Ads hesapları başarıyla keşfedildi.","hesaplar":[{"harici_kimlik":"9530538405","hesap_adi":"ads_oauth","yonetici":true}]}`.
- `GOOGLE_DEVELOPER_TOKEN` `.env` içinde mevcut ve gerçek API isteği çalıştı. Developer Token erişim seviyesi Basic/Standard olarak API yanıtından alınamadı; tahmin edilmedi.
- `listAccessibleCustomers()` sonucu: 2 kaynak — `customers/4150407743` ve `customers/9530538405`.
- `customers/4150407743` müşteri sorgusu: `PERMISSION_DENIED`, `authorizationError=CUSTOMER_NOT_ENABLED`; hesap etkin değil veya deaktive edilmiş olduğundan kaydedilmedi.
- `customers/9530538405` müşteri sorgusu başarılı: `customer.id=9530538405`, `descriptive_name=ads_oauth`, `customer.manager=true`; temel müşteri keşfi kaydedildi.
- Veritabanına kaydedilen hesap sayısı: **1**. `sahip_no=6`, `platform=google`, `harici_kimlik=9530538405`, `hesap_adi=ads_oauth`, `aktif=1`.
- Mevcut OAuth kaydı `no=2` placeholder olarak kullanıldı. Şifreli refresh token dolu kaldı; token değeri response/log'a yazılmadı ve yeni hesaba token kopyalanmadı.
- İkinci gerçek keşif çağrısında duplicate kontrolü başarılı oldu: kayıt sayısı ve `no=2` değişmedi.
- Oturumsuz test sonucu: `{"return":0,"mesaj":"Oturum gerekli."}`.
- `login-customer-id` ile ve onsuz gerçek V25 çağrıları başarılı oldu; bu akış için zorunlu
  olmadığı doğrulandı. Uygulama, Manager hesabını açık ve doğru bir değer olarak
  `withLoginCustomerId(9530538405)` ile kullanır.
- Composer autoload ve SDK v34.0.0 / V25 sınıf-metot kontrolleri başarılı; PHP lint kontrolleri başarılı.

### PROMPT-08.1 — OAuth Kaydı Veri Güvenliği Düzeltmesi

- **Durum:** Uygulandı; canlı gerçek kayıt doğrulaması yapılamadı.
- PROMPT-08 sırasında tespit edilen gerçek OAuth kaydının test cleanup tarafından silinebilmesi
  problemi ele alındı. Repo içinde bu silmeyi yapan production/test `DELETE` sorgusu bulunmadı;
  `api/hesap-sil.php` yalnızca iskelettir. Silme, önceki oturumdaki repo dışı geçici test
  cleanup'ından kaynaklanmıştır.
- Google hesap keşfi servisindeki placeholder adayları artık yalnızca `platform = 'google'`,
  `harici_kimlik IS NULL`/boş ve `refresh_token_sifreli` dolu olan kayıtlar arasından seçilir.
  Böylece gerçek OAuth placeholder kaydı korunur; token içermeyen test/boş kayıtlar gerçek
  OAuth bağlantısı gibi kullanılmaz.
- Genel veya `sahip_no` değerine bağlı cleanup eklenmedi. Mevcut gerçek kayıtlara yönelik
  `DELETE`, refresh token sıfırlama/değiştirme veya test yazma işlemi yapılmadı.
- Projede ayrı PHPUnit/Pest/test cleanup dosyası bulunmadığından yeni DB test dosyası eklenmedi.
  Test altyapısı oluşturulmadan gerçek kullanıcı verisiyle test yapılmadı.
- Senaryo A: Canlı doğrulama yapılamadı; önceki olay nedeniyle mevcut DB'de korunacak kayıt
  bulunmuyor. Kod koşulu, dolu refresh token şartını içeriyor.
- Senaryo B: Canlı DB'de test kaydı oluşturulmadı veya silinmedi; bu nedenle mevcut test verisi
  sayısı `0` ve production verisine dokunulmadı.
- Senaryo C/D: Gerçek API çağrısı yapıldı; SDK v34 `PagedListResponse` iterasyonu düzeltildi,
  müşteri bazlı `CUSTOMER_NOT_ENABLED` hatası diğer erişilebilir müşterilerin keşfini
  durdurmayacak şekilde ele alındı. Duplicate/placeholder mantığı transaction ve token-korumalı
  koşulla çalıştı.
- Google Ads API gerçek sonucu: `listAccessibleCustomers()` başarılı; 2 kaynak bulundu,
  1 müşteri temel bilgileriyle kaydedildi, 1 müşteri `CUSTOMER_NOT_ENABLED` nedeniyle atlandı.
- **Kampanya API çağrısı yapılmadı.** Yalnızca müşteri keşfi için `CustomerService` ve temel
  müşteri bilgisi için `GoogleAdsService.search()` kullanıldı.
- **DB şeması değişmedi.** OAuth callback, login/register, session, şifreleme, Composer ve
  Meta OAuth dosyaları değiştirilmedi.

*(Her yeni prompt dosyası oluşturulduğunda bu tabloya satır eklenir: numara, dosya adı, durum
[Bekliyor / AI'ye verildi / Tamamlandı / Revizyon gerekli], kısa not.)*

## 7. 2026-09-18 — `ads_oauth` → `ads-oauth` / OAuth callback inceleme bulguları

> Bu bölüm yalnızca inceleme sonucudur. Bu inceleme sırasında PHP, config, `.env`,
> `.env.sample`, Composer veya klasör adında düzeltme/yeniden adlandırma yapılmadı.

- Gerçek callback formatı router üzerinden üretilmektedir: **b)**
  `.../api/index.php?islem=oauth-donus`. `php/oauth/google-oauth.php` içinde
  `google_oauth_redirect_uri_al()` değeri `config('GOOGLE_OAUTH_REDIRECT_URI')` üzerinden
  alır; authorization URL oluşturulurken satır 94'te, token exchange sırasında satır 161'de
  aynı değer kullanılır. Kodda callback path'ini domain ile birleştiren sabit bir parça yoktur.
- `api/oauth-baslat.php` ve `api/oauth-donus.php` yalnızca iskelet dosyalardır. Gerçek HTTP
  dispatch'i `api/index.php` içindeki `oauth-baslat` ve `oauth-donus` action'larıdır.
- `.env.sample` satır 4'teki örnek router formatı b ile yapısal olarak uyumludur; ancak
  `https://example.com/ads_oauth/api/index.php?islem=oauth-donus` değeri production için
  doğru değildir: domain placeholder'dır ve klasör adı eski `ads_oauth` biçimindedir.
- `php/config.php` satır 55-67 arasında `GOOGLE_OAUTH_REDIRECT_URI` zorunlu anahtar olarak
  doğrulanmaktadır; `.env` yoksa veya anahtar eksik/boşsa hata fırlatılır.
- Yerel `.env` içinde callback değeri router formatındadır, fakat path hâlâ `ads_oauth` ve
  `localhost` kullanmaktadır. Ayrıca satır 10'da `SIFRELEME_ANAHTARI` değerinin sonuna
  callback ayarının yapışmış olduğu, satır 11'de de callback ayarının tekrar bulunduğu görüldü.
  Hassas değer rapora kopyalanmadı; bu biçimsel bozukluk henüz düzeltilmedi.
- `ads-outh` yazımı proje dosyalarında bulunmadı. Production'daki önceki yanlış yol bu repo
  incelemesinde doğrulanabilir bir dosya içi referans olarak bulunamadı.
- Kök klasör adı değişikliğinden PHP include/require tarafında etkilenmesi beklenen sabit
  mutlak yol bulunmadı. Kod yolları `__DIR__` veya `dirname(__DIR__)` tabanlıdır. Buna karşılık
  `.env.sample`, yerel `.env`, dokümantasyon, UI metinleri, `composer.json` package adı ve
  veritabanı adı gibi fiziksel klasör yolu olmayan eski `ads_oauth` referansları tespit edildi.
- `.htaccess` içindeki `RewriteBase /ads-oauth/` ve `ErrorDocument /ads-oauth/` yeni klasör
  adına göre zaten doğrudur. Production uygulama yolu/callback canlıda hâlâ yayınlanmış veya
  doğrulanmış değildir; önceki kontrolde ilgili yollar 404 vermiştir.
- Production için henüz Google Cloud veya production `.env` değişikliği yapılmadı. Bekleyen
  doğru callback URL'si: `https://n0n1.tr/ads-oauth/api/index.php?islem=oauth-donus`.

## 8. 2026-09-18 — Production OAuth genel hata teşhisi (uygulanmamış bulgular)

> Bu bölüm yalnızca teşhistir. Bu görevde `google-oauth.php`, API giriş noktası,
> `.env`, logger veya başka çalışma kodu değiştirilmedi; yalnızca bu dosyaya bulgu
> eklendi.

- `C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php` içindeki
  `google_oauth_redirect_uri_al()` (29-64. satırlar) gerçek HTTP isteğinin şemasını
  okumaz. `$_SERVER['HTTPS']`, `$_SERVER['SERVER_PORT']`,
  `$_SERVER['HTTP_X_FORWARDED_PROTO']`, `REQUEST_SCHEME` veya benzeri bir değişken
  kullanılmamaktadır.
- Bu fonksiyon önce `config('GOOGLE_OAUTH_REDIRECT_URI')` değerini `trim()` ve
  `parse_url()` ile ayrıştırır; `scheme` ve `host` değerlerini doğrudan bu `.env`
  string'inden alır. Dolayısıyla mevcut kontrol **gerçek isteğin şemasını değil,
  yalnızca yapılandırılmış callback URL'sinin şemasını** denetler.
- `yerel_http` yalnızca şema `http` ve host `localhost`, `127.0.0.1` veya `::1`
  olduğunda true olur (42-43. satırlar). 45-47. satırlardaki reddetme koşulu,
  şema `https` değilse ve bu yerel istisna geçerli değilse veya host boşsa
  exception fırlatır. Verilen production değeri
  `https://n0n1.tr/ads-oauth/api/index.php?islem=oauth-donus` bu kontrolü
  geçmelidir. Reverse proxy'nin PHP'ye boş/false `HTTPS` aktarması bu kontrolü
  tetikleyen bir neden değildir.
- Bu URI kontrolü geçersiz bir `.env` değeriyle karşılaşırsa authorization URL'si
  oluşturulmadan veya token exchange tamamlanmadan exception üretebilir. Böyle bir
  exception `api/index.php` içindeki genel `catch` bloğuna gider. Ancak production
  `.env` callback değerinin doğru olduğu verilen bağlamda, HTTPS kontrolü mevcut
  teşhisin kök nedeni olarak desteklenmemektedir.
- `api/index.php` 72-81. satırlardaki genel `catch`, önce yalnızca
  `function_exists('google_ads_hata_kaydi_yaz')` true ise
  `google_ads_hata_kaydi_yaz('api.index', $hata)` çağrısını yapar; ardından
  kullanıcıya sabit `İşlem gerçekleştirilemedi.` cevabını döndürür. Bu nedenle
  logger fonksiyonu henüz tanımlanmadan include/başlatma aşamasında exception
  oluşursa, aynı genel cevap log çağrısına ulaşmadan üretilebilir. Production
  logunun gerçekten neden boş olduğu bu yerel kod incelemesiyle tek başına
  kesinleştirilemez; canlı PHP error logu ve deployed dosyalar ayrıca kontrol
  edilmelidir.
- `İşlem gerçekleştirilemedi.` tam metninin kod içindeki üç üretim noktası:
  `C:\server\htdocs\ads-oauth\api\index.php` 80. satır (API genel `catch`),
  `C:\server\htdocs\ads-oauth\index.php` 33. satır (form cevap mesajı
  fallback'i) ve aynı dosyanın 35. satırı (form genel `catch`). Paneldeki OAuth
  JavaScript'i bu tam metni üretmez; API'den gelen `mesaj` alanını gösterir.
  İstek/JSON hatasında farklı olarak `OAuth başlatılamadı.` mesajını yazar.
- Bu bulgular henüz uygulanmamıştır. Özellikle `X-Forwarded-Proto` kontrolü veya
  `error_log()` eklenmesi bu teşhis aşamasında yapılmadı.

## 9. 2026-09-18 — PROMPT-16: Bağlanan hesabın doğrulanması ve salt-okunur kampanya listeleme

### Uygulanan kod

- `php/baglayici/google-ads-baglayici.php` içine yalnızca `GoogleAdsService.search()` kullanan
  iki salt-okunur çağrı eklendi:
  - `google_ads_musteri_bilgilerini_al()`: `customer.id`, `customer.descriptive_name`,
    `customer.manager`, `customer.status`, `customer.currency_code` ve `customer.time_zone`
    alanlarını sorgular.
  - `google_ads_kampanyalari_listele()`: `campaign.id`, `campaign.name`, `campaign.status`,
    `campaign.advertising_channel_type` ve `campaign_budget.amount_micros` alanlarını sorgular.
- `php/servis/kampanya-servisi.php` içinde `kampanyalari_listele()` eklendi. Önce en güncel
  aktif ve refresh token içeren Google bağlantısını okur, customer bilgilerini doğrular;
  yalnızca `manager = false` sonucunda kampanya sorgusunu çalıştırır. Kampanyalar yerel
  `kampanyalar` tablosuna yazılmaz.
- `api/kampanya-listele.php` yalnızca `api/index.php` üzerinden erişilecek şekilde korundu ve
  `api/index.php?islem=kampanya-listele` dispatch'i eklendi.
- Çağrı öncesi/sonrası `baglanmis_hesaplar` snapshot'ı; kayıt sayısı, aktif kayıt numarası,
  `harici_kimlik` ve aktif durumu içerecek şekilde credential olmadan raporlanır.
- Google Ads hata kategorisi ve mevcut `google_ads_hata_kaydi_yaz()` loglama mekanizması
  korunmuştur. Refresh token, developer token, OAuth secret veya başka credential response'a
  ya da DURUM.md'ye yazılmaz.

### PROMPT-16 gerçek sonuç durumu

- **Adım A sonucu: a/b/c sınıflandırması henüz yapılamadı.** Bu çalışma kanalında yetkili
  tarayıcı oturumu/cookie bulunmadığı için gerçek kullanıcı oturumuyla `baglanmis_hesaplar`
  kaydı okunamadı ve gerçek Google Ads `customer` sorgusu çalıştırılamadı. Bu nedenle gerçek
  customer ID, `manager` değeri, hesap durumu, para birimi ve saat dilimi hakkında tahmini
  değer yazılmadı.
- **Adım B sonucu: çalıştırılmadı.** Adım A'nın gerçek sonucu doğrulanmadığı için kampanya
  sorgusu ve kampanya listesi hakkında gerçek sayı, ad veya durum raporlanmadı.
- Kodun canlı oturumsuz HTTP doğrulaması başarılıdır:
  - `GET /ads-oauth/api/index.php?islem=kampanya-listele` gerçek response:
    `{"return":0,"mesaj":"Oturum gerekli."}`
  - `GET /ads-oauth/api/kampanya-listele.php` doğrudan erişimi gerçek HTTP `404` döndürdü.
  - `GET /ads-oauth/api/index.php?islem=google-musteri-hesaplari` ve
    `GET /ads-oauth/api/index.php?islem=google-hesap-kesfet` oturumsuz olarak
    `{"return":0,"mesaj":"Oturum gerekli."}` döndürdü.
- Bu prompt doğrulaması sırasında `baglanmis_hesaplar` kaydı, OAuth kaydı, refresh token,
  DB şeması ve başka credential değiştirilmedi; herhangi bir mutate çağrısı yapılmadı.

### Net sonraki adım

Yetkili giriş oturumu açıkken `api/index.php?islem=kampanya-listele` çağrısı bir kez
çalıştırılmalıdır. Response içindeki credential içermeyen `hesap` ve
`baglanmis_hesap_durumu` alanları kaydedilerek Adım A gerçek sonucu a/b/c olarak
`DURUM.md`'ye eklenmelidir. Sonuç (a) ise kampanya listeleme yapılmadan durulmalı; sonuç
(b) ise aynı response içindeki gerçek kampanya sayısı ile ilk kampanyaların adı/durumu
raporlanmalı; sonuç (c) ise response'taki gerçek Google Ads hata kategorisi ve mevcut
güvenli hata kaydı raporlanmalıdır.
