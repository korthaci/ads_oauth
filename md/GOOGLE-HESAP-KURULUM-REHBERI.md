# GOOGLE-HESAP-KURULUM-REHBERI.md
> Bu dosya **kod dosyası değildir.** ARCHITECTURE.md ve DURUM.md'den ayrı tutulur.
> Amacı: Google Cloud Console + Google Ads Console tarafında yapılan hesap/API erişim
> işlemlerini, gerekçeleriyle birlikte adım adım kayıt altına almak. `ads_oauth`
> projesi için 2026-09'da yapılan gerçek kurulum sürecine dayanır.
>
> Kimin için: Kendisi tekrar bir Cloud projesi kurması gerekecek olan proje sahibi,
> ya da bu sisteme davet edilecek yeni bir kullanıcıya süreci anlatırken referans
> olarak kullanılabilir.

---

## 1. Kavramlar — Kim Kime Ne İçin Lazım?

Süreç boyunca birbirine benzeyen ama farklı işler gören 4 ayrı "hesap/kimlik" kavramı var:

| Kavram | Ne işe yarar | Nerede yönetilir |
|---|---|---|
| **Google Cloud Projesi** | OAuth Client ID/Secret'ın ve Developer Token başvurusunun bağlı olduğu teknik "kap". Kod, bu projeye ait kimlik bilgileriyle Google'a istek atar. | console.cloud.google.com |
| **OAuth Consent Screen / Branding** | Kullanıcı "hesabımı bağla" dediğinde önüne çıkan izin ekranı — uygulama adı, logo, gizlilik/kullanım şartları linki buradan gelir. | console.cloud.google.com/auth/branding |
| **Google Ads Manager Hesabı (MCC)** | Birden fazla Google Ads hesabını tek bir üst hesaptan görüntülemeyi/yönetmeyi sağlayan "şemsiye" hesap. Developer Token bu hesaba bağlı olarak alınır. **Reklam yayınlamak için zorunlu değildir** — asıl amacı API erişimi ve çoklu hesap yönetimidir. | ads.google.com |
| **Normal (non-manager) Google Ads Hesabı** | Gerçek reklamların, bütçenin, kampanyanın yaşadığı hesap. Her kullanıcı (biz dahil) kendi normal hesabını Google'ın kendi arayüzünden manuel açar. | ads.google.com |

**Önemli netlik (proje kararı):** Sistem, Manager hesabı üzerinden API ile kullanıcılar için otomatik yeni Ads hesabı **oluşturmaz** (`createCustomerClient` yaklaşımı terk edildi — bkz. ARCHITECTURE.md §7.1). Her kullanıcı kendi normal hesabını kendisi açar, sistem sadece bu var olan hesabı OAuth ile **bağlar**.

---

## 2. Adım Adım Yapılanlar (Kronolojik)

### 2.1. Google Cloud Projesi Oluşturma
- console.cloud.google.com üzerinden yeni proje: `ads-oauth-507614` (proje numarası: `768305172042`)
- Bu adım hızlı/anlıktı, bekleme yoktu.

### 2.2. OAuth Client ID / Secret Alma
- Cloud Console → APIs & Services → Credentials üzerinden OAuth 2.0 Client ID oluşturuldu.
- `.env` dosyasına `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` olarak eklendi.
- Hızlıydı, anlık.

### 2.3. Google Ads Manager Hesabı (MCC) Açma ve Developer Token Alma
- ads.google.com üzerinden Manager hesabı açıldı: **MCC ID `9530538405`**.
- Developer Token, Manager hesabının **Araçlar ve Ayarlar → API Center** sayfasından alındı.
- Başlangıç erişim seviyesi otomatik olarak **Explorer/Test Access** oldu (günlük 15.000 test / düşük production limiti).
- Bu adım da nispeten hızlıydı — asıl bekleme ileride, erişim seviyesi yükseltmesinde çıktı.

### 2.4. Eski Basic Access Başvurusu (2026-09-05) — Geçersiz Kaldı
- İlk denemede Basic Access için Tool Design Document ile eski/manuel inceleme sürecine başvuruldu.
- **1 hafta sonra** Google'dan gelen mail: bu süreç tamamen kaldırılmış (retired), bekleyen başvurular incelenmeyecek. Yeni zorunlu yol: Google Cloud Console üzerinden **OAuth Brand Verification**.
- **Ders:** Google Ads API erişim süreçleri değişebiliyor; eski forum/blog kaynaklarındaki adımlara değil, o anki resmi mail/dokümantasyona güvenmek gerekiyor.

### 2.5. OAuth Consent Screen / Branding Doldurma
Cloud Console → APIs & Services → OAuth consent screen → Branding sayfasında dolduruldu:
- App name, User support email (`korthaci1@gmail.com`)
- App logo (120x120px)
- Application home page, Privacy policy link, Terms of service link
- Authorized domains: `n0n1.tr`

**Karşılaşılan sorunlar ve çözümleri (bu kısım en çok zaman aldı):**

1. **"Home page URL'nin sahipliği doğrulanmamış"** → Çözüm: Google Search Console'a (search.google.com/search-console), branding'i düzenlediğin **aynı Google hesabıyla** girip domaini (`n0n1.tr`) doğrulamak gerekti.
2. **"App name, home page'deki isimle eşleşmiyor"** → Çözüm: Home page URL'si proje köküne (`n0n1.tr`) değil, uygulamaya özel bir sayfaya (`n0n1.tr/ads-oauth/ads-oauth.html`) yönlendirildi; o sayfada OAuth consent screen'deki App name ile birebir aynı isim gösterildi.
3. **"Home page bir giriş ekranının arkasında"** → Çözüm: Home page URL'si, PHP uygulamasının login gerektiren `index.php`'sinden ayrı, statik ve girişsiz erişilebilen bir tanıtım sayfasına (`ads-oauth.html`) çevrildi. Bu sayfadan uygulamanın gerçek giriş ekranına ayrı bir link verildi.
4. Bu üç sorun da **"View issues" → "I have fixed the issues" → yeniden doğrulama iste** döngüsüyle tek tek çözüldü.

