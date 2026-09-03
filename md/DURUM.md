# DURUM.md
> Bu dosya **canlıdır** — her ilerleme sonrası güncellenir. Yeni bir çalışma oturumuna
> başlarken (context sıfırlansa dahi) önce bu dosya okunur, sonra ARCHITECTURE.md, sonra
> ilgili prompt dosyası.

**Son güncelleme:** 2026-09-03

**Proje adı:** ads_oauth
**Proje yolu:** `c:/server/htdocs/ads_oauth/`
**Kod yazıcı ortam:** VS Code + Cline + Requesty (model henüz seçilmedi, düşük token tüketimi hedefleniyor)

---

## 1. Şu An Neredeyiz?

**Aşama:** Planlama tamamlandı, PROMPT 00 (iskelet kurulum) hazırlandı, henüz AI'ye verilmedi.

**Sıradaki adım:** `prompts/00-iskelet-kurulum.md` dosyası Cline'a verilip klasör/dosya
iskeleti oluşturulacak. Bu adım tamamen mekanik olduğu için ucuz/küçük bir model yeterli.
Ardından `Config.php`, `Veritabani.php`, `.env` gerçek mantığı için PROMPT 01 hazırlanacak
(bu, daha güçlü bir model gerektirebilir).

---

## 2. Tamamlanan Adımlar

- [x] Proje fikri ve gerekçesi netleştirildi (Google/Meta reklam kurulum karmaşıklığını basitleştirme).
- [x] Çalışma modeli belirlendi: SaaS/OAuth modeli (ajans modeli değil).
- [x] Rakip analizi yapıldı (Madgicx, Revealbot vb. — farklı segment, tehdit değil).
- [x] Google Ads API ve Meta Marketing API resmi dokümantasyon bağlantıları paylaşıldı.
- [x] Google'ın "hangi ürünü kullanmalıyım" tablosu incelendi, API yolu doğrulandı
      (not: Türkçe sayfadaki "kullanma" ifadesi çeviri hatasıydı, orijinali "Use the Google Ads API").
- [x] OAuth izin akışının adım adım nasıl işlediği netleştirildi.
- [x] Dosya yapısı taslağı çıkarıldı (bkz. ARCHITECTURE.md, ~34 dosya, SQL hariç).
- [x] Çalışma yöntemi belirlendi: genel mimari dosyası + ihtiyaç oldukça tek tek prompt +
      bu durum takip dosyası.

## 3. Bekleyen / Henüz Yapılmayanlar

- [ ] Google Ads API Developer Token başvurusu (Kort tarafında yapılacak).
- [ ] Meta Marketing API App Review süreci (Kort tarafında yapılacak).
- [ ] SQL şema dosyasının hazırlanması (`db/sema.sql`).
- [ ] İlk prompt dosyasının yazılması (temel altyapı: Config, Veritabani, Oturum, .env).
- [ ] OAuth akışı prompt dosyalarının yazılması (GoogleOauth.php, MetaOauth.php).
- [ ] Baglayici (adapter) katmanı prompt dosyalarının yazılması.
- [ ] Servis katmanı prompt dosyalarının yazılması.
- [ ] Cron/senkron mekanizmasının prompt dosyasının yazılması.
- [ ] Panel/tema dosyalarının prompt dosyalarının yazılması.

## 4. Karar Günlüğü (Önemli Mimari Kararlar ve Gerekçeleri)

| Tarih | Karar | Gerekçe |
|---|---|---|
| 2026-09-03 | Ajans modeli değil, SaaS/OAuth modeli seçildi | Hukuki/mali yük istenmiyor, kullanıcı kendi hesabını bağlar |
| 2026-09-03 | Websistem modülü değil, bağımsız sistem | Hedef kitle CMS'ten bağımsız, composer/cron/güvenlik izolasyonu gerekiyor |
| 2026-09-03 | Türkçe eylem-bazlı dosya isimlendirmesi korundu | Kort'un mevcut proje konvansiyonuyla tutarlılık |
| 2026-09-03 | Claude kodu doğrudan düzenlemez, düzeltme promptu yazar | Kod stili tutarlılığı, tek AI üzerinden kod yazımı korunur |

## 5. Açık Sorular / Netleşmemiş Noktalar

- Kampanya sihirbazında kaç adım/soru olacak henüz detaylandırılmadı.
- Senkron cron'unun çalışma sıklığı (15dk/30dk/saatlik) henüz kararlaştırılmadı.
- Çoklu platform (Google + Meta aynı anda) mı yoksa önce tek platformla mı (örn. sadece Google Ads) MVP başlatılacak, netleşmedi.

## 6. Prompt Dosyaları İzleme Tablosu

| # | Dosya | Durum | Not |
|---|---|---|---|
| 00 | `prompts/00-iskelet-kurulum.md` | Hazır, AI'ye verilmedi | Tamamen mekanik, ucuz model yeterli - sadece klasör/dosya + docblock oluşturur, mantık yok |

*(Her yeni prompt dosyası oluşturulduğunda bu tabloya satır eklenir: numara, dosya adı, durum
[Bekliyor / AI'ye verildi / Tamamlandı / Revizyon gerekli], kısa not.)*
