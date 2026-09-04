# DURUM.md
> Bu dosya **canlıdır** — her ilerleme sonrası güncellenir. Yeni bir çalışma oturumuna
> başlarken (context sıfırlansa dahi) önce bu dosya okunur, sonra ARCHITECTURE.md, sonra
> ilgili prompt dosyası.
> **Güncelleme sorumluluğu kod yazıcı AI'ye (Cline/Requesty) aittir.** Claude bu dosyayı
> artık düzenlemez, sadece yeni prompt hazırlarken referans olarak okur.

**Son güncelleme:** 2026-09-04

**Proje adı:** ads_oauth *(eski adı "ads" idi — arama motorlarında/genel aramalarda "ads" kelimesi çok geçtiği için değiştirildi)*
**Proje yolu:** `c:/server/htdocs/ads_oauth/`
**GitHub reposu:** https://github.com/korthaci/ads_oauth (public)
**Kod yazıcı ortam:** VS Code + Cline + Requesty → model: OpenAI GPT Luna (düşük token tüketimi hedefleniyor, proje sonuna kadar yetsin diye bilinçli olarak Claude/OpenAI'nin üst modelleri seçilmedi)
**Workspace notu:** Kort'un VS Code workspace'ine Websistem projesi de referans amaçlı eklendi — amaç, `api/` dosyalarının JSON dönüş biçimini Kort'un zaten alışık olduğu Websistem konvansiyonuyla (`class_f/fonksiyon.php` genel fonksiyonlar, `class_f/class_.php` genel class'lar) tutarlı tutmak. AI'nin Websistem'in tamamını incelemesine gerek yok, sadece bu iki dosyadaki JSON döndürme kalıbını referans almalı.

---

## 1. Şu An Neredeyiz?

**Aşama:** PROMPT 00 (iskelet kurulum), PROMPT 01 (isimlendirme düzeltmesi + autoload kaldırma),
PROMPT 02 (eksik kalan isimlendirme düzeltmelerinin kontrolü) ve PROMPT 03 (Google öncelikli
temel altyapı) tamamlandı. `.env` dosyası oluşturuldu ve veritabanı bilgileriyle dolduruldu;
Google/Meta anahtarları ile Composer bağımlılık kurulumu henüz yapılmadı.

**Sıradaki adım:** Google OAuth akışı için hazırlanacak bir sonraki prompt bekleniyor. Temel
altyapı tamamlandı; OAuth, bağlayıcı, servis ve panel/tema mantığı henüz yazılmadı. Google ve
Meta anahtarları alınacak, ardından gerekli Composer bağımlılıkları kurulacak.

---

## 2. Tamamlanan Adımlar

- [x] Proje fikri ve gerekçesi netleştirildi (Google/Meta reklam kurulum karmaşıklığını basitleştirme).
- [x] Çalışma modeli belirlendi: SaaS/OAuth modeli (ajans modeli değil).
- [x] Rakip analizi yapıldı (Madgicx, Revealbot vb. — farklı segment, tehdit değil).
- [x] Google Ads API ve Meta Marketing API resmi dokümantasyon bağlantıları paylaşıldı.
- [x] Google'ın "hangi ürünü kullanmalıyım" tablosu incelendi, API yolu doğrulandı
      (not: Türkçe sayfadaki "kullanma" ifadesi çeviri hatasıydı, orijinali "Use the Google Ads API").
- [x] OAuth izin akışının adım adım nasıl işlediği netleştirildi.
- [x] Websistem (Kort'un mevcut CMS'i) incelendi, bu projenin **modül değil, bağımsız sistem**
      olması gerektiğine karar verildi (gerekçe: hedef kitle Websistem kullanmayabilir, composer
      bağımlılık çakışma riski, cron/worker doğası CMS modül yapısına uymuyor, güvenlik izolasyonu).
- [x] Dosya yapısı taslağı çıkarıldı (bkz. ARCHITECTURE.md, ~34 dosya, SQL hariç).
- [x] Çalışma yöntemi belirlendi: genel mimari dosyası + ihtiyaç oldukça tek tek prompt +
      bu durum takip dosyası.
- [x] PROMPT 00 uygulandı: klasör/dosya iskeleti oluşturuldu, gerçek mantık eklenmedi.
- [x] PROMPT 01 uygulandı: `php/` altındaki klasör ve dosya adları küçük harfe çevrildi;
      `composer.json` içindeki PSR-4 `autoload` bloğu kaldırıldı.
