# PROMPT 03 — Temel Altyapı (Google Öncelikli)

> Bu promptu uygulamadan önce sırasıyla `DURUM.md`, `ARCHITECTURE.md` ve bu dosyayı oku.

## Kapsam

Bu prompt ile şunlar yazılacak:
1. `db/sema.sql` — veri modeli (ilk sürüm)
2. `php/config.php`
3. `php/veritabani.php`
4. `php/oturum.php`
5. `php/sifreleme.php`

**Kapsam dışı** (sonraki promptlarda ele alınacak): OAuth akışı (`php/oauth/`), Baglayici
katmanı, Servis katmanı, panel/tema dosyaları.

**Karar notu (bu promptla netleşti, DURUM.md'nin Karar Günlüğü'ne eklenmeli):** Sistemin
nihai hedefi çoklu platform (Google + Meta) olsa da, geliştirme sırayla ilerleyecek — önce
Google Ads altyapısı tamamlanacak, Meta daha sonra eklenecek. Bu yüzden DB şemasında
`platform` alanı ileriye dönük olarak bulunuyor ama şu aşamada sadece `google` değeri
kullanılacak, Meta'ya özel hiçbir kod/mantık bu promptta yazılmayacak.

---

## 1. Konvansiyon Hatırlatmaları (kritik)

- Tüm tablo/alan isimleri **küçük harf, snake_case**. camelCase **hiçbir yerde** kullanılmayacak.
- Birincil anahtar alan adı **`no`** olacak (`id` değil) — `int(11) NOT NULL AUTO_INCREMENT`, `PRIMARY KEY`.
- Başka tablolara referans veren alanlar `<tablo_tekil>_no` şeklinde adlandırılır (örn. `sahip_no`, `hesap_no`).
- Dosya adları küçük harf + tire (`veritabani.php`, `sifreleme.php`). Sınıf kullanılacaksa
  sınıf adı PascalCase olabilir ama dosya adı yine küçük harf kalır. Bu promptta basitlik
  için sınıf değil, fonksiyon tabanlı dosyalar yazılacak (mevcut iskeletle tutarlı).
- Hassas veriler (refresh/access token) veritabanına **her zaman** `php/sifreleme.php`
  üzerinden şifrelenmiş yazılır, düz metin asla saklanmaz.

## 2. `db/sema.sql`

Aşağıdaki şema **birebir** uygulanacak. Engine olarak `InnoDB` seçildi (Websistem'in bazı
tablolarında `MyISAM` kullanılsa da, bu projede foreign key desteği ve şifreli token'ların
transactional bütünlüğü gerektiği için `InnoDB` tercih edildi — bu bir varsayımdır, Kort
onaylamazsa değiştirilebilir).

