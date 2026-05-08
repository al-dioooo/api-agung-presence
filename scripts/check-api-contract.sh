#!/usr/bin/env bash
set -euo pipefail

php artisan route:list --path=api
composer test

test -f openapi.yaml