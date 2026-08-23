<?php

namespace Waaiy;

use GuzzleHttp\Client;

/**
 * Waaiy API Connector
 *
 * Waaiy headless CMS'in public REST API'sine (WAAIY_API_URL domaini) bağlanır.
 * Kimlik doğrulama üç özel header ile yapılır: W-Public-Key / W-Private-Key /
 * W-User-Key. Standart Bearer token YOKTUR.
 *
 * Yanıt zarfı her uçta aynıdır:
 *   başarı  -> {"status": 1, "data": {...}}   (bazı uçlarda ek "message")
 *   hata    -> {"status": 0, "message": "..."}
 *
 * Ayrıntılı bilgi: https://waaiy.dev/api
 */
class API
{
    /** SDK sürümü — User-Agent'a yazılır. */
    const SURUM = '2.1.0';

    /** İçerik uçlarında base64(JSON) olarak taşınan alanlar. */
    const PAKETLI_ALANLAR = ['_PHOTOS', '_VIDEOS', '_FILES', '_ACORDIONS', '_TABS', '_FIELDS'];

    /** /yorum-yap ve /yorumlar uçlarının kabul ettiği içerik tipleri. */
    const YORUM_TIPLERI = ['blog', 'sayfa', 'urun'];

    /** _ROBOTS boş geldiğinde varsayılacak meta robots değeri. */
    const ROBOTS_VARSAYILAN = 'index,follow';

    public $public_key = '';
    public $private_key = '';
    public $user_key = '';

    /** Menü uçlarına gönderilen URL önekleri. */
    public $page_prefix = 'page';
    public $blog_category_prefix = 'blogs';
    public $product_category_prefix = 'products';

    /** Aktif dilin okunacağı $_SESSION anahtarı. */
    public $lang_session_key = '_lang_id';

    /** @var \GuzzleHttp\Client */
    private $_client;

    /** @var array|null /languages yanıtının istek-içi kopyası */
    private $_languages_cache = null;

    /** @var object|null /settings yanıtının istek-içi kopyası */
    private $_settings_cache = null;

    /** @var array|null /translations yanıtının istek-içi kopyası (tüm diller) */
    private $_translations_cache = null;

    /** @var object|null active_lang() sonucunun istek-içi kopyası */
    private $_active_lang_cache = null;

    /** @var int|null set_lang() ile verilen, oturumu ezen dil id'si */
    private $_lang_id = null;

    /**
     * @param  string  $base_url  API kök adresi (ör. https://api.waaiy.dev)
     * @param  array   $options   public_key, private_key, user_key, verify,
     *                            timeout, connect_timeout, headers, handler
     *
     * NOT: `verify` artık varsayılan olarak TRUE'dur. SDK her istekte
     * W-Private-Key gönderdiği için TLS doğrulamasının kapalı olması bu
     * anahtarın MITM ile çalınmasına açık kapı bırakır. Kendi imzalı sertifika
     * kullanan bir kurulumda `['verify' => false]` ile eski davranışa
     * dönebilirsiniz (tavsiye edilmez).
     */
    public function __construct($base_url, array $options = [])
    {
        foreach (['public_key', 'private_key', 'user_key'] as $anahtar) {
            if (isset($options[$anahtar])) {
                $this->$anahtar = $options[$anahtar];
            }
        }
        foreach (['page_prefix', 'blog_category_prefix', 'product_category_prefix', 'lang_session_key'] as $onek) {
            if (isset($options[$onek])) {
                $this->$onek = $options[$onek];
            }
        }

        $config = [
            'base_uri' => $base_url,
            'verify' => array_key_exists('verify', $options) ? $options['verify'] : true,
            'timeout' => isset($options['timeout']) ? $options['timeout'] : 20,
            'connect_timeout' => isset($options['connect_timeout']) ? $options['connect_timeout'] : 10,
            // Guzzle 4xx/5xx'te exception fırlatmasın; durum kodunu kendimiz
            // yorumlayıp anlamlı mesaj üretiyoruz (özellikle 403 ve 429).
            'http_errors' => false,
            'headers' => array_merge([
                'Accept' => 'application/json',
                'User-Agent' => 'Waaiy-PHP-SDK/' . self::SURUM,
            ], isset($options['headers']) ? $options['headers'] : []),
        ];
        if (isset($options['handler'])) {
            $config['handler'] = $options['handler'];
        }

        $this->_client = new Client($config);
    }

    // ---------------------------------------------------------------------
    // Ayarlar
    // ---------------------------------------------------------------------

    /**
     * GET /settings — tek bir ayarın değerini döndürür.
     * Ayar tanımlı değilse istisna fırlatmaz, null döner.
     *
     * @return mixed
     */
    public function adjust($ayar_slug)
    {
        $ayarlar = $this->settings();

        return isset($ayarlar->{$ayar_slug}) ? $ayarlar->{$ayar_slug} : null;
    }

