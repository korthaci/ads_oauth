<?php
$sayfa_basligi = 'Kampanyalarım - ads oauth';
require __DIR__ . '/../layout/header.php';
?>
<h1>Kampanyalarım</h1>
<p>Bağlı Google Ads hesabınızdaki kampanyalar.</p>
<p>
    <label for="kampanya-filtresi">Liste filtresi: </label>
    <select id="kampanya-filtresi">
        <option value="active" selected>Kaldırılanlar hariç</option>
        <option value="all">Tümü</option>
    </select>
</p>
<div id="mesaj" class="status-message" role="status" aria-live="polite">Kampanyalar yükleniyor...</div>
<div id="uyari"></div><div id="onay"></div><div id="tablo"></div>
<script src="/ads-oauth/assets/js/kampanyalarim.js" defer></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
