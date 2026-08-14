#!/usr/bin/env bash
set -euo pipefail

msg='Before finishing, run `make` to ensure all QA checks pass.'

if [[ -n "${CLAUDE_PROJECT_DIR:-}" ]] || [[ -n "${CLAUDE_CODE:-}" ]]; then
  echo "$msg" >&2
  exit 0
fi

jq -n --arg msg "$msg" '{followup_message: $msg}'
exit 0
