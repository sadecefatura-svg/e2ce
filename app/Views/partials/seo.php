<?php
use App\Core\Seo;
use App\Core\DB;

$cfg = require __DIR__ . '/../../../config/config.php';
$base = rtrim($cfg['app']['url'] ?? '', '/');
$lang = $lang ?? 'en';
$robots = $robots ?? 'index,follow';
$type = $type ?? 'website';
$image = $image ?? null;

if (empty($canonical)) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? parse_url($base, PHP_URL_HOST);
  $req  = $_SERVER['REQUEST_URI'] ?? '/';
  $canonical = $scheme.'://'.$host.$req;
  if (isset($post) && !empty($post['slug'])) {
    $canonical = $base.'/'.$lang.'/post?slug='.rawurlencode($post['slug']);
  }
}
$url = $canonical;

// Alternates (hreflang)
$alternates = [];
if (isset($post) && !empty($post['translation_key'])) {
  $pdo = DB::conn($cfg['db']);
  $stmt = $pdo->prepare("SELECT language_code, slug FROM posts WHERE translation_key=? AND status='published'");
  $stmt->execute([$post['translation_key']]);
  foreach ($stmt->fetchAll() ?: [] as $r) {
    $alternates[$r['language_code']] = $base.'/'.$r['language_code'].'/post?slug='.rawurlencode($r['slug']);
  }
} else {
  foreach (($cfg['app']['supported_langs'] ?? ['en']) as $lng) {
    $alternates[$lng] = $base.'/'.$lng.'/';
  }
}

// Görsel yoksa çıkar
if (!$image && isset($post)) $image = Seo::firstImageFromHtml($post['body'] ?? '', $base);
if (!$image) $image = $base.'/assets/og-default.jpg';

// Publisher & Twitter
$siteName = $cfg['app']['site_name'] ?? ($cfg['app']['name'] ?? 'E2CE');
$publisher = $cfg['publisher'] ?? [];
$publisherLogo = [
  '@type' => 'ImageObject',
  'url' => $publisher['logo_url'] ?? ($base.'/assets/logo-512.png'),
  'width'  => (int)($publisher['logo_width'] ?? 512),
  'height' => (int)($publisher['logo_height'] ?? 512),
];
$twitterSite    = $publisher['twitter_site']    ?? null;
$twitterCreator = $publisher['twitter_creator'] ?? null;

// JSON-LD
if (isset($post)) {
  $isNews = !empty($post['published_at']) && (time() - strtotime($post['published_at'])) < 60*60*24*30;
  $tagsArr = \App\Core\Seo::normalizeTags($post['tags'] ?? '');
  $jsonArticle = [
    '@context' => 'https://schema.org',
    '@type' => $isNews ? 'NewsArticle' : 'Article',
    'headline' => mb_substr($title ?? ($post['title'] ?? ''), 0, 110, 'UTF-8'),
    'inLanguage' => $lang,
    'mainEntityOfPage' => $canonical,
    'datePublished' => !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : date('c', strtotime($post['created_at'] ?? 'now')),
    'dateModified'  => !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : date('c', strtotime($post['created_at'] ?? 'now')),
    'publisher' => [
      '@type' => 'Organization',
      'name'  => $publisher['name'] ?? $siteName,
      'logo'  => $publisherLogo,
    ],
  ];
  if (!empty($image)) $jsonArticle['image'] = [$image];
  if (!empty($post['section'])) $jsonArticle['articleSection'] = (string)$post['section'];
  if (!empty($tagsArr)) $jsonArticle['keywords'] = implode(', ', $tagsArr);

  $breadcrumb = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
      ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$base.'/'.$lang.'/']
    ]
  ];
  if (!empty($post['section'])) {
    $breadcrumb['itemListElement'][] = ['@type'=>'ListItem','position'=>2,'name'=>(string)$post['section'],'item'=>$base.'/'.$lang.'/?section='.rawurlencode($post['section'])];
    $breadcrumb['itemListElement'][] = ['@type'=>'ListItem','position'=>3,'name'=>(string)($post['title'] ?? 'Article'),'item'=>$canonical];
  } else {
    $breadcrumb['itemListElement'][] = ['@type'=>'ListItem','position'=>2,'name'=>(string)($post['title'] ?? 'Article'),'item'=>$canonical];
  }
  $jsonld = [$jsonArticle, $breadcrumb];
} else {
  $jsonld = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $siteName,
    'inLanguage' => $lang,
    'url' => $base.'/'.$lang.'/',
    'publisher' => ['@type'=>'Organization','name'=>$publisher['name'] ?? $siteName,'logo'=>$publisherLogo],
  ];
}

// AMP & mweb link’leri (post’ta set edilmişse)
$amphtml = $ampUrl ?? null;
$mobileAlt = $mobileUrl ?? null;

echo Seo::tags([
  'title'       => $title ?? $siteName,
  'description' => $desc ?? '',
  'canonical'   => $canonical,
  'url'         => $url,
  'image'       => $image,
  'lang'        => $lang,
  'alternates'  => $alternates,
  'robots'      => $robots,
  'type'        => isset($post) ? 'article' : 'website',
  'jsonld'      => $jsonld,
  'site_name'   => $siteName,
  'twitter_site'=> $twitterSite,
  'twitter_creator' => $twitterCreator,
  'amphtml'     => $amphtml,
  'mobile_alternate' => $mobileAlt,
]);
