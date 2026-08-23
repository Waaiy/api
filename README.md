# Waaiy API SDK

Waaiy headless CMS'in public REST API'si için PHP istemcisi.

Ayrıntılı API dokümantasyonu: <https://waaiy.dev/api>

---

## Kurulum

```bash
composer require waaiy/api
```

Gereksinimler: PHP >= 7.4, `ext-json`, `guzzlehttp/guzzle ^7.9`.

## Başlangıç

```php
use Waaiy\API;

$api = new API("https://api.waaiy.dev", [
    "public_key"  => "...",   // W-Public-Key
    "private_key" => "...",   // W-Private-Key
    "user_key"    => "...",   // W-User-Key
]);
```

Eski (1.x) kullanım da geçerlidir:

```php
$api = new API("https://api.waaiy.dev");
$api->public_key  = "...";
$api->private_key = "...";
$api->user_key    = "...";
```

Kimlik doğrulama **üç header** ile yapılır (Bearer token yoktur). Üçü de
eşleşmezse API `403` döner ve SDK "Yetkisiz Erişim..." istisnası fırlatır.

### Constructor seçenekleri

| Seçenek | Varsayılan | Açıklama |
|---|---|---|
| `public_key` / `private_key` / `user_key` | `""` | Kimlik anahtarları |
| `verify` | `true` | TLS sertifika doğrulaması |
| `timeout` | `20` | Toplam istek zaman aşımı (sn) |
| `connect_timeout` | `10` | Bağlantı zaman aşımı (sn) |
| `headers` | `[]` | Her isteğe eklenecek ek header'lar |
| `page_prefix` | `"page"` | Menüde sayfa URL öneki |
| `blog_category_prefix` | `"blogs"` | Menüde blog kategori öneki |
| `product_category_prefix` | `"products"` | Menüde ürün kategori öneki |
| `lang_session_key` | `"_lang_id"` | Aktif dilin okunacağı `$_SESSION` anahtarı |

## Hata yönetimi

Tüm metotlar hata durumunda `\Exception` fırlatır. HTTP durum kodları anlamlı
mesajlara çevrilir:

| Kod | Mesaj |
|---|---|
| 401 / 403 | Yetkisiz Erişim Lütfen İzinleri Kontrol Edin! |
| 404 | İstenen uç nokta bulunamadı! |
| 422 | Geçersiz İstek: `<alan>: <doğrulama mesajı>` |
| 429 | İstek Sınırı Aşıldı! (okuma 120 istek/dk, yazma 10 istek/dk) |
| 5xx | Sunucu Hatası! |

API'nin kendi `{"status": 0, "message": "..."}` gövdesi de istisnaya çevrilir.
Tek istisna `redirect_check()`'tir: yönlendirme yoksa istisna değil `null` döner.

```php
try {
    $blog = $api->blog("ornek-yazi");
} catch (\Exception $e) {
    // 404 sayfası göster
}
```

---

## Diller

```php
$api->languages();          // [{_ID, _NAME, _LANG_SHORT_NAME, _IS_DEFAULT}, ...]
$api->active_lang();        // aktif dil kaydı
$api->lang_id();            // 3
$api->lang_short_name();    // "tr"
$api->set_lang(3);          // aktif dili sabitle (zincirlenebilir)
```

Aktif dil şu sırayla belirlenir: `set_lang()` → `$_SESSION[$lang_session_key]` →
varsayılan dil. Laravel/Symfony gibi `$_SESSION` kullanmayan projelerde
`set_lang()` tercih edilmelidir.

`languages()` sonucu istek boyunca bellekte tutulur — 1.x'te her `blog()`,
`page()` vb. çağrısı ekstra bir `/languages` isteği doğuruyordu, artık doğurmuyor.
Tazelemek için `languages(true)`.

## Ayarlar

```php
$api->settings();            // tüm ayarlar {slug: deger}
$api->adjust("site_basligi"); // tek ayar; tanımlı değilse null
```

## Menü

```php
$menu = $api->menuitems("ana-menu");
// [{type, slug, informations: {_TITLE}, children: [...]}, ...] — 3 seviyeye kadar
```

