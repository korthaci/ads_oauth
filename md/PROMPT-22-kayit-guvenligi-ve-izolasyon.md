# PROMPT-22 — Kayıt Güvenliği Kilidi ve Çoklu Kullanıcı İzolasyonu Teşhisi

## 0. Bağlam

İki ayrı, öncelikli konu var:

**(A) Gerçek gözlem — olası izolasyon sorunu:** Kort, Firefox'ta **yeni** bir
site_sahibi kaydı oluşturup o hesapla giriş yaptığında, ana sayfada **kendi** (önceki,
farklı tarayıcı/hesaptaki) bağlı Google Ads hesabını (`Customer ID: 4150407743`) gördü —
sanki yeni hesap zaten bir bağlantıya sahipmiş gibi.

Kod incelemesi (bu promptu yazan tarafından, salt-okuma), `baglanmis_hesaplar`'a erişen
**her** sorgunun (`google_aktif_baglantiyi_al`, `google_panel_baglantisini_al`,
`google_kampanya_baglantisini_al`, OAuth callback'teki INSERT/UPDATE) `sahip_no`'ya göre
doğru filtrelendiğini gösteriyor; `oturum_sahip_no_yaz()` de yeni kayıtta
`session_regenerate_id(true)` ile doğru `sahip_no`'yu yazıyor. Yani **kod üzerinde**
böyle bir sızıntıya yol açacak bir şey görünmüyor — bu, ya (a) test sırasında bir insan
hatası (yanlışlıkla "giriş" ile mevcut hesaba girilmiş olması), ya da (b) sunucu/
`.htaccess` seviyesinde bir **önbellekleme (caching)** sorunu olabilir (ana sayfa HTML'i
oturumdan bağımsız önbelleklenip başka bir oturuma servis edilmiş olabilir). Bu **tahmin
edilmeyecek, gerçek runtime kanıtla doğrulanacak**.

**(B) Kayıt güvenliği:** `tema/giris.php`'deki "Kayıt" formu şu an **herkese açık** —
internetteki herhangi biri kendine bir site_sahibi hesabı açıp OAuth akışını
deneyebiliyor. ARCHITECTURE.md §1 açıkça şunu söylüyor: *"Kayıt herkese açık değildir;
yeni kullanıcı erişimi manuel/davet yoluyla verilir."* Bu, mevcut kodla **çelişiyor** ve
gerçek kullanıcılara (Kort'un davet edeceği 1-2 kişiye) erişim verilmeden önce
kapatılmalı.

## 1. Görev A — İzolasyon Sorununun Gerçek Teşhisi (ÖNCELİKLİ, tahmin yok)

1. Geçici, güvenli bir teşhis logu ekle (yalnızca bu görev süresince — şifre/token gibi
   hassas veri **asla** loglanmaz): her istek için `session_id()` (kısaltılmış/hash'lenmiş
   hâli yeterli, ham cookie değeri loglanmaz), `oturum_sahip_no()` dönüş değeri ve
   `baglanmis_hesaplar` sorgusuna geçen `sahip_no` parametresi.
2. Kort'tan, aynı senaryoyu (yeni kayıt → ana sayfa) **tekrar** yaşamasını iste (bu
   promptun DURUM.md notunda net talimat olarak yer alır) ve bu geçici logun gerçek
   çıktısını incele.
3. **Ayrıca**, `.htaccess` ve varsa web sunucusu (Apache/Nginx) seviyesinde herhangi bir
   `Cache-Control`/sayfa önbellekleme kuralı olup olmadığını kontrol et — özellikle ana
   sayfa (`index.php`) için önbellekleme **kapalı** olmalı (oturuma özel dinamik içerik).
4. Kök neden netleşince (insan hatası mı, caching mi, yoksa gerçekten kod tarafında
   bulunamayan bir başka sebep mi), buna göre düzeltme yap:
   - Caching ise: `index.php` yanıtına `Cache-Control: no-store, private` (ve gerekiyorsa
     `.htaccess`'te bu route için önbelleklemeyi devre dışı bırakan bir kural) eklenir.
   - Başka bir kod hatası bulunursa, o düzeltilir.
   - İnsan hatasıysa, bu DURUM.md'ye açıkça yazılır ve **gerçek** bir ikinci kullanıcı
     testiyle (Kort tarafından, dikkatlice) izolasyonun doğru çalıştığı teyit edilir.
5. Teşhis logu, düzeltme sonrası **tamamen kaldırılır** (kalıcı bırakılmaz).

## 2. Görev B — Kayıt Güvenliği Kilidi

1. `index.php`'deki `form_islem === 'kayit'` dalını, public kaydı **reddedecek** şekilde
   değiştir — net bir mesajla (örn. `"Kayıt şu anda davetle sınırlıdır."`) `return: 0`
   döner, hiçbir `site_sahipleri` satırı oluşturmaz.
2. `tema/giris.php`'deki "Kayıt" bölümünü kaldır (yalnızca "Giriş" formu kalır) — kafa
   karıştırıcı, çalışmayan bir form arayüzde durmasın.
3. Kort'un yeni kullanıcı ekleyebilmesi için, **HTTP üzerinden erişilemeyen**, yalnızca
   sunucuda CLI'den çalıştırılabilen küçük bir script ekle (örn.
   `bin/kullanici-olustur.php`, web kök dizini dışında veya `.htaccess` ile dışarıdan
   erişimi engellenmiş bir yerde) — bu script, mevcut `kullanici_kayit()` fonksiyonundaki
   **aynı** doğrulama ve şifre hash'leme mantığını tekrar kullanır (kopyalamaz,
   fonksiyonu çağırır), komut satırından e-posta/ad-soyad/şifre alıp hesap oluşturur.
