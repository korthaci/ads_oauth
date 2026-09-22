# PROMPT-25 — Inline JS/CSS'in Ayrı Dosyalara Taşınması (Saf Refactor)

## 0. Bağlam

Şu an 4 dosyada inline `<script>` blokları var:

- `tema/panel/anasayfa.php`
- `tema/panel/kampanya-sihirbazi.php`
- `tema/panel/kampanyalarim.php`
- `tema/panel/hesap-baglan.php`

Ve `tema/panel/kampanya-sihirbazi.php` içinde inline `style=` kullanımları var.
`assets/js/` ve `assets/css/` dizinleri zaten var ama boş — `tema/css/panel.css` dışında
merkezi bir JS/CSS yapısı yok.

Bu prompt **saf bir refactor**dur: **hiçbir davranış, akış, onay mekanizması, API
çağrısı değişmez** — yalnızca kod nereye yazıldığı değişir.

## 1. Görev A — JS Dosyalarının Ayrıştırılması

1. `assets/js/` altında, sayfa başına bir dosya oluştur:
   - `assets/js/anasayfa.js`
   - `assets/js/kampanya-sihirbazi.js`
   - `assets/js/kampanyalarim.js`
   - `assets/js/hesap-baglan.js` (dosya gerçekten JS içeriyorsa; boşsa/iskeletse
     atlanabilir, DURUM.md'de belirtilir)
2. Sayfalar arasında **tekrarlanan** kod varsa (örn. `oauth-baslat` fetch akışı hem
   anasayfa hem hesap-baglan'da olabilir, onay diyaloğu deseni kampanyalarim'de
   tekrarlanıyor olabilir), bunlar ortak bir `assets/js/ortak.js` dosyasına taşınır ve
   diğer dosyalar bunu kullanır (kopya kod olarak bırakılmaz).
3. Her `tema/panel/*.php` dosyasındaki inline `<script>` bloğu kaldırılır, yerine
   `<script src="/ads-oauth/assets/js/<dosya>.js" defer></script>` (gerçek base path
   projeye göre ayarlanır — `.htaccess`/mevcut asset referansları kontrol edilerek
   doğru yol kullanılır) eklenir.
4. JS içindeki hiçbir mantık/DOM seçici/fetch URL'i/onay metni **değiştirilmez** —
   yalnızca konumu değişir. (İstisna: ortak koda taşırken zorunlu küçük parametrikleştirme
   gerekebilir — örn. farklı sayfalarda farklı endpoint'e fetch atılması — bu da davranışı
   bozmadan yapılır.)

## 2. Görev B — Inline CSS'in Taşınması

1. `tema/panel/kampanya-sihirbazi.php`'deki inline `style=` kullanımları taranır, gerçek
   CSS kuralına dönüştürülüp `tema/css/panel.css`'e (mevcut dosya, PROMPT-23'te
   eklenmişti) sınıf olarak eklenir; HTML'deki `style="..."` yerine `class="..."`
   kullanılır.
2. Proje genelinde **başka** inline `style=`/`<style>` kullanımı olup olmadığı da taranır
   (yalnızca sihirbazla sınırlı kalınmaz) — bulunursa aynı şekilde taşınır, DURUM.md'ye
   nerede bulunduğu yazılır.

## 3. Kesin Yasaklar

- Hiçbir davranış, onay akışı, fetch endpoint'i, DOM yapısı/id/class adı (JS'in
  bağlandığı elementler hariç, onlar da bozulmadan taşınır) değiştirilmez.
- Google Ads/OAuth/kampanya mutate akışlarına dokunulmaz.
- Yeni bir kütüphane/framework eklenmez (vanilla JS/CSS olarak kalır, ARCHITECTURE.md
  §3'e uygun).
- AI önerisi özelliğine (`ai/`, `api/ai-kampanya-onerisi.php`) dokunulmaz.

## 4. Doğrulama

- `php -l` tüm değişen dosyalarda.
- Her sayfa için, taşıma öncesi/sonrası JS dosyasının **satır satır aynı mantığı**
  içerdiği (yeniden yazılmadığı, yalnızca taşındığı) kod incelemesiyle teyit edilir.
- **Canlı test zorunlu** (Kort tarafından): dört sayfanın (`anasayfa`,
  `kampanya-sihirbazi`, `kampanyalarim`, `hesap-baglan`) tüm mevcut işlevlerinin
  (OAuth başlatma, kampanya oluşturma + konum seçim ekranı, durdur/yayına al/kaldır
  onay akışları) **hiçbir davranış değişikliği olmadan** çalıştığının doğrulanması.
  Bu bir refactor olduğu için regresyon riski var — dikkatli test edilmeli.

## 5. DURUM.md

Hangi dosyaların nereye taşındığı, ortak koda çıkarılan varsa ne olduğu, inline CSS
bulunan başka yer olup olmadığı, ve Kort için "her sayfayı dikkatle yeniden test et"
hatırlatması net şekilde yazılır. Ayrı TODO listesi gerekmiyor (odaklı bir refactor).