`type`: `normal` | `page` | `blog_category` | `product_category`.
URL önekleri constructor seçeneklerinden (`page_prefix` vb.) gelir.

## Tekil içerikler

```php
$blog    = $api->blog("ornek-yazi");
$sayfa   = $api->page("hakkimizda");
$urun    = $api->product("urun-slug");
```

Dönen nesne:

| Alan | Açıklama |
|---|---|
| `_INFORMATIONS` | `_TITLE`, `_CONTENT`, `_TITLESEO`, `_DESCRIPTIONSEO` — her biri `{dil_id: deger}` |
| `_SEO` | On-page SEO bloğu (aşağıya bakın) |
| `_JSONLD` | `{dil_id: schema.org grafiği}` |
| `_SLUGS` | `{dil_id: slug}` |
| `_PHOTOS` `_VIDEOS` `_FILES` `_ACORDIONS` `_TABS` `_FIELDS` | SDK tarafından base64+JSON çözülmüş dizi |
| `_CREATED_DATE` `_LAST_UPDATED_DATE` `_STATUS` | |

SDK `_PHOTOS` ve kardeşlerini otomatik çözer — `base64_decode` +
`json_decode` çağırmanıza gerek yoktur. Alan boşsa `[]` gelir.

### Dil haritalarını okumak

```php
$api->informations($blog, "_TITLE");   // aktif dildeki başlık
$api->informations($blog, "_CONTENT", 5); // 5 numaralı dil
$api->slug($blog);                     // aktif dildeki slug
```

### On-page SEO

```php
$seo = $api->seo($blog);
// [
//   'focus_keyword' => '', 'canonical' => '', 'robots' => 'index,follow',
//   'og_title' => '', 'og_description' => '', 'og_image' => 'https://...'
// ]
```

`robots` boş gelirse `index,follow` varsayılır. `og_image` mutlak URL'dir.

### Yapılandırılmış veri (JSON-LD)

```php
echo $api->jsonld_script($blog);  // <script type="application/ld+json">...</script>
$graf = $api->jsonld($blog);      // ham grafik (object)
```

Grafik `Organization`/`LocalBusiness`, `BlogPosting`/`WebPage`/`Product`,
akordiyonlardan türetilen `FAQPage` ve `BreadcrumbList` düğümlerini içerebilir —
hangilerinin üretileceği panelden ayarlanır.

> `_SEO` ve `_JSONLD` yalnızca **tekil** uçlarda (`blog`, `page`, `product`)
> döner. Liste uçlarında bilinçli olarak yoktur.

## Listeler

```php
$sonuc = $api->blogcategory("kategori-slug", 1, 20);
// {__CATEGORY, __BLOGS: [...], __TOTAL_BLOGS: 137}

$sonuc = $api->productcategory("kategori-slug", 1, 12);
// {__CATEGORY, __PRODUCTS: [...]}
```

`$slug` boş (`""`) verilirse tüm bloglar/ürünler döner. `limit` sunucu
tarafında kelepçelenir: bloglar için **1–100**, ürünler için **en fazla 30**.
Liste elemanlarının base64 alanları da otomatik çözülür.

```php
$api->blogcategories();              // kök kategori ağacı
$api->blogcategories("ust-kategori");
$api->productcategories();
```

## Veri setleri

```php
$api->dataset("slug", 50, 1);   // (slug, limit, page)
```

## Medya

```php
$api->media(42, "https://cdn.site.com");
// "https://cdn.site.com/uploads/2026/01/dosya.jpg"

$api->cover_image($blog, "https://cdn.site.com");           // kapak yoksa null
$api->cover_image_or_first($blog, "https://cdn.site.com");  // kapak yoksa ilk foto
```

---

## Önyüz Çevirileri

Website önyüzünde kullanılan anahtar kelimelerin çevirileri bu modülden
yönetilir (ör. anasayfa tasarımında `form.post.add` gibi anahtarlar kullanılır,
metinler Waaiy tarafında tutulur). Çeviriler `Dil.id` bazlıdır.

### Çevirileri okumak

