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
        <small class="hint">Reklam tıklanınca ziyaretçinin gideceği adres; https:// eksikse otomatik eklenir.</small>
    </p>
    <p>
        <label for="kampanya_adi">Kampanya / iş adı</label><br>
        <input type="text" id="kampanya_adi" name="kampanya_adi" required maxlength="255"
        value="n0n1-ads-kampanya-test-2"/>
        <small class="hint">Kampanyayı listede kolayca tanıyacağınız isim.</small>
    </p>
    <p>
        <label for="basliklar">Reklam başlıkları (en az 3, en fazla 15; her biri en fazla 30 karakter)</label><br>
        <textarea id="basliklar" name="basliklar" rows="4" required
                  placeholder="Her satıra veya virgülle ayırarak yazın"></textarea>
        <small class="hint">Her satıra veya virgülle ayırarak yazabilirsiniz; Google uygun başlıkları dönüşümlü kullanır.</small>
                  Örnek : Web sitenizi oluşturun
Şimdi web sitesi yapın
Hemen yayına alın
    </p>
    <p>
        <label for="aciklamalar">Reklam açıklamaları (en az 2, en fazla 4; her biri en fazla 90 karakter)</label><br>
        <textarea id="aciklamalar" name="aciklamalar" rows="3" required
                  placeholder="Her satıra veya virgülle ayırarak yazın"></textarea>
        <small class="hint">Ürününüzü veya hizmetinizi açıklayan kısa metinler; her biri en fazla 90 karakterdir.</small>

                  Örnek : Bu reklam açıklaması test için yapılmıştır,
Bu reklam açıklaması test için yapılmıştır 2
    </p>
    <p>
        <label for="anahtar_kelimeler">Anahtar kelimeler (en az 1; virgülle ayırın)</label><br>
        <textarea id="anahtar_kelimeler" name="anahtar_kelimeler" rows="3" required
                  placeholder="örnek kelime 1, örnek kelime 2"></textarea>
        <small class="hint">Müşterilerin arayabileceği kelimeleri her satıra veya virgülle ayırarak yazın.</small>
                  Örnek : web sitesi yap,web sitesi oluştur
    </p>
    <p>
        <label for="gunluk_butce">Günlük bütçe (TL)</label><br>
        <input type="text" id="gunluk_butce" name="gunluk_butce" required
               inputmode="decimal" placeholder="500" value="500">
        <small class="hint">Google'ın günlük ortalamada hedefleyeceği tutardır; bazı günler biraz daha fazla harcayabilir.</small>
    </p>
    <p>
        <label for="hedef_konum">Hedef konum (şehir / bölge / ülke)</label><br>
        <input type="text" id="hedef_konum" name="hedef_konum" required placeholder="Ankara" value="Ankara">
        <small class="hint">Reklamın gösterileceği şehir, bölge veya ülke; birden fazla eşleşmede seçim yapmanız istenir.</small>
    </p>
    <p>
        <label for="haric_konumlar">Hariç tutulacak bölgeler (opsiyonel; virgülle veya satırla ayırın)</label><br>
        <textarea id="haric_konumlar" name="haric_konumlar" rows="2"
                  placeholder="örn. Trabzon, Kırıkkale"></textarea>
        <small class="hint">Reklam göstermek istemediğiniz bölgeleri virgülle veya satırla ayırın; bu alan isteğe bağlıdır.</small>
    </p>
    <p>
        <label for="toplam_butce">Toplam bütçe (TL, opsiyonel)</label><br>
        <input type="text" id="toplam_butce" name="toplam_butce" inputmode="decimal" placeholder="örn. 5000">
        <small class="hint">İsteğe bağlı yaklaşık toplam tutar; girilirse buna göre bir bitiş tarihi atanır, kesin harcama sınırı değildir.</small>
        <br>
        <small>Bu, kesin bir toplam harcama garantisi değildir — Google bazı günlerde
        günlük bütçenin biraz üzerinde harcayıp ayı ortalayabilir; bu alan yalnızca
        kampanyanın yaklaşık olarak toplam bütçe / günlük bütçe gün sonra otomatik
        olarak durmasını sağlar (bitiş tarihi atanır).</small>
    </p>
    <div id="konum-secim" style="display: none; border: 1px solid #c8c8c8; padding: 10px; margin: 10px 0;"></div>
    <input type="hidden" name="hedef_konum_resource_name" value="">
    <input type="hidden" name="hedef_konum_kaynak_metin" value="">
    <input type="hidden" name="haric_konum_resource_name" value="">
    <input type="hidden" name="haric_konum_kaynak_metin" value="">
    <p>
        <button type="submit" id="sihirbaz-gonder">Kampanyayı oluştur (PAUSED)</button>
    </p>
    <p id="sihirbaz-mesaj" role="status" aria-live="polite"></p>
