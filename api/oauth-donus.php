<?php

/**
 * Google OAuth callback HTTP giris noktasi.
 *
 * Bu dosya yalnizca api/index.php tarafindan include edilir; HTTP giris noktasi
 * olarak dogrudan calistirilamaz. Is mantigi php/oauth/google-oauth.php
 * icindeki google_oauth_donus() fonksiyonundadir.
 */

if (!defined('ADS_OAUTH_API_INDEX')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/oauth/google-oauth.php';

/**
 * @return array{return: int, mesaj: string}
 */
function api_oauth_donus(): array
{
    return google_oauth_donus();
}
