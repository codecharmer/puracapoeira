#!/usr/bin/env bash
# Real photo uploads to the professors' editor, over HTTP, against the local environment
# (npx wp-env start). Signs in with wp-env's default administrator, which exists only there.
#
#   bash plugins/puracapoeira-core/tests/integration/editor-upload.sh
set -euo pipefail

BASE="${BASE:-http://localhost:8888}"
case "$BASE" in http://localhost:*|http://127.0.0.1:*) ;; *) echo "Local environment only."; exit 1 ;; esac

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
JAR="$TMP/cookies"
fail=0
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1 (got $2, want $3)"; fail=1; fi; }
field() { php -r '$d=json_decode(stream_get_contents(STDIN),true); $v=$d; foreach(explode(".",$argv[1]) as $k){ $v=is_array($v)&&array_key_exists($k,$v)?$v[$k]:null; } echo is_array($v)?count($v):(string)$v;' "$1"; }

php -r 'imagejpeg(imagecreatetruecolor(800,600),$argv[1]);' "$TMP/photo.jpg"
printf '<?php echo "no";' > "$TMP/fake.jpg"

curl -s -o /dev/null -c "$JAR" -b "$JAR" "$BASE/wp-login.php"
curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "log=admin" --data-urlencode "pwd=password" --data "wp-submit=Log+In&testcookie=1" "$BASE/wp-login.php"
NONCE="$(curl -s -b "$JAR" "$BASE/wp-admin/admin-ajax.php?action=rest-nonce")"
[ ${#NONCE} -ge 8 ] || { echo "Could not sign in to $BASE"; exit 1; }
SEDE="$(curl -s "$BASE/wp-json/wp/v2/pura_sede?per_page=1&_fields=id" | field 0.id)"
API="$BASE/wp-json/pura/v1/editor/sede/$SEDE"
post() { curl -s -b "$JAR" -H "X-WP-Nonce: $NONCE" -w '\n%{http_code}' "$@"; }
status() { tail -n1; }
body() { sed '$d'; }

before="$(curl -s -b "$JAR" -H "X-WP-Nonce: $NONCE" "$BASE/wp-json/pura/v1/editor/me" | field sedes.0.image.id)"

out="$(post -F "file=@$TMP/photo.jpg;type=image/jpeg" "$API/photo")"
check "a photo becomes the main photo" "$(echo "$out" | status)" 201
main="$(echo "$out" | body | field item.image.id)"
check "  and it is a new one" "$([ -n "$main" ] && [ "$main" != "$before" ] && echo yes)" yes

out="$(post -F "file=@$TMP/photo.jpg;type=image/jpeg" "$API/gallery")"
check "a photo is added to the gallery" "$(echo "$out" | status)" 201
count="$(echo "$out" | body | field item.gallery)"
version="$(echo "$out" | body | field item.modified)"
public="$(curl -s "$BASE/wp-json/wp/v2/pura_sede/$SEDE?_fields=gallery&nocache=$RANDOM" | field gallery)"
check "  and shows in the public sede data" "$public" "$count"

out="$(post -F "file=@$TMP/fake.jpg;type=image/jpeg" "$API/gallery")"
check "a file that is not an image is refused" "$(echo "$out" | status)" 400
out="$(curl -s -b "$JAR" -w '\n%{http_code}' -F "file=@$TMP/photo.jpg;type=image/jpeg" "$API/photo")"
check "an upload without the nonce is refused" "$(echo "$out" | status)" 401
out="$(curl -s -w '\n%{http_code}' -F "file=@$TMP/photo.jpg;type=image/jpeg" "$API/photo")"
check "an upload without a session is refused" "$(echo "$out" | status)" 401

out="$(post -X PUT -H 'Content-Type: application/json' -d "{\"base_modified\":\"$version\",\"ids\":[]}" "$API/gallery")"
check "the gallery is emptied again" "$(echo "$out" | status)$(echo "$out" | body | field item.gallery)" 2000

[ "$fail" = 0 ] && echo "all upload checks passed" || { echo "upload checks failed"; exit 1; }