```php
$ceviriler = $api->translations();              // aktif dil
$ceviriler = $api->translations(5);             // 5 numaralı dil
echo $ceviriler["form.post.add"];               // "Form Ekle"
```

İstenen dilde değeri eksik olan anahtarlar varsayılan dilin değeriyle döner;
pasif çeviriler haritaya girmez. Sonuç düz bir `{anahtar: metin}` dizisidir.

### Otomatik kayıt (auto register)

`translation()` tek anahtarı **oku-ve-yoksa-kaydet** yapar. Anahtar kayıtlıysa
değerini döndürür; değilse verilen varsayılan metinle otomatik olarak kaydeder
(`/translations/create`) ve aynı kaynaktan okur. Özellikle tasarım sırasında
henüz panelden eklenmemiş anahtarları kullanmak için idealdir — ilk okumada
otomatik oluşur, sonraki okumalar aynı kaydı döndürür.

```php
// "menu.iletisim" yoksa "İletişim" ile otomatik kaydedilir ve döner.
echo $api->translation("menu.iletisim", "İletişim");

// Varsayılan metin verilmezse anahtarın kendisi kaydedilir.
echo $api->translation("form.post.add");   // "form.post.add" (yoksa)

// Belirli bir dilde okumak; varsayılan dil dışında bir dilse varsayılan dil de aynı metinle doldurulur.
echo $api->translation("menu.iletisim", "Contact", 5);
```

Sunucu varsayılan dil çevirisini zorunlu tuttuğu için, auto register istenen
dil varsayılan dilden farklıysa varsayılan dili de aynı metinle doldurur.

### Çeviri eklemek / güncellemek

```php
$sonuc = $api->translation_create("form.post.add", [
    3 => "Form Ekle",   // Dil.id => metin
    5 => "Add Form",
]);
echo $sonuc->id;        // kaydın id'si
echo $sonuc->message;   // "Çeviri Oluşturuldu!" | "Çeviri Güncellendi!"
```

Aynı anahtar zaten varsa güncellenir (upsert). **Varsayılan dilin çevirisi
zorunludur** — eksikse istisna fırlatılır. İsteğe bağlı `$durum` (0|1) ile kaydı
pasife alabilirsiniz.

---

## Etkileşim uçları

Bu üç POST ucu tasarım gereği herkese açıktır (modül izni aranmaz) ancak
API anahtarlarınız yine gönderilir ve **dakikada 10 istek** ile sınırlıdır.

### Bülten aboneliği

```php
$sonuc = $api->subscribe("ornek@site.com", "Ad Soyad");
echo $sonuc->message;  // "Abone olundu." | "Abonelik aktif."
echo $sonuc->ukey;     // abonelikten çıkış bağlantısında kullanılır
```

Aynı e-posta tekrar gönderilirse kayıt çoğaltılmaz; iptal edilmiş bir abonelik
yeniden aktifleşir.

Abonelikten çıkış bağlantısı API domaininde **değil**, panel domaininde
servis edilir: `https://<panel-domain>/abonelikten-cik/{ukey}`.

### Form gönderimi

```php
$mesaj = $api->form_submit("iletisim", [
    "ad_soyad" => "Ad Soyad",
    "eposta"   => "ornek@site.com",
    "mesaj"    => "Merhaba",
]);
echo $mesaj;  // formun teşekkür mesajı
```

Anahtarlar panelde tanımlı alan `key`'leriyle eşleşmelidir. Zorunlu bir alan
boşsa veya `email` tipli alan geçersizse istisna fırlatılır.

### Yorumlar

```php
$api->comment_add("blog", 12, "Ad Soyad", "ornek@site.com", "Güzel yazı");
// "Yorumun moderatör onayı bekliyor."

$api->comments("blog", 12);       // düz liste — sadece ONAYLI yorumlar
$api->comments_tree("blog", 12);  // parent_id'ye göre ağaç (children dizisi)
```

`tip`: `blog` | `sayfa` | `urun`. Yanıt yazmak için `$parent_id` verin.
Yeni yorumlar "beklemede" kaydedilir ve panelde onaylanana kadar `comments()`
çıktısında görünmez.

---

## SEO araçları

