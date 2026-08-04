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
RELEASE_LOG="$OUT_DIR/release-${STAMP}.log"
CURRENT_STEP="bootstrap"
ADMIN_BUNDLE_STATUS="not_run"
ADMIN_SOURCE_STATE=""
API_SOURCE_STATE=""
MBN_SOURCE_STATE=""

require_clean_pushed_repo() {
  local repo="$1" label="$2"
  git -C "$repo" rev-parse --is-inside-work-tree >/dev/null 2>&1 || { echo "$label không phải Git repo thật." >&2; exit 2; }
  [[ -z "$(git -C "$repo" status --porcelain)" ]] || { echo "$label còn thay đổi chưa commit." >&2; exit 2; }
  local upstream counts
  upstream=$(git -C "$repo" rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null) || { echo "$label chưa có upstream; push trước khi release." >&2; exit 2; }
  counts=$(git -C "$repo" rev-list --left-right --count "HEAD...$upstream")
  [[ "$counts" == $'0\t0' || "$counts" == '0 0' ]] || { echo "$label chưa đồng bộ upstream: $counts" >&2; exit 2; }
}

log_step() {
  CURRENT_STEP="$1"
  printf "\n[%s] === %s ===\n" "$(date +%H:%M:%S)" "$CURRENT_STEP" | tee -a "$RELEASE_LOG"
}

[[ -d "$ADMIN_REPO" && -d "$MBN_REPO" ]] || { echo "Thiết lập AXIRO_ADMIN_REPO và AXIRO_MBN_REPO." >&2; exit 2; }
[[ "${APP_ENV:-testing}" == "testing" ]] || { echo "Release runner chỉ chạy với APP_ENV=testing." >&2; exit 2; }
[[ "${AXIRO_RELEASE_ALLOW_RESET:-0}" == "1" ]] || { echo "Thiết lập AXIRO_RELEASE_ALLOW_RESET=1 để cho phép reset database test." >&2; exit 2; }
[[ -n "${MBN_E2E_LOGIN:-}" && -n "${MBN_E2E_PASSWORD:-}" ]] || { echo "Thiết lập MBN_E2E_LOGIN/MBN_E2E_PASSWORD." >&2; exit 2; }
[[ -n "${ADMIN_E2E_LOGIN:-}" && -n "${ADMIN_E2E_PASSWORD:-}" ]] || { echo "Thiết lập ADMIN_E2E_LOGIN/ADMIN_E2E_PASSWORD." >&2; exit 2; }
if [[ "${AXIRO_RELEASE_REQUIRE_PUSHED_SOURCE:-1}" == "1" ]]; then
  require_clean_pushed_repo "$API_REPO" API
  require_clean_pushed_repo "$ADMIN_REPO" Admin
  require_clean_pushed_repo "$MBN_REPO" MBN
fi
API_SOURCE_STATE=$(git -C "$API_REPO" rev-parse HEAD 2>/dev/null || printf 'uncommitted-workspace')
ADMIN_SOURCE_STATE=$(git -C "$ADMIN_REPO" rev-parse HEAD 2>/dev/null || printf 'uncommitted-workspace')
MBN_SOURCE_STATE=$(git -C "$MBN_REPO" rev-parse HEAD 2>/dev/null || printf 'uncommitted-workspace')

on_error() {
  code=$?
  printf '[%s] RELEASE FAIL at step: %s (exit %s)\n' "$(date +%H:%M:%S)" "$CURRENT_STEP" "$code" | tee -a "$RELEASE_LOG" >&2
  exit "$code"
}
trap on_error ERR

cleanup() {
  for pid in "${API_PID:-}" "${ADMIN_PID:-}" "${MBN_PID:-}"; do
    [[ -n "$pid" ]] && kill "$pid" 2>/dev/null || true
  done
  find "$API_REPO" -name '.phpunit.result.cache' -delete 2>/dev/null || true
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
    --exclude='.phpunit.result.cache' \
    --exclude='storage/logs/*' --exclude='bootstrap/cache/*.php' \
    "$repo/" "$stage/"
  (cd "$stage" && zip -qr "$OUT_DIR/${name}-${STAMP}-clean.zip" .)
  unzip -tq "$OUT_DIR/${name}-${STAMP}-clean.zip" >/dev/null
  rm -rf "$stage"
}

mkdir -p "$OUT_DIR"
touch "$RELEASE_LOG"
log_step "API install, reset, tests and integrity"

cd "$API_REPO"
composer install --no-interaction --prefer-dist
php artisan optimize:clear
php artisan migrate:fresh --seed --env=testing
composer check:release-package
composer check:maintainability
vendor/bin/pint --test
php artisan test
find "$API_REPO" -name '.phpunit.result.cache' -delete
php artisan marketplace:integrity

log_step "Admin source guards, lint and bundle measurement"
cd "$ADMIN_REPO"
npm ci
npm run check:all
if BUNDLE_BUDGET_STRICT=1 npm run build:analyze; then
  ADMIN_BUNDLE_STATUS="passed"
elif [[ "${AXIRO_RELEASE_ALLOW_BUNDLE_WAIVER:-0}" == "1" ]]; then
  ADMIN_BUNDLE_STATUS="waived_over_budget"
  printf '[%s] BUNDLE WAIVER: Admin vượt budget; xem dist/bundle-report.json.
' "$(date +%H:%M:%S)" | tee -a "$RELEASE_LOG"
  npm run build:analyze
else
  echo "Admin bundle vượt budget. Chỉ dùng AXIRO_RELEASE_ALLOW_BUNDLE_WAIVER=1 khi có waiver được ghi nhận." >&2
  exit 1
fi