4. **DB şeması değişikliği gerekmiyor** — mevcut `site_sahipleri` tablosu yeterli; bu
   yaklaşım (public kayıt kapalı + CLI ile manuel oluşturma) ARCHITECTURE.md §1'deki
   "manuel" erişim tanımını doğrudan karşılıyor, ayrı bir davet-kodu mekanizması bu
   promptta **eklenmiyor** (istenirse ileride ayrı bir konu).

## 3. Kesin Yasaklar

- Görev A'daki teşhis logu **kalıcı** bırakılmaz, hassas veri (şifre, token, tam session
  cookie) **hiçbir zaman** loglanmaz.
- Kampanya oluşturma/listeleme/OAuth akışlarının mantığına dokunulmaz (yalnızca Görev
  A'da gerçekten bir kod hatası bulunursa, yalnızca o nokta düzeltilir).
- CLI script, web sunucusu üzerinden (HTTP ile) **hiçbir şekilde** erişilebilir olmamalı
  — bunu doğrula (örn. `/bin/` dizini `.htaccess` ile engellenir veya belge kökünün
  tamamen dışında tutulur).

## 4. Doğrulama

- `php -l` tüm değişen/yeni dosyalarda.
- Görev A'nın kök nedeni ve kanıtı DURUM.md'ye **gerçek verilerle** yazılır.
- Görev B: public kayıt denemesi artık hesap oluşturmadığı, CLI script'in yeni bir
  `site_sahibi` gerçekten oluşturduğu (yerel/test ortamında) doğrulanır.
- **Kort için canlı test zorunlu**: (1) Görev A'nın senaryosunu tekrarlayıp izolasyonun
  artık doğru çalıştığını teyit etmesi, (2) `https://n0n1.tr/ads-oauth/` üzerinden kayıt
  denemesinin artık reddedildiğini görmesi.

## 5. DURUM.md

Görev A'nın kök nedeni (gerçek kanıtla), Görev B'nin uygulanışı, Kort için CLI
script'in nasıl çalıştırılacağına dair net bir örnek komut, ve iki canlı test
hatırlatması net şekilde yazılır. Ayrı TODO listesi gerekmiyor (odaklı, öncelikli iki
konu).
