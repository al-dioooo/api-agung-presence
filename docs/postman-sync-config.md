# Postman Sync Config

- Postman spec ID: `7a91e8df-1f2d-4c1b-a9a5-c70e8ac65671`
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