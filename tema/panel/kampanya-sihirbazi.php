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
    <div id="konum-secim" class="location-selection" hidden></div>
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

<script src="/ads-oauth/assets/js/kampanya-sihirbazi.js" defer></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