    /**
     * GET /settings — tenant'ın tüm ayarlarını {slug: deger} olarak döndürür.
     * JSON tutulan ayarlar çözülmüş halde gelir.
     *
     * Sonuç istek boyunca bellekte tutulur; her çağrıda ağa çıkılmaz.
     * Tazelemek için settings(true).
     *
     * @param  bool  $yenile  true ise bellekteki kopya atlanır
     * @return object
     */
    public function settings($yenile = false)
    {
        if ($yenile || $this->_settings_cache === null) {
            $this->_settings_cache = (object) $this->veri('GET', '/settings');
        }

        return $this->_settings_cache;
    }

    // ---------------------------------------------------------------------
    // Menü
    // ---------------------------------------------------------------------

    /**
     * GET /menuitems — menü elemanlarını 3 seviyeye kadar iç içe döndürür.
     * Her eleman: {type, slug, informations:{_TITLE}, children:[...]}
     *
     * @param  string    $ayar_slug  menü slug'ı
     * @param  int|null  $lang_id    verilmezse aktif dil kullanılır
     * @return array
     */
    public function menuitems($ayar_slug, $lang_id = null)
    {
        return $this->veri('GET', '/menuitems', ['query' => [
            'slug' => $ayar_slug,
            'lang_id' => $this->dilId($lang_id),
            'p_prefix' => $this->page_prefix,
            'bc_prefix' => $this->blog_category_prefix,
            'pc_prefix' => $this->product_category_prefix,
        ]]);
    }

    // ---------------------------------------------------------------------
    // Tekil içerikler
    // ---------------------------------------------------------------------

    /**
     * GET /blog — slug'a göre tek blog. _SEO ve _JSONLD blokları dahildir.
     *
     * @return object
     */
    public function blog($slug, $lang_id = null)
    {
        return $this->icerikCoz($this->veri('GET', '/blog', ['query' => [
            'slug' => $slug,
            'lang_id' => $this->dilId($lang_id),
        ]]));
    }

    /**
     * GET /page — slug'a göre tek sayfa. _SEO ve _JSONLD blokları dahildir.
     *
     * @return object
     */
    public function page($slug, $lang_id = null)
    {
        return $this->icerikCoz($this->veri('GET', '/page', ['query' => [
            'slug' => $slug,
            'lang_id' => $this->dilId($lang_id),
        ]]));
    }

    /**
     * GET /product — slug'a göre tek ürün. _SEO ve _JSONLD blokları dahildir.
     *
     * @return object
     */
    public function product($slug, $lang_id = null)
    {
        return $this->icerikCoz($this->veri('GET', '/product', ['query' => [
            'slug' => $slug,
            'lang_id' => $this->dilId($lang_id),
        ]]));
    }

    // ---------------------------------------------------------------------
    // Listeler
    // ---------------------------------------------------------------------

    /**
     * GET /blogcategory — kategori + sayfalanmış blog listesi.
     * Dönen: {__CATEGORY, __BLOGS[], __TOTAL_BLOGS}
     *
     * Liste uçları _SEO/_JSONLD taşımaz (sunucu tarafında bilinçli olarak
     * atlanır); bunlara ihtiyaç duyan sayfalar blog() ile tekil çekmelidir.
     *
     * @param  string  $slug   kategori slug'ı; boş verilirse tüm bloglar
     * @param  int     $page   1'den başlar
     * @param  int     $limit  sunucu 1–100 aralığına kelepçeler
     * @return object
     */
    public function blogcategory($slug, $page, $limit, $lang_id = null)
    {
        $data = $this->veri('GET', '/blogcategory', ['query' => [
            'page' => $page,
            'category_slug' => $slug,
            'limit' => $limit,
            'lang_id' => $this->dilId($lang_id),
        ]]);

        if (isset($data->__BLOGS) && is_array($data->__BLOGS)) {
            $data->__BLOGS = array_map([$this, 'icerikCoz'], $data->__BLOGS);
        }

        return $data;
    }

    /**
     * GET /productcategory — kategori + sayfalanmış ürün listesi.
     * Dönen: {__CATEGORY, __PRODUCTS[]}
     *
     * @param  int  $limit  sunucu 30 ile sınırlar
     * @return object
     */
    public function productcategory($slug, $page, $limit, $lang_id = null)
    {
        $data = $this->veri('GET', '/productcategory', ['query' => [
            'page' => $page,
            'category_slug' => $slug,
            'limit' => $limit,
            'lang_id' => $this->dilId($lang_id),
        ]]);

        if (isset($data->__PRODUCTS) && is_array($data->__PRODUCTS)) {
            $data->__PRODUCTS = array_map([$this, 'icerikCoz'], $data->__PRODUCTS);
        }

        return $data;
    }

