<?php

/**
 * Kampanyanin bitis tarihini degistirir.
 */

if (!defined('ADS_OAUTH_API_INDEX')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

/**
 * @return array<string, mixed>
 */
function api_kampanya_bitis_tarihi(): array
{
    return kampanya_bitis_tarihini_degistir($_POST);
}