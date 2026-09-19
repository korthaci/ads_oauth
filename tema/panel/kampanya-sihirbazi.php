<?php
$sayfa_basligi = 'Kampanya Sihirbazı - ads oauth';
require __DIR__ . '/../layout/header.php';
?>
<h1>ads oauth | Kampanya sihirbazı</h1>
<p>
    Bağlı Google Ads hesabınıza yeni bir <strong>Search (Arama)</strong> kampanyası
    oluşturulur. Kampanya, reklam grubu, anahtar kelimeler ve reklam bu formdaki
    bilgilerle otomatik hazırlanır. Kampanya <strong>duraklatılmış (PAUSED)</strong>
    olarak oluşturulur; ayrı bir onay adımı yapılmadan yayına alınmaz.
</p>

<form id="kampanya-sihirbazi-form">
    <p>
        <label for="web_sitesi">Web sitesi (reklamın yönlendireceği adres)</label><br>
        <input type="text" id="web_sitesi" name="web_sitesi" required autocomplete="off"
        value="https://n0n1.tr"
               placeholder="ornek.com (https:// otomatik eklenir)">
    </p>
    <p>
        <label for="kampanya_adi">Kampanya / iş adı</label><br>
        <input type="text" id="kampanya_adi" name="kampanya_adi" required maxlength="255"
        value="n0n1-ads-kampanya-test-1"/>
    </p>
    <p>
        <label for="basliklar">Reklam başlıkları (en az 3, en fazla 15; her biri en fazla 30 karakter)</label><br>
        <textarea id="basliklar" name="basliklar" rows="4" required
                  placeholder="Her satıra veya virgülle ayırarak yazın"></textarea>
                  Örnek : Web sitenizi oluşturun
Şimdi web sitesi yapın
Hemen yayına alın
    </p>
    <p>
        <label for="aciklamalar">Reklam açıklamaları (en az 2, en fazla 4; her biri en fazla 90 karakter)</label><br>
        <textarea id="aciklamalar" name="aciklamalar" rows="3" required
                  placeholder="Her satıra veya virgülle ayırarak yazın"></textarea>

                  Örnek : Bu reklam açıklaması test için yapılmıştır,
Bu reklam açıklaması test için yapılmıştır 2
    </p>
    <p>
        <label for="anahtar_kelimeler">Anahtar kelimeler (en az 1; virgülle ayırın)</label><br>
        <textarea id="anahtar_kelimeler" name="anahtar_kelimeler" rows="3" required
                  placeholder="örnek kelime 1, örnek kelime 2"></textarea>
                  Örnek : web sitesi yap,web sitesi oluştur
    </p>
    <p>
        <label for="gunluk_butce">Günlük bütçe (TL)</label><br>
        <input type="text" id="gunluk_butce" name="gunluk_butce" required
               inputmode="decimal" placeholder="500" value="500">
    </p>
    <p>
        <label for="hedef_konum">Hedef konum (şehir / bölge / ülke)</label><br>
        <input type="text" id="hedef_konum" name="hedef_konum" required placeholder="Ankara" value="Ankara">
    </p>
    <p>
        <button type="submit" id="sihirbaz-gonder">Kampanyayı oluştur (PAUSED)</button>
    </p>
    <p id="sihirbaz-mesaj" role="status" aria-live="polite"></p>
</form>

<p><a href="index.php">Panele dön</a></p>

<script>
document.getElementById('kampanya-sihirbazi-form').addEventListener('submit', function (olay) {
    olay.preventDefault();

    var dugme = document.getElementById('sihirbaz-gonder');
    var mesaj = document.getElementById('sihirbaz-mesaj');

    dugme.disabled = true;
    mesaj.textContent = 'Kampanya oluşturuluyor...';

    fetch('api/index.php?islem=kampanya-olustur', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        body: new FormData(this)
    })
        .then(function (yanit) { return yanit.json(); })
        .then(function (cevap) {
            if (cevap.return === 1) {
                mesaj.textContent = (cevap.mesaj || 'Kampanya oluşturuldu.')
                    + (cevap.kampanya_id ? ' Kampanya ID: ' + cevap.kampanya_id + '.' : '');
                return;
            }

            dugme.disabled = false;
            mesaj.textContent = (cevap.mesaj || 'Kampanya oluşturulamadı.')
                + (cevap.google_ads_hata ? ' (' + cevap.google_ads_hata + ')' : '');
        })
        .catch(function () {
            dugme.disabled = false;
            mesaj.textContent = 'Kampanya oluşturulamadı.';
        });
});
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
