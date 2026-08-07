#!/usr/bin/env bash
set -euo pipefail

base_url="${1:-http://localhost}"
work_dir="$(mktemp -d)"
state_file="$work_dir/state.env"

cleanup() {
  if [[ -f "$state_file" ]]; then
    # shellcheck disable=SC1090
    source "$state_file"
    toggled="${toggled:-0}"
    hidden_slug_to_restore="${hidden_slug:-}"
    if [[ "$toggled" == "1" && "$hidden_slug_to_restore" != "" ]]; then
      ./scripts/php-container -r "require '/var/www/html/includes/web-database.php'; \$pdo = getWebDatabaseConnection(); \$stmt = \$pdo->prepare('UPDATE contenido_articulos SET visible = 1 WHERE slug = :slug'); \$stmt->execute(['slug' => '$hidden_slug_to_restore']);" >/dev/null
    fi
  fi
  rm -rf "$work_dir"
}

trap cleanup EXIT

./scripts/php-container -r 'require "/var/www/html/includes/web-database.php"; $pdo = getWebDatabaseConnection(); $visible = (string) $pdo->query("SELECT slug FROM contenido_articulos WHERE visible = 1 ORDER BY slug ASC LIMIT 1")->fetchColumn(); if ($visible === "") { fwrite(STDERR, "No visible slug\n"); exit(1); } $hidden = (string) $pdo->query("SELECT slug FROM contenido_articulos WHERE visible = 0 ORDER BY slug ASC LIMIT 1")->fetchColumn(); $toggled = 0; if ($hidden === "") { $stmt = $pdo->prepare("SELECT slug FROM contenido_articulos WHERE visible = 1 AND slug <> :visible ORDER BY slug ASC LIMIT 1"); $stmt->execute(["visible" => $visible]); $hidden = (string) $stmt->fetchColumn(); if ($hidden === "") { fwrite(STDERR, "No second visible slug\n"); exit(1); } $stmt = $pdo->prepare("UPDATE contenido_articulos SET visible = 0 WHERE slug = :slug"); $stmt->execute(["slug" => $hidden]); $toggled = 1; } $withoutTrivia = (string) $pdo->query("SELECT a.slug FROM contenido_articulos a LEFT JOIN contenido_trivias t ON t.articulo_id = a.id WHERE a.visible = 1 GROUP BY a.id HAVING COUNT(t.id) = 0 ORDER BY a.slug ASC LIMIT 1")->fetchColumn(); $withoutFact = (string) $pdo->query("SELECT a.slug FROM contenido_articulos a LEFT JOIN contenido_sabias_que s ON s.articulo_id = a.id WHERE a.visible = 1 GROUP BY a.id HAVING COUNT(s.id) = 0 ORDER BY a.slug ASC LIMIT 1")->fetchColumn(); echo "visible_slug=$visible\n"; echo "hidden_slug=$hidden\n"; echo "without_trivia_slug=$withoutTrivia\n"; echo "without_fact_slug=$withoutFact\n"; echo "toggled=$toggled\n";' > "$state_file"

# shellcheck disable=SC1090
source "$state_file"

status="$(curl -sS -o "$work_dir/index" -D "$work_dir/index-headers" -w '%{http_code}' "$base_url/contenidos.php")"
test "$status" = "200"
grep -q '<meta charset="utf-8">' "$work_dir/index"
grep -q '<meta name="robots" content="index, follow">' "$work_dir/index"
grep -q '<link rel="canonical" href="https://aquellaslunas.com.ar/astro/contenidos.php">' "$work_dir/index"
grep -q '"@type":"CollectionPage"' "$work_dir/index"
grep -q "slug=$visible_slug" "$work_dir/index"
! grep -q "slug=$hidden_slug" "$work_dir/index"

status="$(curl -sS -o "$work_dir/search" -D "$work_dir/search-headers" -w '%{http_code}' "$base_url/contenidos.php?q=eclipse")"
test "$status" = "200"
grep -q '<meta name="robots" content="noindex, follow">' "$work_dir/search"
grep -q '<link rel="canonical" href="https://aquellaslunas.com.ar/astro/contenidos.php">' "$work_dir/search"

