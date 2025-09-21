<?php

use App\Core\View;
use App\Core\DB;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Image;
use App\Core\Util;
use App\Core\I18n;
use App\Core\Sanitizer;
use App\Core\Uuid;
use App\Core\Mail;

/**
 * Dil bazında benzersiz slug üretir: en, "test" -> "test-2", "test-3"...
 * $excludeId: edit sırasında mevcut kaydı hariç tutmak için.
 */
function e2ce_make_unique_slug(PDO $pdo, string $lang, string $baseSlug, ?int $excludeId = null): string
{
  $slug = $baseSlug !== '' ? $baseSlug : 'post';
  $i = 1;

  while (true) {
    if ($excludeId) {
      $stmt = $pdo->prepare("SELECT id FROM posts WHERE language_code=? AND slug=? AND id<>? LIMIT 1");
      $stmt->execute([$lang, $slug, $excludeId]);
    } else {
      $stmt = $pdo->prepare("SELECT id FROM posts WHERE language_code=? AND slug=? LIMIT 1");
      $stmt->execute([$lang, $slug]);
    }
    $exists = $stmt->fetchColumn();

    if (!$exists) return $slug;

    $i++;
    $slug = $baseSlug . '-' . $i;
  }
}

// Home: "/"
$router->get('/', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = \App\Core\DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();

  $perPage = 12;
  $page    = 1;
  $offset  = 0;

  // Güvenli cast
  $limit  = (int)$perPage + 1;
  $offset = (int)$offset;

  $sql = "SELECT p.id, p.slug, p.title, p.excerpt, p.section, p.tags, p.language_code, p.published_at, p.body,
                 m.path AS cover_url
          FROM posts p
          LEFT JOIN media m ON m.id = p.cover_media_id
          WHERE p.status='published'
            AND (p.published_at IS NULL OR p.published_at <= NOW())
            AND p.language_code = ?
          ORDER BY COALESCE(p.published_at, p.created_at) DESC
          LIMIT $limit OFFSET $offset";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$lang]);
  $rows = $stmt->fetchAll();

  $hasNext = count($rows) > $perPage;
  if ($hasNext) array_pop($rows);
  $hasPrev = false;

  $totalPages = null;
  if (!empty($cfg['app']['env']) && $cfg['app']['env'] !== 'prod') {
    $c = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status='published' AND (published_at IS NULL OR published_at <= NOW()) AND language_code=?");
    $c->execute([$lang]);
    $total = (int)$c->fetchColumn();
    $totalPages = (int)ceil($total / $perPage);
  }

  \App\Core\View::render('home/index', [
    'posts' => $rows,
    'page'  => $page,
    'hasPrev' => $hasPrev,
    'hasNext' => $hasNext,
    'totalPages' => $totalPages ?? null,
    'metaTitle' => 'Latest stories',
    'metaDescription' => 'Freshly published articles and trend insights.',
    'pageNo' => $page,
    'canonical' => '/' . $lang . '/',
  ]);
});
$router->get('/lang/{code}', function ($params) {
  $code = strtolower(preg_replace('~[^a-z-]~', '', (string)($params['code'] ?? '')));
  if (!\App\Core\I18n::isSupported($code)) {
    $code = \App\Core\I18n::default();
  }
  \App\Core\I18n::set($code);

  $next = isset($_GET['next']) ? urldecode((string)$_GET['next']) : ('/' . $code . '/');
  if ($next === '' || $next[0] !== '/' || strpos($next, '://') !== false) {
    $next = '/' . $code . '/';
  }

  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  header('Location: ' . $next, true, 302);
  exit;
});


// Home paginated: "/page/{n}"
$router->get('/page/{n}', function ($params) {
  $cfg = require __DIR__ . '/config.php';
  $pdo = \App\Core\DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();

  $perPage = 12;
  $page = max(1, (int)($params['n'] ?? 1));
  $offset = ($page - 1) * $perPage;

  $sql = "SELECT id, slug, title, excerpt, section, tags, language_code, published_at, body
          FROM posts
          WHERE status='published'
            AND (published_at IS NULL OR published_at <= NOW())
            AND language_code = ?
          ORDER BY COALESCE(published_at, created_at) DESC
          LIMIT ? OFFSET ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$lang, $perPage + 1, $offset]); // +1: hasNext
  $rows = $stmt->fetchAll();

  $hasNext = count($rows) > $perPage;
  if ($hasNext) array_pop($rows);
  $hasPrev = $page > 1;

  $totalPages = null;
  if (!empty($cfg['app']['env']) && $cfg['app']['env'] !== 'prod') {
    $c = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status='published' AND (published_at IS NULL OR published_at <= NOW()) AND language_code = ?");
    $c->execute([$lang]);
    $total = (int)$c->fetchColumn();
    $totalPages = (int)ceil($total / $perPage);
  }

  \App\Core\View::render('home/index', [
    'posts' => $rows,
    'page'  => $page,
    'hasPrev' => $hasPrev,
    'hasNext' => $hasNext,
    'totalPages' => $totalPages ?? null,
    'metaTitle' => 'Latest stories',
    'metaDescription' => 'Freshly published articles and trend insights.',
    'pageNo' => $page,
    'canonical' => '/' . $lang . '/page/' . $page,
  ]);
});

/*
$router->get('/admin/mail-test', function() {
  // Sadece admin kontrolü istersen:
  // if (!\App\Core\Auth::check() || \App\Core\Auth::role()!=='admin') { http_response_code(403); exit('Forbidden'); }

  \App\Core\Mail::send('ismail@gencan.com.tr', 'E2CE mail test', '<p>This is a <b>test</b> email from E2CE.</p>');
  echo "Sent (or logged) — check your inbox or storage/logs/mail.log";
});
*/
// ---------- ROBOTS ----------
$router->get('/robots.txt', function () {
  $cfg = require __DIR__ . '/config.php';
  header('Content-Type: text/plain; charset=utf-8');
  // kısa cache
  header('Cache-Control: public, max-age=600, stale-while-revalidate=600');
  echo "User-agent: *\nAllow: /\nSitemap: " . rtrim($cfg['app']['url'], '/') . "/sitemap.xml\n";
});

