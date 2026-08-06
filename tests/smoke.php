<?php
/**
 * Waaiy API SDK — smoke testleri.
 *
 * Ağa çıkmaz: Guzzle'ın MockHandler'ı ile sahte yanıtlar verilir, dolayısıyla
 * PHPUnit da API anahtarı da gerektirmez.
 *
 *   composer install
 *   php tests/smoke.php
 *
 * Çıkış kodu 0 = hepsi geçti, 1 = başarısız test var.
 */
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/API.php';

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;
use Waaiy\API;

$gecti = 0; $kaldi = 0;
function ok($ad, $kosul) {
    global $gecti, $kaldi;
    if ($kosul) { $gecti++; echo "  ok   $ad\n"; }
    else { $kaldi++; echo "  FAIL $ad\n"; }
}

$LANGS = ['status' => 1, 'data' => [
    ['_ID' => 3, '_NAME' => 'Türkçe', '_LANG_SHORT_NAME' => 'tr', '_IS_DEFAULT' => 1],
    ['_ID' => 5, '_NAME' => 'English', '_LANG_SHORT_NAME' => 'en', '_IS_DEFAULT' => 0],
]];

function j($x) { return new Response(200, ['Content-Type' => 'application/json'], json_encode($x)); }

function mk(array $yanitlar, array &$kayit = null, array $opts = []) {
    $mock = new MockHandler($yanitlar);
    $stack = HandlerStack::create($mock);
    if ($kayit !== null) { $stack->push(Middleware::history($kayit)); }
    $api = new API('https://api.test', array_merge([
        'public_key' => 'PUB', 'private_key' => 'PRIV', 'user_key' => 'USER',
        'handler' => $stack,
    ], $opts));
    return $api;
}

echo "\n== Diller ==\n";
$kayit = [];
$api = mk([j($GLOBALS['LANGS'] ?? $LANGS), j($LANGS)], $kayit);
ok('lang_id varsayilan dil', $api->lang_id() === 3);
ok('lang_short_name', $api->lang_short_name() === 'tr');
$api->languages();
ok('languages memoize (tek istek)', count($kayit) === 1);
$h = $kayit[0]['request'];
ok('W-Public-Key header', $h->getHeaderLine('W-Public-Key') === 'PUB');
ok('W-Private-Key header', $h->getHeaderLine('W-Private-Key') === 'PRIV');
ok('W-User-Key header', $h->getHeaderLine('W-User-Key') === 'USER');

$api2 = mk([j($LANGS)]);
$api2->set_lang(5);
ok('set_lang', $api2->lang_id() === 5 && $api2->lang_short_name() === 'en');

$_SESSION['_lang_id'] = '5'; // oturumda string
$api3 = mk([j($LANGS)]);
ok('oturumdan dil (string id)', $api3->lang_id() === 5);
unset($_SESSION['_lang_id']);

echo "\n== Ayarlar ==\n";
$api = mk([j(['status' => 1, 'data' => ['site_basligi' => 'Waaiy', 'sosyal' => ['x' => 'a']]])]);
ok('adjust', $api->adjust('site_basligi') === 'Waaiy');
$api = mk([j(['status' => 1, 'data' => ['a' => 1]])]);
ok('adjust tanimsiz -> null', $api->adjust('yok') === null);