status="$(curl -sS -o "$work_dir/visible-public" -D "$work_dir/visible-public-headers" -w '%{http_code}' "$base_url/contenido.php?slug=$visible_slug")"
test "$status" = "200"
grep -q '<meta charset="utf-8">' "$work_dir/visible-public"
grep -q '<meta name="robots" content="index, follow">' "$work_dir/visible-public"
canonical="https://aquellaslunas.com.ar/astro/contenido.php?slug=$visible_slug"
grep -Fq "<link rel=\"canonical\" href=\"$canonical\">" "$work_dir/visible-public"
grep -Fq "<meta property=\"og:url\" content=\"$canonical\">" "$work_dir/visible-public"
grep -q '"@type":"Article"' "$work_dir/visible-public"
grep -q '"@type":"BreadcrumbList"' "$work_dir/visible-public"
grep -Fq "\"url\":\"$canonical\"" "$work_dir/visible-public"
grep -Fq "\"@id\":\"$canonical\"" "$work_dir/visible-public"
grep -Eq '"dateModified":"[0-9]{4}-[0-9]{2}-[0-9]{2}"' "$work_dir/visible-public"
grep -q '<nav class="content-breadcrumb" aria-label="Ruta de navegación">' "$work_dir/visible-public"
grep -q '<li><a href="index.php">Inicio</a></li>' "$work_dir/visible-public"
grep -q '<li><a href="contenidos.php">Contenidos</a></li>' "$work_dir/visible-public"
grep -q '<li aria-current="page">' "$work_dir/visible-public"
test "$(grep -c '<h1' "$work_dir/visible-public")" = "1"
! grep -q 'Contenido oculto' "$work_dir/visible-public"
! grep -q 'Editar artículo' "$work_dir/visible-public"
test "$(grep -c 'Ver otros temas' "$work_dir/visible-public")" = "2"
grep -q 'href="contenidos.php"' "$work_dir/visible-public"

status="$(curl -sS -o "$work_dir/hidden-public" -D "$work_dir/hidden-public-headers" -w '%{http_code}' "$base_url/contenido.php?slug=$hidden_slug")"
test "$status" = "404"
grep -q 'Contenido no encontrado' "$work_dir/hidden-public"
grep -q '<meta name="robots" content="noindex, nofollow">' "$work_dir/hidden-public"
! grep -q 'Editar artículo' "$work_dir/hidden-public"

status="$(curl -sS -o "$work_dir/missing-public" -D "$work_dir/missing-public-headers" -w '%{http_code}' "$base_url/contenido.php?slug=articulo-inexistente-seo")"
test "$status" = "404"
grep -q '<meta name="robots" content="noindex, nofollow">' "$work_dir/missing-public"

if runuser -u www-data -- true >/dev/null 2>&1; then
  session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-content-public-http-" . bin2hex(random_bytes(8))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
else
  session_id="$(docker exec -i --user www-data web-astro php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-content-public-http-" . bin2hex(random_bytes(8))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
fi

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/hidden-admin" -D "$work_dir/hidden-admin-headers" -w '%{http_code}' "$base_url/contenido.php?slug=$hidden_slug")"
test "$status" = "200"
grep -q 'Contenido oculto' "$work_dir/hidden-admin"
grep -q '<meta name="robots" content="noindex, nofollow">' "$work_dir/hidden-admin"
grep -q 'Editar artículo' "$work_dir/hidden-admin"
grep -q "href=\"admin/contenidos/?action=edit&amp;slug=$hidden_slug\"" "$work_dir/hidden-admin"
test "$(grep -c 'Ver otros temas' "$work_dir/hidden-admin")" = "1"

status="$(curl -sS -o "$work_dir/sitemap" -D "$work_dir/sitemap-headers" -w '%{http_code}' "$base_url/sitemap.xml")"
test "$status" = "200"
grep -Eqi '^Content-Type: application/xml; charset=UTF-8' "$work_dir/sitemap-headers"
grep -Fq '<loc>https://aquellaslunas.com.ar/astro/contenidos.php</loc>' "$work_dir/sitemap"
grep -Fq "<loc>$canonical</loc>" "$work_dir/sitemap"
grep -Eq '<lastmod>[0-9]{4}-[0-9]{2}-[0-9]{2}</lastmod>' "$work_dir/sitemap"
! grep -Fq "contenido.php?slug=$hidden_slug" "$work_dir/sitemap"
! grep -Fq 'contenidos.php?q=' "$work_dir/sitemap"
! grep -Fq '/astro/admin/' "$work_dir/sitemap"
! grep -Fq '/astro/tests/' "$work_dir/sitemap"

if [[ "$without_trivia_slug" != "" ]]; then
  status="$(curl -sS -o "$work_dir/without-trivia" -w '%{http_code}' "$base_url/contenido.php?slug=$without_trivia_slug")"
  test "$status" = "200"
fi

if [[ "$without_fact_slug" != "" ]]; then
  status="$(curl -sS -o "$work_dir/without-fact" -w '%{http_code}' "$base_url/contenido.php?slug=$without_fact_slug")"
  test "$status" = "200"
fi

printf 'content public mysql HTTP tests: ok\n'