// ---------- SITEMAP (çok dilli + hreflang) ----------
$router->get('/sitemap.xml', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  header('Content-Type: application/xml; charset=utf-8');
  header('Cache-Control: public, max-age=900, stale-while-revalidate=900');

  // Tüm yayımlanmış içerikleri çeviri gruplarıyla çek
  $sql = "
    SELECT translation_key, language_code, slug,
           COALESCE(updated_at, created_at) AS ts
    FROM posts
    WHERE status='published'
    ORDER BY translation_key ASC, ts DESC
    LIMIT 50000
  ";
  $rows = $pdo->query($sql)->fetchAll();

  $base = rtrim($cfg['app']['url'], '/');
  // translation_key => [lang => ['slug'=>..., 'ts'=>...]]
  $groups = [];
  foreach ($rows as $r) {
    $t = $r['translation_key'] ?: ('__' . $r['language_code'] . '__' . $r['slug']);
    $lang = $r['language_code'];
    if (!isset($groups[$t])) $groups[$t] = [];
    $groups[$t][$lang] = ['slug' => $r['slug'], 'ts' => $r['ts']];
  }

  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";

  // ana sayfalar
  foreach ($cfg['app']['supported_langs'] as $lng) {
    $loc = "{$base}/{$lng}/";
    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    // hreflang alternates for home
    foreach ($cfg['app']['supported_langs'] as $alt) {
      $altLoc = "{$base}/{$alt}/";
      echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$alt}\" href=\"{$altLoc}\" />\n";
    }
    echo "    <changefreq>hourly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
  }

  // içerikler (hreflang kümeleri)
  foreach ($groups as $langs) {
    // her gruptan bir "kanonik" seç — mesela en güncel/zaten döngüde ilk olanı
    arsort($langs); // ts’e göre kaba sıralama
    // birincinin dili & slug’ı
    $first = reset($langs);
    $firstLang = array_key_first($langs);
    $loc = "{$base}/{$firstLang}/post?slug=" . htmlspecialchars($first['slug'], ENT_QUOTES, 'UTF-8');
    $lastmod = date('c', strtotime($first['ts']));

    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    foreach ($langs as $llang => $meta) {
      $href = "{$base}/{$llang}/post?slug=" . htmlspecialchars($meta['slug'], ENT_QUOTES, 'UTF-8');
      echo "    <xhtml:link rel=\"alternate\" hreflang=\"{$llang}\" href=\"{$href}\" />\n";
    }
    echo "    <changefreq>hourly</changefreq>\n";
    echo "  </url>\n";
  }

  echo "</urlset>";
});

// ---------- RSS FEEDS ----------
// /feed.xml : tüm dillerden son içerikler (karma)
$router->get('/feed.xml', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  header('Content-Type: application/rss+xml; charset=utf-8');
  header('Cache-Control: public, max-age=600, stale-while-revalidate=600');

  $base = rtrim($cfg['app']['url'], '/');
  $siteTitle = $cfg['app']['name'] . ' — Global Feed';

  $stmt = $pdo->query("SELECT language_code, slug, title, excerpt, body, COALESCE(published_at, created_at) AS ts
                       FROM posts WHERE status='published'
                       ORDER BY ts DESC LIMIT 50");
  $items = $stmt->fetchAll();

  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "<rss version=\"2.0\">\n<channel>\n";
  echo "<title>" . htmlspecialchars($siteTitle) . "</title>\n";
  echo "<link>{$base}/</link>\n";
  echo "<description>Latest posts from all languages</description>\n";
  echo "<language>en</language>\n"; // kanal dili temsilidir

  foreach ($items as $it) {
    $link = "{$base}/{$it['language_code']}/post?slug=" . htmlspecialchars($it['slug'], ENT_QUOTES, 'UTF-8');
    $pubDate = date(DATE_RSS, strtotime($it['ts']));
    $desc = $it['excerpt'] ?: mb_strimwidth(strip_tags($it['body']), 0, 300, '…', 'UTF-8');
    echo "<item>\n";
    echo "  <title>" . htmlspecialchars($it['title']) . " [{$it['language_code']}]</title>\n";
    echo "  <link>{$link}</link>\n";
    echo "  <guid isPermaLink=\"true\">{$link}</guid>\n";
    echo "  <pubDate>{$pubDate}</pubDate>\n";
    echo "  <description>" . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . "</description>\n";
    echo "</item>\n";
  }

  echo "</channel>\n</rss>";
});