echo "\n== Tekil icerik ==\n";
$blog = ['status' => 1, 'data' => [
    '_INFORMATIONS' => ['_TITLE' => ['3' => 'Başlık', '5' => 'Title'], '_CONTENT' => ['3' => 'İçerik']],
    '_SEO' => [
        '_FOCUS_KEYWORD' => ['3' => 'anahtar'], '_CANONICAL' => ['3' => ''],
        '_ROBOTS' => ['3' => ''], '_OG_TITLE' => ['3' => 'OG'],
        '_OG_DESCRIPTION' => ['3' => ''], '_OG_IMAGE' => ['3' => 'https://c/x.jpg'],
    ],
    '_JSONLD' => ['3' => ['@context' => 'https://schema.org', '@graph' => [['@type' => 'BlogPosting']]]],
    '_SLUGS' => ['3' => 'ornek-yazi', '5' => 'sample'],
    '_PHOTOS' => base64_encode(json_encode([
        ['medya_id' => 7, 'is_cover' => 0], ['medya_id' => 9, 'is_cover' => 1],
    ])),
    '_VIDEOS' => base64_encode(''), '_FILES' => base64_encode('null'),
    '_ACORDIONS' => base64_encode(json_encode([['s' => 'q']])),
    '_TABS' => base64_encode(''), '_FIELDS' => base64_encode(''),
    '_STATUS' => 1,
]];
$kayit = [];
$api = mk([j($LANGS), j($blog)], $kayit);
$b = $api->blog('ornek-yazi');
ok('_PHOTOS cozuldu', is_array($b->_PHOTOS) && count($b->_PHOTOS) === 2);
ok('bos base64 -> []', $b->_VIDEOS === [] && $b->_FILES === [] && $b->_TABS === []);
ok('_ACORDIONS cozuldu', is_array($b->_ACORDIONS) && $b->_ACORDIONS[0]->s === 'q');
ok('informations aktif dil', $api->informations($b) === 'Başlık');
ok('informations acik dil', $api->informations($b, '_TITLE', 5) === 'Title');
ok('informations olmayan alan', $api->informations($b, '_TITLESEO') === '');
ok('slug', $api->slug($b) === 'ornek-yazi' && $api->slug($b, 5) === 'sample');
$seo = $api->seo($b);
ok('seo focus_keyword', $seo['focus_keyword'] === 'anahtar');
ok('seo robots varsayilan', $seo['robots'] === 'index,follow');
ok('seo og_image', $seo['og_image'] === 'https://c/x.jpg');
ok('seo _SEO yok -> varsayilan', $api->seo(new stdClass)['robots'] === 'index,follow');
$ld = $api->jsonld($b);
ok('jsonld', is_object($ld) && $ld->{'@context'} === 'https://schema.org');
ok('jsonld yanlis dil -> null', $api->jsonld($b, 99) === null);
$script = $api->jsonld_script($b);
ok('jsonld_script', strpos($script, '<script type="application/ld+json">') === 0 && substr($script, -9) === '</script>');
ok('blog query lang_id', $kayit[1]['request']->getUri()->getQuery() === 'slug=ornek-yazi&lang_id=3');

// jsonld_script XSS kacisi
$xss = ['status' => 1, 'data' => ['_JSONLD' => ['3' => ['name' => '</script><img src=x onerror=alert(1)>']]]];
$api = mk([j($LANGS), j($xss)]);
$b2 = $api->page('x');
$s2 = $api->jsonld_script($b2);
ok('jsonld_script </script> kacisi', strpos($s2, '</script><img') === false && substr_count($s2, '</script>') === 1);

echo "\n== Kapak gorseli ==\n";
$api = mk([j($LANGS), j($blog), j(['status' => 1, 'data' => 'https://cdn/9.jpg'])]);
$b = $api->blog('x');
ok('cover_image (kapak 2. sirada)', $api->cover_image($b, 'https://cdn') === 'https://cdn/9.jpg');

$noCover = $blog;
$noCover['data']['_PHOTOS'] = base64_encode(json_encode([['medya_id' => 7, 'is_cover' => 0]]));
$api = mk([j($LANGS), j($noCover)]);
ok('cover_image kapak yok -> null', $api->cover_image($api->blog('x'), 'https://cdn') === null);

$api = mk([j($LANGS), j($noCover), j(['status' => 1, 'data' => 'https://cdn/7.jpg'])]);
ok('cover_image_or_first', $api->cover_image_or_first($api->blog('x'), 'https://cdn') === 'https://cdn/7.jpg');

$empty = $blog; $empty['data']['_PHOTOS'] = base64_encode('');
$api = mk([j($LANGS), j($empty)]);
ok('cover_image foto yok -> null', $api->cover_image($api->blog('x'), 'https://cdn') === null);

echo "\n== Listeler ==\n";
$liste = ['status' => 1, 'data' => [
    '__CATEGORY' => ['_INFORMATIONS' => ['_TITLE' => ['3' => 'Kat']], '_SLUGS' => ['3' => 'kat']],
    '__BLOGS' => [
        ['_SLUGS' => ['3' => 'a'], '_PHOTOS' => base64_encode(json_encode([['medya_id' => 1]])), '_VIDEOS' => base64_encode('')],
        ['_SLUGS' => ['3' => 'b'], '_PHOTOS' => base64_encode(''), '_VIDEOS' => base64_encode('')],
    ],
    '__TOTAL_BLOGS' => 137,
]];
$kayit = [];
$api = mk([j($LANGS), j($liste)], $kayit);
$r = $api->blogcategory('kat', 1, 20);
ok('__TOTAL_BLOGS', $r->__TOTAL_BLOGS === 137);
ok('liste _PHOTOS cozuldu', is_array($r->__BLOGS[0]->_PHOTOS) && $r->__BLOGS[0]->_PHOTOS[0]->medya_id === 1);
ok('liste bos _PHOTOS -> []', $r->__BLOGS[1]->_PHOTOS === []);
parse_str($kayit[1]['request']->getUri()->getQuery(), $q);
ok('blogcategory query', $q['page'] == 1 && $q['category_slug'] === 'kat' && $q['limit'] == 20 && $q['lang_id'] == 3);

