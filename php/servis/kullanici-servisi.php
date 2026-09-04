<?php

/**
 * Site sahibi kayit, giris, oturum kontrolu ve cikis mantigi.
 *
 * Bu servis web giris noktasi ve api/index.php tarafindan kullanilir.
 * Veritabani sorgulari PDO prepared statement ile calistirilir.
 */

require_once dirname(__DIR__) . '/oturum.php';
require_once dirname(__DIR__) . '/veritabani.php';

/**
 * E-posta girdisini karsilastirmalarda kullanilacak sekilde normalize eder.
 */
function kullanici_eposta_normalize_et(mixed $eposta): string
{
    if (!is_string($eposta)) {
        return '';
    }

    return strtolower(trim($eposta));
}

/**
 * Kayit verilerini dogrular ve normalize edilmis degerleri dondurur.
 *
 * @param array<string, mixed> $veriler
 * @return array{return: int, mesaj: string, eposta?: string, sifre?: string, ad_soyad?: string}
 */
function kullanici_kayit_verilerini_dogrula(array $veriler): array
{
    $eposta = kullanici_eposta_normalize_et($veriler['eposta'] ?? null);
    $sifre = is_string($veriler['sifre'] ?? null) ? $veriler['sifre'] : '';
    $ad_soyad = is_string($veriler['ad_soyad'] ?? null) ? trim($veriler['ad_soyad']) : '';

    if ($eposta === '' || $sifre === '' || $ad_soyad === '') {
        return [
            'return' => 0,
            'mesaj' => 'Ad soyad, e-posta ve şifre alanları zorunludur.',
        ];
    }

    if (filter_var($eposta, FILTER_VALIDATE_EMAIL) === false || strlen($eposta) > 255) {
        return [
            'return' => 0,
            'mesaj' => 'Geçerli bir e-posta adresi girin.',
        ];
    }

    if (strlen($ad_soyad) > 255) {
        return [
            'return' => 0,
            'mesaj' => 'Ad soyad alanı çok uzun.',
        ];
    }

    return [
        'return' => 1,
        'mesaj' => 'Kayıt verileri geçerli.',
        'eposta' => $eposta,
        'sifre' => $sifre,
        'ad_soyad' => $ad_soyad,
    ];
}

/**
 * Başarili login sonrasinda session fixation riskini azaltir ve sahip numarasini yazar.
 */
function kullanici_oturum_ac(int $sahip_no): void
{
    if ($sahip_no < 1) {
        throw new InvalidArgumentException('Geçersiz site sahibi numarası.');
    }

    oturum_baslat();

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Oturum güvenli şekilde yenilenemedi.');
    }

    oturum_sahip_no_yaz($sahip_no);
}

/**
 * Yeni site sahibi kaydeder ve kayit basarisinda otomatik login yapar.
 *
 * @param array<string, mixed> $veriler
 * @return array{return: int, mesaj: string}
 */
function kullanici_kayit(array $veriler): array
{
    $dogrulama = kullanici_kayit_verilerini_dogrula($veriler);

    if (($dogrulama['return'] ?? 0) !== 1) {
        return [
            'return' => 0,
            'mesaj' => (string) $dogrulama['mesaj'],
        ];
    }

    $baglanti = veritabani_baglan();
    $eposta = (string) $dogrulama['eposta'];

    $mevcut = $baglanti->prepare(
        'SELECT `no` FROM `site_sahipleri` WHERE `eposta` = :eposta LIMIT 1'
    );
    $mevcut->execute(['eposta' => $eposta]);

    if ($mevcut->fetch() !== false) {
        return [
            'return' => 0,
            'mesaj' => 'Bu e-posta adresi zaten kayıtlı.',
        ];
    }

    $sifre_hash = password_hash((string) $dogrulama['sifre'], PASSWORD_DEFAULT);

    if (!is_string($sifre_hash) || $sifre_hash === '') {
        throw new RuntimeException('Şifre güvenli şekilde işlenemedi.');
    }

    try {
        $ekle = $baglanti->prepare(
            'INSERT INTO `site_sahipleri` (`eposta`, `sifre`, `ad_soyad`) '
            . 'VALUES (:eposta, :sifre, :ad_soyad)'
        );
        $ekle->execute([
            'eposta' => $eposta,
            'sifre' => $sifre_hash,
            'ad_soyad' => (string) $dogrulama['ad_soyad'],
        ]);
    } catch (PDOException $hata) {
        if ((string) $hata->getCode() === '23000') {
            return [
                'return' => 0,
                'mesaj' => 'Bu e-posta adresi zaten kayıtlı.',
            ];
        }

        throw $hata;
    }

    $sahip_no = (int) $baglanti->lastInsertId();
    kullanici_oturum_ac($sahip_no);

    return [
        'return' => 1,
        'mesaj' => 'Kayıt başarılı.',
    ];
}

/**
 * E-posta ve sifre ile site sahibi girisi yapar.
 *
 * Basarisiz login durumlarinda kullanici var/yok ayrimi yapilmaz.
 *
 * @param array<string, mixed> $veriler
 * @return array{return: int, mesaj: string}
 */
function kullanici_giris(array $veriler): array
{
    $eposta = kullanici_eposta_normalize_et($veriler['eposta'] ?? null);
    $sifre = is_string($veriler['sifre'] ?? null) ? $veriler['sifre'] : '';

    if ($eposta === '' || $sifre === '' || filter_var($eposta, FILTER_VALIDATE_EMAIL) === false) {
        return [
            'return' => 0,
            'mesaj' => 'E-posta veya şifre hatalı.',
        ];
    }

    $baglanti = veritabani_baglan();
    $sec = $baglanti->prepare(
        'SELECT `no`, `sifre` FROM `site_sahipleri` WHERE `eposta` = :eposta LIMIT 1'
    );
    $sec->execute(['eposta' => $eposta]);
    $kullanici = $sec->fetch();

    if (!is_array($kullanici)
        || !isset($kullanici['no'], $kullanici['sifre'])
        || !is_string($kullanici['sifre'])
        || !password_verify($sifre, $kullanici['sifre'])) {
        return [
            'return' => 0,
            'mesaj' => 'E-posta veya şifre hatalı.',
        ];
    }

    kullanici_oturum_ac((int) $kullanici['no']);

    return [
        'return' => 1,
        'mesaj' => 'Giriş başarılı.',
    ];
}

/**
 * Mevcut session mekanizmasini kullanarak oturumu kapatir.
 *
 * @return array{return: int, mesaj: string}
 */
function kullanici_cikis(): array
{
    oturum_sonlandir();

    return [
        'return' => 1,
        'mesaj' => 'Çıkış başarılı.',
    ];
}

/**
 * Aktif site sahibi oturumunu API standardinda bildirir.
 * Sahip numarasi credential olmadigi icin response'a eklenmez.
 *
 * @return array{return: int, mesaj: string}
 */
function kullanici_oturum_kontrolu(): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'Aktif oturum bulunamadı.',
        ];
    }

    return [
        'return' => 1,
        'mesaj' => 'Oturum aktif.',
    ];
}