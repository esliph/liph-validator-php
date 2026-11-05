#!/usr/bin/env bash
# PreToolUse (Edit|Write|NotebookEdit): blocks edits to composer.lock and vendor/.
# These files must only change through `make deps`; a manual edit leaves the lock
# out of sync with composer.json.
#
# No jq dependency: the path is extracted from the stdin JSON with sed.

normalize() {
  printf '%s' "$1" \
    | sed -e 's#\\\\#/#g' -e 's#\\#/#g' -e 's#/*$##' \
    | tr '[:upper:]' '[:lower:]' \
    | sed -E 's#^([a-z]):/#/\1/#'
}

file_path=$(sed -nE 's/.*"(file_path|notebook_path)"[[:space:]]*:[[:space:]]*"([^"]*)".*/\2/p' | head -n 1)
[ -z "$file_path" ] && exit 0

target=$(normalize "$file_path")
root=$(normalize "${CLAUDE_PROJECT_DIR:-$PWD}")
rel=${target#"$root"/}

case "$rel" in
  composer.lock|vendor/*)
    echo "Blocked: '$rel' must only change through 'make deps'. Edit composer.json and run 'make deps' to regenerate composer.lock and vendor/." >&2
    exit 2
    ;;
esac

exit 0