// /feed-{lang}.xml : belirli dil
$router->get('/feed-:lang.xml', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);

  // Basit parametre yakalama (Router'ımızda pattern yoksa querystring ile kullanabilirsin: /feed.xml?lang=en)
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
  if (!preg_match('#/feed-([a-z]{2})\.xml$#i', $path, $m)) {
    http_response_code(404);
    echo "Not Found";
    return;
  }
  $lang = strtolower($m[1]);
  if (!I18n::isSupported($lang)) {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  header('Content-Type: application/rss+xml; charset=utf-8');
  header('Cache-Control: public, max-age=600, stale-while-revalidate=600');

  $base = rtrim($cfg['app']['url'], '/');
  $siteTitle = $cfg['app']['name'] . " — {$lang} Feed";

  $stmt = $pdo->prepare("SELECT slug, title, excerpt, body, COALESCE(published_at, created_at) AS ts
                         FROM posts WHERE status='published' AND language_code=?
                         ORDER BY ts DESC LIMIT 50");
  $stmt->execute([$lang]);
  $items = $stmt->fetchAll();

  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "<rss version=\"2.0\">\n<channel>\n";
  echo "<title>" . htmlspecialchars($siteTitle) . "</title>\n";
  echo "<link>{$base}/{$lang}/</link>\n";
  echo "<description>Latest {$lang} posts</description>\n";
  echo "<language>{$lang}</language>\n";

  foreach ($items as $it) {
    $link = "{$base}/{$lang}/post?slug=" . htmlspecialchars($it['slug'], ENT_QUOTES, 'UTF-8');
    $pubDate = date(DATE_RSS, strtotime($it['ts']));
    $desc = $it['excerpt'] ?: mb_strimwidth(strip_tags($it['body']), 0, 300, '…', 'UTF-8');
    echo "<item>\n";
    echo "  <title>" . htmlspecialchars($it['title']) . "</title>\n";
    echo "  <link>{$link}</link>\n";
    echo "  <guid isPermaLink=\"true\">{$link}</guid>\n";
    echo "  <pubDate>{$pubDate}</pubDate>\n";
    echo "  <description>" . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . "</description>\n";
    echo "</item>\n";
  }

  echo "</channel>\n</rss>";
});

// ---------- ADMIN ----------
$router->get('/admin', function () {
  // asla cache'leme
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  if (\App\Core\Auth::check()) {
    \App\Core\View::render('admin/dashboard', ['title' => 'Dashboard']);
  } else {
    header('Location: /admin/login', true, 302);
    exit;
  }
});
// ── LOGIN FORM (GET)
$router->get('/admin/login', function () {
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  // Hangi dosya çağrılıyor, logla:
  error_log('[admin/login GET] render admin/login');

  \App\Core\View::render('admin/login', [
    'title' => 'Admin Login',
  ]);
});

// ── LOGIN SUBMIT (POST)
$router->post('/admin/login', function () {
  $cfg = require __DIR__ . '/config.php';
  if (!\App\Core\Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'] ?? null)) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  if ($email !== '' && $pass !== '' && \App\Core\Auth::attempt($email, $pass)) {
    header('Location: /admin', true, 302);
    exit;
  }

  // Hata durumunda formu tekrar göster
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  \App\Core\View::render('admin/login', [
    'title' => 'Admin Login',
    'error' => 'Invalid credentials',
    'old'   => ['email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8')],
  ]);
});

// ── LOGOUT
$router->get('/admin/logout', function () {
  \App\Core\Auth::logout();
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');
  header('Location: /admin/login', true, 302);
  exit;
});

$router->get('/admin/audit/auth', function () {
  \App\Core\Auth::requireRole(['admin']);
  $cfg = require __DIR__ . '/config.php';
  $pdo = \App\Core\DB::conn($cfg['db']);

  $stmt = $pdo->query("SELECT id, user_id, email, ip, user_agent, ok, reason, created_at
                       FROM auth_log ORDER BY id DESC LIMIT 200");
  $rows = $stmt->fetchAll();

  \App\Core\View::render('admin/audit_auth', ['rows' => $rows]);
});

// Posts list
$router->get('/admin/posts', function () {
  Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = $_GET['lang'] ?? null;
  if ($lang && !I18n::isSupported($lang)) $lang = null;
  if ($lang) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE language_code=? ORDER BY updated_at DESC LIMIT 500");
    $stmt->execute([$lang]);
  } else {
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY updated_at DESC LIMIT 500");
  }
  $rows = $stmt->fetchAll();
  View::render('admin/posts/index', ['title' => 'Posts', 'rows' => $rows, 'lang' => $lang, 'langs' => I18n::supported()]);
});

// Create
$router->get('/admin/posts/create', function () {
  Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';
  $token = Csrf::token($cfg['security']['csrf_key']);
  $tkey  = Uuid::v4();
  View::render('admin/posts/create', [
    'title' => 'Create Post',
    '_csrf' => $token,
    'langs' => I18n::supported(),
    'default_lang' => I18n::get(),
    'translation_key' => $tkey
  ]);
});