$plist = ['status' => 1, 'data' => ['__CATEGORY' => [], '__PRODUCTS' => [
    ['_PHOTOS' => base64_encode(json_encode([['medya_id' => 2]]))],
]]];
$api = mk([j($LANGS), j($plist)]);
$r = $api->productcategory('', 1, 12);
ok('productcategory cozuldu', $r->__PRODUCTS[0]->_PHOTOS[0]->medya_id === 2);

echo "\n== Menu ==\n";
$kayit = [];
$api = mk([j($LANGS), j(['status' => 1, 'data' => [['type' => 'page', 'slug' => 'x']]])], $kayit);
$m = $api->menuitems('ana-menu');
ok('menuitems', is_array($m) && $m[0]->type === 'page');
parse_str($kayit[1]['request']->getUri()->getQuery(), $q);
ok('menuitems onekleri', $q['p_prefix'] === 'page' && $q['bc_prefix'] === 'blogs' && $q['pc_prefix'] === 'products' && $q['lang_id'] == 3);

echo "\n== Etkilesim ==\n";
$kayit = [];
$api = mk([j($LANGS), j(['status' => 1, 'message' => 'Abone olundu.', 'data' => ['ukey' => 'U-1']])], $kayit);
$s = $api->subscribe('a@b.com', 'Ad');
ok('subscribe', $s->ukey === 'U-1' && $s->message === 'Abone olundu.');
parse_str((string) $kayit[1]['request']->getBody(), $body);
ok('subscribe govde', $body['eposta'] === 'a@b.com' && $body['adi'] === 'Ad' && $body['dil_id'] == 3);
ok('subscribe POST', $kayit[1]['request']->getMethod() === 'POST');

$kayit = [];
$api = mk([j(['status' => 1, 'message' => 'Teşekkürler'])], $kayit);
ok('form_submit', $api->form_submit('iletisim', ['ad' => 'X', 'eposta' => 'a@b.com']) === 'Teşekkürler');
parse_str((string) $kayit[0]['request']->getBody(), $body);
ok('form_submit govde', $body['form_slug'] === 'iletisim' && $body['veriler']['ad'] === 'X');

$api = mk([j(['status' => 0, 'message' => '"E-posta" zorunludur.'])]);
try { $api->form_submit('iletisim', []); ok('form_submit hata', false); }
catch (\Exception $e) { ok('form_submit hata mesaji', $e->getMessage() === '"E-posta" zorunludur.'); }

$kayit = [];
$api = mk([j(['status' => 1, 'message' => 'Yorumun moderatör onayı bekliyor.'])], $kayit);
ok('comment_add', $api->comment_add('blog', 12, 'Ad', 'a@b.com', 'Merhaba') === 'Yorumun moderatör onayı bekliyor.');
try { $api->comment_add('haber', 1, 'a', 'a@b.com', 'm'); ok('gecersiz tip', false); }
catch (\Exception $e) { ok('gecersiz tip reddedildi', strpos($e->getMessage(), 'Geçersiz içerik tipi') === 0); }

$yorumlar = ['status' => 1, 'data' => [
    ['id' => 1, 'parent_id' => 0, 'adi' => 'A', 'mesaj' => 'm1'],
    ['id' => 2, 'parent_id' => 1, 'adi' => 'B', 'mesaj' => 'm2'],
    ['id' => 3, 'parent_id' => 0, 'adi' => 'C', 'mesaj' => 'm3'],
    ['id' => 4, 'parent_id' => 99, 'adi' => 'D', 'mesaj' => 'yetim'],
]];
$kayit = [];
$api = mk([j($yorumlar)], $kayit);
ok('comments', count($api->comments('blog', 12)) === 4);
ok('comments yolu', (string) $kayit[0]['request']->getUri()->getPath() === '/yorumlar/blog/12');
$api = mk([j($yorumlar)]);
$agac = $api->comments_tree('blog', 12);
ok('comments_tree kok sayisi', count($agac) === 3);
ok('comments_tree children', count($agac[0]->children) === 1 && $agac[0]->children[0]->id === 2);
ok('comments_tree yetim koke alindi', $agac[2]->id === 4);

