#!/usr/bin/env bash
set -euo pipefail
wp_root="$RUNNER_TEMP/kmd-wp"
storage="$RUNNER_TEMP/kmd-private"
manifest="$RUNNER_TEMP/kmd-http.json"
base=http://localhost:8080
mkdir -m 700 "$storage"
wp --path="$wp_root" option update home "$base"
wp --path="$wp_root" option update siteurl "$base"
wp --path="$wp_root" config set KMD_PRIVATE_DIR "$storage"
KMD_INTEGRATION_TEST=1 KMD_HTTP_MANIFEST="$manifest" wp --path="$wp_root" eval-file tests/http-fixtures.php
php -S localhost:8080 -t "$wp_root" >"$RUNNER_TEMP/kmd-http.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true' EXIT
for n in {1..30}; do
    if curl -fsS "$base/wp-login.php" >/dev/null; then break; fi
    sleep 1
done
value() { jq -r "$1" "$manifest"; }
member="$(value .cookie_name)=$(value .member_cookie)"
denied="$(value .cookie_name)=$(value .denied_cookie)"
experience="$(value .experience)"
lesson="$(value .lesson)"
future="$(value .future)"
expired="$(value .expired)"
expired_lesson="$(value .expired_lesson)"
tmp="$(mktemp -d)"
portal() {
    curl -fsS -D "$tmp/headers" -o "$tmp/json" "$@" --data "action=kmd_portal&experience=$experience" "$base/wp-admin/admin-ajax.php"
    jq -er '.success == true' "$tmp/json" >/dev/null
    grep -qi 'Cache-Control:.*no-store' "$tmp/headers"
    jq -r '.data.html' "$tmp/json" >"$tmp/body"
}
portal
! grep -q 'KMD_HTTP_.*_SECRET' "$tmp/body" || exit 1
portal --cookie "$member"
grep -q 'KMD_HTTP_WELCOME_SECRET' "$tmp/body"
grep -q 'KMD_HTTP_RELEASED_SECRET' "$tmp/body"
! grep -q 'KMD_HTTP_FUTURE_SECRET' "$tmp/body" || exit 1
portal --cookie "$denied"
! grep -q 'KMD_HTTP_.*_SECRET' "$tmp/body" || exit 1
portal
! grep -q 'KMD_HTTP_.*_SECRET' "$tmp/body" || exit 1
echo 'PASS: authenticated portal and no-store, denied and anonymous responses'
curl -fsS --cookie "$member" "$base/?page_id=$(value .page)" >"$tmp/page"
grep -q 'data-kmd-experience' "$tmp/page"
! grep -q 'KMD_HTTP_.*_SECRET' "$tmp/page" || exit 1
echo 'PASS: public page shell does not contain protected text'
curl -sS "$base/?p=$lesson&post_type=kmd_lesson" >"$tmp/direct"
! grep -q 'KMD_HTTP_RELEASED_SECRET' "$tmp/direct" || exit 1
echo 'PASS: direct post request does not expose lesson'
media() {
    local id="$1" expected="$2"; shift 2
    local key code
    key="$(value ".keys[\"$id\"]")"
    code="$(curl -sS -D "$tmp/headers" -o "$tmp/media" -w '%{http_code}' "$@" "$base/wp-admin/admin-post.php?action=kmd_media&lesson=$id&file=$key")"
    if [[ "$code" != "$expected" ]]; then echo "Expected $expected, got $code"; cat "$tmp/headers"; exit 1; fi
    grep -qi 'Cache-Control:.*no-store' "$tmp/headers"
}
media "$lesson" 200 --cookie "$member"
[[ "$(cat "$tmp/media")" == KMD0123456789 ]]
media "$lesson" 206 --cookie "$member" -H 'Range: bytes=3-6'
[[ "$(cat "$tmp/media")" == 0123 ]]
grep -qi 'Content-Range: bytes 3-6/13' "$tmp/headers"
media "$lesson" 416 --cookie "$member" -H 'Range: bytes=100-120'
media "$lesson" 404
media "$lesson" 404 --cookie "$denied"
media "$future" 404 --cookie "$member"
media "$expired_lesson" 404 --cookie "$member"
echo 'PASS: private file full response, byte ranges, and anonymous, unrelated, future and expired denial'
