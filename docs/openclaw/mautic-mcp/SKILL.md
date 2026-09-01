---
name: mautic-mcp
description: Operate Mautic through its authenticated MCP server, including contacts, segments, campaigns and flows, emails, analytics, CRM, forms, webhooks, and incremental events.
---

# Mautic MCP

Use the configured Mautic MCP server. Call `mautic_health` first when identity, permissions, Mautic version, or MCP version matters.

Use `mautic_read_tags` to list or fetch tags and `mautic_manage_tags` to create, update, delete, or associate tags with contacts. Create tags before referencing them in campaign flows.

To tag a contact while a campaign runs, add an action event such as:

```json
{
  "key": "tag-engaged",
  "name": "Tag engaged contact",
  "type": "lead.changetags",
  "eventType": "action",
  "properties": {
    "add_tags": ["engaged"],
    "remove_tags": []
  }
}
```

Prefer dedicated read tools before mutations. Use previews and `dryRun` whenever available. For retried mutations, reuse the same `idempotencyKey`. Before updating an existing entity, read its `dateModified` and pass it as `expectedDateModified`. Never set `confirm=true` until the user has approved a send, deletion, merge, complete campaign-flow replacement, or external webhook test.

## Read tools

- `mautic_health`
- `mautic_search_contacts`, `mautic_fetch_contact`, `mautic_get_contact_timeline`
- `mautic_read_segments`, `mautic_search_campaigns`, `mautic_fetch_campaign`
- `mautic_read_campaign_flow` with `list_types`, `get`, or `validate`
- `mautic_read_emails`, `mautic_preview_email_send`
- `mautic_read_email_html` before any HTML edit; preserve `dateModified` for the write
- `mautic_read_email_templates`, including complete HTML and preview/lock state
- `mautic_analytics`
- `mautic_read_crm`
- `mautic_read_forms`
- `mautic_read_webhooks`
- `mautic_read_tags`
- `mautic_read_events` for cursor-based incremental synchronization

## Write tools

- `mautic_manage_contacts`
- `mautic_manage_segments`
- `mautic_manage_campaigns`
- `mautic_write_campaign_flow` with `replace` or `delete_events`
- `mautic_manage_emails`, including `clone`, `create_ab_test`, and confirmed `send_test`
- `mautic_manage_email_templates` for reusable template CRUD and HTML/preview/lock changes
- `mautic_manage_crm`
- `mautic_manage_webhooks`
- `mautic_manage_tags`, including tag creation and contact assignment/removal

`mautic_write_campaign_flow` can replace the complete graph (including replacing it with an empty graph) or remove specific persisted event IDs. `delete_events` rejects parent deletion when children remain unless `cascade=true`. Read the current graph first, perform a `dryRun`, and only then execute with confirmation.

For email A/B tests, call `mautic_manage_emails` with `action=create_ab_test`, the parent email ID, and `data.variants`. Configure `winnerCriteria`, `sendWinnerDelay`, and `totalWeight` in `data`. Use `send_test` with `data.recipients` to send a non-statistical sample before publishing.

Analytics uses page-based pagination. Incremental event and webhook-log reads intentionally use `afterId`/`nextCursor` because cursors are stable while new events arrive.
