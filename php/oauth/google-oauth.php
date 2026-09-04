<?php

/**
 * Google OAuth teknik akisini yurutur.
 *
 * Bu dosya api/index.php tarafindan yuklenir; HTTP giris noktasi degildir.
 * Google Auth ve Google Ads PHP client paketleri mevcut Composer autoload'u
 * uzerinden kullanilir.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/oturum.php';
require_once dirname(__DIR__) . '/sifreleme.php';
require_once dirname(__DIR__) . '/veritabani.php';

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Auth\OAuth2;

const GOOGLE_OAUTH_SCOPE = 'https://www.googleapis.com/auth/adwords';
const GOOGLE_OAUTH_AUTHORIZATION_URI = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token';
const GOOGLE_OAUTH_REDIRECT_URI = 'http://localhost/ads_oauth/api/index.php?islem=oauth-donus';
const GOOGLE_OAUTH_STATE_SESSION_KEY = 'google_oauth_state';

/**
 * Google OAuth ayarlarini zorunlu alanlari aciga cikarmadan dogrular.
 *
 * @return array{client_id: string, client_secret: string}
 */
function google_oauth_ayarlarini_al(): array
{
    $client_id = config('GOOGLE_CLIENT_ID');
    $client_secret = config('GOOGLE_CLIENT_SECRET');

    if ($client_id === '' || $client_secret === '') {
        throw new RuntimeException('Google OAuth client ayarlari eksik.');
    }

    return [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
    ];
}

/**
 * Authorization URL'sini Google Auth OAuth2 sinifi ile uretir.
 */
function google_oauth_yetkilendirme_urlu_uret(string $state): string
{
    $ayarlar = google_oauth_ayarlarini_al();
    $oauth = new OAuth2([
        'authorizationUri' => GOOGLE_OAUTH_AUTHORIZATION_URI,
        'redirectUri' => GOOGLE_OAUTH_REDIRECT_URI,
        'clientId' => $ayarlar['client_id'],
        'clientSecret' => $ayarlar['client_secret'],
        'scope' => GOOGLE_OAUTH_SCOPE,
        'state' => $state,
    ]);

    $url = $oauth->buildFullAuthorizationUri([
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);

    return (string) $url;
}

/**
 * Oturumdaki kullanici icin Google OAuth akisini baslatir.
 *
 * @return array{return: int, mesaj: string, url?: string}
 */
function google_oauth_baslat(): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth başlatmak için giriş yapmalısınız.',
        ];
    }

    $state = bin2hex(random_bytes(32));
    $url = google_oauth_yetkilendirme_urlu_uret($state);

    oturum_baslat();
    $_SESSION[GOOGLE_OAUTH_STATE_SESSION_KEY] = $state;

    return [
        'return' => 1,
        'mesaj' => 'Google OAuth URL hazır.',
        'url' => $url,
    ];
}

/**
 * Callback parametrelerinin tekil, bos olmayan string oldugunu kontrol eder.
 */
function google_oauth_tekil_parametre(array $parametreler, string $anahtar): ?string
{
    if (!array_key_exists($anahtar, $parametreler) || !is_string($parametreler[$anahtar])) {
        return null;
    }

    $deger = trim($parametreler[$anahtar]);

    return $deger === '' ? null : $deger;
}

/**
 * Authorization code'u Google token endpoint'inde refresh token'a cevirir.
 * Access token bu akista kullanilmaz ve saklanmaz.
 */
function google_oauth_refresh_token_al(string $code): string
{
    $ayarlar = google_oauth_ayarlarini_al();
    $oauth = new OAuth2([
        'tokenCredentialUri' => GOOGLE_OAUTH_TOKEN_URI,
        'redirectUri' => GOOGLE_OAUTH_REDIRECT_URI,
        'clientId' => $ayarlar['client_id'],
        'clientSecret' => $ayarlar['client_secret'],
    ]);
    $oauth->setCode($code);
    $oauth->setGrantType('authorization_code');

    $token_response = $oauth->fetchAuthToken();
    $refresh_token = $token_response['refresh_token'] ?? null;
    unset($token_response);

    if (!is_string($refresh_token) || trim($refresh_token) === '') {
        throw new RuntimeException('Google refresh token alınamadı.');
    }

    return trim($refresh_token);
}

/**
 * Refresh token'in Google Ads PHP client tarafinda kullanilabilir credential
 * olarak kurulabildigini kontrol eder. Google Ads API istegi gondermez.
 */
