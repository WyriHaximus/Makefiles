#!/usr/bin/env bash
set -euo pipefail

input=$(cat)
command=$(echo "$input" | jq -r '.command // .tool_input.command // empty')

if [[ -z "$command" ]]; then
  echo '{ "permission": "allow" }'
  exit 0
fi

if echo "$command" | grep -qE '(^|[[:space:]])(sudo|su|docker)([[:space:]]|$)' \
  || echo "$command" | grep -qE '(^|[[:space:]])cd([[:space:]]|$)' \
  || echo "$command" | grep -qE 'sudo[[:space:]]+su'; then
  msg='Forbidden command: sudo, su, cd, and docker are blocked. Use make run for container commands.'
  if echo "$input" | jq -e '.tool_name' >/dev/null 2>&1; then
    echo "$msg" >&2
    exit 2
  fi
  jq -n --arg msg "$msg" '{permission: "deny", user_message: $msg, agent_message: $msg}'
  exit 0
fi

echo '{ "permission": "allow" }'
exit 0
