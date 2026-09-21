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

<div class="campaign-builder">
<section id="ads-ai-yardimci" class="ai-helper-panel">
    <h2>Ads AI yardımcısı</h2>
    <p>Ne reklamı vermek istediğinizi yazın. AI yalnızca öneri üretir; kampanya oluşturmaz veya yayınlamaz.</p>
    <p>
        <label for="ai-brief">AI'ya ne yapmak istediğinizi yazın</label><br>
        <textarea id="ai-brief" rows="4" maxlength="4000"
                  placeholder="Trabzon'da web tasarım hizmetim için yeni müşteri bulmak istiyorum."></textarea>
    </p>
    <p>
        <button type="button" id="ai-oneri-olustur">Öneri oluştur</button>
    </p>
    <p id="ai-mesaj" role="status" aria-live="polite"></p>
    <div id="ai-oneri-sonucu" hidden>
        <h3>Öneriler</h3>
        <p><strong>Kampanya adı:</strong> <span id="ai-kampanya-adi"></span></p>
        <p><strong>Kampanya amacı:</strong> <span id="ai-kampanya-amaci"></span></p>
        <p><strong>Hedef bölge:</strong> <span id="ai-hedef-konum"></span></p>
        <div id="ai-oneri-listeleri"></div>
        <small>Önerileri kontrol edin; AI yalnızca forma öneri aktarır, Google Ads kampanyasını sizin form gönderiminiz oluşturur.</small>
        <p>
            <button type="button" id="ai-onerileri-uygula">Önerileri forma uygula</button>
        </p>
    </div>
</section>

<div class="campaign-form-column">
<form id="kampanya-sihirbazi-form" class="campaign-form">
    <p>
        <label for="web_sitesi">Web sitesi (reklamın yönlendireceği adres)</label><br>
        <input type="text" id="web_sitesi" name="web_sitesi" required autocomplete="off"
               placeholder="ornek.com (https:// otomatik eklenir)">
        <small class="hint">Reklam tıklanınca ziyaretçinin gideceği adres; https:// eksikse otomatik eklenir. Örnek : n0n1.tr</small>
    </p>
    <p>
        <label for="kampanya_adi">Kampanya / iş adı</label><br>
        <input type="text" id="kampanya_adi" name="kampanya_adi" required maxlength="255" />
        <small class="hint">Kampanyayı listede kolayca tanıyacağınız isim. Herhangi bir şey olabilir.  Örnek : n0n1-ads-kampanya-test-2</small>
    </p>
    <p>
        <label for="basliklar">Reklam başlıkları (en az 3, en fazla 15; her biri en fazla 30 karakter)</label><br>
        <textarea id="basliklar" name="basliklar" rows="4" required
                  placeholder="Her satıra veya virgülle ayırarak yazın"></textarea>
        <small class="hint">Her satıra veya virgülle ayırarak yazabilirsiniz; Google uygun başlıkları dönüşümlü kullanır.
        <br>
        Örnek : Web sitenizi oluşturun Şimdi web sitesi yapın Hemen yayına alın 
        </small>                  
    </p>
    <p>
        <label for="aciklamalar">Reklam açıklamaları (en az 2, en fazla 4; her biri en fazla 90 karakter)</label><br>
        <textarea id="aciklamalar" name="aciklamalar" rows="3" required
                  placeholder="Her açıklamayı ayrı bir satıra yazın"></textarea>
        <small class="hint">Ürününüzü veya hizmetinizi açıklayan kısa metinler; her biri en fazla 90 karakterdir.<br>
    Her açıklamayı ayrı bir satıra yazın; açıklama içindeki virgüller korunur.<br>
    Örnek : Bu reklam açıklaması test için yapılmıştır,
Bu reklam açıklaması test için yapılmıştır 2
</small>                  
    </p>
    <p class="form-field">
        <label for="anahtar_kelimeler">Anahtar kelimeler (en az 1; virgülle ayırın)</label><br>
        <textarea id="anahtar_kelimeler" name="anahtar_kelimeler" rows="3" required
                  placeholder="örnek kelime 1, örnek kelime 2"></textarea>
        <small class="hint">Müşterilerin arayabileceği kelimeleri her satıra veya virgülle ayırarak yazın.<br>Örnek : web sitesi yap,web sitesi oluştur</small>
                  
    </p>
    <p class="form-field keyword-negative-field">
        <label for="negatif_anahtar_kelimeler">Negatif anahtar kelimeler (opsiyonel; en fazla 20)</label><br>
        <textarea id="negatif_anahtar_kelimeler" name="negatif_anahtar_kelimeler" rows="3"
                  placeholder="ücretsiz, kurs, &quot;iş ilanı&quot;, [staj]"></textarea>
        <small class="hint">Reklamınızın gösterilmesini istemediğiniz aramaları yazın. Her satıra veya virgülle ayırın; düz metin geniş, &quot;kelime&quot; sıralı, [kelime] tam eşleme kullanır.</small>
    </p>
    <p>
        <label for="gunluk_butce">Günlük bütçe (TL)</label><br>
        <input type="text" id="gunluk_butce" name="gunluk_butce" required
               inputmode="decimal" placeholder="500">
        <small class="hint">Google'ın günlük ortalamada hedefleyeceği tutardır; bazı günler biraz daha fazla harcayabilir.</small>
    </p>
    <p>
        <label for="hedef_konum">Hedef konum (şehir / bölge / ülke)</label><br>
        <input type="text" id="hedef_konum" name="hedef_konum" required placeholder="Ankara" value="">
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
    <p class="form-field">
        <label for="bitis_tarihi">Bitiş tarihi (opsiyonel)</label><br>
        <input type="date" id="bitis_tarihi" name="bitis_tarihi">
        <small class="hint">Kampanya seçtiğiniz günün sonunda otomatik olarak durur. Boş bırakırsanız toplam bütçe girildiğinde yaklaşık süre hesabı kullanılır.</small>
    </p>
    <div id="konum-secim" class="location-selection" style="display: none;"></div>
    <input type="hidden" name="hedef_konum_resource_name" value="">
    <input type="hidden" name="hedef_konum_kaynak_metin" value="">
    <input type="hidden" name="haric_konum_resource_name" value="">
    <input type="hidden" name="haric_konum_kaynak_metin" value="">
    <p>
        <button type="submit" id="sihirbaz-gonder">Kampanyayı oluştur (PAUSED)</button>
    </p>
    <p id="sihirbaz-mesaj" role="status" aria-live="polite"></p>
