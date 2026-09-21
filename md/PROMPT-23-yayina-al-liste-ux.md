# PROMPT-23 — Yayına Al/Duraklat, Kampanya Listesi Paneli, Navigasyon ve UX İyileştirmeleri

## 0. Bağlam ve Kapsam

Şimdiye kadar sistem: OAuth bağlantı, salt-okunur kampanya listeleme (yalnızca API
yanıtı, arayüz yok), ve kampanya oluşturma (her zaman `PAUSED`) çalışıyor. Bu prompt
üç şeyi birleştirir: **(A) kampanyayı gerçekten yayına alma/duraklatma**, **(B) panelde
gerçek bir kampanya listesi görünümü**, **(C) genel navigasyon + UX iyileştirmeleri**.

**(A) en kritik güvenlik önemi taşıyan kısımdır** — bu, sistemin **ilk kez gerçek para
harcamaya başlatacağı** an. Buna göre tasarlanır (§1.3).

## 1. Görev A — Yayına Al / Duraklat (`api/kampanya-durdur.php`)

### 1.1. Backend

1. `api/kampanya-durdur.php` gerçek işlevine kavuşturulur (PROMPT-18 desenindeki gibi
   ince köprü), `php/servis/kampanya-servisi.php` içine `kampanya_durumunu_degistir()`
   eklenir.
2. Girdi: `kampanya_id` (zorunlu) ve `hedef_durum` (`ENABLED` veya `PAUSED` — başka
   değer kabul edilmez).
3. Akış: oturum kontrolü → aktif bağlı hesap → **taze manager kontrolü** (PROMPT-16
   deseniyle, her mutate öncesi tekrar) → `kampanya_id`'nin **gerçekten bu bağlı hesaba
   ait olduğu** salt-okunur bir sorguyla doğrulanır (başka bir hesaba ait/var olmayan
   bir ID'ye mutate denenmez) → tek bir `CampaignService`/`GoogleAdsService.mutate`
   `update` operation'ı ile `campaign.status` değiştirilir (`update_mask` doğru
   ayarlanır — yalnızca `status` alanı güncellenir).
4. Google Ads API'nin gerçek yanıt yapısını (bu promptun önceki hatalarında olduğu
   gibi, `MutateGoogleAdsResponse`/tekil `CampaignService.mutate` farkı olabilir)
   **vendor'dan doğrula, tahmin etme** — PROMPT-20.5'teki `RESOURCE_NOT_FOUND`/
   `getResults()` hatalarının tekrarlanmaması için.

### 1.2. Frontend — Kritik Güvenlik UX'i

1. **`PAUSED` yapma** (durdurma): tek onaylı bir buton yeterli (`confirm()` veya basit
   bir "emin misiniz" diyaloğu) — geri dönüşü kolay, harcamayı durdurduğu için düşük
   risk.
