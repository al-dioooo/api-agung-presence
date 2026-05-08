# Automating Postman Updates With Codex MCP

This document explains how to keep the Agung Presence API documentation in Postman synchronized when Laravel API code changes.

## Current Project State

The API project is a Laravel API located in `api-agung-presence`.

Important files:

| Purpose | File |
| --- | --- |
| API routes | `routes/api.php` |
| API controllers | `app/Http/Controllers/Api/` |
| Request validation | `app/Http/Requests/` |
| Response resources | `app/Http/Resources/` |
| Existing Postman collection | `Agung_Presence_API.postman_collection.json` |

Current API groups:

| Group | Endpoints |
| --- | --- |
| Status | `GET /api/status` |
| Auth | `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout`, `POST /api/auth/register` |
| Offices | `GET/POST /api/offices`, `GET/PUT/PATCH/DELETE /api/offices/{office}` |
| Attendances | `GET/POST /api/attendances`, `GET/PUT/PATCH/DELETE /api/attendances/{attendance}` |

There is already a Postman collection JSON file, but there is not yet an OpenAPI specification file such as `openapi.yaml` or `openapi.json`.

## What Codex Can Do Through Postman MCP

The Postman MCP connection lets Codex call Postman API tools directly from this workspace.

Useful MCP actions include:

| Action | Use |
| --- | --- |
| `updateSpecFile` | Push updated OpenAPI content into a Postman API specification file |
| `updateSpecProperties` | Rename or update Postman API specification metadata |
| `getSpecFiles` | Inspect files inside a Postman API specification |
| Collection update tools | Update folders, requests, and saved examples inside Postman collections |

The MCP connection does not watch files by itself. Automation still needs a trigger, such as:

- a Codex instruction after API changes
- a local script
- a Git hook
- a CI workflow

## Recommended Architecture

Use OpenAPI as the contract source of truth.

```text
Laravel route/controller/request changes
        |
        v
Generate or maintain openapi.yaml
        |
        v
Run API tests
        |
        v
Codex checks route/spec drift
        |
        v
Codex calls Postman MCP updateSpecFile
        |
        v
Postman API docs and collections stay current
```

This is cleaner than treating the Postman collection JSON as the source of truth because OpenAPI is designed for API contracts, documentation, validation, generated clients, and Postman import/update flows.

## Setup Step 1: Create A Postman API Specification

In Postman:

1. Open the target workspace.
2. Create or open the API for Agung Presence.
3. Add an OpenAPI specification file.
4. Copy the Postman API spec ID.
5. Confirm the root file path, usually something like `openapi.yaml`.

You will need these values:

```text
POSTMAN_SPEC_ID=your-postman-spec-id
POSTMAN_SPEC_FILE_PATH=openapi.yaml
```

Codex needs the `specId` and `filePath` to call `updateSpecFile`.

## Setup Step 2: Add A Local OpenAPI File

Recommended file:

```text
api-agung-presence/openapi.yaml
```

Minimum starter structure:

```yaml
openapi: 3.1.0
info:
  title: Agung Presence API
  version: 1.0.0
  description: API contract for the Agung Presence backend.
servers:
  - url: "{{base_url}}"
paths:
  /api/status:
    get:
      summary: Get API status
      responses:
        "200":
          description: API status response
```

After this exists, every API change should update this file.

## Setup Step 3: Decide How OpenAPI Will Be Maintained

There are two practical options.

### Option A: Manual Spec Updates With Codex

Use this when the API is still small.

Workflow:

1. Update Laravel routes, controllers, requests, or resources.
2. Ask Codex to update `openapi.yaml`.
3. Codex compares:
   - `routes/api.php`
   - controller methods
   - form request validation rules
   - API resource response shapes
   - feature tests
4. Codex edits `openapi.yaml`.
5. Codex runs tests.
6. Codex pushes the updated spec to Postman through MCP.

Example prompt:

```text
I changed the attendance API. Please update openapi.yaml and sync it to Postman through MCP.
Postman specId: <spec-id>
Postman filePath: openapi.yaml
```

### Option B: Generate OpenAPI From Laravel Code

Use this when the API grows and manual updates become repetitive.

Common Laravel-compatible approaches:

| Tool | Notes |
| --- | --- |
| Scribe | Good for Laravel API documentation and generated examples |
| swagger-php / L5-Swagger | Good when you want annotation-driven OpenAPI |
| Laravel Scramble | Good for generating OpenAPI from Laravel types and routes |

Before installing one, decide which style your team prefers:

| Style | Tradeoff |
| --- | --- |
| Generated from code | Less manual work, but requires package setup and annotations/types |
| Manually maintained spec | More explicit and stable, but requires discipline |

For this project, start with manual `openapi.yaml` plus Codex review. Add a generator later when the API surface becomes larger.

## Setup Step 4: Add Local Sync Instructions For Codex

Create a short project note so Codex knows the Postman sync process.

Suggested file:

```text
api-agung-presence/docs/postman-sync-config.md
```

Suggested content:

```md
# Postman Sync Config

- Postman spec ID: `<POSTMAN_SPEC_ID>`
- Root spec file path: `openapi.yaml`
- Local spec file: `openapi.yaml`
- Existing collection file: `Agung_Presence_API.postman_collection.json`

When API routes, controllers, requests, or resources change:

1. Update `openapi.yaml`.
2. Run `composer test`.
3. Use Postman MCP `updateSpecFile` with:
   - `specId`: `<POSTMAN_SPEC_ID>`
   - `filePath`: `openapi.yaml`
   - `content`: full contents of local `openapi.yaml`
```

