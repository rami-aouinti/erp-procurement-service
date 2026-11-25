#!/usr/bin/env bash
set -euo pipefail

# Run from the repository root. Two independent SQL*Plus sessions are used
# so Oracle must enforce row-level serialization, not just sequential logic.
tmp_dir="$(mktemp -d)"
session_a_pid=""
cleanup_tmp() {
  if [[ -n "$session_a_pid" ]]; then
    wait "$session_a_pid" 2>/dev/null || true
  fi
  rm -rf "$tmp_dir"
}
trap cleanup_tmp EXIT

run_sql() {
  local script="$1"
  docker compose exec -T oracle bash -lc \
    'sqlplus -s "$APP_USER/$APP_USER_PASSWORD@//localhost:1521/FREEPDB1"' \
    < "$script"
}

echo "1/5 Preparing isolated test order (10 units)..."
run_sql database/oracle/014_prepare_goods_receiving_concurrency_test.sql

echo "2/5 Starting Session A (it locks the order, then receives 6 units)..."
run_sql database/oracle/015_goods_receiving_concurrency_session_a.sql >"$tmp_dir/session-a.log" 2>&1 &
session_a_pid=$!

# Session A acquires the row lock immediately, then sleeps for five seconds.
sleep 1

echo "3/5 Starting Session B (it attempts to receive 5 units and must wait)..."
if ! run_sql database/oracle/016_goods_receiving_concurrency_session_b.sql >"$tmp_dir/session-b.log" 2>&1; then
  cat "$tmp_dir/session-b.log"
  cat "$tmp_dir/session-a.log"
  exit 1
fi

if ! wait "$session_a_pid"; then
  cat "$tmp_dir/session-a.log"
  cat "$tmp_dir/session-b.log"
  session_a_pid=""
  exit 1
fi
session_a_pid=""

grep -q "SESSION_A_RECEIPT_COMMITTED" "$tmp_dir/session-a.log"
grep -q "PASS concurrency: Session B waited" "$tmp_dir/session-b.log"
cat "$tmp_dir/session-a.log"
cat "$tmp_dir/session-b.log"

echo "4/5 Verifying the final Oracle state..."
run_sql database/oracle/017_verify_goods_receiving_concurrency_test.sql

echo "5/5 Cleaning up the isolated test data..."
run_sql database/oracle/018_cleanup_goods_receiving_concurrency_test.sql

echo "All two-session goods-receiving concurrency checks passed."