Sonuç: **"Your branding has been verified and is being shown to users."**

### 2.6. Production'a Geçiş (Publish)
- Audience/Publishing status sayfasında **Publish** butonuna basıldı.
- Çıkan uyarı: logo var + hassas (`adwords`) scope isteniyor → verification'a gönderilmesi gerekecek. Bu beklenen bir uyarıydı, **Confirm** ile devam edildi.

### 2.7. Sensitive Scope OAuth App Verification
- `adwords` scope'u Google tarafından "hassas (sensitive) scope" sayılıyor, bu yüzden brand verification'dan **ayrı, ek bir doğrulama** daha var.
- Bu adım beklenenin aksine **otomatik ve hızlı geçti** — OAuth Overview → Project Checkup sayfasında tüm kriterler (secure flow kullanımı, WebView kullanılmaması, güncel platform/browser desteği vb.) zaten sağlandığı için insan incelemesine gerek kalmadı, "Your app has been verified by Google" direkt göründü.
- **Ders:** Sensitive scope verification, sanılanın aksine (demo video/manuel inceleme gerektirebilir diye düşünülmüştü) bazı durumlarda tamamen otomatik geçebiliyor — teknik checkup kriterlerini baştan sağlıyorsan bekleme olmuyor.

### 2.8. Basic Access Başvurusu — Doğru ve Yanlış Yol
- **Yanlış yol (kafa karıştıran kısım):** Google Ads Console → API Center → "Temel Erişim için başvur" linki, bizi **App Conversion Tracking & Remarketing API** başvuru formuna yönlendirdi — bu tamamen farklı bir API (mobil uygulama/MMP entegrasyonu içindir), Google Ads API ile ilgisi yok. Bu form doldurulmadı.
- **Doğru yol:** Cloud Console → APIs & Services → Enabled APIs & Services → **Google Ads API** → kendi Overview sayfası. Orada "Upgrade access level" bölümünden **Apply for access** butonuyla başvuru yapıldı.
- Başvuru sonrası **beklenmedik şekilde anında** Basic Access'e geçildi (sayfa "10-15 iş günü" yazsa da, muhtemelen brand verification zaten tamamlanmış olduğu için otomatik onaylandı).

### 2.9. Sonuç — Mevcut Erişim Seviyesi
- **Current access level: Basic**
- Günlük 15.000 işlem (test), günlük 15.000 işlem (production)
- Tüm API işlevselliğine erişim
- **Standard Access'e ihtiyaç yok** — bu, çok yüksek hacimli (ajans/SaaS ölçeği) kullanım için; kişisel + davetli 1-2 kişi kullanımı için Basic fazlasıyla yeterli.

---

## 3. Genel Zaman Çizelgesi Özeti

| Adım | Süre | Neden |
|---|---|---|
| Cloud proje + OAuth client | Anlık | Basit form doldurma |
| Manager hesabı + Developer Token (Explorer) | Anlık | Otomatik verilen başlangıç seviyesi |
| Eski Basic Access başvurusu | 1 hafta bekleme, sonra **geçersiz** çıktı | Süreç Google tarafından tamamen değiştirildi |
| Brand Verification (branding + Search Console + 3 hata döngüsü) | Birkaç gün, çoğu zaman hata düzeltme/tekrar denemeyle geçti | Home page/domain/login gibi teknik detaylar ilk seferde eksik/yanlıştı |
| Sensitive scope OAuth app verification | Anlık/otomatik | Teknik checkup kriterleri zaten sağlanıyordu |
| Basic Access başvurusu (doğru formdan) | Anlık | Brand verification tamamlanmış olduğu için otomatik onay |

**Genel ders:** Asıl zaman kaybı, tek bir uzun bekleme değil, **yanlış/eski sürece girmek** (2.4 ve 2.8'deki yanlış yönlendirmeler) ve **branding'deki küçük teknik eksiklikleri tek tek keşfetmek** oldu. Bu rehber varken, bir dahaki sefer bu adımlar muhtemelen 1 günden kısa sürede tamamlanabilir.

---

## 4. Yeni Bir Kullanıcı/Proje İçin Hızlı Kontrol Listesi

Aynı süreci tekrar yaşamamak için, yeni bir Cloud projesi/Ads entegrasyonu kurarken sırayla:

1. Cloud projesi oluştur, OAuth Client ID/Secret al.
2. Google Ads (Manager veya normal) hesabından Developer Token al (otomatik Explorer seviyesinde başlar).
3. OAuth Consent Screen → Branding'i **ilk seferde eksiksiz** doldur:
   - Home page: girişsiz erişilebilen, ayrı bir statik sayfa (uygulamanın panel/login sayfası DEĞİL)
   - Bu sayfadaki başlık, OAuth consent screen'deki App name ile **birebir aynı**
   - Privacy policy + Terms of service: ayrı, basit, girişsiz sayfalar
   - Authorized domains: kök domain, Search Console'da **aynı Google hesabıyla** önceden doğrulanmış olmalı
4. Publish → Confirm (sensitive scope uyarısı normal, devam et).
5. Google Ads Console'daki "API Center" linkine değil, **Cloud Console → APIs & Services → Google Ads API → Overview** sayfasına git, Basic Access için oradan başvur.
6. Explorer Access'in senin ölçeğin için yeterli olup olmadığını önce değerlendir — küçük ölçekli kullanım için genelde yeterlidir, Basic Access'e sadece limitlere takılırsan geç.
