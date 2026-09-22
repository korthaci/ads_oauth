(function () {
    var mesaj = document.getElementById('mesaj'), uyari = document.getElementById('uyari'), onay = document.getElementById('onay'), tablo = document.getElementById('tablo'), filtre = document.getElementById('kampanya-filtresi');
    var durum = {ENABLED:'Yayında', PAUSED:'Duraklatıldı', REMOVED:'Kaldırıldı'};
    var kanal = {SEARCH:'Arama', DISPLAY:'Görüntülü', SHOPPING:'Alışveriş', VIDEO:'Video', MULTI_CHANNEL:'Çok kanallı'};
    function s(x) { return x === null || x === undefined ? '' : String(x); }
    function tl(x) { if (x === null || x === undefined || x === '') return 'Belirtilmemiş'; var n = Number(x) / 1000000; return Number.isFinite(n) ? new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY'}).format(n) : 'Belirtilmemiş'; }
    function tarih(x) { return x ? s(x).replace(/^([0-9]{4})-([0-9]{2})-([0-9]{2}).*$/, '$3.$2.$1') : 'Bitiş tarihi yok'; }
    function bildir(x,c) { mesaj.className = 'status-message alert ' + c; mesaj.textContent = x; }
    function durumDegistir(k, hedef, buton, girdi) {
        buton.disabled = true; bildir('Kampanya durumu değiştiriliyor...', 'alert-warning');
        var f = new FormData(); f.append('kampanya_id', s(k.id)); f.append('hedef_durum', hedef);
        if (hedef === 'REMOVED') f.append('kaldirma_onayi', girdi ? girdi.value : '');
        fetch('api/index.php?islem=kampanya-durdur',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:f})
            .then(function(r){return r.json();}).then(function(c){onay.replaceChildren();if(c.return===1){k.status=hedef;bildir(c.mesaj||'Kampanya durumu güncellendi.','alert-success');liste(window.ks);}else bildir(c.mesaj||'Kampanya durumu değiştirilemedi.','alert-error');})
            .catch(function(){onay.replaceChildren();bildir('Kampanya durumu değiştirilemedi.','alert-error');});
    }
    function bitisTarihiniDegistir(k, girdi, buton) {
        buton.disabled = true; bildir('Kampanya bitiş tarihi güncelleniyor...', 'alert-warning');
        var f = new FormData(); f.append('kampanya_id', s(k.id)); f.append('bitis_tarihi', girdi.value);
        fetch('api/index.php?islem=kampanya-bitis-tarihi',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:f})
            .then(function(r){return r.json();}).then(function(c){if(c.return===1){k.end_date_time=c.end_date_time||girdi.value;bildir(c.mesaj||'Kampanya bitiş tarihi güncellendi.','alert-success');liste(window.ks);}else{buton.disabled=false;bildir(c.mesaj||'Kampanya bitiş tarihi değiştirilemedi.','alert-error');}})
            .catch(function(){buton.disabled=false;bildir('Kampanya bitiş tarihi değiştirilemedi.','alert-error');});
    }
    function onayKutusu(k, hedef) {
        onay.replaceChildren(); var kutu=document.createElement('div'); kutu.className='confirm-box';
        var h=document.createElement('h2'); h.textContent=hedef==='ENABLED'?'Kampanyayı yayına al':(hedef==='REMOVED'?'Kampanyayı kaldır':'Kampanyayı duraklat'); kutu.appendChild(h);
        var p=document.createElement('p'); p.textContent='Kampanya: '+s(k.name)+' — Günlük bütçe: '+tl(k.budget_amount_micros)+' — '+tarih(k.end_date_time); kutu.appendChild(p);
        var girdi=null;
        if(hedef==='ENABLED'){var risk=document.createElement('p');risk.innerHTML='<strong>Dikkat:</strong> Bu işlem gerçek harcamayı başlatabilir.';kutu.appendChild(risk);}
        if(hedef==='REMOVED'){var kaldirmaRisk=document.createElement('p');kaldirmaRisk.innerHTML='<strong>DİKKAT: Bu işlem GERİ ALINAMAZ. Kampanya kalıcı olarak kaldırılacak.</strong>';kutu.appendChild(kaldirmaRisk);}
        if(hedef==='ENABLED'||hedef==='REMOVED'){var label=document.createElement('label');label.textContent='Onaylamak için kampanya adını tam olarak yazın: ';girdi=document.createElement('input');girdi.type='text';girdi.autocomplete='off';girdi.setAttribute('aria-label','Kampanya adı onayı');label.appendChild(girdi);kutu.appendChild(label);}
        var vazgec=document.createElement('button');vazgec.type='button';vazgec.textContent='Vazgeç';var gonder=document.createElement('button');gonder.type='button';gonder.textContent=hedef==='ENABLED'?'Yayına al':(hedef==='REMOVED'?'Kaldır':'Duraklat');gonder.className='button-spaced';
        if(girdi){gonder.disabled=true;girdi.oninput=function(){gonder.disabled=girdi.value!==s(k.name);};}
        kutu.appendChild(document.createElement('br'));kutu.appendChild(vazgec);kutu.appendChild(gonder);onay.appendChild(kutu);vazgec.onclick=function(){onay.replaceChildren();};gonder.onclick=function(){durumDegistir(k,hedef,gonder,girdi);};
    }

    function liste(ks) {
        tablo.replaceChildren(); if(!ks.length){tablo.textContent='Bu hesapta kampanya bulunamadı.';return;}
        var t=document.createElement('table');t.className='campaign-table';var th=document.createElement('tr');['Kampanya','Durum','Kanal','Günlük bütçe','Bitiş','İşlem'].forEach(function(x){var c=document.createElement('th');c.textContent=x;th.appendChild(c);});var head=document.createElement('thead');head.appendChild(th);t.appendChild(head);var body=document.createElement('tbody');
        ks.forEach(function(k){var tr=document.createElement('tr');[s(k.name),durum[k.status]||'Diğer ('+s(k.status)+')',kanal[k.advertising_channel_type]||s(k.advertising_channel_type),tl(k.budget_amount_micros)].forEach(function(x){var c=document.createElement('td');c.textContent=x;tr.appendChild(c);});var tarihHucre=document.createElement('td');if(k.status!=='REMOVED'){var tarihGirdi=document.createElement('input');tarihGirdi.type='date';tarihGirdi.value=k.end_date_time?s(k.end_date_time).slice(0,10):'';tarihGirdi.min=new Date().toISOString().slice(0,10);tarihGirdi.setAttribute('aria-label','Kampanya bitiş tarihi');tarihHucre.appendChild(tarihGirdi);var tarihKaydet=document.createElement('button');tarihKaydet.type='button';tarihKaydet.textContent='Kaydet';tarihKaydet.className='button-spaced';tarihKaydet.disabled=!tarihGirdi.value;tarihGirdi.oninput=function(){tarihKaydet.disabled=!tarihGirdi.value;};tarihKaydet.onclick=function(){bitisTarihiniDegistir(k,tarihGirdi,tarihKaydet);};tarihHucre.appendChild(tarihKaydet);}else tarihHucre.textContent=tarih(k.end_date_time);tr.appendChild(tarihHucre);var c=document.createElement('td');if(k.status!=='REMOVED'){var hedef=k.status==='ENABLED'?'PAUSED':'ENABLED';var b=document.createElement('button');b.type='button';b.textContent=hedef==='ENABLED'?'Yayına Al':'Duraklat';b.onclick=function(){onayKutusu(k,hedef);};c.appendChild(b);var kaldir=document.createElement('button');kaldir.type='button';kaldir.textContent='Kaldır';kaldir.className='button-spaced';kaldir.onclick=function(){onayKutusu(k,'REMOVED');};c.appendChild(kaldir);}else c.textContent='İşlem yok';tr.appendChild(c);body.appendChild(tr);});t.appendChild(body);tablo.appendChild(t);
    }
    function filtreliListe() { return window.ks.filter(function(k){return filtre.value==='all'||k.status!=='REMOVED';}); }
    filtre.onchange=function(){liste(filtreliListe());};
    fetch('api/index.php?islem=kampanya-listele',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(c){if(c.return!==1){bildir(c.mesaj||'Kampanyalar yüklenemedi.','alert-error');return;}mesaj.className='status-message';mesaj.textContent=c.mesaj||'';if(c.hesap&&c.hesap.manager){var u=document.createElement('p');u.className='alert alert-warning';u.textContent='Bağlı hesap Manager (MCC) hesabıdır; kampanya yönetilemez. Non-manager müşteri hesabı bağlayın.';uyari.appendChild(u);return;}window.ks=Array.isArray(c.kampanyalar)?c.kampanyalar:[];liste(filtreliListe());}).catch(function(){bildir('Kampanyalar yüklenemedi.','alert-error');});
}());
