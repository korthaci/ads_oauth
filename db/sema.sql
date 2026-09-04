
-- Veritabanı adı : ads_oauth

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