document.getElementById('google-ads-hesap-secim-formu').addEventListener('submit', function (olay) {
    olay.preventDefault();

    var form = olay.target;
    var dugme = form.querySelector('button[type="submit"]');
    var mesaj = document.getElementById('google-hesap-secim-mesaj');

    dugme.disabled = true;
    mesaj.textContent = 'Google Ads hesabı bağlanıyor...';

    fetch('api/index.php?islem=google-hesap-sec', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        body: new URLSearchParams(new FormData(form))
    })
        .then(function (yanit) { return yanit.json(); })
        .then(function (cevap) {
            if (cevap.return === 1) {
                window.location.href = 'index.php';
                return;
            }

            dugme.disabled = false;
            mesaj.textContent = cevap.mesaj || 'Google Ads hesabı bağlanamadı.';
        })
        .catch(function () {
            dugme.disabled = false;
            mesaj.textContent = 'Google Ads hesabı bağlanamadı.';
        });
});