### Yönlendirme kontrolü

Frontend'inizin 404 dalında çağırın:

```php
$y = $api->redirect_check("/eski/adres");
if ($y) {
    header("Location: " . $y->yeni_url, true, $y->tip); // tip: 301 | 302
    exit;
}
// eşleşme yoksa $y === null — istek aynı zamanda panelde 404 olarak raporlanır
```

### Sitemap ve robots

```php
header("Content-Type: application/xml; charset=utf-8");
echo $api->sitemap();
```

```php
header("Content-Type: text/plain; charset=utf-8");
echo $api->robots();
```

URL sayısı 50.000'i aşarsa `sitemap()` bir `<sitemapindex>` döndürür ve
içindeki adresler **sizin** domaininizi gösterir. Arama motorları API
anahtarlarını gönderemeyeceği için bu adresleri kendi tarafınızda
`sitemap_part()` çıktısına proxy'lemeniz gerekir:

```php
// GET /sitemap-blog-1.xml gibi bir rota
echo $api->sitemap_part("blog", 1);
```

Geçerli tipler: `anasayfa`, `sayfa`, `blog`, `urun`, `blog_kategori`,
`urun_kategori`.

---

## Testler

```bash
composer install
php tests/smoke.php
```

Guzzle'ın `MockHandler`'ı ile çalışır — ağa çıkmaz, PHPUnit ve API anahtarı
gerektirmez. 78 kontrol; çıkış kodu 0 = hepsi geçti.

---

## 2.1 ile gelen değişiklikler

Yeni:

- Önyüz Çevirileri uçları: `translations()` (GET /translations) ve
  `translation_create()` (POST /translations/create, upsert).
  Çeviriler `Dil.id` bazlıdır; istenen dilde değeri eksik anahtarlar varsayılan
  dilin değeriyle döner, varsayılan dil çevirisi zorunludur.
- **Auto register**: `translation()` tek anahtarı okur, kayıtlı değilse verilen
  varsayılan metinle otomatik kaydeder ve aynı kaynaktan okur (get-or-create).

Mevcut tüm metot adları, parametre sıraları ve dönüş şekilleri korunmuştur.

## 2.0 ile gelen değişiklikler

Yeni:

- Etkileşim uçları: `subscribe()`, `form_submit()`, `comment_add()`,
  `comments()`, `comments_tree()`
- SEO uçları: `redirect_check()`, `sitemap()`, `sitemap_part()`, `robots()`
- İçerik yardımcıları: `seo()`, `jsonld()`, `jsonld_script()`,
  `informations()`, `slug()`, `cover_image_or_first()`, `settings()`
- Dil kontrolü: `set_lang()`, `lang_id()`, `lang_short_name()`
- Constructor seçenekleri (`verify`, `timeout`, anahtarlar, önekler)

Davranış değişiklikleri (1.x'ten yükseltirken dikkat):

- **TLS doğrulaması artık açık.** 1.x `verify => false` ile çalışıyordu; bu,
  her istekte gönderilen `W-Private-Key`'i MITM'e açık bırakıyordu. Kendi
  imzalı sertifika kullanıyorsanız `new API($url, ["verify" => false])`.
- **403/429 gerçekten yakalanıyor.** 1.x'te Guzzle bu kodlarda önce istisna
  fırlattığı için durum kodu kontrolleri ölü koddu ve hepsi tek tip
  "Bağlantı Hatası!" oluyordu.
- **`adjust()` tanımsız ayarda `null` döner** (1.x'te PHP uyarısı üretiyordu).
- **`cover_image()` düzeltildi.** 1.x'te kapak olmayan fotoğraflar diziye
  `null` olarak giriyor ve kapak ilk sırada değilse hata veriyordu; artık kapak
  bulunamazsa `null` döner.
- **Base64 alanlar boşsa `null` değil `[]` döner** — doğrudan `foreach`
  edilebilir.
- İçerik ve liste metotları sona **isteğe bağlı `$lang_id`** parametresi aldı;
  mevcut çağrılar değişmeden çalışır.

Mevcut tüm metot adları, parametre sıraları ve dönüş şekilleri korunmuştur.
