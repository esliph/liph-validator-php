#!/usr/bin/env bash
# Usage: check-tag.sh <tag>   (run from a full clone: fetch-depth: 0)
# Prints "stable" or "prerelease". Exits 1 with the reason otherwise.
set -euo pipefail

tag="$1"

if [[ ! "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-rc\.[0-9]+)?$ ]]; then
  echo "::error::Tag '$tag' must look like vX.Y.Z or vX.Y.Z-rc.N." >&2
  exit 1
fi

commit=$(git rev-list -n 1 "refs/tags/$tag")

if [[ "$tag" == *-rc.* ]]; then
  if [ -z "$(git branch -r --contains "$commit" --list 'origin/release/*')" ]; then
    echo "::error::Release candidate '$tag' must point to a commit reachable from a release/* branch." >&2
    exit 1
  fi

  echo prerelease
  exit 0
fi

if ! git merge-base --is-ancestor "$commit" origin/master; then
  echo "::error::Stable tag '$tag' must point to a commit reachable from master." >&2
  exit 1
fi

echo stable
