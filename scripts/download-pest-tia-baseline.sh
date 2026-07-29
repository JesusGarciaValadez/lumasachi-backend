#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if ! command -v gh >/dev/null 2>&1; then
    echo "GitHub CLI (gh) is required. Install it and run gh auth login first." >&2
    exit 1
fi

if ! gh auth status -h github.com >/dev/null 2>&1; then
    echo "GitHub CLI is not authenticated. Run gh auth login -h github.com and retry." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required to copy the baseline into the Sail container." >&2
    exit 1
fi

repository="${GITHUB_REPOSITORY:-}"
remote_url=""
if [ -z "$repository" ]; then
    remote_url="$(git config --get remote.origin.url || true)"
    repository="$(printf '%s' "$remote_url" | sed -E 's#^git@github\.com:##; s#^https://github\.com/##; s#\.git$##')"
fi

if [ -z "$repository" ] || [ "$repository" = "$remote_url" ]; then
    echo "Unable to determine the GitHub repository from origin. Set GITHUB_REPOSITORY=owner/repository and retry." >&2
    exit 1
fi

baseline_branch="${TIA_BASELINE_BRANCH:-main}"
run_id="$(gh run list \
    --repo "$repository" \
    --workflow tia-baseline.yml \
    --branch "$baseline_branch" \
    --status success \
    --limit 1 \
    --json databaseId \
    --jq '.[0].databaseId // empty')"

if [ -z "$run_id" ]; then
    echo "No successful tia-baseline.yml run was found for $repository on $baseline_branch." >&2
    echo "Run the workflow once from GitHub Actions, then retry this command." >&2
    exit 1
fi

container_id="$(docker compose ps -q laravel.test)"
if [ -z "$container_id" ]; then
    echo "The laravel.test Sail container is not running. Start it with vendor/bin/sail up -d and retry." >&2
    exit 1
fi

download_dir="$(mktemp -d "${TMPDIR:-/tmp}/lumasachi-pest-tia.XXXXXX")"
trap 'rm -rf "$download_dir"' EXIT

gh run download "$run_id" \
    --repo "$repository" \
    --name pest-tia-baseline \
    --dir "$download_dir"

graph_path="$(find "$download_dir" -type f -name graph.json -print -quit)"
if [ -z "$graph_path" ]; then
    echo "The pest-tia-baseline artifact from run $run_id does not contain graph.json." >&2
    exit 1
fi

artifact_root="$(dirname "$graph_path")"
baseline_path="$(vendor/bin/sail exec -u sail -e TIA_VITE_PAGES_DIR=resources/js/pages laravel.test php vendor/bin/pest --baseline)"

docker exec "$container_id" mkdir -p "$baseline_path"
docker cp "$artifact_root/." "$container_id:$baseline_path/"
docker exec "$container_id" chown -R sail:sail "$baseline_path"
docker exec "$container_id" test -s "$baseline_path/graph.json"

echo "Installed TIA baseline from run $run_id at $baseline_path."
