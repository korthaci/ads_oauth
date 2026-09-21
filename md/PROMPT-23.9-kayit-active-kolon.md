# PROMPT-23.9 — CLI Kullanıcı Oluşturmayı Kaldırma, Public Kayıt + Manuel Onay (`active` Kolonu)

## 0. Bağlam

PROMPT-22'de kayıt güvenliği için şu yaklaşım uygulanmıştı: public "Kayıt" formu
tamamen kaldırıldı, yerine yalnızca CLI'den çalışan `bin/kullanici-olustur.php` scripti
eklendi. Kort bu yaklaşımı **değiştirmek** istiyor — daha basit bir model:

- Public "Kayıt" formu **geri gelir** — herkes kayıt olabilir, tıpkı PROMPT-22 öncesi
  gibi.
- Ama yeni kaydolan bir `site_sahipleri` satırı **varsayılan olarak pasif** olur ve
  **giriş yapamaz**.
- Kort'un tek yapması gereken manuel iş: yeni kayıt olan kişinin veritabanı satırını
  bulup `active` sütununu `1` yapmak (phpMyAdmin/SQL ile, elle). Bu, ARCHITECTURE.md
  §1'deki "manuel/davet yoluyla erişim" ilkesini karşılıyor — kayıt herkese açık ama
  **kullanılabilir olması** Kort'un onayına bağlı.

Bu prompt, PROMPT-22'nin Görev B'sini (CLI script yaklaşımı) **geri alır** ve yerine bu
yeni modeli kurar. **PROMPT-24 bu promptun kapsamı dışındadır ve ayrı, sonra verilecektir
— bu promptta PROMPT-24'e dair hiçbir şey yapılmaz/değiştirilmez.**

## 1. Görev A — CLI Script'in Kaldırılması

1. `bin/kullanici-olustur.php` dosyası ve `bin/` dizini (başka bir şey içermiyorsa)
   silinir.
2. `.htaccess`'teki `bin/` dizinini engelleyen kural (PROMPT-22'de eklenmişti) kaldırılır
   (artık gereksiz).

## 2. Görev B — `active` Kolonu (DB Şema Değişikliği)

1. `db/sema.sql` içindeki `site_sahipleri` tablo tanımına yeni bir sütun eklenir:
   ```sql
   `active` TINYINT(1) NOT NULL DEFAULT 0
   ```