Do not commit secrets or Postman API tokens into this file.

## Manual Codex Sync Procedure

Use this procedure after API code changes.

### 1. Inspect Route Changes

Run:

```bash
php artisan route:list --path=api
```

Check whether these changed:

- HTTP method
- URI path
- controller action
- middleware
- route name

### 2. Inspect Request Validation

Review the matching form request classes in:

```text
app/Http/Requests/
```

These define request body fields, required fields, enum values, and authorization behavior.

### 3. Inspect Response Shape

Review:

```text
app/Http/Resources/
app/Traits/ApiResponse.php
```

This project should return JSON in the standard shape:

```json
{
  "message": "Human-readable message.",
  "data": {}
}
```

### 4. Update OpenAPI

Update:

```text
openapi.yaml
```

Each endpoint should document:

- method and path
- auth requirement
- request body
- path parameters
- query parameters
- success response
- validation error response
- unauthorized or forbidden response when relevant

### 5. Run Tests

Run:

```bash
composer test
```

If only one API area changed, optionally run a filtered test first:

```bash
php artisan test --filter=AttendanceTest
```

### 6. Push To Postman With MCP

Codex should call Postman MCP:

```text
updateSpecFile(
  specId: "<POSTMAN_SPEC_ID>",
  filePath: "openapi.yaml",
  content: "<full contents of local openapi.yaml>"
)
```

Important MCP constraints:

- `updateSpecFile` cannot receive an empty request body.
- Send only one of `content`, `name`, or `type` per call.
- Files cannot exceed 10 MB.
- If setting `type: ROOT`, only one root file is allowed.

## Local Automation Option

For local development, use a script that prepares the spec and then ask Codex to push it through MCP.

Suggested script name:

```text
scripts/check-api-contract.sh
```

Suggested behavior:

```bash
#!/usr/bin/env bash
set -euo pipefail

php artisan route:list --path=api
composer test

test -f openapi.yaml
```

Then the workflow is:

```bash
./scripts/check-api-contract.sh
```

Then ask Codex:

```text
Sync openapi.yaml to Postman MCP using specId <spec-id> and filePath openapi.yaml.
```

This keeps the Postman write action explicit, which is safer during development.

## Git Hook Option

A Git hook can remind developers when API code changes.

Recommended hook: `pre-commit`.

Trigger when these paths change:

```text
routes/api.php
app/Http/Controllers/Api/
app/Http/Requests/
app/Http/Resources/
app/Models/
database/migrations/
```

Hook behavior:

1. If any API files changed, check whether `openapi.yaml` changed too.
2. If not, stop the commit with a message.
3. Developer updates `openapi.yaml`.
4. Codex syncs the spec to Postman after review.

Example message:

```text
API implementation changed, but openapi.yaml was not updated.
Please update the API contract before committing.
```

Avoid automatically pushing to Postman from a pre-commit hook. It is better to sync after tests pass.

## CI Automation Option

For a stronger workflow, run the sync in CI after merge to the main branch.

Recommended CI stages:

```text
Pull request:
  - install dependencies
  - run tests
  - verify openapi.yaml exists
  - optionally compare route list against OpenAPI paths

Main branch:
  - install dependencies
  - run tests
  - push openapi.yaml to Postman
```

The CI job should store Postman credentials as encrypted secrets.

Example secret names:

```text
POSTMAN_API_KEY
POSTMAN_SPEC_ID
POSTMAN_SPEC_FILE_PATH
```

With the current Codex MCP setup, Codex can perform the MCP call interactively. For fully unattended CI, use Postman's HTTP API directly from CI, because MCP tools are available inside Codex sessions rather than as a normal shell command.

## Codex Prompt Templates

### After Changing An Endpoint

```text
I changed an API endpoint in api-agung-presence.

Please:
1. inspect the changed Laravel routes/controllers/requests/resources
2. update openapi.yaml
3. run the relevant tests
4. sync openapi.yaml to Postman through MCP

Postman specId: <spec-id>
Postman filePath: openapi.yaml
```

### Before Opening A Pull Request

```text
Please verify the API contract is current.

Check:
- routes/api.php
- app/Http/Controllers/Api
- app/Http/Requests
- app/Http/Resources
- tests/Feature
- openapi.yaml

Then run tests and tell me whether Postman needs to be synced.
```

### Sync Only

```text
Please sync api-agung-presence/openapi.yaml to Postman through MCP.

specId: <spec-id>
filePath: openapi.yaml
```

## Fallback: Updating The Existing Postman Collection

This repository already has:

```text
Agung_Presence_API.postman_collection.json
```

You can keep this collection updated manually, but it is less ideal than OpenAPI for contract automation.

Use the collection file for:

- runnable API examples
- local Postman import/export
- saved request bodies
- shared development workflows

Use OpenAPI for:

- formal API contract
- generated docs
- generated clients
- automated drift checks
- Postman API specification sync

If the collection remains important, the recommended flow is:

```text
openapi.yaml
        |
        v
Import/update Postman API specification
        |
        v
Generate or refresh Postman collection from the specification
```

## Definition Of Done

An API update is complete when:

- Laravel route/controller/request/resource changes are implemented.
- Feature tests pass.
- `openapi.yaml` documents the changed behavior.
- Postman API specification has been updated through MCP or CI.
- The existing Postman collection is refreshed if the team still uses it directly.

## Next Recommended Step

Add `openapi.yaml` to this repository and fill it with the current API contract. After that, Codex can keep the spec and Postman in sync whenever API code changes.
