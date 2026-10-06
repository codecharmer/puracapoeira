#!/usr/bin/env bash
# Seeds the local wp-env site after `wp-env start`. Idempotent: safe to run repeatedly.
# The content import skips posts edited in the admin; set PURA_SEED_FORCE=1 to overwrite them.
set -euo pipefail

cd "$(dirname "$0")/.."

wp() {
	npx wp-env run cli wp "$@"
}

echo "==> Site basics"
wp option update blogname "Pura Capoeira" >/dev/null
wp option update blogdescription "Escuela internacional de capoeira" >/dev/null
wp option update timezone_string "America/Mexico_City" >/dev/null
wp option update date_format 'j \d\e F \d\e Y' >/dev/null
wp option update time_format 'H:i' >/dev/null
wp option update blog_public 0 >/dev/null

echo "==> Language (es_MX)"
if ! wp language core is-installed es_MX >/dev/null 2>&1; then
	wp language core install es_MX >/dev/null || true
fi
wp site switch-language es_MX >/dev/null 2>&1 || wp language core activate es_MX >/dev/null 2>&1 || true

echo "==> Permalinks"
wp rewrite structure '/%postname%/' --hard >/dev/null

echo "==> Theme and plugins"
wp theme activate puracapoeira >/dev/null 2>&1 || true
wp plugin activate puracapoeira-core >/dev/null 2>&1 || true
wp plugin activate fluent-smtp >/dev/null 2>&1 || true

echo "==> Content import (if the theme importer is available)"
if wp cli has-command "pura-theme import" >/dev/null 2>&1; then
	if [ "${PURA_SEED_FORCE:-0}" = "1" ]; then
		wp pura-theme import all --force
	else
		wp pura-theme import all
	fi
else
	echo "    (wp pura-theme import not registered yet; skipping)"
fi

echo "==> Done. Site: http://localhost:8888  Admin: http://localhost:8888/wp-admin (admin / password)"
