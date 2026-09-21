# PROMPT-24 — Kampanya Kaldırma Butonu ve Liste Filtresi

## 0. Bağlam

`kampanyalarim.php` paneli çalışıyor (PROMPT-23), ama iki eksik var:

1. Kampanyayı **kaldırma** (`REMOVED` yapma) için buton yok — şu an yalnızca
   ads.google.com arayüzünden yapılabiliyor.
2. Liste her zaman **tüm** kampanyaları (`REMOVED` dahil) gösteriyor — geçmiş/eski
   kampanyalar (örn. "explore trabzon" gibi yıllar önce kaldırılmışlar) her seferinde
   listeyi kalabalıklaştırıyor.

## 1. Görev A — Kaldırma Butonu

1. `kampanya_durumunu_degistir()` (`php/servis/kampanya-servisi.php`) fonksiyonu, kabul
   ettiği `hedef_durum` değerlerine **`REMOVED`**'ı da ekler (`ENABLED`/`PAUSED`'a ek
   olarak) — aynı doğrulama/manager kontrolü/sahiplik kontrolü akışı korunur.
2. **`REMOVED` işlemi geri alınamaz** (Google Ads'te bir kampanya tekrar `ENABLED`/
   `PAUSED` yapılamaz, kalıcıdır — bu proje kapsamında da böyle kalır, bkz. PROMPT-16.1
   sırasında görülen eski "explore trabzon" kampanyaları örneği). Bu yüzden frontend'de
   **`ENABLED`'dan bile daha güçlü** bir onay gerekir:
   - Kullanıcı kampanya adını tam yazmalı (mevcut `ENABLED` onay deseni tekrar
     kullanılabilir).
   - Ek olarak açık bir uyarı: *"Bu işlem GERİ ALINAMAZ. Kampanya kalıcı olarak
     kaldırılacak."*
3. `kampanyalarim.php`'de her kampanya satırına (zaten `REMOVED` olanlar hariç) bir
   "Kaldır" butonu eklenir.

## 2. Görev B — Varsayılan Filtre

1. `kampanyalarim.php` sayfasının üstüne basit bir filtre eklenir: "Tümü" /
   "Kaldırılanlar hariç" (varsayılan: **Kaldırılanlar hariç**) — en az bu iki seçenek,
   isterse durum bazlı (`PAUSED`/`ENABLED`) filtre de eklenebilir ama zorunlu değil.
2. Bu filtre **yalnızca frontend'de** (JS ile, mevcut `kampanya-listele` API'sinin
   döndürdüğü tam liste üzerinde) uygulanabilir — backend'e yeni bir parametre eklemek
   şart değil, ama eklemek istenirse (örn. API'den de filtrelenmiş dönebilme) bu da
   kabul edilir; hangisi seçilirse DURUM.md'de belirtilir.
3. Filtre değişince sayfa **yenilenmez**, liste JS ile anında güncellenir.

## 3. Kesin Yasaklar

- `REMOVED` yapma işlemi, güçlü onay olmadan hiçbir yoldan tetiklenemez.
- Diğer mutate akışlarına (oluşturma, ENABLED/PAUSED) dokunulmaz.
- Manager kontrolü ve kampanya sahiplik doğrulaması `REMOVED` için de aynen korunur.

## 4. Doğrulama

- `php -l`.
- Sentetik test: `hedef_durum=REMOVED` artık kabul ediliyor, geçersiz değerler hâlâ
  reddediliyor.
- **Canlı test zorunlu** (Kort tarafından): düşük bütçeli, gerçek bir test kampanyasını
  (örn. dil hatası nedeniyle zaten kullanılmayacak eski test kampanyalarından biri)
  `REMOVED` yapıp hem onay akışının hem de listedeki filtrenin beklendiği gibi
  çalıştığını doğrulaması. **Bu işlem geri alınamaz, dikkatli bir kampanya seçilmeli**
  (örn. zaten yanlış dille oluşturulmuş, kullanılmayacak olan `24268992914` veya
  `24276234886` gibi bir test kampanyası önerilir).

## 5. Ayrıca Hatırlatma (bu promptun konusu değil, ama açık kalan bir madde)

PROMPT-22'deki çoklu kullanıcı izolasyon testi Kort tarafından henüz **yapılmadı** ve
geçici teşhis logu (`php/teshis-log.php`) hâlâ production'da **aktif**. Bu promptun
kapsamı dışında ama DURUM.md'de bu maddenin hâlâ açık olduğu **tekrar** not edilir —
unutulmasın.

## 6. DURUM.md

Görev A/B'nin uygulanışı, deploy listesi, canlı test talimatı (§4) ve §5'teki açık madde
hatırlatması net şekilde yazılır. Ayrı TODO listesi gerekmiyor (odaklı, iki maddelik).