</form>

<p><a href="index.php">Panele dön</a></p>

<script>
(function () {
    var form = document.getElementById('kampanya-sihirbazi-form');
    var dugme = document.getElementById('sihirbaz-gonder');
    var mesaj = document.getElementById('sihirbaz-mesaj');
    var secimKutusu = document.getElementById('konum-secim');
    var hedefKaynak = form.querySelector('input[name="hedef_konum_resource_name"]');
    var hedefMetin = form.querySelector('input[name="hedef_konum_kaynak_metin"]');
    var haricKaynak = form.querySelector('input[name="haric_konum_resource_name"]');
    var haricMetin = form.querySelector('input[name="haric_konum_kaynak_metin"]');

    function secimi_gizle() {
        secimKutusu.style.display = 'none';
        secimKutusu.innerHTML = '';
        secimKutusu.removeAttribute('data-baglam');
        secimKutusu.removeAttribute('data-metin');
    }

    function secimi_sifirla() {
        secimi_gizle();
        hedefKaynak.value = '';
        hedefMetin.value = '';
        haricKaynak.value = '';
        haricMetin.value = '';
    }

    // PROMPT-21 §1.1.4: konum metni değişirse önceki seçim geçersizdir;
    // seçenek listesi gizlenir ve bir sonraki gönderimde yeniden sorulur.
    document.getElementById('hedef_konum').addEventListener('input', function () {
        hedefKaynak.value = '';
        hedefMetin.value = '';
        secimi_gizle();
    });

    document.getElementById('haric_konumlar').addEventListener('input', function () {
        haricKaynak.value = '';
        haricMetin.value = '';
        secimi_gizle();
    });

    // PROMPT-21 §1.2.2: kullanıcı bir adayı işaretleyince ilgili gizli alan
    // doldurulur; form "Kampanyayı oluştur" ile seçimle birlikte tekrar
    // gönderilir. Sayfa yenilenmez.
    secimKutusu.addEventListener('change', function () {
        var secili = secimKutusu.querySelector('input[name="konum_secim"]:checked');

        if (!secili) {
            return;
        }

        if (secimKutusu.getAttribute('data-baglam') === 'haric') {
            haricKaynak.value = secili.value;
            haricMetin.value = secimKutusu.getAttribute('data-metin') || '';
        } else {
            hedefKaynak.value = secili.value;
            hedefMetin.value = secimKutusu.getAttribute('data-metin') || '';
        }
    });

    function konum_seceneklerini_goster(cevap) {
        var secenekler = cevap.konum_secenekleri;

        if (!Array.isArray(secenekler) || secenekler.length === 0) {
            return false;
        }

        var baglam = cevap.konum_secenekleri_baglam === 'haric' ? 'haric' : 'hedef';
        var metin = String(cevap.konum_secenekleri_metin || '');

        secimi_gizle();

        var baslik = document.createElement('p');
        baslik.textContent = "'" + metin + "'"
            + (baglam === 'haric' ? ' (hariç tutulan konum)' : '')
            + ' için birden fazla eşleşme bulundu; bir tanesini seçin:';
        secimKutusu.appendChild(baslik);

        secenekler.forEach(function (aday) {
            var etiket = document.createElement('label');
            etiket.style.display = 'block';

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'konum_secim';
            radio.value = String(aday.resource_name || '');

            etiket.appendChild(radio);
            etiket.appendChild(document.createTextNode(
                ' ' + String(aday.ad || '')
                + (aday.tip ? ' (' + aday.tip + ')' : '')
                + (aday.canonical_name ? ' — ' + aday.canonical_name : '')
            ));

            secimKutusu.appendChild(etiket);
        });

        secimKutusu.setAttribute('data-baglam', baglam);
        secimKutusu.setAttribute('data-metin', metin);
        secimKutusu.style.display = 'block';
        secimKutusu.scrollIntoView({ block: 'nearest' });

        return true;
    }

    form.addEventListener('submit', function (olay) {
        olay.preventDefault();

        dugme.disabled = true;
        mesaj.textContent = 'Kampanya oluşturuluyor...';

        fetch('api/index.php?islem=kampanya-olustur', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: new FormData(form)
        })
            .then(function (yanit) { return yanit.json(); })
            .then(function (cevap) {
                if (cevap.konum_secenekleri) {
                    // PROMPT-21 §1.2.1: belirsiz konum — form gönderilmeden
                    // engellenip aynı sayfada radio listesi gösterilir.
                    dugme.disabled = false;
                    konum_seceneklerini_goster(cevap);
                    mesaj.textContent = cevap.mesaj || 'Konum belirsiz, lütfen birini seçin.';
                    return;
                }

                if (cevap.return === 1) {
                    secimi_sifirla();
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
})();
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
