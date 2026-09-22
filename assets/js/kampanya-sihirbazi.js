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
        secimKutusu.hidden = true;
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
            etiket.className = 'location-selection-option';

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
        secimKutusu.hidden = false;
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