echo "\n== SEO ==\n";
$api = mk([j(['status' => 1, 'data' => ['yeni_url' => '/yeni', 'tip' => 301]])]);
$y = $api->redirect_check('/eski');
ok('redirect_check bulundu', $y->yeni_url === '/yeni' && $y->tip === 301);
$api = mk([j(['status' => 0, 'message' => 'Yönlendirme yok.'])]);
ok('redirect_check yok -> null (istisna yok)', $api->redirect_check('/x') === null);

$xml = '<?xml version="1.0"?><urlset><url><loc>https://a/b</loc></url></urlset>';
$api = mk([new Response(200, ['Content-Type' => 'application/xml'], $xml)]);
ok('sitemap ham XML', $api->sitemap() === $xml);
$kayit = [];
$api = mk([new Response(200, [], $xml)], $kayit);
$api->sitemap_part('blog_kategori', 2);
ok('sitemap_part yolu', $kayit[0]['request']->getUri()->getPath() === '/sitemap/blog_kategori/2');
$api = mk([new Response(200, [], "User-agent: *\nDisallow:\n")]);
ok('robots', $api->robots() === "User-agent: *\nDisallow:\n");
$api = mk([j(['status' => 0, 'message' => 'Yetkisiz Erişim!'])]);
try { $api->sitemap(); ok('sitemap hata', false); }
catch (\Exception $e) { ok('sitemap status=0 -> istisna', $e->getMessage() === 'Yetkisiz Erişim!'); }

echo "\n== Hata kodlari ==\n";
$api = mk([new Response(403, [], '{"message":"Yetkisiz"}')]);
try { $api->settings(); ok('403', false); }
catch (\Exception $e) { ok('403 mesaji', $e->getMessage() === 'Yetkisiz Erişim Lütfen İzinleri Kontrol Edin!'); }

$api = mk([new Response(429, [], '')]);
try { $api->settings(); ok('429', false); }
catch (\Exception $e) { ok('429 mesaji', strpos($e->getMessage(), 'İstek Sınırı Aşıldı') === 0); }

$api = mk([new Response(422, [], json_encode(['message' => 'x', 'errors' => ['eposta' => ['E-posta geçersiz.']]]))]);
try { $api->settings(); ok('422', false); }
catch (\Exception $e) { ok('422 dogrulama detayi', $e->getMessage() === 'Geçersiz İstek: eposta: E-posta geçersiz.'); }

$api = mk([new Response(500, [], '')]);
try { $api->settings(); ok('500', false); }
catch (\Exception $e) { ok('500 mesaji', $e->getMessage() === 'Sunucu Hatası! (HTTP 500)'); }

$api = mk([new Response(200, [], '<html>bozuk')]);
try { $api->settings(); ok('bozuk json', false); }
catch (\Exception $e) { ok('bozuk JSON istisnasi', strpos($e->getMessage(), 'Geçersiz API yanıtı') === 0); }

$api = mk([new \GuzzleHttp\Exception\ConnectException('cURL error 6', new \GuzzleHttp\Psr7\Request('GET', '/'))]);
try { $api->settings(); ok('baglanti', false); }
catch (\Exception $e) { ok('baglanti hatasi sarildi', strpos($e->getMessage(), 'Bağlantı Hatası: ') === 0); }

echo "\n== Geriye donuk uyum ==\n";
$api = new API('https://api.test');
$api->public_key = 'P'; $api->private_key = 'Q'; $api->user_key = 'R';
ok('1.x property atamasi', $api->public_key === 'P');
$r = new ReflectionClass(API::class);
$imza = [];
foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $m) { $imza[$m->getName()] = true; }
foreach (['adjust','menuitems','blog','page','product','blogcategory','productcategory',
          'blogcategories','productcategories','languages','dataset','media','cover_image','active_lang'] as $eski) {
    ok("1.x metodu mevcut: $eski", isset($imza[$eski]));
}
ok('dataset(slug,limit,page) sirasi', (string) (new ReflectionMethod(API::class, 'dataset'))->getParameters()[1]->getName() === 'limit');

echo "\n----------------------------\nGecen: $gecti   Kalan: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