2. **`ENABLED` yapma** (yayına alma): **çok daha güçlü bir onay gerektirir**, çünkü bu
   an itibarıyla gerçek para harcanmaya başlar. Şunlar zorunludur:
   - Standart bir `confirm()` yeterli değildir. Kullanıcının kampanya adını (veya
     "YAYINA AL" gibi sabit bir onay kelimesini) bir metin kutusuna **yazması**
     istenir, yazdığı metin eşleşmeden buton aktif olmaz.
   - Onay ekranında kampanyanın **günlük bütçesi** (varsa bitiş tarihi) tekrar,
     büyük ve net şekilde gösterilir ("Bu kampanya günde ~X TL harcamaya
     başlayacak").
   - Yayına alma işlemi başarılı olduğunda, arayüzde **belirgin** bir uyarı/onay
     mesajı kalıcı olarak gösterilir (toast değil, sayfada kalan bir bildirim).

## 2. Görev B — Kampanya Listesi Paneli (`tema/panel/kampanyalarim.php`)

1. Yeni bir görünüm dosyası: mevcut `kampanya-listele` API'sini `fetch` ile çağırır,
   sayfa yenilenmeden sonuçları bir tabloda gösterir: ad, durum (`PAUSED`/`ENABLED`/
   `REMOVED`/diğer — Türkçe karşılıkları ile, örn. "Duraklatıldı"/"Yayında"), kanal
   tipi, günlük bütçe (TL'ye çevrilmiş, micros'tan bölünerek gösterilir).
2. `REMOVED` olmayan her kampanya satırında, Görev A'daki `kampanya-durdur` akışını
   tetikleyen bir buton (durumuna göre "Yayına Al" veya "Duraklat" yazısı ve §1.2'deki
   ilgili onay akışı).
3. Manager hesap bağlıyken bu sayfa, PROMPT-16'daki gibi net bir uyarıyla boş liste
   yerine "bağlı hesap Manager, kampanya yönetilemez" mesajı gösterir.
4. Yükleniyor/hata durumları (API çağrısı başarısız olursa) kullanıcıya net gösterilir.

## 3. Görev C — Navigasyon ve UX İyileştirmeleri

1. `tema/layout/header.php` (zaten var) içine, **giriş yapılmış her sayfada** görünen
   basit bir navigasyon menüsü eklenir: Ana Sayfa | Kampanyalarım | Kampanya Oluştur |
   Çıkış Yap. Şu an her sayfa birbirinden kopuk (örn. sihirbazdan ana sayfaya veya yeni
   kampanya listesine dönmek için link yok) — bu navigasyon her sayfada (`anasayfa.php`,
   `kampanya-sihirbazi.php`, yeni `kampanyalarim.php`) tutarlı şekilde görünür.
2. Sihirbazdaki **her** `input`/`textarea` alanının altına/yanına kısa, gri renkli bir
   açıklama metni eklenir (örn. bütçe alanı → "Google'ın günlük ortalamada bu tutarı
   hedefleyeceği bütçe. Bazı günler biraz daha fazla harcanabilir."; anahtar kelimeler →
   "Her satıra veya virgülle ayırarak yazabilirsiniz."). Var olan tüm alanlar (web
   sitesi, kampanya adı, başlıklar, açıklamalar, anahtar kelimeler, günlük bütçe, hedef
   konum, hariç konumlar, toplam bütçe) için bu eklenir.
3. Genel görsel tutarlılık: tutarlı bir renk paleti, buton stilleri, form aralıkları
   (mevcut CSS'i tamamen değiştirmek yerine, tutarlılaştırmak ve okunabilirliği
   artırmak — kapsamlı bir "yeniden tasarım" değil, **cilalama**).
4. Bu görev, davranışı **değiştirmez**, yalnızca görünürlük/anlaşılırlık/gezinme
   iyileştirir.

## 4. Kesin Yasaklar

- Kampanya oluşturma akışının mutate mantığına (PROMPT-20/20.x/21/21.1) dokunulmaz.
- `ENABLED` yapma işlemi, §1.2'deki güçlü onay akışı **olmadan** hiçbir yoldan
  tetiklenemez (örn. doğrudan API çağrısı hâlâ mümkün olsa da — bu bir sonraki güvenlik
  katmanı konusu olabilir — arayüzden yanlışlıkla tetiklenmesi engellenir).
- Manager hesaba hiçbir mutate denenmez (tekrar doğrulanır).
- Görev A/B/C'nin dışında hiçbir yeni özellik eklenmez (senkron, hesap silme, admin
  rolü — bunlar ayrı, sonraki promptlar).

## 5. Doğrulama

- `php -l` tüm dosyalarda.
- `MutateGoogleAdsResponse`/`CampaignService` yanıt yapısı vendor'dan doğrulanır
  (tahmin yok).
- Sentetik testler: hedef_durum geçersiz değer → red; kampanya_id başka hesaba aitse →
  red; manager hesapta → red.
- **Canlı test zorunlu** (Kort tarafından, düşük bütçeli mevcut test kampanyalarından
  biriyle): (1) bir kampanyayı gerçekten `ENABLED` yapıp arayüzde onay akışının
  beklendiği gibi çalıştığını, (2) kısa süre sonra tekrar `PAUSED` yapıp durdurduğunu,
  (3) kampanya listesi panelinin doğru durumları gösterdiğini doğrulaması. DURUM.md'de
  bu net hatırlatılır — **gerçek harcama olabileceği için düşük bütçeli bir kampanyayla
  ve kısa süreliğine yapılması önerilir.**

## 6. DURUM.md — TODO Listesi

```markdown
## PROMPT-23 TODO

- [ ] kampanya-durdur.php gerçek işlevine kavuştu (ENABLED/PAUSED, kampanya_id doğrulama)
- [ ] Mutate öncesi taze manager kontrolü eklendi
- [ ] ENABLED için güçlü onay akışı (metin yazma + bütçe gösterimi) eklendi
- [ ] PAUSED için basit onay akışı eklendi
- [ ] tema/panel/kampanyalarim.php eklendi, kampanya-listele'yi kullanıyor
- [ ] Kampanya listesinde durum + buton (Yayına Al/Duraklat) çalışıyor
- [ ] Manager hesapta net uyarı gösteriliyor
- [ ] Navigasyon menüsü tüm sayfalarda tutarlı eklendi
- [ ] Sihirbazdaki tüm alanlara açıklama metni eklendi
- [ ] Genel görsel cilalama yapıldı (davranış değişmeden)
- [ ] php -l tüm dosyalarda geçti
- [ ] Deploy listesi verildi
- [ ] Kort için düşük bütçeli canlı ENABLED/PAUSED test talimatı net yazıldı
- [ ] DURUM.md güncellendi
```

Tamamlanamayan madde varsa nedeni yazılır, tahminle işaretlenmez.