$router->post('/admin/posts/create', function () {
  \App\Core\Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';
  if (!\App\Core\Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'])) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }
  $pdo = \App\Core\DB::conn($cfg['db']);

  $lang = $_POST['language_code'] ?? \App\Core\I18n::default();
  if (!\App\Core\I18n::isSupported($lang)) $lang = \App\Core\I18n::default();

  $tkey = $_POST['translation_key'] ?? \App\Core\Uuid::v4();

  $slug   = trim((string)($_POST['slug'] ?? ''));
  $title  = trim((string)($_POST['title'] ?? ''));
  $excerpt = $_POST['excerpt'] ?? null;

  $section = trim((string)($_POST['section'] ?? ''));
  $section = ($section !== '') ? $section : null;

  $tagsCsv = trim((string)($_POST['tags'] ?? ''));
  $tagsArr = $tagsCsv === '' ? [] : array_filter(array_map('trim', explode(',', $tagsCsv)));
  $tagsCsv = $tagsCsv !== '' ? $tagsCsv : null;

  $cleanBody = \App\Core\Sanitizer::cleanHtml($_POST['body'] ?? '');

  // Kapak medya ID (opsiyonel)
  $coverMediaId = null;
  if (isset($_POST['cover_media_id']) && $_POST['cover_media_id'] !== '') {
    $coverMediaId = (int)$_POST['cover_media_id'];
    // var mı diye hafif doğrulama
    $chk = $pdo->prepare("SELECT id FROM media WHERE id=? LIMIT 1");
    $chk->execute([$coverMediaId]);
    if (!$chk->fetchColumn()) $coverMediaId = null;
  }

  // Zaman durumu
  $status = $_POST['status'] ?? 'draft';
  $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;

  $stmt = $pdo->prepare("INSERT INTO posts
       (type, slug, title, excerpt, section, tags, body, status, published_at, author_id, language_code, translation_key, cover_media_id)
       VALUES ('post', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $slug,
    $title,
    $excerpt,
    $section,
    $tagsCsv,
    $cleanBody,
    $status,
    $published_at,
    \App\Core\Auth::id(),
    $lang,
    $tkey,
    $coverMediaId,
  ]);

  \App\Core\Invalidation::purgeForPost($slug, $lang, $tagsArr, $section);
  header("Location: /admin/posts?lang=" . urlencode($lang));
  exit;
});

// Edit (GET) – formu göster
$router->get('/admin/posts/edit', function () {
  \App\Core\Auth::requireRole(['admin', 'editor', 'author']);

  $cfg = require __DIR__ . '/config.php';
  $pdo = \App\Core\DB::conn($cfg['db']);

  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  // Post + kapak yolunu birlikte çek
  $stmt = $pdo->prepare("
    SELECT p.*, m.path AS cover_url
    FROM posts p
    LEFT JOIN media m ON m.id = p.cover_media_id
    WHERE p.id=? LIMIT 1
  ");
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  $_csrf = \App\Core\Csrf::token($cfg['security']['csrf_key']);
  $langs = \App\Core\I18n::supported();

  \App\Core\View::render('admin/posts/edit', [
    'title' => 'Edit Post',
    'row' => $row,
    '_csrf' => $_csrf,
    'langs' => $langs,
  ]);
});

// Edit
$router->post('/admin/posts/edit', function () {
  \App\Core\Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';
  if (!\App\Core\Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'])) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }
  $pdo = \App\Core\DB::conn($cfg['db']);

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(400);
    echo "Invalid id";
    return;
  }

  $lang = $_POST['language_code'] ?? \App\Core\I18n::default();
  if (!\App\Core\I18n::isSupported($lang)) $lang = \App\Core\I18n::default();

  $slug   = trim((string)($_POST['slug'] ?? ''));
  $title  = trim((string)($_POST['title'] ?? ''));
  $excerpt = $_POST['excerpt'] ?? null;

  $section = trim((string)($_POST['section'] ?? ''));
  $section = ($section !== '') ? $section : null;

  $tagsCsv = trim((string)($_POST['tags'] ?? ''));
  $tagsArr = $tagsCsv === '' ? [] : array_filter(array_map('trim', explode(',', $tagsCsv)));
  $tagsCsv = $tagsCsv !== '' ? $tagsCsv : null;

  $cleanBody = \App\Core\Sanitizer::cleanHtml($_POST['body'] ?? '');

  // Kapak medya ID (opsiyonel)
  $coverMediaId = null;
  if (isset($_POST['cover_media_id']) && $_POST['cover_media_id'] !== '') {
    $coverMediaId = (int)$_POST['cover_media_id'];
    $chk = $pdo->prepare("SELECT id FROM media WHERE id=? LIMIT 1");
    $chk->execute([$coverMediaId]);
    if (!$chk->fetchColumn()) $coverMediaId = null;
  }

  $status = $_POST['status'] ?? 'draft';
  $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;

  $stmt = $pdo->prepare("UPDATE posts
     SET slug=?, title=?, excerpt=?, section=?, tags=?, body=?, status=?, published_at=?, language_code=?, cover_media_id=?
     WHERE id=?");
  $stmt->execute([
    $slug,
    $title,
    $excerpt,
    $section,
    $tagsCsv,
    $cleanBody,
    $status,
    $published_at,
    $lang,
    $coverMediaId,
    $id
  ]);

  \App\Core\Invalidation::purgeForPost($slug, $lang, $tagsArr, $section);
  header("Location: /admin/posts?lang=" . urlencode($lang));
  exit;
});
// Delete
// DELETE (tek kayıt)
$router->post('/admin/posts/delete', function () {
  Auth::requireRole(['admin', 'editor']);
  $cfg = require __DIR__ . '/config.php';
  if (!\App\Core\Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'])) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $pdo = DB::conn($cfg['db']);
  $id  = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(400);
    echo "Invalid ID";
    return;
  }

  // Purge için önce mevcut kaydın alanlarını al
  $stmt = $pdo->prepare("SELECT slug, language_code AS lang, tags, section FROM posts WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $row = $stmt->fetch();

  if (!$row) { // yoksa sessizce listeye dön
    header("Location: /admin/posts");
    exit;
  }

  $slug = (string)$row['slug'];
  $lang = (string)$row['lang'];
  $tags = [];
  if (!empty($row['tags'])) {
    $tags = array_filter(array_map('trim', explode(',', (string)$row['tags'])));
  }
  $section = $row['section'] !== null && $row['section'] !== '' ? (string)$row['section'] : null;

  $stmt = $pdo->prepare("SELECT translation_key FROM posts WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $trow = $stmt->fetch();
  if ($trow && !empty($trow['translation_key'])) {
    $tk = (string)$trow['translation_key'];
    // tüm diller:
    $pdo->prepare("DELETE FROM posts WHERE translation_key=?")->execute([$tk]);
    \App\Core\Invalidation::purgeAll(); // grup silinince tüm cache’i temizlemek daha güvenli
  } else {
    // Sil
    $del = $pdo->prepare("DELETE FROM posts WHERE id=?");
    $del->execute([$id]);
  }


  // Cache invalidation
  \App\Core\Invalidation::purgeForPost($slug, $lang, $tags, $section);

  header("Location: /admin/posts");
  exit;
});

// ---------- MEDIA ----------
$router->get('/admin/media', function () {
  Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $rows = $pdo->query("SELECT * FROM media WHERE kind='image' ORDER BY created_at DESC, id DESC LIMIT 200")->fetchAll();
  View::render('admin/media/index', ['title' => 'Media', 'rows' => $rows]);
});
// Media JSON (son 100 kayıt) – admin için
$router->get('/admin/media/json', function () {
  \App\Core\Auth::requireRole(['admin', 'editor', 'author']);
  header('Content-Type: application/json; charset=utf-8');
  $cfg = require __DIR__ . '/config.php';
  $pdo = \App\Core\DB::conn($cfg['db']);
  $stmt = $pdo->query("SELECT id, path, original_name, width, height, mime, created_at
                       FROM media
                       ORDER BY id DESC
                       LIMIT 100");
  $rows = $stmt->fetchAll();
  echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});

// YÜKLEME: /admin/media/upload (POST)  — DB'ye kaydeder

$router->post('/admin/media/upload', function () {
  \App\Core\Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';

  if (!\App\Core\Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'] ?? null)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF']);
    return;
  }

  if (empty($_FILES['file'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No file']);
    return;
  }

  $f = $_FILES['file'];

  try {
    // 1) Diske yaz + varyantları üret
    \App\Core\ImageKit::validateUpload($f);
    $origUrl   = \App\Core\ImageKit::moveOriginal($f);      // ör: /uploads/2025/09/abc.jpg
    $variants  = \App\Core\ImageKit::generateVariants($origUrl);

    // 2) Meta bilgileri hesapla
    $projectRoot = dirname(__DIR__, 1);
    $publicRoot  = $projectRoot . '/public';
    $absPath     = $publicRoot . $origUrl;                  // gerçek dosya yolu

    // Boyutlar
    [$w, $h] = \App\Core\Image::dimensions($absPath);       // Image::dimensions dosyanızda var

    // MIME + size
    $size = @filesize($absPath) ?: 0;
    $fi   = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($absPath) ?: ($f['type'] ?? 'application/octet-stream');

    // Orijinal isim (temizle)
    $origName = \App\Core\Util::sanitizeFileName($f['name'] ?? basename($absPath));

    // 3) DB'ye yaz
    $pdo = \App\Core\DB::conn($cfg['db']);
    $stmt = $pdo->prepare(
      "INSERT INTO media (kind, original_name, path, mime, size, width, height, uploaded_by)
       VALUES ('image',?,?,?,?,?,?,?)"
    );
    $stmt->execute([$origName, $origUrl, $mime, (int)$size, (int)$w, (int)$h, \App\Core\Auth::id()]);
    $id = (int)$pdo->lastInsertId();

    // 4) JSON cevap
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok'       => true,
      'id'       => $id,
      'original' => $origUrl,
      'variants' => $variants
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  } catch (\Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
});

// /admin/media/delete (POST)
$router->post('/admin/media/delete', function () {
  \App\Core\Auth::requireRole(['admin', 'editor']);
  $cfg = require __DIR__ . '/config.php';
  if (!\App\Core\Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'] ?? null)) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(400);
    echo "Bad id";
    return;
  }

  $pdo = \App\Core\DB::conn($cfg['db']);
  $row = $pdo->prepare("SELECT path FROM media WHERE id=? LIMIT 1");
  $row->execute([$id]);
  $m = $row->fetch();

  if ($m) {
    $projectRoot = dirname(__DIR__, 1);
    $abs = $projectRoot . '/public' . $m['path'];
    if (is_file($abs)) @unlink($abs);
    // varyantları isterseniz ImageKit tarafında adlandırma standardına göre silebilirsiniz
    $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
  }
  header('Location: /admin/media');
  exit;
});


// CKEditor upload
$router->post('/admin/media/ckeditor-upload', function () {
  Auth::requireRole(['admin', 'editor', 'author']);
  $cfg = require __DIR__ . '/config.php';

  $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
  $maxPostBytes  = \App\Core\Util::toBytes((string)ini_get('post_max_size'));
  if ($contentLength > 0 && $maxPostBytes > 0 && $contentLength > $maxPostBytes) {
    http_response_code(413);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Payload too large']]);
    return;
  }

  $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  $maxSize = 10 * 1024 * 1024;

  $file = $_FILES['upload'] ?? null;
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'No file']]);
    return;
  }
  if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Too large']]);
    return;
  }
  $mime = $file['type'] ?? '';
  if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Invalid type']]);
    return;
  }

  $projectRoot = dirname(__DIR__, 1);
  $public = $projectRoot . '/public';
  $uploadBase = $public . '/uploads/' . date('Y') . '/' . date('m');
  \App\Core\Util::ensureDir($uploadBase);

  $origName = \App\Core\Util::sanitizeFileName($file['name'] ?? 'image');
  $ext  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
  $base = \App\Core\Util::slugify(pathinfo($origName, PATHINFO_FILENAME));
  if ($base === 'n-a') $base = 'img-' . \App\Core\Util::randomString(6);
  $unique = $base . '-' . date('YmdHis') . '-' . random_int(1000, 9999);

  $destAbs = $uploadBase . '/' . $unique . '.' . $ext;
  if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Save failed']]);
    return;
  }

  $publicUrl = '/uploads/' . date('Y') . '/' . date('m') . '/' . basename($destAbs);
  $pdo = DB::conn($cfg['db']);
  [$w, $h] = \App\Core\Image::dimensions($destAbs);
  $size = @filesize($destAbs) ?: 0;
  $stmt = $pdo->prepare("INSERT INTO media (kind, original_name, path, mime, size, width, height, uploaded_by) VALUES ('image',?,?,?,?,?,?,?)");
  $stmt->execute([$origName, $publicUrl, $mime, $size, $w, $h, Auth::id()]);

  header('Content-Type: application/json');
  echo json_encode(['url' => $publicUrl]);
});

