#!/usr/bin/env bash
# Usage: .docker/run.sh [PHP_VERSION=8.1] -- <cmd...>
# Runs the given command inside the doclite-dev:<version> image with the repo mounted at /app.
set -euo pipefail

PHP_VERSION="${PHP_VERSION:-8.1}"
IMAGE="doclite-dev:${PHP_VERSION}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    docker build --build-arg "PHP_VERSION=${PHP_VERSION}" -t "$IMAGE" "$REPO/.docker" >&2
fi

exec docker run --rm \
    -v "$REPO":/app \
    -w /app \
    -u "$(id -u):$(id -g)" \
    -e HOME=/tmp \
    -e COMPOSER_HOME=/tmp/composer \
    "$IMAGE" "$@"
