#!/usr/bin/env bash
set -Eeuo pipefail

API_REPO="${AXIRO_API_REPO:-$(cd "$(dirname "$0")/.." && pwd)}"
ADMIN_REPO="${AXIRO_ADMIN_REPO:-$(cd "$API_REPO/../axiro-base-admin" 2>/dev/null && pwd || true)}"
MBN_REPO="${AXIRO_MBN_REPO:-$(cd "$API_REPO/../mbn-react" 2>/dev/null && pwd || true)}"
OUT_DIR="${AXIRO_RELEASE_OUT_DIR:-$API_REPO/../release-artifacts}"
STAMP="${AXIRO_RELEASE_STAMP:-$(date +%Y%m%d-%H%M)}"
API_PORT="${AXIRO_RELEASE_API_PORT:-8000}"
ADMIN_PORT="${AXIRO_RELEASE_ADMIN_PORT:-5173}"
MBN_PORT="${AXIRO_RELEASE_MBN_PORT:-5174}"

[[ -d "$ADMIN_REPO" && -d "$MBN_REPO" ]] || { echo "Thiết lập AXIRO_ADMIN_REPO và AXIRO_MBN_REPO." >&2; exit 2; }
[[ "${APP_ENV:-testing}" == "testing" ]] || { echo "Release runner chỉ chạy với APP_ENV=testing." >&2; exit 2; }
[[ "${AXIRO_RELEASE_ALLOW_RESET:-0}" == "1" ]] || { echo "Thiết lập AXIRO_RELEASE_ALLOW_RESET=1 để cho phép reset database test." >&2; exit 2; }
[[ -n "${MBN_E2E_LOGIN:-}" && -n "${MBN_E2E_PASSWORD:-}" ]] || { echo "Thiết lập MBN_E2E_LOGIN/MBN_E2E_PASSWORD." >&2; exit 2; }

cleanup() {
  for pid in "${API_PID:-}" "${ADMIN_PID:-}" "${MBN_PID:-}"; do
    [[ -n "$pid" ]] && kill "$pid" 2>/dev/null || true
  done
}
trap cleanup EXIT

wait_url() {
  local url="$1"; local label="$2"
  for _ in {1..90}; do curl -fsS "$url" >/dev/null 2>&1 && return 0; sleep 1; done
  echo "Timeout chờ $label: $url" >&2; return 1
}

package_repo() {
  local repo="$1"; local name="$2"; local stage
  stage="$(mktemp -d)"
  rsync -a --delete \
    --exclude='.git' --include='.env.example' --include='.env.production.example' --exclude='.env' --exclude='.env.*' \
    --exclude='node_modules' --exclude='vendor' --exclude='dist' --exclude='build' \
    --exclude='storage/logs/*' --exclude='bootstrap/cache/*.php' \
    "$repo/" "$stage/"
  (cd "$stage" && zip -qr "$OUT_DIR/${name}-${STAMP}-clean.zip" .)
  unzip -tq "$OUT_DIR/${name}-${STAMP}-clean.zip" >/dev/null
  rm -rf "$stage"
}

mkdir -p "$OUT_DIR"

cd "$API_REPO"
composer install --no-interaction --prefer-dist
php artisan optimize:clear
php artisan migrate:fresh --seed --env=testing
composer check:release-package
composer check:maintainability
vendor/bin/pint --test
php artisan test
php artisan marketplace:integrity

cd "$ADMIN_REPO"
npm ci
npm run check:all
npm run build:analyze

cd "$MBN_REPO"
npm ci
npm run check:release-readiness
npm run check:ownership
npm run lint
npm run build:analyze

cd "$API_REPO"
php artisan serve --host=127.0.0.1 --port="$API_PORT" --env=testing >"$OUT_DIR/api-server.log" 2>&1 & API_PID=$!
cd "$ADMIN_REPO"
npm run dev -- --host 127.0.0.1 --port "$ADMIN_PORT" >"$OUT_DIR/admin-server.log" 2>&1 & ADMIN_PID=$!
cd "$MBN_REPO"
npm run dev -- --host 127.0.0.1 --port "$MBN_PORT" >"$OUT_DIR/mbn-server.log" 2>&1 & MBN_PID=$!
wait_url "http://127.0.0.1:$API_PORT/api/v1/marketplace/options" API
wait_url "http://127.0.0.1:$ADMIN_PORT/login" Admin
wait_url "http://127.0.0.1:$MBN_PORT" MBN

cd "$MBN_REPO"
MBN_E2E_URL="http://127.0.0.1:$MBN_PORT" \
MBN_E2E_AVATAR_PATH="${MBN_E2E_AVATAR_PATH:-$MBN_REPO/tests/fixtures/avatar-e2e.png}" \
npm run e2e:browser-core
MBN_E2E_ALLOW_MUTATION=1 \
MBN_E2E_API_URL="http://127.0.0.1:$API_PORT/api/v1" \
MBN_E2E_ADMIN_LOGIN="${MBN_E2E_ADMIN_LOGIN:-admin}" \
MBN_E2E_ADMIN_PASSWORD="${MBN_E2E_ADMIN_PASSWORD:-change-me}" \
npm run e2e:transactional-api

cd "$ADMIN_REPO"
ADMIN_E2E_URL="http://127.0.0.1:$ADMIN_PORT" \
ADMIN_E2E_LOGIN="${ADMIN_E2E_LOGIN:-admin}" \
ADMIN_E2E_PASSWORD="${ADMIN_E2E_PASSWORD:-change-me}" \
npm run e2e:browser-crud

if command -v soffice >/dev/null 2>&1; then
  for repo in "$ADMIN_REPO" "$API_REPO" "$MBN_REPO"; do
    docx="$repo/PRODUCT_ONLY_TRANSACTION_FIRST_NOTES-20260802.docx"
    [[ -f "$docx" ]] || continue
    out="$OUT_DIR/docx-$(basename "$repo")"
    mkdir -p "$out"
    soffice --headless --convert-to pdf --outdir "$out" "$docx" >/dev/null
  done
elif [[ "${AXIRO_RELEASE_REQUIRE_DOCX_QA:-0}" == "1" ]]; then
  echo "Thiếu soffice để render DOCX QA." >&2
  exit 2
fi

package_repo "$ADMIN_REPO" axiro-base-admin
package_repo "$API_REPO" axiro-base-api
package_repo "$MBN_REPO" mbn-react
printf 'Release PASS. Artifacts: %s\n' "$OUT_DIR"