// ---------- PUBLIC POST ----------
$router->get('/post', function () {
  $slug = trim($_GET['slug'] ?? '');
  if ($slug === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  $cfg = require __DIR__ . '/config.php';
  $pdo = \App\Core\DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();

  $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug=? AND status='published' AND language_code=? LIMIT 1");
  $stmt->execute([$slug, $lang]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  \App\Core\View::render('post', [
    'title' => $row['title'],
    'post'  => $row,
    'lang'  => $lang,
    'layout' => 'layout',   // <- açıkça belirt
  ]);
});


// --- SECTION LİSTESİ ---
$router->get('/section', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();

  // /section/{name} yakalama (path’ten)
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
  $section = null;
  if (preg_match('#^/section/([^/]+)$#', $path, $m)) $section = urldecode($m[1]);
  if (!$section) $section = trim($_GET['name'] ?? '');

  if ($section === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  $stmt = $pdo->prepare("SELECT id, slug, title, excerpt, published_at FROM posts WHERE status='published' AND language_code=? AND section=? ORDER BY COALESCE(published_at, created_at) DESC LIMIT 50");
  $stmt->execute([$lang, $section]);
  $rows = $stmt->fetchAll();
  View::render('listing', [
    'title' => 'Section: ' . $section,
    'rows'  => $rows,
    'lang'  => $lang,
    'heading' => 'Section: ' . $section
  ]);
});

// --- SECTION RSS ---
$router->get('/section/feed.xml', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();
  $section = trim($_GET['name'] ?? '');
  // path: /section/{name}/feed.xml
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
  if (preg_match('#^/section/([^/]+)/feed\.xml$#', $path, $m)) $section = urldecode($m[1]);

  if ($section === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  header('Content-Type: application/rss+xml; charset=utf-8');
  header('Cache-Control: public, max-age=600, stale-while-revalidate=600');

  $base = rtrim($cfg['app']['url'], '/');
  $siteTitle = $cfg['app']['name'] . " — {$lang} / {$section}";

  $stmt = $pdo->prepare("SELECT slug, title, excerpt, body, COALESCE(published_at, created_at) AS ts
                         FROM posts WHERE status='published' AND language_code=? AND section=?
                         ORDER BY ts DESC LIMIT 50");
  $stmt->execute([$lang, $section]);
  $items = $stmt->fetchAll();

  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\">\n<channel>\n";
  echo "<title>" . htmlspecialchars($siteTitle) . "</title>\n";
  echo "<link>{$base}/{$lang}/?section=" . rawurlencode($section) . "</link>\n";
  echo "<description>Latest {$section} posts</description>\n";
  echo "<language>{$lang}</language>\n";
  foreach ($items as $it) {
    $link = "{$base}/{$lang}/post?slug=" . htmlspecialchars($it['slug'], ENT_QUOTES, 'UTF-8');
    $pubDate = date(DATE_RSS, strtotime($it['ts']));
    $desc = $it['excerpt'] ?: mb_strimwidth(strip_tags($it['body']), 0, 300, '…', 'UTF-8');
    echo "<item>\n<title>" . htmlspecialchars($it['title']) . "</title>\n<link>{$link}</link>\n<guid isPermaLink=\"true\">{$link}</guid>\n<pubDate>{$pubDate}</pubDate>\n<description>" . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . "</description>\n</item>\n";
  }
  echo "</channel>\n</rss>";
});

// --- TAG LİSTESİ ---
$router->get('/tag', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();

  // /tag/{tag}
  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
  $tag = null;
  if (preg_match('#^/tag/([^/]+)$#', $path, $m)) $tag = urldecode($m[1]);
  if (!$tag) $tag = trim($_GET['name'] ?? '');

  if ($tag === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  // Basit LIKE araması (etiket CSV alanında)
  $stmt = $pdo->prepare("SELECT id, slug, title, excerpt, published_at
                         FROM posts
                         WHERE status='published' AND language_code=? AND (FIND_IN_SET(?, REPLACE(tags, ' ', '')) OR tags LIKE ?)
                         ORDER BY COALESCE(published_at, created_at) DESC LIMIT 50");
  $stmt->execute([$lang, $tag, '%' . $tag . '%']);
  $rows = $stmt->fetchAll();
  View::render('listing', [
    'title' => 'Tag: ' . $tag,
    'rows'  => $rows,
    'lang'  => $lang,
    'heading' => '#' . $tag
  ]);
});

// --- TAG RSS ---
$router->get('/tag/feed.xml', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();

  $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
  $tag = trim($_GET['name'] ?? '');
  if (preg_match('#^/tag/([^/]+)/feed\.xml$#', $path, $m)) $tag = urldecode($m[1]);

  if ($tag === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }

  header('Content-Type: application/rss+xml; charset=utf-8');
  header('Cache-Control: public, max-age=600, stale-while-revalidate=600');

  $base = rtrim($cfg['app']['url'], '/');
  $siteTitle = $cfg['app']['name'] . " — {$lang} / #{$tag}";

  $stmt = $pdo->prepare("SELECT slug, title, excerpt, body, COALESCE(published_at, created_at) AS ts
                         FROM posts
                         WHERE status='published' AND language_code=?
                           AND (FIND_IN_SET(?, REPLACE(tags, ' ', '')) OR tags LIKE ?)
                         ORDER BY ts DESC LIMIT 50");
  $stmt->execute([$lang, $tag, '%' . $tag . '%']);
  $items = $stmt->fetchAll();

  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\">\n<channel>\n";
  echo "<title>" . htmlspecialchars($siteTitle) . "</title>\n";
  echo "<link>{$base}/{$lang}/tag/" . rawurlencode($tag) . "</link>\n";
  echo "<description>Latest posts tagged #{$tag}</description>\n";
  echo "<language>{$lang}</language>\n";
  foreach ($items as $it) {
    $link = "{$base}/{$lang}/post?slug=" . htmlspecialchars($it['slug'], ENT_QUOTES, 'UTF-8');
    $pubDate = date(DATE_RSS, strtotime($it['ts']));
    $desc = $it['excerpt'] ?: mb_strimwidth(strip_tags($it['body']), 0, 300, '…', 'UTF-8');
    echo "<item>\n<title>" . htmlspecialchars($it['title']) . "</title>\n<link>{$link}</link>\n<guid isPermaLink=\"true\">{$link}</guid>\n<pubDate>{$pubDate}</pubDate>\n<description>" . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . "</description>\n</item>\n";
  }
  echo "</channel>\n</rss>";
});

// --- AMP post ---
$router->get('/amp/post', function () {
  // Dil öneki index.php tarafından düşürülmüş durumda; I18n::get() geçerli.
  $slug = trim($_GET['slug'] ?? '');
  if ($slug === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();
  $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug=? AND status='published' AND language_code=? LIMIT 1");
  $stmt->execute([$slug, $lang]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(404);
    echo "Not Found";
    return;
  }
  View::render('post.amp', ['title' => $row['title'], 'post' => $row, 'lang' => $lang]);
});

// --- mweb post ---
$router->get('/m/post', function () {
  $slug = trim($_GET['slug'] ?? '');
  if ($slug === '') {
    http_response_code(404);
    echo "Not Found";
    return;
  }
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $lang = \App\Core\I18n::get();
  $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug=? AND status='published' AND language_code=? LIMIT 1");
  $stmt->execute([$slug, $lang]);
  $row = $stmt->fetch();
  if (!$row) {
    http_response_code(404);
    echo "Not Found";
    return;
  }
  View::render('post.mobile', ['title' => $row['title'], 'post' => $row, 'lang' => $lang]);
});

/* ---------- Users: list ---------- */
$router->get('/admin/users', function () {
  if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
  }
  Auth::requireRole(['admin']);

  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);

  $q = trim($_GET['q'] ?? '');
  if ($q !== '') {
    $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name
                           FROM users u JOIN roles r ON r.id=u.role_id
                           WHERE u.email LIKE ? OR u.full_name LIKE ?
                           ORDER BY u.id DESC LIMIT 200");
    $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
  } else {
    $stmt = $pdo->query("SELECT u.*, r.name AS role_name
                         FROM users u JOIN roles r ON r.id=u.role_id
                         ORDER BY u.id DESC LIMIT 200");
  }
  $rows = $stmt->fetchAll();
  $_csrf = Csrf::token();
  $me_id = Auth::id();
  View::render('admin/users/index', compact('rows', '_csrf', 'me_id'));
});

/* ---------- Users: create (GET) ---------- */
$router->get('/admin/users/create', function () {
  if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
  }
  Auth::requireRole(['admin']);

  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $roles = $pdo->query("SELECT id,name FROM roles ORDER BY id")->fetchAll();
  $_csrf = Csrf::token();
  View::render('admin/users/create', compact('roles', '_csrf'));
});

/* ---------- Users: create (POST) ---------- */
$router->post('/admin/users/create', function () {
  if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
  }
  Auth::requireRole(['admin']);

  $cfg = require __DIR__ . '/config.php';
  if (!Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'])) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $pdo = DB::conn($cfg['db']);

  $email = trim($_POST['email'] ?? '');
  $full  = trim($_POST['full_name'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $role  = (int)($_POST['role_id'] ?? 0);
  $act   = (int)($_POST['is_active'] ?? 1);

  if ($email === '' || $pass === '' || $role <= 0) {
    http_response_code(422);
    echo "Missing fields";
    return;
  }

  // email unique kontrol
  $ex = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $ex->execute([$email]);
  if ($ex->fetch()) {
    http_response_code(409);
    echo "Email already exists";
    return;
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO users (email,password_hash,full_name,role_id,is_active) VALUES (?,?,?,?,?)");
  $stmt->execute([$email, $hash, $full, $role, $act]);

  header('Location: /admin/users');
  exit;
});

/* ---------- Users: edit (GET) ---------- */
$router->get('/admin/users/edit', function () {
  if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
  }
  Auth::requireRole(['admin']);

  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(404);
    echo "Not found";
    return;
  }

  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $row = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
  $row->execute([$id]);
  $row = $row->fetch();

  $roles = $pdo->query("SELECT id,name FROM roles ORDER BY id")->fetchAll();
  $_csrf = Csrf::token();

  View::render('admin/users/edit', compact('row', 'roles', '_csrf'));
});

/* ---------- Users: edit (POST) ---------- */
$router->post('/admin/users/edit', function () {
  if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
  }
  Auth::requireRole(['admin']);

  $cfg = require __DIR__ . '/config.php';
  if (!Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'])) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $pdo = DB::conn($cfg['db']);

  $id    = (int)($_POST['id'] ?? 0);
  $email = trim($_POST['email'] ?? '');
  $full  = trim($_POST['full_name'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $role  = (int)($_POST['role_id'] ?? 0);
  $act   = (int)($_POST['is_active'] ?? 1);

  if ($id <= 0 || $email === '' || $role <= 0) {
    http_response_code(422);
    echo "Missing fields";
    return;
  }

  // e-mail başka kullanıcıda mı?
  $ex = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
  $ex->execute([$email, $id]);
  if ($ex->fetch()) {
    http_response_code(409);
    echo "Email already in use";
    return;
  }

  if ($pass !== '') {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET email=?, full_name=?, password_hash=?, role_id=?, is_active=? WHERE id=?");
    $stmt->execute([$email, $full, $hash, $role, $act, $id]);
  } else {
    $stmt = $pdo->prepare("UPDATE users SET email=?, full_name=?, role_id=?, is_active=? WHERE id=?");
    $stmt->execute([$email, $full, $role, $act, $id]);
  }

  header('Location: /admin/users');
  exit;
});

/* ---------- Users: delete (GET link with CSRF) ---------- */
$router->get('/admin/users/delete', function () {
  if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
  }
  Auth::requireRole(['admin']);

  $cfg = require __DIR__ . '/config.php';
  if (!Csrf::check($_GET['_csrf'] ?? '', $cfg['security']['csrf_key'])) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    http_response_code(404);
    echo "Not found";
    return;
  }
  if ($id === (int)Auth::id()) {
    http_response_code(400);
    echo "You cannot delete yourself.";
    return;
  }

  $pdo = DB::conn($cfg['db']);
  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);

  header('Location: /admin/users');
  exit;
});

/* ====== Forgot (GET) ====== */
$router->get('/auth/forgot', function () {
  $_csrf = Csrf::token();
  View::render('auth/forgot', ['_csrf' => $_csrf]);
});

/* ====== Forgot (POST) -> reset link üret ====== */
$router->post('/auth/forgot', function () {
  $cfg = require __DIR__ . '/config.php';
  if (!Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'] ?? null)) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $email = trim($_POST['email'] ?? '');
  $_csrf = Csrf::token(); // ekranda tekrar gösterelim
  $pdo = DB::conn($cfg['db']);

  // Kullanıcı var mı?
  $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE email=? AND is_active=1 LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  // Güvenlik gereği: “var/yok” ayrımı vermeden her durumda başarı mesajı göster.
  $msg = 'If this email exists, a reset link has been sent. Please check your inbox.';

  // Varsa token oluşturup e-postayı log’a yaz
  if ($u) {
    // Eski tokenları opsiyonel olarak geçersiz kıl (kullanılmamışları da)
    $pdo->prepare("DELETE FROM password_resets WHERE user_id=? OR expires_at < NOW()")->execute([(int)$u['id']]);

    $token = bin2hex(random_bytes(32)); // 64 char
    $ttlMinutes = 30;
    $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL {$ttlMinutes} MINUTE))")
      ->execute([(int)$u['id'], $token]);

    $base = rtrim($cfg['app']['url'], '/');
    $link = $base . '/auth/reset?token=' . urlencode($token);

    $html = "<p>Hello " . htmlspecialchars($u['full_name'] ?: $u['email']) . ",</p>"
      . "<p>Use the link below to set a new password (valid for {$ttlMinutes} minutes):</p>"
      . "<p><a href=\"{$link}\">{$link}</a></p>"
      . "<p>If you didn’t request this, you can ignore this email.</p>";

    $to = $u['email'];
    $subj = 'Password reset for E2CE';
    Mail::send($to, $subj, $html);
  }

  View::render('auth/forgot', ['_csrf' => $_csrf, 'msg' => $msg]);
});

/* ====== Reset (GET) – token doğrula ve form göster ====== */
$router->get('/auth/reset', function () {
  $cfg = require __DIR__ . '/config.php';
  $pdo = DB::conn($cfg['db']);
  $token = trim($_GET['token'] ?? '');

  $error = null;
  if ($token === '') {
    $error = 'Invalid or missing token.';
  } else {
    $stmt = $pdo->prepare("SELECT pr.id, pr.user_id, u.email FROM password_resets pr JOIN users u ON u.id=pr.user_id
                           WHERE pr.token=? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                           LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) $error = 'Link expired or already used.';
  }

  $_csrf = Csrf::token();
  if ($error) {
    View::render('auth/reset', ['error' => $error, '_csrf' => $_csrf, 'token' => $token]);
  } else {
    View::render('auth/reset', ['_csrf' => $_csrf, 'token' => $token]);
  }
});

/* ====== Reset (POST) – parolayı güncelle ====== */
$router->post('/auth/reset', function () {
  $cfg = require __DIR__ . '/config.php';
  if (!Csrf::check($_POST['_csrf'] ?? '', $cfg['security']['csrf_key'] ?? null)) {
    http_response_code(400);
    echo "Invalid CSRF";
    return;
  }

  $pdo = DB::conn($cfg['db']);
  $token = trim($_POST['token'] ?? '');
  $p1 = (string)($_POST['password'] ?? '');
  $p2 = (string)($_POST['password2'] ?? '');

  $_csrf = Csrf::token();

  if ($token === '') {
    View::render('auth/reset', ['error' => 'Invalid token.', '_csrf' => $_csrf, 'token' => $token]);
    return;
  }
  if ($p1 === '' || $p1 !== $p2 || strlen($p1) < 6) {
    View::render('auth/reset', ['error' => 'Passwords must match and be at least 6 characters.', '_csrf' => $_csrf, 'token' => $token]);
    return;
  }

  // Token doğrula
  $stmt = $pdo->prepare("SELECT pr.id, pr.user_id FROM password_resets pr
                         WHERE pr.token=? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                         LIMIT 1");
  $stmt->execute([$token]);
  $row = $stmt->fetch();
  if (!$row) {
    View::render('auth/reset', ['error' => 'Link expired or already used.', '_csrf' => $_csrf, 'token' => $token]);
    return;
  }

  // Parola güncelle
  $hash = password_hash($p1, PASSWORD_DEFAULT);
  $pdo->beginTransaction();
  try {
    $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, (int)$row['user_id']]);
    $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
    $pdo->commit();
  } catch (\Throwable $e) {
    $pdo->rollBack();
    View::render('auth/reset', ['error' => 'Unexpected error. Try again.', '_csrf' => $_csrf, 'token' => $token]);
    return;
  }

  View::render('auth/reset', ['ok' => 'Your password has been updated. You can now log in.', '_csrf' => $_csrf]);
});