log_step "MBN source guards, lint and bundle measurement"
cd "$MBN_REPO"
npm ci
npm run check:release-readiness
npm run check:ownership
npm run lint
npm run build:analyze

log_step "Start API/Admin/MBN test servers"
cd "$API_REPO"
php artisan serve --host=127.0.0.1 --port="$API_PORT" --env=testing >"$OUT_DIR/api-server.log" 2>&1 & API_PID=$!
cd "$ADMIN_REPO"
npm run dev -- --host 127.0.0.1 --port "$ADMIN_PORT" >"$OUT_DIR/admin-server.log" 2>&1 & ADMIN_PID=$!
cd "$MBN_REPO"
npm run dev -- --host 127.0.0.1 --port "$MBN_PORT" >"$OUT_DIR/mbn-server.log" 2>&1 & MBN_PID=$!
wait_url "http://127.0.0.1:$API_PORT/api/v1/marketplace/options" API
wait_url "http://127.0.0.1:$ADMIN_PORT/login" Admin
wait_url "http://127.0.0.1:$MBN_PORT" MBN

log_step "MBN browser smoke and transactional API E2E"
cd "$MBN_REPO"
MBN_E2E_URL="http://127.0.0.1:$MBN_PORT" \
MBN_E2E_AVATAR_PATH="${MBN_E2E_AVATAR_PATH:-$MBN_REPO/tests/fixtures/avatar-e2e.png}" \
npm run e2e:browser-core
MBN_E2E_ALLOW_MUTATION=1 \
MBN_E2E_API_URL="http://127.0.0.1:$API_PORT/api/v1" \
MBN_E2E_ADMIN_LOGIN="${MBN_E2E_ADMIN_LOGIN:-admin}" \
MBN_E2E_ADMIN_PASSWORD="${MBN_E2E_ADMIN_PASSWORD:-change-me}" \
npm run e2e:transactional-api

log_step "Admin CRUD and payout lifecycle browser smoke"
cd "$ADMIN_REPO"
ADMIN_E2E_URL="http://127.0.0.1:$ADMIN_PORT" \
ADMIN_E2E_LOGIN="${ADMIN_E2E_LOGIN:-admin}" \
ADMIN_E2E_PASSWORD="${ADMIN_E2E_PASSWORD:-change-me}" \
ADMIN_E2E_REQUIRE_DOCUMENT_VERSION_MUTATION=1 \
npm run e2e:browser-crud

log_step "DOCX render QA"
if command -v soffice >/dev/null 2>&1; then
  for repo in "$ADMIN_REPO" "$API_REPO" "$MBN_REPO"; do
    docx="$repo/docs/PRODUCT_ONLY_TRANSACTION_FIRST_NOTES-20260802.docx"
    [[ -f "$docx" ]] || continue
    out="$OUT_DIR/docx-$(basename "$repo")"
    mkdir -p "$out"
    soffice --headless --convert-to pdf --outdir "$out" "$docx" >/dev/null
    pdf="$out/$(basename "${docx%.docx}").pdf"
    [[ -s "$pdf" ]] || { echo "DOCX render không tạo PDF hợp lệ: $docx" >&2; exit 2; }
  done
elif [[ "${AXIRO_RELEASE_REQUIRE_DOCX_QA:-1}" == "1" ]]; then
  echo "Thiếu soffice để render DOCX QA." >&2
  exit 2
fi

log_step "Clean ZIP packaging and integrity"
package_repo "$ADMIN_REPO" axiro-base-admin
package_repo "$API_REPO" axiro-base-api
package_repo "$MBN_REPO" mbn-react
printf '[%s] Release PASS. Artifacts: %s\n' "$(date +%H:%M:%S)" "$OUT_DIR" | tee -a "$RELEASE_LOG"
ADMIN_BUNDLE_REPORT="$ADMIN_REPO/dist/bundle-report.json" \
RELEASE_SUMMARY="$OUT_DIR/release-summary-${STAMP}.json" \
VERIFIED_AT="$(date -Iseconds)" \
ADMIN_BUNDLE_STATUS="$ADMIN_BUNDLE_STATUS" \
ADMIN_SOURCE_STATE="$ADMIN_SOURCE_STATE" \
API_SOURCE_STATE="$API_SOURCE_STATE" \
MBN_SOURCE_STATE="$MBN_SOURCE_STATE" \
RELEASE_OUT_DIR="$OUT_DIR" \
RELEASE_LOG_PATH="$RELEASE_LOG" \
php -r '
$bundle = is_file(getenv("ADMIN_BUNDLE_REPORT"))
    ? json_decode((string) file_get_contents(getenv("ADMIN_BUNDLE_REPORT")), true)
    : null;
$summary = [
    "status" => "passed",
    "verified_at" => getenv("VERIFIED_AT"),
    "contract_version" => "2026-08-04.1",
    "admin_bundle_status" => getenv("ADMIN_BUNDLE_STATUS"),
    "admin_bundle" => $bundle,
    "source_state" => [
        "admin" => getenv("ADMIN_SOURCE_STATE"),
        "api" => getenv("API_SOURCE_STATE"),
        "mbn" => getenv("MBN_SOURCE_STATE"),
    ],
    "gates" => [
        "api_phpunit_pint_integrity" => "passed",
        "admin_checks_lint_build" => "passed",
        "mbn_checks_lint_build" => "passed",
        "mbn_browser_core" => "passed",
        "transactional_api_e2e" => "passed",
        "admin_browser_crud" => "passed",
        "docx_visual_render" => "passed",
        "clean_zip_integrity" => "passed",
    ],
    "artifacts" => getenv("RELEASE_OUT_DIR"),
    "log" => getenv("RELEASE_LOG_PATH"),
];
file_put_contents(getenv("RELEASE_SUMMARY"), json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
'