function google_oauth_credential_dogrula(string $refresh_token): void
{
    $credential = (new OAuth2TokenBuilder())
        ->withClientId(config('GOOGLE_CLIENT_ID'))
        ->withClientSecret(config('GOOGLE_CLIENT_SECRET'))
        ->withRefreshToken($refresh_token)
        ->build();

    unset($credential);
}

/**
 * Refresh token'i mevcut bagli hesap kaydina ekler veya kullanicinin mevcut
 * Google kaydini gunceller. Harici hesap kimligi bu asamada bilincli olarak
 * NULL birakilir.
 */
function google_oauth_refresh_token_kaydet(int $sahip_no, string $refresh_token): void
{
    $sifreli_refresh_token = sifrele($refresh_token);
    $baglanti = veritabani_baglan();

    $baglanti->beginTransaction();

    try {
        $sec = $baglanti->prepare(
            'SELECT `no` FROM `baglanmis_hesaplar` '
            . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform '
            . 'ORDER BY `no` DESC LIMIT 1'
        );
        $sec->execute([
            'sahip_no' => $sahip_no,
            'platform' => 'google',
        ]);
        $hesap = $sec->fetch();

        if (is_array($hesap) && isset($hesap['no'])) {
            $guncelle = $baglanti->prepare(
                'UPDATE `baglanmis_hesaplar` SET '
                . '`refresh_token_sifreli` = :refresh_token_sifreli, '
                . '`erisim_token_sifreli` = NULL, `token_bitis` = NULL, `aktif` = 1 '
                . 'WHERE `no` = :no AND `sahip_no` = :sahip_no AND `platform` = :platform'
            );
            $guncelle->execute([
                'refresh_token_sifreli' => $sifreli_refresh_token,
                'no' => (int) $hesap['no'],
                'sahip_no' => $sahip_no,
                'platform' => 'google',
            ]);
        } else {
            $ekle = $baglanti->prepare(
                'INSERT INTO `baglanmis_hesaplar` '
                . '(`sahip_no`, `platform`, `harici_kimlik`, `refresh_token_sifreli`, '
                . '`erisim_token_sifreli`, `token_bitis`, `aktif`) '
                . 'VALUES (:sahip_no, :platform, NULL, :refresh_token_sifreli, NULL, NULL, 1)'
            );
            $ekle->execute([
                'sahip_no' => $sahip_no,
                'platform' => 'google',
                'refresh_token_sifreli' => $sifreli_refresh_token,
            ]);
        }

        $baglanti->commit();
    } catch (Throwable $hata) {
        if ($baglanti->inTransaction()) {
            $baglanti->rollBack();
        }

        throw $hata;
    }
}

/**
 * Google OAuth callback'ini dogrular, token'i alir ve sifreli olarak kaydeder.
 *
 * @param array<string, mixed>|null $parametreler Test edilebilirlik icin
 * callback parametreleri; NULL verilirse $_GET kullanilir.
 * @return array{return: int, mesaj: string}
 */
function google_oauth_donus(?array $parametreler = null): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth callback için giriş yapmalısınız.',
        ];
    }

    $parametreler = $parametreler ?? $_GET;
    $gelen_state = google_oauth_tekil_parametre($parametreler, 'state');
    oturum_baslat();
    $oturum_state = $_SESSION[GOOGLE_OAUTH_STATE_SESSION_KEY] ?? null;

    if (!is_string($oturum_state) || $gelen_state === null || !hash_equals($oturum_state, $gelen_state)) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth state doğrulaması başarısız.',
        ];
    }

    unset($_SESSION[GOOGLE_OAUTH_STATE_SESSION_KEY]);

    if (google_oauth_tekil_parametre($parametreler, 'error') !== null) {
        return [
            'return' => 0,
            'mesaj' => 'Google OAuth işlemi kullanıcı tarafından reddedildi.',
        ];
    }

    $code = google_oauth_tekil_parametre($parametreler, 'code');

    if ($code === null) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth authorization code alınamadı.',
        ];
    }

    $refresh_token = google_oauth_refresh_token_al($code);
    google_oauth_credential_dogrula($refresh_token);
    google_oauth_refresh_token_kaydet($sahip_no, $refresh_token);

    unset($refresh_token);

    return [
        'return' => 1,
        'mesaj' => 'Google Ads hesabı başarıyla bağlandı.',
    ];
}