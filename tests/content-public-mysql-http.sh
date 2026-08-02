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

./scripts/php-container -r 'require "/var/www/html/includes/web-database.php"; $pdo = getWebDatabaseConnection(); $visible = (string) $pdo->query("SELECT slug FROM contenido_articulos WHERE visible = 1 ORDER BY slug ASC LIMIT 1")->fetchColumn(); if ($visible === "") { fwrite(STDERR, "No visible slug\n"); exit(1); } $hidden = (string) $pdo->query("SELECT slug FROM contenido_articulos WHERE visible = 0 ORDER BY slug ASC LIMIT 1")->fetchColumn(); $toggled = 0; if ($hidden === "") { $hidden = $visible; $stmt = $pdo->prepare("UPDATE contenido_articulos SET visible = 0 WHERE slug = :slug"); $stmt->execute(["slug" => $hidden]); $toggled = 1; } $withoutTrivia = (string) $pdo->query("SELECT a.slug FROM contenido_articulos a LEFT JOIN contenido_trivias t ON t.articulo_id = a.id WHERE a.visible = 1 GROUP BY a.id HAVING COUNT(t.id) = 0 ORDER BY a.slug ASC LIMIT 1")->fetchColumn(); $withoutFact = (string) $pdo->query("SELECT a.slug FROM contenido_articulos a LEFT JOIN contenido_sabias_que s ON s.articulo_id = a.id WHERE a.visible = 1 GROUP BY a.id HAVING COUNT(s.id) = 0 ORDER BY a.slug ASC LIMIT 1")->fetchColumn(); echo "visible_slug=$visible\n"; echo "hidden_slug=$hidden\n"; echo "without_trivia_slug=$withoutTrivia\n"; echo "without_fact_slug=$withoutFact\n"; echo "toggled=$toggled\n";' > "$state_file"

# shellcheck disable=SC1090
source "$state_file"

status="$(curl -sS -o "$work_dir/index" -D "$work_dir/index-headers" -w '%{http_code}' "$base_url/contenidos.php")"
test "$status" = "200"
grep -q '<meta charset="utf-8">' "$work_dir/index"
grep -q "slug=$visible_slug" "$work_dir/index"
! grep -q "slug=$hidden_slug" "$work_dir/index"

status="$(curl -sS -o "$work_dir/visible-public" -D "$work_dir/visible-public-headers" -w '%{http_code}' "$base_url/contenido.php?slug=$visible_slug")"
test "$status" = "200"
grep -q '<meta charset="utf-8">' "$work_dir/visible-public"
! grep -q 'Contenido oculto' "$work_dir/visible-public"
! grep -q 'Editar artículo' "$work_dir/visible-public"
test "$(grep -c 'Ver otros temas' "$work_dir/visible-public")" = "2"
grep -q 'href="contenidos.php"' "$work_dir/visible-public"

status="$(curl -sS -o "$work_dir/hidden-public" -D "$work_dir/hidden-public-headers" -w '%{http_code}' "$base_url/contenido.php?slug=$hidden_slug")"
test "$status" = "404"
grep -q 'Contenido no encontrado' "$work_dir/hidden-public"
! grep -q 'Editar artículo' "$work_dir/hidden-public"

if runuser -u www-data -- true >/dev/null 2>&1; then
  session_id="$(runuser -u www-data -- php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-content-public-http-" . bin2hex(random_bytes(8))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
else
  session_id="$(docker exec -i --user www-data web-astro php -r 'require "/var/www/html/includes/store-admin-auth.php"; session_id("admin-content-public-http-" . bin2hex(random_bytes(8))); startStoreAdminSession(); $_SESSION[STORE_ADMIN_SESSION_KEY] = true; session_write_close(); echo session_id();')"
fi

status="$(curl -sS -H "Cookie: aquellas_lunas_admin=$session_id" -o "$work_dir/hidden-admin" -D "$work_dir/hidden-admin-headers" -w '%{http_code}' "$base_url/contenido.php?slug=$hidden_slug")"
test "$status" = "200"
grep -q 'Contenido oculto' "$work_dir/hidden-admin"
grep -q 'Editar artículo' "$work_dir/hidden-admin"
grep -q "href=\"admin/contenidos/?action=edit&amp;slug=$hidden_slug\"" "$work_dir/hidden-admin"
test "$(grep -c 'Ver otros temas' "$work_dir/hidden-admin")" = "1"

if [[ "$without_trivia_slug" != "" ]]; then
  status="$(curl -sS -o "$work_dir/without-trivia" -w '%{http_code}' "$base_url/contenido.php?slug=$without_trivia_slug")"
  test "$status" = "200"
fi

if [[ "$without_fact_slug" != "" ]]; then
  status="$(curl -sS -o "$work_dir/without-fact" -w '%{http_code}' "$base_url/contenido.php?slug=$without_fact_slug")"
  test "$status" = "200"
fi

printf 'content public mysql HTTP tests: ok\n'
