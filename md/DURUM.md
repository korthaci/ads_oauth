# ads_oauth

Yeni bir sohbete başlarken önce bu sırayla oku:
1. DURUM.md — şu an neredeyiz, hangi promptlar tamamlandı
2. ARCHITECTURE.md — sabit mimari/kararlar
3. md/GOOGLE-HESAP-KURULUM-REHBERI.md — Google Cloud/Ads hesap kurulum süreci (kod değil, referans)

GitHub: https://github.com/korthaci/ads_oauth 
(GitHub:Bu adresteki yenilemeler görülemiyordu. Çünkü github js ile yeniliyor. AI cevabı : Çözüldü — web_fetch ile GitHub'ın HTML sayfasını çekmek yerine, repoyu doğrudan klonlayabiliyorum. Bunu az önce test ettim ve çalıştı; artık senden git ls-files gibi çıktı istemeden kodu doğrudan görebilirim. Bundan sonra bu şekilde ilerleyeceğim.)

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

## 10. 2026-09-18 — PROMPT-16.1: `kampanya-listele` dispatch kontrolü

- Yerel `C:\server\htdocs\ads-oauth\api\index.php` dosyasının 66–68. satırlarında
  exact route mevcuttur:
  `case 'kampanya-listele':` ve hemen ardından `api_kampanya_listele()` çağrısı.
- Aynı dosyanın 20. satırında `api/kampanya-listele.php` include edilir; wrapper’ın
  21–24. satırlarında `api_kampanya_listele()` doğrudan
  `kampanyalari_listele()` servisine yönlenir. `oauth-donus`,
  `google-hesap-kesfet` ve `google-musteri-hesaplari` de aynı `switch ($islem)`
  dispatch mekanizmasındadır. Action string’inde `kampanya_listele`,
  `kampanyalari-listele` veya case/boşluk varyantı yoktur.
- Yerel canlı HTTP kontrolü:
  `GET http://localhost/ads-oauth/api/index.php?islem=kampanya-listele`
  → `{"return":0,"mesaj":"Oturum gerekli."}`. Bu, yerel route’un bulunduğunu
  ve isteğin session kontrolüne ulaştığını doğrular.
- Production canlı HTTP kontrolü:
  `GET https://n0n1.tr/ads-oauth/api/index.php?islem=kampanya-listele`
  → `{"return":0,"mesaj":"Geçersiz API işlemi."}`. Bu response production
  `api/index.php` dosyasının bu route’u içeren sürümü çalıştırmadığını gösterir.
- GitHub `main` dalındaki `api/index.php` de exact `case 'kampanya-listele'`
  route’unu içeriyor; GitHub Contents API dosya SHA’sı:
  `c1f5c0fa4c25de74985136c998a5d4412b170837`. Bu nedenle gerçek kodda string
  düzeltmesi gerekmiyor; kök neden production upload/deploy sürüm farkıdır.
- Bu promptta yalnızca bu durum tespiti kaydedildi. `kampanya-servisi.php`,
  `google-ads-baglayici.php`, DB şeması, OAuth kayıtları ve refresh tokenlar
  değiştirilmedi; hiçbir Google Ads mutate/API çağrısı yapılmadı.

### PROMPT-16.1 net sonraki adım

Yerel/GitHub’daki güncel `api/index.php` ve bağımlı `api/kampanya-listele.php`
dosyaları production `n0n1.tr/ads-oauth/api/` dizinine upload/deploy edilmelidir.
Deploy sonrasında önce oturumsuz aynı URL’nin `Oturum gerekli.` döndürdüğü
doğrulanmalı; ardından Kort yetkili oturumuyla gerçek customer/kampanya isteği
çalıştırılmalıdır. Bu çalışma kanalında production upload yetkisi ve Kort’un
oturum cookie’si bulunmadığından deploy ve yetkili son test burada yapılamadı.

## PROMPT-16.1 TODO

- [x] `api/index.php` dispatch tablosunda `kampanya-listele` action’ı bulundu ve kök
      neden teşhis edildi. Yerel ve GitHub `main` sürümünde exact
      `case 'kampanya-listele':` mevcut; production’daki response eski deploy sürümünü
      gösteriyor.
- [x] Kök nedene göre dispatch düzeltmesi yapıldı (ya da düzeltmeye gerek olmadığı,
      örn. deploy eksikliği olduğu tespit edildi). Yerel/GitHub kodunda dispatch
      düzeltmesi gerekmiyor; production’a güncel `api/index.php` ve
      `api/kampanya-listele.php` yüklenmeli.
- [x] Oturumsuz istekle `{"return":0,"mesaj":"Oturum gerekli."}` doğrulandı
      (yerel `http://localhost/ads-oauth/api/index.php?islem=kampanya-listele`).
- [x] Ana sayfanın bağlantı durumunu neden göstermediği teşhis edildi.
      `index.php` yalnızca oturum sahibini kontrol ediyor; eski
      `tema/panel/anasayfa.php` ise `baglanmis_hesaplar` sorgusu yapmadan sabit bağlanma
      metni ve butonu gösteriyordu.
- [x] Ana sayfa görüntüleme mantığı düzeltildi. `index.php`, yalnızca credential
      döndürmeyen `google_panel_baglantisini_al()` okumasını çağırıyor; görünüm aktif
      Google bağlantısını, varsa hesap adı/customer ID’sini gösteriyor. OAuth callback,
      DB yazma akışı, `kampanya-servisi.php` ve `google-ads-baglayici.php` değiştirilmedi.
- [ ] Ana sayfa düzeltmesi mümkünse doğrulandı. PHP lint ve sentetik bağlı/bağlı değil
      görünüm branch kontrolleri başarıyla yapıldı; ancak production deploy ve Kort’un
      yetkili tarayıcı session cookie’si bu çalışma kanalında bulunmadığından gerçek bağlı
      hesapla canlı render doğrulanamadı. Deploy sonrası Kort’un tarayıcıdan kontrol etmesi
      gerekiyor.
- [x] `DURUM.md` güncellendi (bu TODO listesi dahil, gerçek sonuçlarla).

### PROMPT-16.1 bu oturumdaki gerçek değişiklikler

- `C:\server\htdocs\ads-oauth\index.php`: Oturum sahibi için ana sayfa görüntüleme
  öncesinde aktif Google bağlantısı read-only olarak yükleniyor.
- `C:\server\htdocs\ads-oauth\php\servis\hesap-servisi.php`:
  `google_panel_baglantisini_al()` yalnızca `hesap_adi` ve `harici_kimlik` döndüren,
  `aktif = 1`, `platform = google` ve dolu şifreli refresh token koşullu görüntüleme
  sorgusu olarak eklendi; token değeri response/veri yapısına alınmıyor.
- `C:\server\htdocs\ads-oauth\tema\panel\anasayfa.php`: Bağlı hesapta bağlantı
  durumu gösteriliyor ve OAuth başlatma butonu gizleniyor; bağlı hesap yoksa mevcut
  OAuth butonu korunuyor; DB kontrolü hata verirse güvenli uyarı gösteriliyor.
- Production’a upload/deploy yapılmadı. Production endpoint kontrolünde hâlâ eski
  response bekleniyor: `{"return":0,"mesaj":"Geçersiz API işlemi."}`.
- Bu oturumda DB’ye yazılmadı, OAuth kayıtlarına/refresh tokenlara dokunulmadı ve
  hiçbir Google Ads mutate/API çağrısı yapılmadı.

---

## PROMPT-17 — Çoklu erişilebilir hesapta non-manager seçimi (2026-09-18)

### Mevcut seçim mantığının gerçek kod incelemesi

- Önceki OAuth callback akışında `C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php`
  dosyasının HEAD sürümündeki `google_oauth_donus()` (`:262-313`) yalnızca authorization
  code’dan refresh token alıyor, credential oluşturuyor ve
  `google_oauth_refresh_token_kaydet()` çağırıyordu. Callback içinde
  `ListAccessibleCustomers` veya eşdeğer bir erişilebilir hesap listesi çağrısı yoktu.
- Önceki `google_oauth_refresh_token_kaydet()` (`HEAD` sürümünde `:199-253`) mevcut
  Google kaydını `ORDER BY no DESC LIMIT 1` ile seçiyordu. Kayıt varsa
  `UPDATE`, yoksa `INSERT` yapıyordu. `harici_kimlik` callback aşamasında bilinçli olarak
  `NULL` bırakılıyor, `manager`/`customer.manager` alanı hiç kontrol edilmiyordu.
- Erişilebilir müşteri listesi OAuth callback’ten ayrı olan
  `C:\server\htdocs\ads-oauth\php\baglayici\google-ads-baglayici.php`
  içindeki `google_ads_hesaplarini_kesfet()` (`:419-504`) fonksiyonunda alınıyor:
  `CustomerService::listAccessibleCustomers()` (`:425-427`) çağrılıyor ve dönen her
  customer resource için `GoogleAdsService::search()` (`:445-447`) çalıştırılıyor.
  Salt-okunur GAQL (`:432-434`) `customer.manager` alanını seçiyor; sonuç
  `getManager()` ile `yonetici` alanına yazılıyor (`:470-475`). Önceki kodda bu alan
  yalnızca response/kayıt verisine taşınıyor, aktif OAuth hesabını seçmek için
  kullanılmıyordu. API listesindeki ilk/son hesaba göre bir seçim kriteri yoktu.
- Ayrı `google-hesap-kesfet` akışındaki
  `google_kesfedilen_hesaplari_kaydet()` (`C:\server\htdocs\ads-oauth\php\servis\hesap-servisi.php:184-263`)
  mevcut duplicate kontrolünü koruyarak keşfedilen hesapları kaydetmeye devam eder.
  PROMPT-17 kapsamında bu endpoint’in tüm keşif listesini saklayan davranışı
  değiştirilmedi; aktif OAuth hesabı seçimi callback’e uygulandı.

### Uygulanan seçim ve kayıt mantığı

- `C:\server\htdocs\ads-oauth\php\servis\hesap-servisi.php:113-165`
  içinde salt veri üzerinde çalışan `google_baglanti_hesabini_sec()` eklendi.
  `yonetici = false` olan hesaplar arasından liste sırasındaki ilk hesap seçiliyor;
  en az bir non-manager varsa manager hesaplar dikkate alınmıyor. Non-manager yoksa
  mevcut davranışı korumak için liste sırasındaki ilk manager seçiliyor.
- `C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php:326-346` içindeki
  callback artık refresh token doğrulamasından sonra mevcut
  `google_ads_hesaplarini_kesfet($refresh_token)` read-only akışını çağırıyor ve
  helper ile seçim yapıyor. Liste alınamazsa veya geçerli hesap yoksa token DB’ye
  yazılmadan hata akışı sonlanıyor.
- `google_oauth_refresh_token_kaydet()` (`google-oauth.php:201-276`) artık seçilen
  `harici_kimlik` ve `hesap_adi` değerlerini refresh token ile aynı transaction içinde
  kaydediyor. `aktif = 1` olan mevcut Google kaydı `UPDATE` ediliyor; aynı sahibin
  diğer Google kayıtları `aktif = 0` yapılıyor. Hiç kayıt yoksa yalnızca seçilen hesap
  için bir `INSERT` yapılıyor. Böylece OAuth tekrarında birden fazla aktif kayıt
  oluşturulmuyor ve mevcut aktif kayıt (örneğin `no=2`) seçilen non-manager hesap
  üzerine yazılıyor.
- Birden fazla non-manager bulunması durumunda seçim arayüzü yapılmadı. Şu an ilk
  non-manager seçiliyor; helper sonucu `non_manager_sayisi` ve seçilen
  `harici_kimlik` değerlerini taşıyor. Bu prompt bağlamında iki non-manager olsaydı
  rapor formatı açıkça “birden fazla non-manager hesap bulundu, şu an X seçildi”
  şeklindedir. Mevcut gerçek listede görülen aday için beklenen seçim
  `4150407743` (API formatı; ekranda `415-040-7743`) ve manager aday
  `9530538405`’in seçilmemesidir.

Sentetik üçlü listede birden fazla non-manager hesap bulundu, şu an
`4150407743` seçildi. Production OAuth callback’i bu durumla henüz çalıştırılmadığı
için canlı Google listesindeki birden fazla non-manager sonucu gözlemlendiği iddia
edilmemektedir.

## PROMPT-17 TODO

- [x] Mevcut seçim mantığı gerçek kod ve satır referanslarıyla belgelendi.
- [x] Çoklu hesap durumunda non-manager tercih eden mantık eklendi.
- [x] Gerçek Google API/DB çağrısı yapmayan sentetik seçim testi geçti:
  - yalnızca manager (`9530538405`) → manager seçildi;
  - manager (`9530538405`) + non-manager (`4150407743`) → non-manager seçildi.
- [x] `php -l` kontrolleri geçti:
  `C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php` ve
  `C:\server\htdocs\ads-oauth\php\servis\hesap-servisi.php`.
- [x] Deploy dosyaları listelendi.
- [x] `DURUM.md` güncellendi.

Ek doğrulamalar: `git diff --check` geçti. `kampanya-servisi.php`,
`google-ads-baglayici.php` ve `db/sema.sql` bu promptta değiştirilmedi.
`createCustomerClient`, mutate veya hesap oluşturma çağrısı eklenmedi; refresh token
şifreleme mekanizması korunuyor.

### PROMPT-17 deploy listesi ve canlı doğrulama

Production’a şu dosyalar birlikte deploy edilmelidir:

1. `C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php`
2. `C:\server\htdocs\ads-oauth\php\servis\hesap-servisi.php`

`php/baglayici/google-ads-baglayici.php`, `php/servis/kampanya-servisi.php`,
`api/index.php`, `api/kampanya-listele.php` ve `db/sema.sql` bu prompt için deploy
edilecek değişiklikler arasında değildir. Production deploy yetkisi ve Kort’un
yetkili OAuth/tarayıcı session’ı bu çalışma kanalında bulunmadığından canlı OAuth
başlatma, gerçek `ListAccessibleCustomers` sonucu ve DB’de `4150407743` doğrulaması
henüz yapılamadı. Deploy sonrası Kort yetkili oturumla OAuth akışını yeniden başlatıp
aktif kaydın tek kayıt olarak non-manager ID’ye güncellendiğini kontrol etmelidir.
---

## PROMPT-18 — Hesabı yeniden bağlama butonu ve iskelet dosya taraması (2026-09-18)

### Görev A — Ana sayfada bağlı hesapla yeniden bağlama butonu

- `C:\server\htdocs\ads-oauth\tema\panel\anasayfa.php:27-34`: bağlı dalda
  “Farklı bir Google Ads hesabı bağla” butonu, `aria-describedby` ile bağlı
  `google-oauth-yeniden-baglama-uyarisi` açıklaması ve ortak `google-oauth-mesaj`
  durumu eklendi. Uyarı metni açıkça belirtiyor: yetkilendirme sonrası seçilen hesap,
  mevcut aktif bağlantı ve bağlı hesap kaydının üzerine yazılır.
- `C:\server\htdocs\ads-oauth\tema\panel\anasayfa.php:40`: bağlı-değil dalındaki mevcut
  buton aynı davranışla korundu; `id="google-oauth-baslat"` yerine ortak
  `google-oauth-baslat-dugmesi` sınıfı kullanılmaya başlandı.
- `C:\server\htdocs\ads-oauth\tema\panel\anasayfa.php:44-72`: script iki dal için ortak
  tek handler’a taşındı (`querySelectorAll`). `fetch('api/index.php?islem=oauth-baslat')`
  ve `window.location.href = cevap.url` davranışı aynen korundu; kopya JS handler yok.
- `oauth-baslat` bağlıyken engellenmiyor: `google_oauth_baslat()`
  (`C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php:115-137`) yalnızca oturum
  kontrolü yapıyor; bağlı hesaba bakmıyor. Kort’un üretimde manuel URL ile çalıştırdığı
  akış bu davranışı zaten göstermişti. Yerel regresyon:
  `http://localhost/ads-oauth/api/index.php?islem=oauth-baslat` oturumsuz istek
  `200 {"return":0,"mesaj":"OAuth başlatmak için giriş yapmalısınız."}` döndü;
  `islem=kampanya-listele` ise `{"return":0,"mesaj":"Oturum gerekli."}` döndü.
  Yetkili tarayıcı oturumlu canlı buton testi bu kanalda yapılamadı; Kort’a kaldı.

### Görev B — İskelet/gerçek dosya taraması (vendor hariç 28 PHP dosyası)

Tarama yöntemi: dosya boyutu/satır sayısı listesi, “İskelet” yorum grep’i ve
`require`/dispatch çağrı araması. Sonuçlar gerçek içerikten okundu, tahmin edilmedi.

Gerçek kod içeren dosyalar (iskelet değil):

- `C:\server\htdocs\ads-oauth\index.php` (form akışı + panel yükleme)
- `C:\server\htdocs\ads-oauth\api\index.php` (tek API giriş noktası + dispatch)
- `C:\server\htdocs\ads-oauth\api\kampanya-listele.php` (`ADS_OAUTH_API_INDEX` koruması +
  `api_kampanya_listele()` köprüsü; PROMPT-16 deseni)
- `C:\server\htdocs\ads-oauth\php\config.php`, `php\veritabani.php`, `php\oturum.php`,
  `php\sifreleme.php`
- `C:\server\htdocs\ads-oauth\php\oauth\google-oauth.php`
- `C:\server\htdocs\ads-oauth\php\baglayici\google-ads-baglayici.php`
- `C:\server\htdocs\ads-oauth\php\servis\kullanici-servisi.php`,
  `php\servis\hesap-servisi.php`, `php\servis\kampanya-servisi.php`
- `C:\server\htdocs\ads-oauth\tema\giris.php`, `tema\layout\header.php`,
  `tema\layout\footer.php`, `tema\panel\anasayfa.php`


Bu promptta taşınan dosyalar (karar (a) — davranış değişmeden):

1. `C:\server\htdocs\ads-oauth\api\oauth-baslat.php`
   - Öncesi: 2 satırlık iskelet; gerçek akış PROMPT-06 Revize kararından beri
     `api\index.php` dispatch’inde doğrudan `google_oauth_baslat()` çağırıyordu
     (DURUM.md PROMPT-06 Revize kaydı: `api/oauth-baslat.php` ile
     `api/oauth-donus.php` iskelet ve dispatch için kullanılmıyor).
   - Şimdi: `api\oauth-baslat.php:11-14` `ADS_OAUTH_API_INDEX` doğrudan erişim
     koruması (PROMPT-16 `api\kampanya-listele.php` deseni), `:21-24`
     `api_oauth_baslat()` köprüsü `google_oauth_baslat()`’ı çağırıyor
     (`php\oauth\google-oauth.php:115-137`). `api\index.php:21` köprüyü require
     ediyor; `api\index.php:52-54` dispatch artık `api_oauth_baslat()` çağırıyor.
     Davranış değişmedi (sentetik + localhost HTTP regresyon geçti).
2. `C:\server\htdocs\ads-oauth\api\oauth-donus.php`
   - Öncesi: 2 satırlık iskelet; gerçek callback akışı `api\index.php`
     dispatch’inde doğrudan `google_oauth_donus()` çağrısıydı.
   - Şimdi: `api\oauth-donus.php:11-14` koruma, `:21-24` `api_oauth_donus()`
     köprüsü; `api\index.php:22` require, `:56-58` dispatch köprüyü çağırıyor.
     Davranış değişmedi.

Bilerek iskelet bırakılanlar (karar (b)) ve gerçek mantık/plan durumu:

- `C:\server\htdocs\ads-oauth\php\oauth\meta-oauth.php` ve
  `C:\server\htdocs\ads-oauth\php\baglayici\meta-ads-baglayici.php`: Meta kapsam
  dışı (DURUM.md proje durumu: “Meta entegrasyonu bu projenin kapsamı değildir”).
- `C:\server\htdocs\ads-oauth\api\hesap-sil.php`: bağlantı kaldırma henüz
  uygulanmadı; dispatch’te yok, gerçek karşılığı yok.
- `C:\server\htdocs\ads-oauth\api\kampanya-olustur.php` ve
  `api\kampanya-durdur.php`: kampanya yazma işlemleri henüz uygulanmadı;
  mevcut kampanya işi PROMPT-16/17 read-only listeleme kapsamındadır.
- `C:\server\htdocs\ads-oauth\api\senkron-tetikle.php`,
  `php\servis\senkron-servisi.php`, `php\cron\senkron-calistir.php`: senkron
  sistemi henüz uygulanmadı; gerçek karşılığı yok.
- `C:\server\htdocs\ads-oauth\tema\panel\hesap-baglan.php` ve
  `tema\panel\kampanya-sihirbazi.php`: bu görünümler hiçbir dosyada require
  edilmiyor (grep doğrulandı); henüz uygulanmamış ilerideki ekranlar.

## PROMPT-18 TODO

- [x] Ana sayfaya “hesabı yeniden bağla” butonu eklendi
- [x] Buton için oauth-baslat’ın bağlıyken de çalıştığı doğrulandı (kod incelemesi +
  localhost oturumsuz HTTP regresyon; yetkili canlı tarayıcı testi Kort’ta)
- [x] Repo genelinde tüm PHP dosyaları iskelet/gerçek diye tarandı (liste bu bölümde)
- [x] Her iskelet dosya için gerçek mantığın nerede olduğu bulundu ve raporlandı
- [x] Katman ayrımına aykırı olanlar doğru dosyaya taşındı (regresyon yaratmadan)
- [x] Bilerek iskelet bırakılanlar (örn. Meta) işaretlendi
- [x] `php -l` tüm değişen dosyalarda geçti
- [x] Deploy listesi net şekilde verildi
- [x] `DURUM.md` güncellendi

### PROMPT-18 doğrulama ve deploy listesi

- `php -l`: `api\index.php`, `api\oauth-baslat.php`, `api\oauth-donus.php`,
  `tema\panel\anasayfa.php` → hepsi geçti.
- Sentetikler: korumasız köprü include `exit` (sonraki satır çalışmadı), korumalı
  köprü fonksiyonları tanımlı ve oturumsuz davranış `return:0` mesajıyla aynı,
  ana sayfa bağlı/bağlı değil/hata üç dal render testi geçti.
- `git diff --check` geçti; kapsam kontrolü: yalnızca aşağıdaki 4 kod dosyası
  değişti; `php\oauth\google-oauth.php`, `php\servis\hesap-servisi.php`,
  `php\servis\kampanya-servisi.php`, `php\baglayici\google-ads-baglayici.php` ve
  `db\sema.sql` bu promptta değiştirilmedi.
- Production deploy listesi (PROMPT-18):
  1. `C:\server\htdocs\ads-oauth\api\index.php`
  2. `C:\server\htdocs\ads-oauth\api\oauth-baslat.php`
  3. `C:\server\htdocs\ads-oauth\api\oauth-donus.php`
  4. `C:\server\htdocs\ads-oauth\tema\panel\anasayfa.php`
- PROMPT-17 dosyaları (`php\oauth\google-oauth.php`,
  `php\servis\hesap-servisi.php`) production’a hâlâ yüklenmediyse PROMPT-18
  listesiyle birlikte yüklenebilir.
- Bu oturumda DB’ye yazılmadı, OAuth kayıtlarına/refresh tokenlara dokunulmadı ve
  hiçbir Google Ads mutate/API çağrısı yapılmadı.
---

## PROMPT-19 — `php/Cron/` / `php/cron/` çakışma temizliği (2026-09-18)

- `git rm "php/Cron/senkron-calistir.php"` ile index’teki büyük harfli yol kaldırıldı
  (staged: `D  php/Cron/senkron-calistir.php`). HEAD’de iki yol aynı blob’u
  (`21169303192c3cf24ffc9c56a72a5708a3d18dda`) paylaştığı için içerik kaybı yok;
  `php/cron/senkron-calistir.php` çalışma ağacında iskelet içeriğiyle korundu ve
  `php -l` geçti. Senkron mantığı yazılmadı.
- `md/ARCHITECTURE.md:200` §5 referansı `php/Cron/` → `php/cron/` olarak düzeltildi;
  §4 (`:170`) zaten küçük harf `cron/` idi. `:129` “Cron:” yol referansı değil,
  terim başlığıdır; değiştirilmedi.
- Repo taramasında büyük harfli `Cron/` kalan referanslar bilinçli olarak
  değiştirilmedi (“başka dosyaya dokunulmaz”): `md/00-iskelet-kurulum.md:75,90` ve
  `md/01-isimlendirme-duzeltme.md:14` geçmiş prompt kayıtları; `md/DURUM.md:401`
  kavramsal kullanım (“Cron/senkron mekanizması”), yol referansı değildir.
- Beklenen commit içeriği: `D  php/Cron/senkron-calistir.php`,
  `M  md/ARCHITECTURE.md`, `M  md/DURUM.md`.
- Production kontrol (Kort): push/checkout sonrası Linux’ta `php/Cron/` klasörü
  tamamen silinmeli (production’da eski büyük harfli klasör ayrı klasör olarak
  kalmış olabilir) ve `php/cron/senkron-calistir.php` iskelet olarak mevcut
  olmalıdır. Production silme/yükleme yetkisi bu kanalda bulunmadığından canlı
  teyit yapılamadı.
---

## PROMPT-20 — Kampanya oluşturma sihirbazı (ilk gerçek mutate akışı) (2026-09-19)

Bu prompt, projenin ilk gerçek mutate (oluşturma) akışıdır; kapsam yalnızca Search
kampanyasıdır ve kampanya **her zaman PAUSED** oluşturulur. Bu oturumda hiçbir gerçek
Google Ads API çağrısı (mutate veya salt-okunur) yapılmadı; tüm doğrulama sentetik/
birim test düzeyinde tamamlandı. Canlı test Kort’a bırakıldı (aşağıda).

### Uygulanan katmanlar (ARCHITECTURE §5)

- **`C:\server\htdocs\ads-oauth\tema\panel\kampanya-sihirbazi.php`** (PROMPT-18’de bilerek
  iskelet işaretlenen dosya gerçek işlevine kavuştu; 89 satır): 7 alanlı form
  (web sitesi, kampanya/iş adı, başlıklar, açıklamalar, anahtar kelimeler, günlük bütçe TL,
  hedef konum); fetch ile `api/index.php?islem=kampanya-olustur` POST eder; başarı mesajı
  PAUSED + ayrı onay adımı bilgisini içerir. Görev sırası ve dağıtım bilgisi
  (bidding/match type/network) kullanıcıya gösterilmez (§1 gereği).
- **`C:\server\htdocs\ads-oauth\index.php:58-61`**: `?islem=kampanya-sihirbazi` rotası
  oturum kontrolünden sonra sihirbaz görünümüne yönlenir;
  `tema/panel/anasayfa.php:74` panelde “Kampanya sihirbazı ile kampanya oluştur” linki
  eklendi.
- **`C:\server\htdocs\ads-oauth\api\kampanya-olustur.php`**: PROMPT-18 deseniyle
  `ADS_OAUTH_API_INDEX` koruması (`:11-14`) + `api_kampanya_olustur()` köprüsü (`:21-24`)
  → `kampanya_olustur($_POST)`. `api\index.php:21` require, `:73-75` dispatch.
- **`C:\server\htdocs\ads-oauth\php\servis\kampanya-servisi.php`**:
  - `kampanya_metnini_listeye_ayir()` (`:289`): satır/virgül ayrıştırıcı (saf).
  - `kampanya_butcesini_microsa_cevir()` (`:316`): TL→micros çevrimi; **float matemiği
    yerine string tabanlı** çevrim (19.99 → 19990000, float artifact yok); “500 TL”
    eki, virgül/nokta ondalık kabul; geçersiz girdide `InvalidArgumentException`.
  - `kampanya_girdilerini_dogrula()` (`:374`): §4 kuralları — URL normalize (https://
    ekleme + FILTER_VALIDATE_URL), kampanya adı ≤255, başlık 3–15 adet ve ≤30 karakter,
    açıklama 2–4 adet ve ≤90 karakter, anahtar kelime 1–20 adet ve ≤80 karakter,
    bütçe pozitif, konum 1–100 karakter.
  - `kampanya_olustur()` (`:513`): akış sırası — oturum → girdi doğrulama (saf) →
    `google_kampanya_baglantisini_al()` → token çözme → **mutate öncesi taze manager
    kontrolü** (`google_ads_musteri_bilgilerini_al`, salt-okunur; `:570` manager ise
    mutate hiç denenmeden `return 0`) → konum çözümleme (salt-okunur) → tek atomik
    mutate → yerel `kampanyalar` kaydı (non-fatal; hata halinde yalnızca
    `yerel_kayit=yazilamadi` işaretlenir, Google tarafındaki başarı etkilenmez).
    Response `Websistem {return, mesaj}` formatı; başarı mesajı PAUSED + ayrı onay
    adımı bilgisini içerir.
- **`C:\server\htdocs\ads-oauth\php\baglayici\google-ads-baglayici.php`**:
  - `google_ads_konum_onerilerini_al()` (`:800`): salt-okunur
    `GeoTargetConstantService.suggestGeoTargetConstants` (`tr` locale, `TR` ülke).
    Yalnızca **tam isim eşleşmesi** kabul edilir; eşleşme yoksa önerilerle hata,
    en yakın eşleşme sessizce seçilmez (§4).
  - `google_ads_kampanya_id_al()` (`:911`): kaynak adından sayısal ID.
  - `google_ads_kampanya_olustur()` (`:953`): tek `GoogleAdsService::mutate` istegi,
    geçici kaynak adlarıyla bağlantılı 7 mutate adımı (§2.1 sırası): CampaignBudget
    (STANDARD, explicitly_shared=false) → Campaign (`advertising_channel_type=SEARCH`,
    **`status=PAUSED`** (`:1018`), Maximize Clicks, yalnızca Google Search ağı açık) →
    CampaignCriterion konum → CampaignCriterion dil (`languageConstants/1017`) →
    AdGroup (SEARCH_STANDARD, ENABLED) → AdGroupCriterion anahtar kelimeler
    (PHRASE match) → AdGroupAd Responsive Search Ad (başlıklar/açıklamalar/final_urls).
    `partial_failure=false` (`:1107`) → atomik; hata durumunda Google kendi tarafında
    tam rollback yapar. GoogleAdsException birincil hata mesajı mevcut
    `google_ads_hata_mesajini_sanitize_et` ile temizlenip kullanıcıya iletilir
    (bütçe minimumu gibi gerçek API hataları kullanıcıya görünür olur), tüm detay
    mevcut güvenli log pipeline’ına yazılır.

### Güvenlik doğrulamaları (koddan)

- Kampanya status: yalnızca `CampaignStatus::PAUSED` set edilir (`:1018`);
  `CampaignStatus::ENABLED` adapter’da hiçbir yerde kullanılmaz (ENABLED yalnızca
  ad group `:1059`, ad group criterion `:1069`, ad group ad `:1102` düzeyindedir —
  bu kaynaklar kampanya durumunu etkilemez).
- `createCustomerClient` kullanılmadı (yalnızca doküman yorumunda geçiyor).
- Bu promptta gerçek API çağrısı yapılmadı; manager kontrolü/mutate çağrısı yalnızca
  kod yolunda doğrulandı.


### Doğrulama (bu oturumda gerçek API çağrısı yok)

- Birim testi: `C:\server\htdocs\ads-oauth\tests\butce-micros-test.php`
  (yeni dosya) → `php tests/butce-micros-test.php` sonucu:
  `PROMPT-20 butce micros testleri: PASS (16 gecerli, 16 gecersiz vaka)`.
  Kapsam: `500`/`500.00`/`500 TL`/` 500 tl ` → 500000000; `12,50`/`12.50`/`12.5`/
  float 12.50 → 12500000; `19,99`/float 19.99 → 19990000 (float artifact yok);
  `0,5` → 500000; `1000000` → 1000000000000; boş/metin/`-5`/`0`/`1.234`
  (binlik ayraç)/`12,345`/`1e3` gibi girdiler `InvalidArgumentException` fırlatır.
- Sentetik girdi doğrulama testleri (API/DB çağrısız): geçerli örnek formda
  `https://ornek.com` normalize edildi, başlık 3/açıklama 2/anahtar kelime 2
  ayrıştırıldı; 8 hata senaryosu (2 başlık, 31 karakter başlık, 5 açıklama,
  91 karakter açıklama, 21 anahtar kelime, `abc` bütçe, geçersiz URL, 256 karakter
  ad) beklenen mesajlarla reddedildi → `PASS`.
- Sentetik köprü testleri: `api/kampanya-olustur.php` korumasız include → exit
  (sonraki satır çalışmadı); korumalı include → `api_kampanya_olustur`/`kampanya_olustur`
  tanımlı ve oturumsuz çağrı girdi işlemeden önce `return 0` döndü → `PASS`.
- Sentetik görünüm testleri: sihirbaz 7 alan + fetch URL + PAUSED mesajı içeriyor;
  panelde sihirbaz linki var → `PASS`.
- `php -l`: değişen/yeni tüm PHP dosyaları geçti (`api/index.php`,
  `api/kampanya-olustur.php`, `index.php`,
  `php/baglayici/google-ads-baglayici.php`, `php/servis/kampanya-servisi.php`,
  `tema/panel/anasayfa.php`, `tema/panel/kampanya-sihirbazi.php`,
  `tests/butce-micros-test.php`). `git diff --check` temiz.

## PROMPT-20 TODO

- [x] Sihirbaz formu (7 alan) tema/panel/kampanya-sihirbazi.php'ye eklendi
- [x] api/kampanya-olustur.php gerçek işlevine kavuştu
- [x] kampanya-servisi.php: kampanya_olustur() + manager tekrar-doğrulama eklendi
- [x] google-ads-baglayici.php: budget/campaign/criterion/ad-group/keyword/ad mutate
      fonksiyonları eklendi
- [x] GeoTargetConstant konum çözümleme eklendi (salt-okunur)
- [x] TL→micros çevrimi için birim testi yazıldı ve geçti (16 geçerli, 16 geçersiz vaka)
- [x] Kampanyanın her zaman PAUSED oluşturulduğu koddan doğrulandı (adapter:1018;
      CampaignStatus::ENABLED adapter’da hiç kullanılmıyor)
- [x] Manager hesaba mutate denenmediği doğrulandı (servis:570 mutate öncesi taze
      salt-okunur kontrol; manager ise mutate yok; bu oturumda gerçek çağrı da yok)
- [x] Girdi doğrulama kuralları (başlık/açıklama uzunluk, min/max sayılar) uygulandı
      ve sentetik testlerle geçti
- [x] php -l tüm dosyalarda geçti
- [x] Deploy listesi verildi
- [x] Kort'a canlı düşük bütçeli test talimatı DURUM.md'de not edildi
- [x] DURUM.md güncellendi

### PROMPT-20 deploy listesi

Production’a birlikte deploy edilmeli:

1. `C:\server\htdocs\ads-oauth\php\baglayici\google-ads-baglayici.php`
2. `C:\server\htdocs\ads-oauth\php\servis\kampanya-servisi.php`
3. `C:\server\htdocs\ads-oauth\api\kampanya-olustur.php`
4. `C:\server\htdocs\ads-oauth\api\index.php`
5. `C:\server\htdocs\ads-oauth\index.php`
6. `C:\server\htdocs\ads-oauth\tema\panel\anasayfa.php`
7. `C:\server\htdocs\ads-oauth\tema\panel\kampanya-sihirbazi.php`

`tests/butce-micros-test.php` repo içi test dosyasıdır; production’da bulunması
gerekmez. PROMPT-17/18/19 dosyaları production’da henüz yoksa PROMPT-20 listesiyle
birlikte yüklenebilir. Not: `md/PROMPT-20-kampanya-olustur.md` kullanıcı tarafından
eklenen prompt dokümanıdır; bu oturumda untracked bırakıldı ve değiştirilmedi.

### PROMPT-20 canlı test talimatı (Kort için; bu promptun son adımı)

1. Önce dağıtım/dağıtım-sonrası sanity: yetkili oturumla
   `https://n0n1.tr/ads-oauth/index.php` → panelde “Kampanya sihirbazı…” linki
   görünmeli; oturumsuz `api/index.php?islem=kampanya-olustur` POST için
   `{"return":0,"mesaj":"Kampanya oluşturmak için giriş yapmalısınız."}` dönmeli.
2. Sihirbazda **küçük, düşük bütçeli** bir test kampanyası doldur (örn. günlük
   bütçe `10` TL, 3 kısa başlık, 2 açıklama, 1 anahtar kelime, konum `Ankara`).
   Bu ilk gerçek mutate denemesidir; bütçe küçük tutulmalıdır.
3. Başarılı response’ta `kampanya_id` ve PAUSED mesajı beklenir; ardından Google Ads
   arayüzünden (önce manager MCC görünümü, sonra müşteri hesap) kampanyanın
   **PAUSED** duraklatılmış olarak listede göründüğü, bütçenin 10 TL × 1.000.000 =
   10.000.000 micros olduğu, reklam grubu/anahtar kelimeler/reklamın oluştuğu
   görsel olarak doğrulanmalıdır. Kampanya reklam gözden geçirilecekse yine PAUSED
   kalır; **ENABLED yapılmamalı** (yayına al endpoint’i bu promptta yazılmadı;
   bilinçli olarak ayrı prompt konusudur).
4. Herhangi bir Google API hatası alınırsa (örn. minimum bütçe), response’daki
   `google_ads_hata` alanındaki mesajın içeriği DURUM.md’ye eklenmeli; tahminle
   minimum bütçe kuralı yazılmamalıdır.
5. Test kampanyası tamamlandıktan sonra ister Google Ads arayüzünden REMOVED
   yapılabilir (yerel DB kaydı silinmez; yerel kayıt bilgilendirme amaçlıdır).

## PROMPT-20.1 — `LocationNames` namespace ve V25 import doğrulaması (2026-09-19)

- `composer.lock` içinde `googleads/google-ads-php` sürümü `v34.0.0`; vendor API
  namespace'i `V25` olarak doğrulandı.
- Hatalı namespace `Google\Ads\GoogleAds\V25\Services\LocationNames` idi.
  Doğrusu nested sınıf olarak
  `Google\Ads\GoogleAds\V25\Services\SuggestGeoTargetConstantsRequest\LocationNames`.
- Aynı adapter taramasında V25'te bulunmayan `Common\Ad`, `Common\NetworkSettings`
  ve `Common\MaximizeClicks` importları da düzeltildi: `Resources\Ad`,
  `Resources\Campaign\NetworkSettings` ve `Common\TargetSpend`. V25 Campaign modelinde
  bu strateji `setTargetSpend(new TargetSpend())` ile temsil ediliyor; teklif tutarı
  verilmediği için varsayılan Maximize Clicks davranışı korunuyor.
- `php -l php/baglayici/google-ads-baglayici.php` başarılı; doğrulanan V25 sınıf/importları
  için `class_exists`/`interface_exists` kontrolü başarılı.
- Salt-okunur `google_ads_konum_onerilerini_al(..., 'Ankara')` çağrısı aktif şifreli
  bağlantı üzerinden denendi; güvenli test çıktısı `kategori=oauth` oldu ve bu nedenle
  gerçek `suggestGeoTargetConstants` sonucu alınamadı. Token veya ham API hatası loglanmadı.
  Gerçek mutate çağrısı yapılmadı.
- Kort, deploy sonrası oturumlu ortamda önce Ankara için salt-okunur konum önerisini,
  ardından düşük bütçeli test kampanyası oluşturma akışını yeniden denemeli; kampanya
  `PAUSED` kalmalı ve `ENABLED` yapılmamalıdır.

## PROMPT-20.2 — `suggestGeoTargetConstants` response getter düzeltmesi (2026-09-19)

- `vendor/googleads/google-ads-php` v34.0.0 içindeki V25
  `SuggestGeoTargetConstantsResponse` sınıfı açılarak gerçek getter doğrulandı:
  `getGeoTargetConstantSuggestions()`; dönüş tipi `RepeatedField<GeoTargetConstantSuggestion>`.
- `google_ads_konum_onerilerini_al()` içinde response nesnesi üzerinde doğrudan dönen
  `foreach ($yanit as $oneri)` satırı, API sonuç listesini okumadığı için
  `foreach ($yanit->getGeoTargetConstantSuggestions() as $oneri)` olarak düzeltildi.
  Tam eşleşme, tarama limiti ve hata mesajı mantığı değiştirilmedi; gerçek mutate çağrısı
  yapılmadı.
- Kort, deploy sonrası oturumlu ortamda `Ankara` için salt-okunur çağrının artık gerçek
  `resource_name` ve `name` döndürdüğünü doğrulamalı; ardından düşük bütçeli gerçek test
  kampanyası akışını yeniden denemeli ve kampanyayı `PAUSED` bırakmalıdır.
- Bu oturumdaki gerçek salt-okunur Ankara denemesi token/API çıktısı loglanmadan yapıldı;
  sonuç yine güvenli `kategori=oauth` oldu. Bu nedenle gerçek `resource_name` ve `name`
  sonucu henüz doğrulanamadı. Gerçek mutate çağrısı yapılmadı.

## PROMPT-20.3 — Mutate temp resource adları ve konum belirsizlik mesajı (2026-09-19)

- Gerçek mutate denemesinde `campaign_budget`/`campaign`/`ad_group` create'lerinde
  `mutateError: RESOURCE_NOT_FOUND` (trigger `-1/-2/-3`) görüldü; kök neden, temp
  kaynaklara referans verilip onları oluşturan `create` operation'larında
  `resource_name` atanmamış olmasıydı.
- Görev A: `google_ads_kampanya_olustur()` içinde CampaignBudget create'e
  `->setResourceName($on_ek . '/campaignBudgets/-1')`, Campaign create'e
  `->setResourceName($on_ek . '/campaigns/-2')`, AdGroup create'e
  `->setResourceName($on_ek . '/adGroups/-3')` satırları eklendi. Referanslar
  (`-1/-2/-3`), alanlar, `PAUSED` durumu ve operation sırası değiştirilmedi.
- Görev B: vendor'dan doğrulanan `GeoTargetConstant::getTargetType()` ve
  `getCanonicalName()` ile eşleşme kayıtlarına `target_type` ve `canonical_name`
  alanları eklendi; `count($tam_eslesenler) > 1` hatası artık adayları ve en fazla 5
  örnek canonical_name değerini listeliyor (örn. "Ankara (Province), Ankara (County). …
  Örnek canonical_name değerleri: Ankara,Ankara,Turkey | Ankara,Kızılcahamam,Turkey.").
  Tam eşleşme karar mantığı ve sessiz best-match yasağı değişmedi; yanıt işleme,
  davranışı koruyan saf `google_ads_konum_yanitini_isle()` fonksiyonuna taşındı
  (sentetik test edilebilirlik için).
- Sentetik test `tests/konum-oneri-mesaj-testi.php` eklendi (gerçek SDK response
  sınıflarıyla, API çağrısı olmadan): tek eşleşme, belirsiz çok eşleşme, canonical'sız
  belirsizlik, 6 adayda ilk 5 sınırı, tam eşleşme yok, boş/geçersiz öneri vakaları
  PASS; `php -l` ve mevcut butce micros testleri PASS. Bu promptta gerçek mutate
  çağrısı yapılmadı.
- KORT CANLI TEST: deploy sonrası "Türkiye" (tek eşleşmeli konum) ile kampanya
  sihirbazı yeniden denenmeli; `RESOURCE_NOT_FOUND` alınmadan gerçek `PAUSED`
  kampanya oluşturulmalı (bu promptun gerçek başarı kriteri). Kampanya `PAUSED`
  kalmalı, `ENABLED` yapılmamalı.

## PROMPT-20.4 — Zorunlu `contains_eu_political_advertising` alanı (2026-09-19)

- "Türkiye" (tek eşleşmeli konum) ile gerçek mutate denemesi bu kez `mutateError:
  RESOURCE_NOT_FOUND` almadan mutate'e ulaştı (PROMPT-20.3 Görev A düzeltmesi
  doğru çalıştı); bu kez `campaign_operation.create.contains_eu_political_advertising`
  alanında `FieldError.REQUIRED` ("The required field was not present.") alındı.
- Kök neden: 3 Eylül 2025'ten itibaren Google Ads API ile oluşturulan her yeni
  kampanya, AB Siyasi Reklam Şeffaflık Yönetmeliği (TTPA) gereği
  `contains_eu_political_advertising` alanını (`EuPoliticalAdvertisingStatus`
  enum'u) açıkça beyan etmek zorunda; alan boş bırakılırsa `FieldError.REQUIRED`
  dönüyor.
- Düzeltme: `google_ads_kampanya_olustur()` içindeki Campaign create nesnesine
  vendor'dan doğrulanan
  `Google\Ads\GoogleAds\V25\Enums\EuPoliticalAdvertisingStatusEnum\EuPoliticalAdvertisingStatus`
  enum'u ile `->setContainsEuPoliticalAdvertising(EuPoliticalAdvertisingStatus::DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING)`
  eklendi (enum sınıfı, sabit ve `Campaign::setContainsEuPoliticalAdvertising()`
  vendor V25 kaynaklarından doğrulandı; tahmin edilmedi).
- Sabit değer `DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING` olarak **hardcoded**dır
  ve kullanıcıya arayüzde hiçbir şekilde sorulmaz (ARCHITECTURE.md §1.1 ilkesiyle
  tutarlı): bu proje Türkiye pazarına yönelik, siyasi olmayan reklamlar hedefler ve
  kapsamda siyasi reklam desteği yoktur. Bu değer sorulmadan değiştirilmemelidir.
- Kapsam: yalnızca bu tek alan eklendi; mutate akışının başka hiçbir kısmına
  dokunulmadı, kullanıcıya alanla ilgili seçenek/soru eklenmedi. Bu promptta gerçek
  mutate çağrısı yapılmadı; `php -l`, sentetik konum/TTPA doğrulama testi ve mevcut
  micros testleri PASS.
- KORT CANLI TEST: deploy sonrası "Türkiye" ile kampanya sihirbazı tekrar denenmeli;
  bu kez hiçbir `REQUIRED`/`RESOURCE_NOT_FOUND` hatası alınmadan gerçek, `PAUSED`
  durumda bir kampanya oluşturulmalıdır (bu promptun gerçek başarı kriteri).
  Kampanya `PAUSED` kalmalı, `ENABLED` yapılmamalı.
- Not: Bu alan eklendikten sonra başka bir eksik zorunlu alan hatası çıkarsa
  (Google API'nin başka yeni zorunlu alanları da olabilir), gerçek hata mesajından
  tahmin edilmeden teşhis edilip AYRI bir düzeltme promptuna konu olacaktır; bu
  promptun kapsamı yalnızca bilinen bu tek alandır.

## PROMPT-20.5 — Mutate yanıt okuma düzeltmesi: `getResults()` → `getMutateOperationResponses()` (2026-09-19)

### Görev A — Yarım kalmış kampanya kontrolü (salt-okunur, zorunlu)

- PROMPT-20.4 canlı testinde mutate isteği Google'a ulaştı ancak yanıt işlenirken
  `Call to undefined method MutateGoogleAdsResponse::getResults()` hatası fırladı;
  yani gerçek hesapta (`4150407743`) bir kampanya oluşmuş olabilir ve sistem bunu
  doğrulayıp kullanıcıya göstermeden çökmüş olabilir.
- Bu oturumda salt-okunur kontrol (Görev A) denendi: geçici CLI scripti
  (PROMPT-16 `google_ads_kampanyalari_listele()` + `GoogleAdsService.search`
  deseni; yalnızca `SELECT campaign.id, campaign.name, campaign.status, ...`
  salt-okunur sorgusu, mutate yok) ile `baglanmis_hesaplar`'daki aktif Google
  kaydı okundu: **no=2, sahip_no=6, harici_kimlik=9530538405 (Manager),
  aktif=1**. Sihirbazın mutate hedefi ise alt hesap **4150407743**.
- Gerçek API sorgusu bu ortamdan yine güvenli `kategori=oauth` hatası döndürdü
  (refresh token bu CLI ortamından kullanılamadı; token/çıktı loglanmadı —
  önceki oturumlardaki salt-okunur denemelerle aynı güvenli davranış). Sonuç:
  **yetim kampanya var mı/yok mu bu oturumdan doğrulanamadı**; gerçek kampanya
  adı/ID/oluşturulma zamanı verisi alınamadığı için buraya yazılamadı.
- Geçici script kullanıldıktan sonra silindi; hiçbir token/credential ekrana
  veya loga yazılmadı.
- **KORT'A GÖRSEL TEYİT (zorunlu):** ads.google.com arayüzünden `4150407743`
  hesabında beklenmedik bir "Türkiye"/test kampanyası (muhtemel ad:
  `n0n1-ads-kampanya-test` / `n0n1-ads-kampanya-test-1`) olup olmadığı kontrol
  edilmelidir. Bulunursa: `PAUSED` olduğu için harcama yapmaz (zararsız), ancak
  varlığı bu kayda eklenmeli; istenirse kampanya arayüzden silinebilir.

### Görev B — Yanıt okuma düzeltmesi (vendor'dan doğrulanmış)

- `google_ads_kampanya_olustur()` içindeki `$sonuclar = $yanit->getResults();`
  satırı, `getResults()` yöntemi `MutateGoogleAdsResponse` sınıfında bulunmadığı
  için (`vendor/.../V25/Services/MutateGoogleAdsResponse.php` içinde yalnızca
  `getPartialFailureError()` ile repeated `mutate_operation_responses` alanının
  `getMutateOperationResponses()`/`setMutateOperationResponses()` erişimcileri
  var — satır 103) şu şekilde değiştirildi:
  `$sonuclar = $yanit->getMutateOperationResponses();`. Bu, Google'ın resmi SDK
  örneklerindeki heterojen `mutate_operations` yanıt okuma deseniyle aynıdır.
- Vendor teyitleri (tahmin edilmedi): `MutateOperationResponse::getCampaignResult()`
  (satır 1221, oneof; eşleşmeyen operation için null döner — mevcut
  `count($sonuclar) > 1 && $sonuclar[1]->getCampaignResult() !== null` koruması
  korunmalıydı ve korundu), ayrıca `getCampaignBudgetResult()`,
  `getCampaignCriterionResult()`, `getAdGroupResult()`,
  `getAdGroupCriterionResult()`, `getAdGroupAdResult()` ve
  `MutateCampaignResult::getResourceName()` (satır 56) hepsi V25 vendor
  kaynaklarında mevcut.
- Index varsayımı teyit edildi: istekteki operation sırası [campaign_budget,
  campaign, campaign_criterion(konum), ..., ad_group, keywords, ad]; mutate
  yanıtı sonuç dizisini istek sırasıyla aynı sırada döndürdüğü için
  `$sonuclar[1]` kampanya operation'ının sonucudur (sentetik testle de
  doğrulandı, aşağıda).
- Kapsam: yalnızca yanıt okuma (response parsing) düzeltmesi; mutate isteğinin
  oluşturulma kısmına (operation'lar, alanlar, PAUSED durumu, EU political
  advertising alanı) hiç dokunulmadı. Bu promptta gerçek mutate çağrısı yapılmadı.

### Doğrulama (PROMPT-20.5)

- `php -l`: `php/baglayici/google-ads-baglayici.php` ve
  `tests/konum-oneri-mesaj-testi.php` PASS.
- Sentetik test güncellendi: adapter kaynak taramasına
  `$sonuclar = $yanit->getMutateOperationResponses();` (tam 1 kez) ve
  `->getResults()` (tam 0 kez) desenleri eklendi; yeni 10. vaka: SDK'da
  `getResults` yokluğu, `getMutateOperationResponses`/`setMutateOperationResponses`
  varlığı, `MutateOperationResponse` getter'larının (getCampaignBudgetResult,
  getCampaignResult, getCampaignCriterionResult, getAdGroupResult,
  getAdGroupCriterionResult, getAdGroupAdResult) varlığı ve sentetik 6 elemanlı
  `MutateGoogleAdsResponse` zinciriyle index-1 kampanya kaynağı doğrulaması
  (`customers/.../campaigns/...` regex dahil). Test PASS (10 vaka); micros testi
  PASS (16 gecerli, 16 gecersiz); `git diff --check` temiz.
- Kapsam notu: bu promptta kod değişen dosyalar `php/baglayici/google-ads-baglayici.php`
  (1 satır değişim + 3 satır yorum) ve `tests/konum-oneri-mesaj-testi.php`
  (tarama desenleri + 10. vaka) ile `md/DURUM.md` (bu bölüm).
  `tema/panel/kampanya-sihirbazi.php` çalışma kopyasındaki tek satırlık değişiklik
  (varsayılan kampanya adı `n0n1-ads-kampanya-test` → `n0n1-ads-kampanya-test-1`)
  bu promptun kapsamı dışındadır; bu promptta o dosyaya dokunulmadı.
- **Bu promptun gerçek başarı kriteri (KORT CANLI TEST):** deploy sonrası sihirbaz
  Türkiye ile tekrar denenmeli; bu kez hem mutate isteği hem yanıt okuma başarılı
  olmalı, kullanıcıya gerçek kampanya ID'si ve `PAUSED` durumu net şekilde
  gösterilmelidir. Kampanya `PAUSED` bırakılır, `ENABLED` yapılmaz. Bir sonraki
  hata çıkarsa gerçek API yanıtından teşhis edilip AYRI promptta çözülür.

## PROMPT-21 — Konum belirsizliği çözümü, hariç tutulan bölgeler, yaklaşık toplam bütçe (2026-09-20)

### TODO

- [x] Konum çözümleme fonksiyonu çoklu eşleşmede adayları döndürecek şekilde genişletildi
- [x] api/kampanya-olustur.php konum_secenekleri yanıtını destekliyor (köprü zaten
      `kampanya_olustur($_POST)` döndürüyor; `konum_secenekleri` yanıtı service'ten
      otomatik geçiyor; dosyada değişiklik gerekmedi)
- [x] Sihirbaz formu: belirsizlikte seçim ekranı + hedef_konum_resource_name akışı
- [x] Hariç tutulan bölge(ler) alanı eklendi, aynı çözümleme mantığından geçiyor
- [x] Hariç konum mutate'e negative=true kriteri olarak ekleniyor
- [x] Hedef ile hariç konum çakışması reddediliyor
- [x] Toplam bütçe alanı + end_date hesaplama birim testi geçti
- [x] Toplam bütçe < günlük bütçe doğrulaması eklendi
- [x] Sihirbazda toplam bütçe için uyarı metni eklendi
- [x] php -l tüm dosyalarda geçti
- [x] Deploy listesi verildi
- [x] Kort için üç canlı test senaryosu (Ankara seçim, Trabzon hariç, toplam bütçe)
      net yazıldı (aşağıda)
- [x] DURUM.md güncellendi

Tamamlanamayan madde yok.

### Görev A — Konum belirsizliği: gerçek seçim arayüzü

- `google_ads_konum_yanitini_isle()` ve `google_ads_konum_onerilerini_al()` dönüş
  şekli genişletildi: `{'durum': 'tek'|'belirsiz', 'konum': ?kayit, 'adaylar': list}`.
  Tek tam eşleşme → 'tek' (davranış korunur); 0 tam eşleşme → eskisi gibi
  `GoogleAdsKesifHatasi` (bulunamadı / örnek önerilerle); **birden fazla tam eşleşme
  artık exception FIRLATMAZ**, adayların tam listesi (resource_name, name,
  target_type, canonical_name) çağıran koda döner.
- Kullanıcının serbest metni (`"Ankara,Turkiye"` gibi) suggest `LocationNames`'e
  geçerli bir arama terimi olmadığı için canonical_name ile çözüm mümkün değildi;
  exception yolu kaldırıldı ve `google_ads_konum_belirsizlik_mesaji()` silindi
  (artık hata mesajı üretilmiyor; adaylar UI'da radio listesiyle seçtirilir).
- `php/servis/kampanya-servisi.php`: konum belirsizse mutate HİÇ denenmeden
  `api/kampanya-olustur.php` üzerinden frontend'e şu yanıt döner:
  `{'return': 0, 'mesaj': 'Konum belirsiz, lütfen birini seçin.', 'konum_secenekleri':
  [{'resource_name', 'ad', 'tip', 'canonical_name'}...], 'konum_secenekleri_baglam':
  'hedef'|'haric', 'konum_secenekleri_metin': ...}`.
- `hedef_konum_resource_name` (+ tutarlılık için `hedef_konum_kaynak_metin`) gizli
  alanları: kaynak ad doluysa, biçimi `geoTargetConstants/[0-9]+` ise ve seçimin
  yapıldığı metin mevcut `hedef_konum` ile aynıysa suggest çağrısı ATLANIR ve
  doğrudan bu kaynak ad kriter olarak kullanılır; metin değiştiyse seçim geçersiz
  sayılıp suggest yeniden yapılır (`kampanya_konum_secimi_kullanilabilir()` saf
  fonksiyonu, PROMPT-21 §1.1.4; sessiz best-match hâlâ yok).
- Sihirbaz (`tema/panel/kampanya-sihirbazi.php`): `konum_secenekleri` içeren yanıtta
  form sayfa yenilenmeden radio-listeli seçim kutusunu gösterir (`ad` + `tip` +
  `canonical_name`); kullanıcı bir adayı işaretleyince ilgili gizli alan doldurulur
  ve "Kampanyayı oluştur" ile form seçimle birlikte tekrar gönderilir. Konum metni
  değişirse seçim temizlenir/kutusu gizlenir.

### Görev B — Hariç tutulan bölge(ler)

- Sihirbaza opsiyonel `haric_konumlar` textarea eklendi (virgül/satır ayrımı,
  `kampanya_metnini_listeye_ayir()` ile bölünür; en fazla 20 adet, her biri en fazla
  100 karakter).
- Her hariç konum hedef konumla AYNI çözümleme mantığından geçer; birden fazla
  belirsiz hariç konum girilirse İLK belirsiz olan için seçim istenir, o çözülünce
  bir sonraki istekte gerekirse diğeri istenir (PROMPT-21 §2.2; her biri için
  `haric_konum_resource_name` + `haric_konum_kaynak_metin` gizli alanları).
- Çözümlenen her hariç konum, mutate isteğine ek `MutateOperation` +
  `CampaignCriterionOperation.create` olarak `->setNegative(true)` ile eklenir
  (hedef konum kriterinin yanında, aynı atomik mutate içinde; `partial_failure`
  kapalı kalır, ayrı mutate çağrısı yapılmaz; PAUSED ve manager taze kontrolü
  aynen korunur).
- Hedef konumla aynı kaynak adına sahip hariç konum → anlamlı hata:
  "Aynı konum hem hedef hem hariç tutulan olamaz: <metin>. Farklı bir hariç konum
  yazın." Hariç listesinde aynı konumun tekrarı da anlamlı hata ile reddedilir
  (sessizce yok sayılmaz).

### Görev C — Yaklaşık toplam bütçe (bitiş tarihi ile)

- Sihirbaza opsiyonel `toplam_butce` alanı + AÇIK uyarı metni eklendi: "Bu, kesin bir
  toplam harcama garantisi değildir — Google bazı günlerde günlük bütçenin biraz
  üzerinde harcayıp ayı ortalayabilir; bu alan yalnızca kampanyanın yaklaşık olarak
  toplam bütçe / günlük bütçe gün sonra otomatik olarak durmasını sağlar (bitiş
  tarihi atanır)."
- `kampanya_bitis_tarihi_hesapla($bugun, $toplam_micros, $gunluk_micros)` saf
  fonksiyonu: `bitis = bugun + floor(toplam/gunluk)` gün (intdiv, micros cinsinden
  kesin; asgari 1 gün). `toplam < gunluk` ise InvalidArgumentException ("Toplam bütçe
  günlük bütçeden küçük olamaz...") — sessizce 0/1 güne yuvarlanmaz; bu mesaj
  kullanıcıya aynen döner. Toplam bütçe TL girdisi `kampanya_butcesini_microsa_cevir()`
  ile çözümlenir (yeni opsiyonel `etiket` parametresi: 'Toplam bütçe'; mevcut günlük
  bütçe davranışı ve 16/16 micros testi değişmedi).
- **Vendor doğrulaması (tahmin yok):** V25 `Campaign` sınıfında `end_date` alanı YOK;
  yalnızca `end_date_time` (`optional string end_date_time = 105;`,
  `Campaign::setEndDateTime()` vendor satır 2058) var. Bu nedenle bitiş tarihi
  `->setEndDateTime($bitis . ' 23:59:59')` olarak atanır (YYYY-MM-DD HH:MM:SS,
  hesabın saat diliminde; 23:59:59 ile belirlenen günün sonuna kadar çalışır).
  Alan doldurulmamışsa kampanya eskisi gibi bitiş tarihsiz (süresiz) oluşur.
- `CampaignCriterion::setNegative()` (vendor satır 383) ve
  `GeoTargetConstant::getCanonicalName()/getTargetType()/getName()` vendor'dan
  teyit edildi.

### Doğrulama (PROMPT-21)

- `php -l`: `php/baglayici/google-ads-baglayici.php`, `php/servis/kampanya-servisi.php`,
  `tema/panel/kampanya-sihirbazi.php`, `tests/konum-oneri-mesaj-testi.php` → PASS.
- Sentetik test güncellendi/genişletildi: (a) tek eşleşme → 'tek' + kayıt; (b) çoklu
  eşleşme → 'belirsiz' + tam aday listesi (exception yok; tam eşleşme olmayan aday
  listede yer almaz; 6 adayın tamamı döner — eski 5 adaylık mesaj sınırı kaldırıldı);
  (c) `kampanya_konum_secimi_kullanilabilir()` birim vakaları (geçerli seçim
  kullanılır; metin değişirse / geçersiz biçim / boş → kullanılmaz); (d) çakışma ve
  tekrar hata mesajları kaynak taramasıyla; (e) toplam < günlük →
  InvalidArgumentException; (f) günlük 100 TL + toplam 1000 TL → 10 gün sonrası
  (2026-09-20 → 2026-09-30), eşit → 1 gün, 999/100 → floor 9 gün.
- Kaynak taramaları: adapter'da `->setNegative(true)` (1), `->setEndDateTime(` (1),
  `'durum' => 'tek'`/`'belirsiz'` (1'er), hariç kaynak/bitiş tarihi doğrulama
  mesajları; serviste yeni fonksiyonlar + `konum_secenekleri` yanıtı + gizli alan
  okumaları; sihirbazda yeni alanlar + JS seçim akışı + uyarı metni.
- Sonuç: sentetik test PASS (20 vaka), micros testi PASS (16 gecerli, 16 gecersiz),
  `git diff --check` temiz.
- Bu promptta gerçek mutate çağrısı YAPILMADI; tüm API doğrulamaları vendor kaynak
  okumasından ve sentetik nesnelerden geldi.

### Deploy listesi (PROMPT-21)

1. `C:\server\htdocs\ads-oauth\php\baglayici\google-ads-baglayici.php`
2. `C:\server\htdocs\ads-oauth\php\servis\kampanya-servisi.php`
3. `C:\server\htdocs\ads-oauth\tema\panel\kampanya-sihirbazi.php`
4. (değişmedi; köprü davranışı kod incelemesiyle onaylandı) `api/kampanya-olustur.php`
5. (repo) `tests/konum-oneri-mesaj-testi.php`
Not: `db/sema.sql` ve yerel `kampanyalar` kaydı değişmedi; yeni alanlar (hariç
konumlar, toplam bütçe, konum seçimi) yerel tabloya yazılmaz.

### KORT CANLI TEST senaryoları (hepsi PAUSED kalmalı, hiçbiri ENABLED yapılmamalı)

1. **Ankara seçim:** Sihirbazda hedef konum "Ankara" girilip gönderildiğinde
   `konum_secenekleri` radio listesi (örn. Province/City adayları) çıkmalı; kullanıcı
   bir aday seçip tekrar gönderdiğinde kampanya seçilen konumla oluşmalı ve kullanıcıya
   gerçek kampanya ID'si + PAUSED durumu gösterilmeli. Artık "Ankara,Turkiye" gibi
   canonical_name yazması istenmemektedir; seçim ekranı gerçek mekanizmadır.
2. **Türkiye + Trabzon hariç:** Hedef "Türkiye", hariç "Trabzon" ile test kampanyası;
   mutasyon tek istekte başarılı olmalı; Google Ads arayüzünde kampanyanın konum
   ayarında Trabzon'un "Hariç tutulan konumlar" altında göründüğü teyit edilmeli.
   "Trabzon" belirsiz çıkarsa seçim ekranı aynı akışla Trabzon için de sorulur.
3. **Toplam bütçe:** Günlük 100 TL + toplam 1000 TL gibi değerlerle oluşturulan test
   kampanyasının Google Ads arayüzünde Bitiş tarihinin bugün+10 gün olduğu teyit
   edilmeli; arayüzdeki uyarı metni ("kesin harcama garantisi değildir") kullanıcıya
   göründüğü gibi kontrol edilmeli.
4. Üç senaryoda da PAUSED korunur; ENABLED denemesi yapılmaz. Herhangi bir hata
   çıkarsa gerçek API yanıtından teşhis edilip AYRI promptta çözülür.

---

## PROMPT-21.1 — Yanlış Dil Sabiti Düzeltmesi (`1017` → `1037`) — TAMAMLANDI (kod tarafı)

**Sorun:** PROMPT-20'den beri dil hedeflemesi sabit `languageConstants/1017` ile
yapılıyordu ("Türkçe" varsayımıyla). Gerçek test kampanyası `24265218474`
("Türkiye + Trabzon hariç") Google Ads arayüzünde **Çince (basitleştirilmiş)**
dil hedefiyle çıktı.

**Doğrulama (Görev A):**
- Canlı GAQL salt-okunur sorgu (`SELECT ... FROM language_constant WHERE
  language_constant.code = 'tr'`) bu oturumdan çalıştırılamadı: CLI'de refresh
  token `invalid_grant` (web oturumu dışında kullanılamaz, `kategori=oauth`
  kısıtı). Token/çıktı loglanmadı; geçici CLI scripti silindi.
- İki bağımsız kanıt birbirini teyit ediyor:
  1. Google Ads "Codes and formats" dil sabitleri tablosu (developers.google.com):
     `zh_CN` = **1017** (Chinese, simplified), `tr` = **1037** (Turkish).
  2. Canlı kampanya `24265218474` arayüzde Çince (basitleştirilmiş) görünmesi —
     1017'nin gerçekten zh_CN olduğunu doğrudan kanıtlar.
- **Sonuç:** eski yanlış ID `1017` = Çince (basitleştirilmiş); yeni doğru ID
  `1037` = Türkçe. (Kort istersen web oturumuyla aynı GAQL sorgusunu
  çalıştırıp `tr -> 1037`'yi bir kez daha teyit edebilir.)

**Değişiklik (Görev B):**
- `php/baglayici/google-ads-baglayici.php` satır ~1207:
  `->setLanguageConstant('languageConstants/1017')` →
  `->setLanguageConstant('languageConstants/1037')` + hangi sorgu/kaynakla
  doğrulandığını anlatan yorum bloğu eklendi. `1017` kodda başka hiçbir yerde
  kullanılmıyordu (grep ile doğrulandı; sabit/const tanımı da yok).
- Başka hiçbir mutate akışı değiştirilmedi; dil sabiti hardcoded kaldı, arayüze
  dil seçimi eklenmedi (ARCHITECTURE.md §1.1 ile tutarlı).
- `php -l` temiz.

**Kort için hatırlatmalar:**
1. **Canlı doğrulama (gerçek başarı kriteri):** production'a deploy sonrası
   sihirbazla **yeni** bir test kampanyası oluştur (PAUSED) ve Google Ads
   arayüzünde **Diller** ayarının **Türkçe** olduğunu teyit et.
2. **Eski yanlış-dilli test kampanyaları** (`24268992914`, `24276234886`,
   `24265218474` vb.) otomatik düzeltilmedi; dilediğini arayüzden Türkçe'ye
   çevir veya `REMOVED` yap (senin kararın).