</form>
</div>
</div>

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
    var aiBrief = document.getElementById('ai-brief');
    var aiOneriOlustur = document.getElementById('ai-oneri-olustur');
    var aiMesaj = document.getElementById('ai-mesaj');
    var aiSonuc = document.getElementById('ai-oneri-sonucu');
    var aiUygula = document.getElementById('ai-onerileri-uygula');
    var aiOneri = null;

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

    function ai_listeyi_goster(etiket, degerler) {
        if (!Array.isArray(degerler) || degerler.length === 0) {
            return;
        }

        var blok = document.createElement('p');
        var baslik = document.createElement('strong');
        baslik.textContent = etiket + ':';
        blok.appendChild(baslik);

        var liste = document.createElement('ul');
        degerler.forEach(function (deger) {
            var satir = document.createElement('li');
            satir.textContent = String(deger);
            liste.appendChild(satir);
        });
        blok.appendChild(liste);
        document.getElementById('ai-oneri-listeleri').appendChild(blok);
    }

    function ai_oneriyi_goster(oneri) {
        aiOneri = oneri;
        document.getElementById('ai-kampanya-adi').textContent = String(oneri.kampanya_adi || 'Belirtilmedi');
        document.getElementById('ai-kampanya-amaci').textContent = String(oneri.kampanya_amaci || 'Belirtilmedi');
        document.getElementById('ai-hedef-konum').textContent = String(oneri.hedef_konum || 'Belirtilmedi');

        var listeAlani = document.getElementById('ai-oneri-listeleri');
        listeAlani.replaceChildren();
        ai_listeyi_goster('Başlıklar', oneri.basliklar);
        ai_listeyi_goster('Açıklamalar', oneri.aciklamalar);
        ai_listeyi_goster('Anahtar kelimeler', oneri.anahtar_kelimeler);
        ai_listeyi_goster('Negatif anahtar kelimeler', oneri.negatif_anahtar_kelimeler);
        ai_listeyi_goster('Eksik bilgiler', oneri.eksik_bilgiler);
        ai_listeyi_goster('Uyarılar', oneri.uyarilar);
        aiSonuc.hidden = false;
    }

    aiOneriOlustur.addEventListener('click', function () {
        var brief = aiBrief.value.trim();

        if (brief === '') {
            aiMesaj.textContent = 'Önce ne yapmak istediğinizi yazın.';
            return;
        }

        aiOneriOlustur.disabled = true;
        aiSonuc.hidden = true;
        aiMesaj.textContent = 'Ads önerisi hazırlanıyor...';

        var aiForm = new FormData(form);
        aiForm.append('brief', brief);

        fetch('api/index.php?islem=ai-kampanya-onerisi', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: aiForm
        })
            .then(function (yanit) { return yanit.json(); })
            .then(function (cevap) {
                if (cevap.return !== 1 || !cevap.oneri) {
                    throw new Error(cevap.mesaj || 'AI önerisi alınamadı.');
                }

                ai_oneriyi_goster(cevap.oneri);
                aiMesaj.textContent = cevap.mesaj || 'Öneri hazırlandı.';
            })
            .catch(function (hata) {
                aiMesaj.textContent = 'AI önerisi alınamadı.';
            })
            .finally(function () {
                aiOneriOlustur.disabled = false;
            });
    });

    aiUygula.addEventListener('click', function () {
        if (!aiOneri) {
            return;
        }

        var alanlar = {
            kampanya_adi: 'kampanya_adi',
            hedef_konum: 'hedef_konum',
            haric_konumlar: 'haric_konumlar',
            basliklar: 'basliklar',
            aciklamalar: 'aciklamalar',
            anahtar_kelimeler: 'anahtar_kelimeler',
            negatif_anahtar_kelimeler: 'negatif_anahtar_kelimeler'
        };

        Object.keys(alanlar).forEach(function (oneriAlani) {
            var deger = aiOneri[oneriAlani];
            var alan = form.elements[alanlar[oneriAlani]];

            if (!alan || deger === undefined || deger === null) {
                return;
            }

            if (Array.isArray(deger)) {
                alan.value = deger.join('\n');
            } else if (String(deger).trim() !== '') {
                alan.value = String(deger);
            }

            alan.dispatchEvent(new Event('input', { bubbles: true }));
        });

        aiMesaj.textContent = 'Öneriler forma uygulandı. Kampanyayı oluşturmak için formu ayrıca gönderin.';
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
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