<?php
$sayfa_basligi = 'Panel - ads_oauth';
require __DIR__ . '/../layout/header.php';
?>
<h1>Hoş geldiniz</h1>
<p>Oturum açıldı. Google Ads hesabınızı bağlayarak OAuth akışını başlatabilirsiniz.</p>

<p>
    <button type="button" id="google-oauth-baslat">Google Ads hesabını bağla</button>
</p>
<p id="google-oauth-mesaj" role="status" aria-live="polite"></p>

<p><a href="index.php?islem=cikis">Çıkış yap</a></p>

<script>
document.getElementById('google-oauth-baslat').addEventListener('click', function () {
    var dugme = this;
    var mesaj = document.getElementById('google-oauth-mesaj');

    dugme.disabled = true;
    mesaj.textContent = 'Google OAuth hazırlanıyor...';

    fetch('api/index.php?islem=oauth-baslat', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(function (yanit) { return yanit.json(); })
        .then(function (cevap) {
            if (cevap.return === 1 && typeof cevap.url === 'string' && cevap.url !== '') {
                window.location.href = cevap.url;
                return;
            }

            dugme.disabled = false;
            mesaj.textContent = cevap.mesaj || 'OAuth başlatılamadı.';
        })
        .catch(function () {
            dugme.disabled = false;
            mesaj.textContent = 'OAuth başlatılamadı.';
        });
});
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>