    /**
     * GET /blogcategories — kategori ağacı. $parent_slug boşsa kökten başlar.
     *
     * @return array
     */
    public function blogcategories($parent_slug = '')
    {
        return $this->veri('GET', '/blogcategories', ['query' => ['slug' => $parent_slug]]);
    }

    /**
     * GET /productcategories — kategori ağacı. $parent_slug boşsa kökten başlar.
     *
     * @return array
     */
    public function productcategories($parent_slug = '')
    {
        return $this->veri('GET', '/productcategories', ['query' => ['slug' => $parent_slug]]);
    }

    /**
     * GET /dataset — veri seti içerikleri (sayfalanmış).
     * Parametre sırası geriye dönük uyumluluk için slug, limit, page'dir.
     *
     * @return array
     */
    public function dataset($slug, $limit, $page)
    {
        return $this->veri('GET', '/dataset', ['query' => [
            'page' => $page,
            'slug' => $slug,
            'limit' => $limit,
        ]]);
    }

    /**
     * GET /media — medya id'sini tam URL'e çevirir.
     *
     * @param  int     $id   medya id'si
     * @param  string  $url  URL öneki (ör. https://cdn.site.com)
     * @return string
     */
    public function media($id, $url)
    {
        return $this->veri('GET', '/media', ['query' => ['id' => $id, 'url' => $url]]);
    }

    // ---------------------------------------------------------------------
    // Diller
    // ---------------------------------------------------------------------

    /**
     * GET /languages — aktif diller; varsayılan dil ilk sıradadır.
     * Her eleman: {_ID, _NAME, _LANG_SHORT_NAME, _IS_DEFAULT}
     *
     * Sonuç istek boyunca bellekte tutulur; her çağrıda ağa çıkılmaz.
     *
     * @param  bool  $yenile  true ise bellekteki kopya atlanır
     * @return array
     */
    public function languages($yenile = false)
    {
        if ($yenile || $this->_languages_cache === null) {
            $this->_languages_cache = $this->veri('GET', '/languages');
        }

        return $this->_languages_cache;
    }

    /**
     * Aktif dil kaydını döndürür.
     *
     * Sıra: set_lang() ile verilen id → $_SESSION[$lang_session_key] →
     * varsayılan dil (listenin ilk elemanı).
     *
     * @return object|null
     */
    public function active_lang()
    {
        if ($this->_active_lang_cache !== null) {
            return $this->_active_lang_cache;
        }

        $languages = $this->languages();
        if (! is_array($languages) || count($languages) === 0) {
            throw new \Exception('Websitesi için tanımlı aktif dil bulunamadı!');
        }

        $istenen = $this->_lang_id;
        if ($istenen === null && isset($_SESSION[$this->lang_session_key])) {
            $istenen = $_SESSION[$this->lang_session_key];
        }

        if ($istenen !== null) {
            foreach ($languages as $dil) {
                // Oturumda id string olarak durabilir; gevşek karşılaştırma bilinçlidir.
                if (isset($dil->_ID) && (string) $dil->_ID === (string) $istenen) {
                    return $this->_active_lang_cache = $dil;
                }
            }
        }

        // Sunucu is_default DESC sıraladığı için ilk eleman varsayılan dildir.
        return $this->_active_lang_cache = $languages[0];
    }

    /**
     * Aktif dili oturumdan bağımsız olarak sabitler. $_SESSION kullanmayan
     * framework'lerde (Laravel, Symfony, Slim...) bunu kullanın.
     *
     * @param  int|null  $lang_id  null verilirse oturum/varsayılana geri dönülür
     * @return $this
     */
    public function set_lang($lang_id)
    {
        $this->_lang_id = ($lang_id === null) ? null : (int) $lang_id;
        $this->_active_lang_cache = null;

        return $this;
    }

    /** Aktif dilin id'si. */
    public function lang_id()
    {
        $dil = $this->active_lang();

        return $dil ? (int) $dil->_ID : null;
    }

    /** Aktif dilin kısa kodu (tr, en...). */
    public function lang_short_name()
    {
        $dil = $this->active_lang();

        return $dil ? $dil->_LANG_SHORT_NAME : null;
    }

    // ---------------------------------------------------------------------
    // İçerik yardımcıları
    // ---------------------------------------------------------------------

    /**
     * Bir içeriğin kapak görselinin tam URL'ini döndürür; kapak yoksa null.
     *
     * @param  object  $content    blog()/page()/product() çıktısı
     * @param  string  $media_url  URL öneki
     * @return string|null
     */
    public function cover_image($content, $media_url)
    {
        $fotograflar = $this->fotograflar($content);
        if ($fotograflar === []) {
            return null;
        }

        foreach ($fotograflar as $foto) {
            if (isset($foto->is_cover) && (int) $foto->is_cover === 1 && isset($foto->medya_id)) {
                return $this->media($foto->medya_id, $media_url);
            }
        }

        return null;
    }

