#!/bin/sh
set -eu

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 <TASK_ID> <EVENT_TYPE> [ACTOR] [NOTE]" >&2
  exit 2
fi

TASK_ID=$1
EVENT_TYPE=$2
ACTOR=${3:-unknown}
NOTE=${4:-}
LOG_FILE=${TASK_EVENT_LOG:-doc/tasktracking/task-events.jsonl}

case "$EVENT_TYPE" in
  TASK_READY|TASK_STARTED|TASK_PAUSED|TASK_RESUMED|IMPLEMENTATION_COMPLETED|USER_TESTING_STARTED|USER_TESTING_APPROVED|USER_TESTING_REJECTED|REWORK_STARTED|TASK_BLOCKED|TASK_UNBLOCKED|TASK_CANCELLED) ;;
  *) echo "Invalid event type: $EVENT_TYPE" >&2; exit 3 ;;
esac

mkdir -p "$(dirname "$LOG_FILE")"
TS=$(date -u '+%Y-%m-%dT%H:%M:%SZ')

json_escape() {
  printf '%s' "$1" | tr '\r\n' '  ' | sed 's/\\/\\\\/g; s/"/\\"/g; s/	/\\t/g'
}

TASK_ESC=$(json_escape "$TASK_ID")
EVENT_ESC=$(json_escape "$EVENT_TYPE")
ACTOR_ESC=$(json_escape "$ACTOR")
NOTE_ESC=$(json_escape "$NOTE")

printf '{"timestamp":"%s","task_id":"%s","event":"%s","actor":"%s","note":"%s"}\n' \
  "$TS" "$TASK_ESC" "$EVENT_ESC" "$ACTOR_ESC" "$NOTE_ESC" >> "$LOG_FILE"

echo "$TS $TASK_ID $EVENT_TYPE"
