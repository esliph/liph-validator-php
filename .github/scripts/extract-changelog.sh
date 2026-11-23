#!/usr/bin/env bash
# Usage: extract-changelog.sh <version> [file]
# Prints the body of the "## [<version>]" section of a Keep a Changelog file.
# Exits 1 when the section is missing or has no non-blank line.
set -euo pipefail

version="$1"
file="${2:-CHANGELOG.md}"

# index() matches the version literally, so the dots need no escaping.
body=$(awk -v version="$version" '
  { sub(/\r$/, "") }
  index($0, "## [" version "]") == 1 { inside = 1; next }
  inside && index($0, "## [") == 1 { exit }
  # Keep a Changelog puts the link references after the last section.
  inside && $0 ~ /^\[[^]]+\]: / { exit }
  inside { lines[++n] = $0 }
  END {
    first = 1
    while (first <= n && lines[first] ~ /^[[:space:]]*$/) first++
    last = n
    while (last >= first && lines[last] ~ /^[[:space:]]*$/) last--
    for (i = first; i <= last; i++) print lines[i]
  }
' "$file")

# A section holding only headings has nothing to publish.
if ! grep -qv -e '^[[:space:]]*$' -e '^#' <<< "$body"; then
  echo "::error::$file has no section with content for version $version." >&2
  exit 1
fi

printf '%s\n' "$body"
