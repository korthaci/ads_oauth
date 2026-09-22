document.querySelectorAll('.google-oauth-baslat-dugmesi').forEach(function (dugme) {
    dugme.addEventListener('click', function () {
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
});