    /**
     * Kapak yoksa ilk fotoğrafa düşen cover_image varyantı.
     *
     * @return string|null
     */
    public function cover_image_or_first($content, $media_url)
    {
        $kapak = $this->cover_image($content, $media_url);
        if ($kapak !== null) {
            return $kapak;
        }

        $fotograflar = $this->fotograflar($content);
        foreach ($fotograflar as $foto) {
            if (isset($foto->medya_id)) {
                return $this->media($foto->medya_id, $media_url);
            }
        }

        return null;
    }

    /**
     * `_INFORMATIONS` içinden aktif dile ait değeri çeker.
     * Alan adı `_TITLE`, `_CONTENT`, `_TITLESEO`, `_DESCRIPTIONSEO` olabilir.
     *
     * @return string
     */
    public function informations($content, $alan = '_TITLE', $lang_id = null)
    {
        if (! isset($content->_INFORMATIONS)) {
            return '';
        }

        return $this->dildenDeger($content->_INFORMATIONS, $alan, $lang_id, '');
    }

    /**
     * `_SLUGS` içinden aktif dile ait slug'ı çeker.
     *
     * @return string
     */
    public function slug($content, $lang_id = null)
    {
        if (! isset($content->_SLUGS)) {
            return '';
        }

        $harita = $this->haritaya($content->_SLUGS);
        $id = (string) $this->dilId($lang_id);

        return isset($harita[$id]) ? (string) $harita[$id] : '';
    }

    /**
     * `_SEO` bloğunu aktif dil için düzleştirir.
     * Dönen anahtarlar: focus_keyword, canonical, robots, og_title,
     * og_description, og_image. `robots` boşsa "index,follow" varsayılır.
     *
     * Liste uçları _SEO taşımaz; tekil blog()/page()/product() kullanın.
     *
     * @return array
     */
    public function seo($content, $lang_id = null)
    {
        $bos = [
            'focus_keyword' => '',
            'canonical' => '',
            'robots' => self::ROBOTS_VARSAYILAN,
            'og_title' => '',
            'og_description' => '',
            'og_image' => '',
        ];
        if (! isset($content->_SEO)) {
            return $bos;
        }

        $seo = $content->_SEO;
        $cikti = [
            'focus_keyword' => $this->dildenDeger($seo, '_FOCUS_KEYWORD', $lang_id, ''),
            'canonical' => $this->dildenDeger($seo, '_CANONICAL', $lang_id, ''),
            'robots' => $this->dildenDeger($seo, '_ROBOTS', $lang_id, ''),
            'og_title' => $this->dildenDeger($seo, '_OG_TITLE', $lang_id, ''),
            'og_description' => $this->dildenDeger($seo, '_OG_DESCRIPTION', $lang_id, ''),
            'og_image' => $this->dildenDeger($seo, '_OG_IMAGE', $lang_id, ''),
        ];

        // Alanlar sonradan eklendi; eski içeriklerde boş gelir.
        if ($cikti['robots'] === '') {
            $cikti['robots'] = self::ROBOTS_VARSAYILAN;
        }

        return $cikti;
    }

    /**
     * `_JSONLD` bloğundan aktif dilin schema.org grafiğini döndürür.
     *
     * @return object|null
     */
    public function jsonld($content, $lang_id = null)
    {
        if (! isset($content->_JSONLD)) {
            return null;
        }

        $harita = $this->haritaya($content->_JSONLD);
        $id = (string) $this->dilId($lang_id);

        return isset($harita[$id]) ? $harita[$id] : null;
    }