2. Production veritabanında bu sütunun eklenmesi için gerçek bir `ALTER TABLE` ifadesi
   DURUM.md'ye **çalıştırılabilir SQL olarak** yazılır (Kort'un phpMyAdmin'den elle
   çalıştırması için):
   ```sql
   ALTER TABLE `site_sahipleri` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ad_soyad`;
   ```
   (Gerçek kolon sırası/pozisyonu mevcut şemaya göre ayarlanır — `sema.sql`'deki gerçek
   sütun sırası kontrol edilerek `AFTER` ifadesi doğru yazılır.)
3. **Mevcut, zaten kayıtlı olan** kullanıcı(lar) (örn. Kort'un kendi hesabı, PROMPT-22
   testinde oluşan hesap varsa) bu migration sonrası `active = 0` olarak görünecektir
   (sütunun `DEFAULT 0` olması nedeniyle yeni satırlar için geçerli, ama mevcut satırlar
   için de varsayılan olarak 0 gelir). Bu yüzden migration SQL'ine **ek olarak**, Kort'un
   **kendi** hesabını (ve varsa halihazırda kullanıyor olduğu diğer gerçek hesapları)
   `active = 1` yapması gerektiği **açıkça ve öncelikli** şekilde DURUM.md'de belirtilir
   — aksi halde Kort kendi hesabından da dışarıda kalır.

## 3. Görev C — Kayıt Akışının Geri Getirilmesi ve `active` Kontrolü

1. `index.php`'deki `form_islem === 'kayit'` reddi (PROMPT-22'de eklenmişti) kaldırılır,
   `kullanici_kayit($_POST)` çağrısı **geri getirilir**.
2. `tema/giris.php`'deki kayıt formu/bölümü **geri getirilir** (PROMPT-22'de
   kaldırılmıştı).
3. `php/servis/kullanici-servisi.php` içinde:
   - `kullanici_kayit()`: `INSERT` ifadesine `active` sütunu eklenmez (DB'nin
     `DEFAULT 0`'ı yeterli — yeni kayıtlar otomatik pasif başlar). **Kayıt sonrası
     otomatik giriş (`kullanici_oturum_ac()` çağrısı) kaldırılır** — pasif bir hesap
     oturum açamamalı. Yanıt mesajı, kaydın alındığını ama onay beklediğini net şekilde
     belirtir (örn. `"Kaydınız alındı. Hesabınız onaylandıktan sonra giriş
     yapabilirsiniz."`), `return: 1` döner (kayıt başarılı ama giriş yok anlamında —
     veya `return: 0` ile birlikte bu mesaj; hangisi seçilirse frontend'de tutarlı
     şekilde ele alınır, DURUM.md'de belirtilir).
   - `kullanici_giris()`: mevcut e-posta/şifre doğrulamasından **sonra**, `active`
     sütunu kontrol edilir (sorguya `active` de eklenir). `active = 0` ise giriş
     **reddedilir**, net bir mesajla (örn. `"Hesabınız henüz onaylanmadı."`) — ama bu
     mesaj, var olmayan bir hesapla var olan-ama-pasif bir hesabı **ayırt etmemeli**
     mi yoksa ayırt edebilir mi? Mevcut kod yorumunda "kullanıcı var/yok ayrımı
     yapılmaz" ilkesi var — bu ilke **korunur**: pasif hesap mesajı, e-posta/şifre
     hatalı mesajından **farklı olabilir** (kullanıcı deneyimi için makul), ama bunun
     güvenlik açısından kabul edilebilir olduğu (yalnızca doğru şifre girildiğinde bu
     mesajın görünmesi, hesabın var olup olmadığını ifşa etmenin ötesine geçmediği)
     DURUM.md'de kısaca gerekçelendirilir.

## 4. Kesin Yasaklar

- **PROMPT-24'e ait hiçbir şey** (kaldırma butonu, liste filtresi) bu promptta
  yapılmaz.
- Google Ads/OAuth/kampanya akışlarına dokunulmaz.
- Şifre hash'leme mantığı (`password_hash`/`password_verify`) değiştirilmez.
- Geçici teşhis logu (`php/teshis-log.php`, PROMPT-22) bu promptta **kaldırılmaz** —
  o hâlâ ayrı, açık bir konu (izolasyon testi henüz yapılmadı).

## 5. Doğrulama

- `php -l` tüm değişen dosyalarda.
- Sentetik test: yeni kayıt → `active=0` ile oluşuyor, oturum açılmıyor; `active=0`
  hesapla giriş denemesi reddediliyor; `active=1` yapılan hesapla giriş başarılı.
- Migration SQL'inin gerçek `sema.sql` sütun sırasıyla tutarlı olduğu kontrol edilir.

## 6. DURUM.md

- Çalıştırılabilir `ALTER TABLE` SQL'i (§2.2).
- **Öncelikli** hatırlatma: Kort'un kendi hesabını migration sonrası `active=1` yapması
  gerektiği (örnek `UPDATE` ifadesiyle, kendi e-postasına göre).
- Görev C'deki mesaj/`return` kararının gerekçesi.
- Deploy listesi.
- PROMPT-22'nin hâlâ açık kalan izolasyon testi hatırlatması (bu promptun konusu değil
  ama unutulmasın diye tekrar not edilir).

Ayrı TODO listesi gerekmiyor (odaklı, üç görevli bir geri alma/değiştirme).
