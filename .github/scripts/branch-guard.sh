#!/usr/bin/env bash
# Usage: branch-guard.sh <base_ref> <head_ref> <head_repo> <base_repo>
# Only release/* and hotfix/* branches of this repository may target master.
set -euo pipefail

base_ref="$1"
head_ref="$2"
head_repo="$3"
base_repo="$4"

if [ "$base_ref" != "master" ]; then
  exit 0
fi

# A fork can name its branch release/x, so the branch name alone proves nothing.
if [ "$head_repo" = "$base_repo" ] && [[ "$head_ref" =~ ^(release|hotfix)/[^/]+$ ]]; then
  exit 0
fi

echo "::error::Pull requests to master must come from a release/* or hotfix/* branch of this repository, not from '$head_ref' ($head_repo). Target develop instead."
exit 1