    /**
     * JSON-LD'yi doğrudan <head>'e basılabilir script etiketi olarak döndürür.
     * İçerik yoksa boş string döner.
     *
     * @return string
     */
    public function jsonld_script($content, $lang_id = null)
    {
        $graf = $this->jsonld($content, $lang_id);
        if ($graf === null) {
            return '';
        }

        // JSON_HEX_TAG: içerikteki "<" / ">" < / >'ye çevrilir; gövdedeki
        // bir </script> dizisi etiketi erken kapatamaz (XSS).
        $json = json_encode($graf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        if ($json === false) {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    // ---------------------------------------------------------------------
    // Etkileşim (form / yorum / bülten) — POST uçları
    // ---------------------------------------------------------------------

    /**
     * POST /abone-ol — bülten aboneliği.
     * Zaten kayıtlı e-posta tekrar gönderilirse abonelik yeniden aktifleşir.
     *
     * Dönen: {message, ukey}
     *
     * @return object
     */
    public function subscribe($eposta, $adi = null, $dil_id = null)
    {
        $govde = ['eposta' => $eposta];
        if ($adi !== null) {
            $govde['adi'] = $adi;
        }
        $govde['dil_id'] = ($dil_id === null) ? $this->dilId(null) : (int) $dil_id;

        $yanit = $this->cagir('POST', '/abone-ol', ['form_params' => $govde]);

        $cikti = new \stdClass;
        $cikti->message = isset($yanit->message) ? $yanit->message : '';
        $cikti->ukey = isset($yanit->data->ukey) ? $yanit->data->ukey : null;

        return $cikti;
    }

    /**
     * POST /form-gonder — panelde tanımlı bir formu doldurup gönderir.
     * Zorunlu alan boşsa ya da e-posta alanı geçersizse istisna fırlatır.
     *
     * @param  string  $form_slug  panelde tanımlı form slug'ı
     * @param  array   $veriler    {alan_key: deger}
     * @return string              formun teşekkür mesajı
     */
    public function form_submit($form_slug, array $veriler)
    {
        $yanit = $this->cagir('POST', '/form-gonder', ['form_params' => [
            'form_slug' => $form_slug,
            'veriler' => $veriler,
        ]]);

        return isset($yanit->message) ? $yanit->message : '';
    }

    /**
     * POST /yorum-yap — içeriğe yorum bırakır.
     * Yorum "beklemede" (durum=1) kaydedilir; moderatör onayından sonra
     * comments() ile görünür hale gelir.
     *
     * @param  string  $tip        blog | sayfa | urun
     * @param  int     $icerik_id  içeriğin id'si
     * @param  int     $parent_id  yanıtlanan yorumun id'si (0 = kök yorum)
     * @return string              bilgilendirme mesajı
     */
    public function comment_add($tip, $icerik_id, $adi, $eposta, $mesaj, $parent_id = 0)
    {
        $this->tipDogrula($tip);

        $yanit = $this->cagir('POST', '/yorum-yap', ['form_params' => [
            'tip' => $tip,
            'icerik_id' => (int) $icerik_id,
            'adi' => $adi,
            'eposta' => $eposta,
            'mesaj' => $mesaj,
            'parent_id' => (int) $parent_id,
        ]]);

        return isset($yanit->message) ? $yanit->message : '';
    }

    /**
     * GET /yorumlar/{tip}/{id} — yalnızca ONAYLI yorumlar, düz liste.
     * Her eleman: {id, parent_id, adi, mesaj, created_at}
     *
     * @return array
     */
    public function comments($tip, $icerik_id)
    {
        $this->tipDogrula($tip);
        $data = $this->veri('GET', '/yorumlar/' . rawurlencode($tip) . '/' . (int) $icerik_id);

        return is_array($data) ? $data : [];
    }

    /**
     * comments() çıktısını parent_id'ye göre ağaca dönüştürür.
     * Her elemana `children` dizisi eklenir.
     *
     * @return array
     */
    public function comments_tree($tip, $icerik_id)
    {
        $duz = $this->comments($tip, $icerik_id);

        $indeks = [];
        foreach ($duz as $yorum) {
            $yorum->children = [];
            $indeks[(int) $yorum->id] = $yorum;
        }

        $kok = [];
        foreach ($indeks as $yorum) {
            $ust = (int) (isset($yorum->parent_id) ? $yorum->parent_id : 0);
            // Üstü onaysız/silinmişse yetim kalmasın diye köke alınır.
            if ($ust > 0 && isset($indeks[$ust])) {
                $indeks[$ust]->children[] = $yorum;
            } else {
                $kok[] = $yorum;
            }
        }

        return $kok;
    }

    // ---------------------------------------------------------------------
    // SEO araçları
    // ---------------------------------------------------------------------

    /**
     * GET /redirect-check — panelde tanımlı bir 301/302 var mı diye bakar.
     * Frontend'in 404 dalında çağrılması beklenir; eşleşme yoksa sunucu bunu
     * aynı zamanda 404 kaydı olarak işler.
     *
     * Yönlendirme yoksa istisna DEĞİL, null döner.
     *
     * @return object|null  {yeni_url, tip}
     */
    public function redirect_check($path)
    {
        $yanit = $this->cagir('GET', '/redirect-check', ['query' => ['path' => $path]], false);

        if (! isset($yanit->status) || (int) $yanit->status !== 1) {
            return null;
        }

        return isset($yanit->data) ? $yanit->data : null;
    }

    /**
     * GET /sitemap — XML gövdesi (metin olarak).
     * URL sayısı 50.000'i aşarsa <urlset> yerine <sitemapindex> döner; index
     * içindeki adresler kiracının kendi domainini gösterir ve frontend bunları
     * sitemap_part() çıktısına proxy'lemelidir (crawler'lar API anahtarı
     * gönderemez).
     *
     * @return string
     */
    public function sitemap()
    {
        return $this->govde('GET', '/sitemap');
    }

    /**
     * GET /sitemap/{tip}/{sayfa} — sitemap index'in işaret ettiği parça.
     *
     * @param  string  $tip    anasayfa|sayfa|blog|urun|blog_kategori|urun_kategori
     * @param  int     $sayfa  1'den başlar
     * @return string
     */
    public function sitemap_part($tip, $sayfa = 1)
    {
        $sayfa = max(1, (int) $sayfa);

        return $this->govde('GET', '/sitemap/' . rawurlencode($tip) . '/' . $sayfa);
    }

    /**
     * GET /robots — panelden düzenlenen robots.txt gövdesi.
     *
     * @return string
     */
    public function robots()
    {
        return $this->govde('GET', '/robots');
    }

    // ---------------------------------------------------------------------
    // Önyüz Çevirileri (tenant'ın websitesine sunduğumuz modül)
    // ---------------------------------------------------------------------

    /**
     * GET /translations — TÜM aktif dillerin çeviri haritasını TEK istekle döndürür.
     *
     * Dönen şekil: {anahtar: {Dil.id: metin}} — örn.
     *   ["form.post.add" => [3 => "Form Ekle", 5 => "Add Form"], ...]
     * Yalnızca dolu çeviriler girilir; boş diller ve pasif kayıtlar yoktur.
     * Böylece frontend tümünü bir kez çeker, dili yerelde seçer (istek sayısı 1).
     *
     * Sonuç istek boyunca bellekte tutulur. Tazelemek için translations(true).
     *
     * @param  bool  $yenile  true ise bellekteki kopya atlanır
     * @return array  {anahtar: {Dil.id: metin}}
     */
    public function translations($yenile = false)
    {
        if ($yenile || $this->_translations_cache === null) {
            $data = $this->veri('GET', '/translations');
            $this->_translations_cache = $this->translationsHarita($data);
        }

        return $this->_translations_cache;
    }

    /**
     * Tek dile ait düz {anahtar: metin} haritasını döndürür.
     *
     * Ağa ÇIKMAZ: `translations()` önbelleğinden hesaplanır. İstenen dilde
     * değeri boş olan anahtarlar varsayılan dilin değeriyle doldurulur.
     *
     * @param  int|null  $lang_id  verilmezse aktif dil kullanılır
     * @return array  {form.post.add: "Form Ekle", ...}
     */
    public function translation_map($lang_id = null)
    {
        $dilId = $this->dilId($lang_id);
        $varsayilanDilId = $this->varsayilanDilId();

        $harita = [];
        foreach ($this->translations() as $anahtar => $diller) {
            $deger = $this->ceviriDeger($diller, $dilId, $varsayilanDilId);
            if ($deger !== '') {
                $harita[$anahtar] = $deger;
            }
        }

        return $harita;
    }

    /**
     * POST /translations/create — bir anahtar kelimenin çevirilerini ekler
     * veya günceller (aynı anahtar varsa upsert).
     *
     * Varsayılan dilin çevirisi zorunludur; diğer diller boş bırakılabilir
     * (panelde "eksik" olarak işaretlenir).
     *
     * @param  string    $anahtar     anahtar kelime (ör. form.post.add)
     * @param  array     $ceviriler   {Dil.id: metin}
     * @param  int|null  $durum       1=aktif (varsayılan), 0=pasif
     * @return object    {id, message}
     */
    public function translation_create($anahtar, array $ceviriler, $durum = null)
    {
        $govde = [
            'anahtar' => $anahtar,
            'ceviriler' => $ceviriler,
        ];
        if ($durum !== null) {
            $govde['durum'] = (int) $durum;
        }

        $yanit = $this->cagir('POST', '/translations/create', ['form_params' => $govde]);

        $cikti = new \stdClass;
        $cikti->id = isset($yanit->data->id) ? $yanit->data->id : null;
        $cikti->message = isset($yanit->message) ? $yanit->message : '';

        return $cikti;
    }

    /**
     * Tek anahtarın çevirisini okur; anahtar kayıtlı DEĞİLSE otomatik olarak
     * kaydeder (auto register) ve aynı kaynaktan (aynı tablo) okur.
     *
     * Akış: translations() ile haritayı okur → anahtar yoksa translation_create()
     * ile varsayılan metinle kaydeder → metni döndürür. Sunucu varsayılan dil
     * çevirisini zorunlu tuttuğu için, istenen dil varsayılan dilden farklıysa
     * varsayılan dil de aynı metinle doldurulur.
     *
     * @param  string      $anahtar     anahtar kelime (ör. form.post.add)
     * @param  string|null $varsayilan  anahtar yoksa kaydedilecek metin; null ise anahtarın kendisi
     * @param  int|null    $lang_id     verilmezse aktif dil kullanılır
     * @return string                   anahtarın metni
     */
    public function translation($anahtar, $varsayilan = null, $lang_id = null)
    {
        $dilId = $this->dilId($lang_id);
        $varsayilanDilId = $this->varsayilanDilId();

        $tum = $this->translations();
        if (isset($tum[$anahtar])) {
            $deger = $this->ceviriDeger($tum[$anahtar], $dilId, $varsayilanDilId);
            if ($deger !== '') {
                return $deger;
            }
        }

        $metin = ($varsayilan === null) ? $anahtar : $varsayilan;
        $harita = [$dilId => $metin];
        if ($varsayilanDilId !== null && (int) $dilId !== $varsayilanDilId) {
            $harita[$varsayilanDilId] = $metin;
        }

        $this->translation_create($anahtar, $harita);

        // Auto-register sonrası yerel önbelleği güncelle; kalan okumalar istek atmasın.
        if (! isset($this->_translations_cache[$anahtar]) || ! is_array($this->_translations_cache[$anahtar])) {
            $this->_translations_cache[$anahtar] = [];
        }
        foreach ($harita as $id => $metinX) {
            $this->_translations_cache[$anahtar][$id] = $metinX;
        }

        return $metin;
    }

    // ---------------------------------------------------------------------
    // İç yardımcılar
    // ---------------------------------------------------------------------

    /** Kimlik header'ları — her istekte gönderilir. */
    private function basliklar()
    {
        return [
            'W-User-Key' => $this->user_key,
            'W-Public-Key' => $this->public_key,
            'W-Private-Key' => $this->private_key,
        ];
    }

    /**
     * Ham HTTP isteği. Ağ/DNS/TLS hataları tek tip istisnaya çevrilir.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    private function ham($method, $path, array $options = [])
    {
        $options['headers'] = array_merge(
            $this->basliklar(),
            isset($options['headers']) ? $options['headers'] : []
        );

        try {
            return $this->_client->request($method, $path, $options);
        } catch (\Exception $e) {
            throw new \Exception('Bağlantı Hatası: ' . $e->getMessage(), 0, $e);
        }
    }

    /** HTTP durum kodunu ortak mesajlara çevirir; sorun yoksa hiçbir şey yapmaz. */
    private function durumDogrula($code, $body)
    {
        if ($code === 200) {
            return;
        }
        if ($code === 401 || $code === 403) {
            throw new \Exception('Yetkisiz Erişim Lütfen İzinleri Kontrol Edin!');
        }
        if ($code === 404) {
            throw new \Exception('İstenen uç nokta bulunamadı!');
        }
        if ($code === 422) {
            throw new \Exception('Geçersiz İstek: ' . $this->dogrulamaMesaji($body));
        }
        if ($code === 429) {
            throw new \Exception('İstek Sınırı Aşıldı! Lütfen daha sonra tekrar deneyin.');
        }
        if ($code >= 500) {
            throw new \Exception('Sunucu Hatası! (HTTP ' . $code . ')');
        }

        throw new \Exception('Bağlantı Hatası! (HTTP ' . $code . ')');
    }

    /**
     * JSON zarfını çözer.
     *
     * @param  bool  $status_dogrula  false ise status=0 istisna fırlatmaz
     *                                (redirect-check "bulunamadı"yı böyle bildirir)
     * @return object  ham zarf: {status, data?, message?}
     */
    private function cagir($method, $path, array $options = [], $status_dogrula = true)
    {
        $response = $this->ham($method, $path, $options);
        $code = $response->getStatusCode();
        $raw = (string) $response->getBody();

        $this->durumDogrula($code, $raw);

        $body = json_decode($raw);
        if (! is_object($body)) {
            throw new \Exception('Geçersiz API yanıtı (JSON çözülemedi).');
        }
        if ($status_dogrula && (! isset($body->status) || (int) $body->status !== 1)) {
            throw new \Exception(isset($body->message) ? $body->message : 'Bilinmeyen API hatası.');
        }

        return $body;
    }

    /**
     * cagir() + `data` alanını döndürür.
     *
     * @return mixed
     */
    private function veri($method, $path, array $options = [])
    {
        $body = $this->cagir($method, $path, $options);

        return isset($body->data) ? $body->data : null;
    }

    /**
     * JSON değil, düz gövde bekleyen uçlar (sitemap XML, robots.txt).
     * Hata durumunda sunucu yine JSON döndürür; mesajı yakalarız.
     *
     * @return string
     */
    private function govde($method, $path, array $options = [])
    {
        $response = $this->ham($method, $path, $options);
        $code = $response->getStatusCode();
        $raw = (string) $response->getBody();

        $this->durumDogrula($code, $raw);

        $body = json_decode($raw);
        if (is_object($body) && isset($body->status) && (int) $body->status === 0) {
            throw new \Exception(isset($body->message) ? $body->message : 'Bilinmeyen API hatası.');
        }

        return $raw;
    }

    /** 422 gövdesindeki Laravel doğrulama mesajlarını tek satıra indirger. */
    private function dogrulamaMesaji($raw)
    {
        $body = json_decode($raw, true);
        if (! is_array($body)) {
            return 'doğrulama hatası.';
        }
        if (isset($body['errors']) && is_array($body['errors'])) {
            $mesajlar = [];
            foreach ($body['errors'] as $alan => $hatalar) {
                $mesajlar[] = $alan . ': ' . (is_array($hatalar) ? implode(' ', $hatalar) : $hatalar);
            }

            return implode(' | ', $mesajlar);
        }

        return isset($body['message']) ? $body['message'] : 'doğrulama hatası.';
    }

    /**
     * İçerik uçlarındaki base64(JSON) alanları yerinde çözer.
     * Alan boş/bozuksa boş dizi verilir; çağıran taraf foreach ile dolaşabilsin.
     *
     * @return object
     */
    private function icerikCoz($item)
    {
        if (! is_object($item)) {
            return $item;
        }

        foreach (self::PAKETLI_ALANLAR as $alan) {
            if (! isset($item->{$alan}) || ! is_string($item->{$alan})) {
                continue;
            }
            $cozulmus = json_decode(base64_decode($item->{$alan}));
            $item->{$alan} = ($cozulmus === null) ? [] : $cozulmus;
        }

        return $item;
    }

    /** `_PHOTOS` alanını her koşulda dizi olarak verir. */
    private function fotograflar($content)
    {
        if (! isset($content->_PHOTOS) || ! $content->_PHOTOS) {
            return [];
        }

        $fotograflar = $content->_PHOTOS;
        if (is_string($fotograflar)) {
            // Ham yanıt (icerikCoz uygulanmamış) da kabul edilir.
            $cozulmus = json_decode($fotograflar);
            if ($cozulmus === null) {
                $cozulmus = json_decode(base64_decode($fotograflar));
            }
            $fotograflar = $cozulmus;
        }

        if (is_object($fotograflar)) {
            $fotograflar = (array) $fotograflar;
        }

        return is_array($fotograflar) ? $fotograflar : [];
    }

    /**
     * {dil_id: deger} haritalarını dizi olarak normalize eder.
     * Sunucu bunları object olarak yollar (dil id'leri sayısal olduğu için
     * JsonResource dizileri bozmasın diye bilinçli olarak (object) cast edilir).
     */
    private function haritaya($deger)
    {
        if (is_object($deger)) {
            return (array) $deger;
        }
        if (is_string($deger)) {
            $cozulmus = json_decode($deger, true);

            return is_array($cozulmus) ? $cozulmus : [];
        }

        return is_array($deger) ? $deger : [];
    }

    /** Bir kapsayıcıdaki dil haritasından aktif dilin değerini alır. */
    private function dildenDeger($kapsayici, $alan, $lang_id, $varsayilan)
    {
        $kapsayici = $this->haritaya($kapsayici);
        if (! isset($kapsayici[$alan])) {
            return $varsayilan;
        }

        $harita = $this->haritaya($kapsayici[$alan]);
        $id = (string) $this->dilId($lang_id);

        return isset($harita[$id]) && $harita[$id] !== null ? $harita[$id] : $varsayilan;
    }

    /** Verilen dil id'si yoksa aktif dile düşer. */
    private function dilId($lang_id)
    {
        return ($lang_id === null) ? $this->lang_id() : (int) $lang_id;
    }

    /** languages() içinden varsayılan dilin id'si; bulunamazsa null. */
    private function varsayilanDilId()
    {
        foreach ($this->languages() as $dil) {
            if (isset($dil->_IS_DEFAULT) && (int) $dil->_IS_DEFAULT === 1) {
                return (int) $dil->_ID;
            }
        }

        return null;
    }

    /** /translations gövdesini {anahtar: [Dil.id => metin]} dizisine normalleştirir. */
    private function translationsHarita($data)
    {
        $cikti = [];
        foreach ($this->haritaya($data) as $anahtar => $diller) {
            $cikti[$anahtar] = $this->haritaya($diller);
        }

        return $cikti;
    }

    /** Bir anahtarın dil haritasından istenen dilin değerini döndürür; boşsa varsayılana düşer. */
    private function ceviriDeger(array $diller, $dilId, $varsayilanDilId)
    {
        $deger = $diller[$dilId] ?? null;
        if (trim((string) $deger) === '' && $varsayilanDilId !== null && (string) $dilId !== (string) $varsayilanDilId) {
            $deger = $diller[$varsayilanDilId] ?? null;
        }

        return (trim((string) $deger) === '') ? '' : $deger;
    }

    private function tipDogrula($tip)
    {
        if (! in_array($tip, self::YORUM_TIPLERI, true)) {
            throw new \Exception('Geçersiz içerik tipi. Beklenen: ' . implode(', ', self::YORUM_TIPLERI));
        }
    }
}
