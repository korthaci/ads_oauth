<?php

/**
 * Google Ads kampanyalarini read-only listeler.
 *
 * Bu dosya yalnizca api/index.php tarafindan include edilir; HTTP giris noktasi
 * olarak dogrudan calistirilamaz. Is mantigi php/servis/kampanya-servisi.php
 * icindedir. 
 */

if (!defined('ADS_OAUTH_API_INDEX')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

/**
 * @return array<string, mixed>
 */
function api_kampanya_listele(): array
{
    return kampanyalari_listele();
}