```sql
CREATE TABLE `site_sahipleri` (
  `no` int(11) NOT NULL AUTO_INCREMENT,
  `eposta` varchar(255) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `ad_soyad` varchar(255) DEFAULT NULL,
  `kayit_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`no`),
  UNIQUE KEY `eposta` (`eposta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `baglanmis_hesaplar` (
  `no` int(11) NOT NULL AUTO_INCREMENT,
  `sahip_no` int(11) NOT NULL,
  `platform` varchar(20) NOT NULL DEFAULT 'google',
  `harici_kimlik` varchar(100) DEFAULT NULL,
  `hesap_adi` varchar(255) DEFAULT NULL,
  `refresh_token_sifreli` text DEFAULT NULL,
  `erisim_token_sifreli` text DEFAULT NULL,
  `token_bitis` datetime DEFAULT NULL,
  `baglanti_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`no`),
  KEY `sahip_no` (`sahip_no`),
  CONSTRAINT `fk_baglanmis_hesaplar_sahip` FOREIGN KEY (`sahip_no`) REFERENCES `site_sahipleri` (`no`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kampanyalar` (
  `no` int(11) NOT NULL AUTO_INCREMENT,
  `hesap_no` int(11) NOT NULL,
  `platform` varchar(20) NOT NULL DEFAULT 'google',
  `harici_kampanya_id` varchar(100) DEFAULT NULL,
  `kampanya_adi` varchar(255) DEFAULT NULL,
  `gunluk_butce` decimal(10,2) DEFAULT NULL,
  `hedef_url` varchar(500) DEFAULT NULL,
  `durum` varchar(20) NOT NULL DEFAULT 'taslak',
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`no`),
  KEY `hesap_no` (`hesap_no`),
  CONSTRAINT `fk_kampanyalar_hesap` FOREIGN KEY (`hesap_no`) REFERENCES `baglanmis_hesaplar` (`no`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `senkron_kayitlari` (
  `no` int(11) NOT NULL AUTO_INCREMENT,
  `kampanya_no` int(11) DEFAULT NULL,
  `calisma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `sonuc` varchar(20) NOT NULL,
  `detay` text DEFAULT NULL,
  PRIMARY KEY (`no`),
  KEY `kampanya_no` (`kampanya_no`),
  CONSTRAINT `fk_senkron_kampanya` FOREIGN KEY (`kampanya_no`) REFERENCES `kampanyalar` (`no`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Not:** `durum` (kampanyalar) ve `sonuc` (senkron_kayitlari) alanları serbest metin
`varchar` olarak bırakıldı, enum kullanılmadı — ileride yeni durum değerleri eklemek
(örn. Meta'ya özel bir durum) migration gerektirmesin diye.

## 3. `.env.sample` — bu promptla ek gerekmiyor

Mevcut liste (ARCHITECTURE.md §6) bu adım için yeterli:
`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SIFRELEME_ANAHTARI`.
`DB_NAME` değeri örnek dosyada `ads_oauth` olarak yazılmalı (proje adıyla aynı).

## 4. `php/config.php`

Sorumluluğu: `.env` dosyasını okuyup sabit/erişilebilir hale getirmek. Üçüncü parti bir
paket (`vlucas/phpdotenv` vb.) **eklenmeyecek** — projede zaten minimum bağımlılık ilkesi var,
bu iş birkaç satır manuel parse ile çözülebilir.

Gereken davranış:
- `.env` dosyasını satır satır okur, `ANAHTAR=deger` formatını ayrıştırır, `#` ile başlayan
  satırları ve boş satırları atlar.
- Değerleri `getenv()`/`$_ENV` yerine basit bir global fonksiyonla erişilebilir yapar:
  `config('DB_HOST')` gibi bir fonksiyon öneriliyor (isimlendirme kod yazıcı AI'nin
  takdirinde, ama Türkçe/eylem-bazlı konvansiyona uysun).
- `.env` bulunamazsa veya zorunlu bir anahtar eksikse **açık bir hata ile durmalı**
  (sessizce null dönmemeli) — aksi halde ileride token şifreleme gibi kritik noktalarda
  sessiz başarısızlık riski olur.

## 5. `php/veritabani.php`

Sorumluluğu: tek bir PDO bağlantısı kurup paylaşmak (her çağrıda yeni bağlantı açmamak).

Gereken davranış:
- `config.php`'den `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` okunur.
- PDO, `utf8mb4` charset ile, `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` ayarıyla kurulur.
- Bağlantı statik bir değişkende tutulup tekrar kullanılır (fonksiyon her çağrıldığında
  yeniden bağlanmaz).
- Örnek fonksiyon adı: `veritabani_baglan()` — PDO nesnesi döner.

## 6. `php/oturum.php`

Sorumluluğu: `site_sahipleri.no` bazlı basit oturum yönetimi.

Gereken davranış (fonksiyon isimleri kod yazıcı AI'nin takdirinde, Türkçe/eylem-bazlı olsun):
- Oturumu başlatan bir fonksiyon (`session_start()` sarmalayıcı, zaten başlamışsa tekrar
  başlatmaz).
- Giriş yapmış kullanıcının `no` değerini oturuma yazan bir fonksiyon.
- O anki oturumdaki `sahip_no`'yu döndüren bir fonksiyon (giriş yoksa `null`).
- Oturumu sonlandıran (çıkış) bir fonksiyon.

## 7. `php/sifreleme.php`

Sorumluluğu: `baglanmis_hesaplar.refresh_token_sifreli` / `erisim_token_sifreli` alanlarını
şifrelemek/çözmek.

Gereken davranış:
- `openssl_encrypt` / `openssl_decrypt`, algoritma `AES-256-CBC`.
- Anahtar `.env`'deki `SIFRELEME_ANAHTARI`'ndan okunur (`config.php` üzerinden).
- Her şifreleme işleminde rastgele bir IV üretilir (`openssl_random_pseudo_bytes`), IV
  şifreli veriyle birlikte saklanır (örn. base64 ile birleştirilip tek bir string olarak
  döndürülür), çünkü aynı IV'nin tekrar kullanılması güvenlik açığı yaratır.
- İki fonksiyon: şifreleyen (`sifrele($veri)`) ve çözen (`coz($sifreli_veri)`).

---

## Görev Sonu

Bu görev tamamlandığında, her zamanki gibi **`DURUM.md`'yi kod yazıcı AI güncelleyecek**:
- "Tamamlanan Adımlar" listesine PROMPT 03 eklenir.
- "Prompt Dosyaları İzleme Tablosu"na yeni satır eklenir.
- "Karar Günlüğü"ne şu iki karar not düşülür:
  1. Çoklu platform hedefleniyor ama geliştirme sıralı ilerleyecek — önce Google Ads.
  2. DB engine olarak InnoDB seçildi (FK ve transactional bütünlük gerekçesiyle).
- "Açık Sorular" bölümünden "çoklu platform mu tek platform mu" maddesi kaldırılabilir
  (bu promptla netleşti).
