# MCP tools supplied by plugins

MauticMcpBundle 0.17 discovers explicit providers from the registered Mautic bundles. Each plugin opts in with `Config/mcp.php`:

```php
<?php
return [
    'namespace' => 'MauticPlugin\\ExampleBundle\\Mcp\\',
    'directory' => __DIR__.'/../Mcp',
];
```

The directory must resolve inside the bundle directory. The MCP extension registers its services with autowiring/autoconfiguration and includes that directory in SDK discovery. Classes expose `#[McpTool]` and parameter `#[Schema]` metadata. The manifest is installed PHP code, not user-editable configuration. Installing a random plugin does not expose its controllers or routes: only its explicit provider and annotated tools are discovered.

Normal plugin service loading must exclude `Mcp`. This keeps the business plugin usable without MauticMcpBundle or the MCP SDK. Install the MCP bundle and SDK to activate the adapters. After installing/updating/removing a provider, clear the Mautic cache and reconnect clients so they refresh `tools/list`. There is no hot discovery or automatic client push notification in this release. Clients call a selected entry with `tools/call`.

## Ownership and compatibility

- MauticMcpBundle owns authentication, SDK transport, common schemas and mutation support.
- MauticMetaBundle owns its `Mcp/Tool` and `Mcp/Application` adapters, including the existing tool names. WhatsApp/Instagram clients retain their contracts. `mautic_send_meta_message` now also accepts Facebook direct messages/public replies; `mautic_read_meta_api` adds Facebook profile/posts/reels/comments.
- MauticInboxBundle owns multichannel support and AI tools.

Update all three plugins together for this migration. Old PHP adapter namespaces inside MauticMcpBundle are removed; public MCP tool names remain stable. Do not run both old and new tool providers. The central discovery exclusion also prevents stale deployed legacy Meta adapters from producing duplicate entries.

## Inbox tools

- `mautic_read_inbox`: conversations, detail, timeline, templates, eligible agents, users, canned responses. Conversation IDs are **inbox state IDs**, not raw Meta conversation IDs. Use `queue=all` when not filtering by operator; `kind=comments` for comments. Listing is read-only and does not wake snoozed conversations.
- `mautic_manage_inbox`: take, transfer, resolve, reopen, snooze, unassign, read, note, assign_ai. Read first and provide the current `version` for transitions. `target_user_id`, `until`, `body` and `agent` depend on action. Assigning AI can result in external replies.
- `mautic_reply_inbox`: text or approved WhatsApp template, scoped to a conversation. Requires a unique `request_id`; queued does not mean delivered. It uses the same ownership/window/consent/queue rules as the UI.
- `mautic_read_inbox_ai`: admin-only agents, documents, configuration, permissions and saved Pi health. Documents include drafts/published versions and history; credentials are not returned.
- `mautic_manage_inbox_ai`: admin-only document/agent/config updates and Pi health/validate/install/import-auth. Supply full settings on replacement; document and agent updates require their current revision. Do not pass credentials in data. Import reads the existing server Codex login, and install uses the pinned Pi version.

New mutation tools default to preview and require explicit `confirm=true` for execution. Preview describes the requested mutation but does not guarantee business validation or delivery. Where available, use an idempotency key; reply requests use their own unique request ID. Tools retain the authenticated user's Mautic permissions. AI and human response safety controls are never disabled by MCP; internal `_origin`/`_ai_*` payload overrides are rejected.

The Pi customer agent does **not** automatically gain these administrative MCP tools. Its restricted CMS/funnel tool list remains separate.