- [x] PROMPT 02 uygulandı: PROMPT 01'in devamı/düzeltmesi olarak `php/` altındaki tüm klasör
      ve dosya adlarının küçük harfli son hali doğrulandı; ek yeniden adlandırma gerekmedi.
- [x] PROMPT 03 uygulandı: `db/sema.sql` oluşturuldu, temel config/veritabanı/oturum/şifreleme
      mantığı yazıldı ve `.env.sample` içindeki `DB_NAME` değeri `ads_oauth` olarak ayarlandı.
- [x] Gerçek `.env` dosyası oluşturuldu ve veritabanı bilgileriyle dolduruldu.

## 3. Bekleyen / Henüz Yapılmayanlar

- [ ] Google Ads API Developer Token başvurusu (Kort tarafında yapılacak).
- [ ] Google OAuth anahtarlarının (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) alınması.
- [ ] Meta Marketing API App Review süreci (Kort tarafında yapılacak).
- [ ] Meta OAuth anahtarlarının (`META_APP_ID`, `META_APP_SECRET`) alınması.
- [ ] Composer bağımlılıklarının kurulması ve `vendor/` klasörünün oluşturulması.
- [ ] OAuth akışı prompt dosyalarının yazılması (`php/oauth/google-oauth.php`, `php/oauth/meta-oauth.php`).
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
| 2026-09-04 | api/ JSON formatı `{return, mesaj}` olarak belirlendi | Websistem `php/class_f/` ve `api/` koduna bakılarak netleştirildi (Kort'un alışık olduğu format) |
| 2026-09-04 | `php/` altındaki klasör ve dosya adları küçük harfe çevrildi | Dosya yolu tutarlılığı ve platformlar arası adlandırma uyumu |
| 2026-09-04 | Kendi sınıfları için Composer PSR-4 autoload kullanılmamasına karar verildi | Manuel `require_once` stratejisi kullanılacak |
| 2026-09-04 | PROMPT 02 ile `php/` altındaki tüm adlandırmaların küçük harfli son hali doğrulandı | PROMPT 01'in devamı/düzeltmesi; ek yeniden adlandırma gerekmedi |
| 2026-09-04 | Nihai hedef çoklu platform olsa da geliştirme sıralı ilerleyecek; önce Google Ads altyapısı tamamlanacak | Meta daha sonra eklenecek, bu aşamada yalnızca `google` platform değeri kullanılacak |
| 2026-09-04 | DB engine olarak InnoDB seçildi | Foreign key desteği ve şifreli token'ların transactional bütünlüğü gerekiyor |
| 2026-09-04 | Gerçek `.env` dosyası DB bilgileriyle oluşturuldu | Yerel veritabanı bağlantı ayarları hazırlandı; hassas değerler repora yazılmayacak |
| 2026-09-04 | Google ve Meta anahtarları ile Composer bağımlılıkları henüz alınmadı/kurulmadı | OAuth ve üçüncü parti SDK entegrasyonları sonraki aşamaya bırakıldı |

## 5. Açık Sorular / Netleşmemiş Noktalar

- Kampanya sihirbazında kaç adım/soru olacak henüz detaylandırılmadı.
- Senkron cron'unun çalışma sıklığı (15dk/30dk/saatlik) henüz kararlaştırılmadı.

## 6. Prompt Dosyaları İzleme Tablosu

| # | Dosya | Durum | Not |
|---|---|---|---|
| 00 | `md/00-iskelet-kurulum.md` | Tamamlandı | Sadece klasör/dosya + docblock oluşturuldu, mantık eklenmedi |
| 01 | `md/01-isimlendirme-duzeltme.md` | Tamamlandı | `php/` altındaki adlar küçültüldü, Composer PSR-4 `autoload` bloğu kaldırıldı |
| 02 | PROMPT 01 devamı/düzeltmesi | Tamamlandı | `php/` altındaki tüm klasör ve dosya adlarının küçük harfli son hali doğrulandı; ek yeniden adlandırma gerekmedi |
| 03 | `md/03-temel-altyapi.md` | Tamamlandı | İlk SQL şeması ve Google öncelikli temel altyapı fonksiyonları eklendi |

*(Her yeni prompt dosyası oluşturulduğunda bu tabloya satır eklenir: numara, dosya adı, durum
[Bekliyor / AI'ye verildi / Tamamlandı / Revizyon gerekli], kısa not.)*
