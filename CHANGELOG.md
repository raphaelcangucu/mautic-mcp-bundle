# Changelog

## 0.9.0 - 2026-09-01

- Add live, read-only Instagram Graph API access for profiles, media, comments, insights, conversations, and messages.
- Add WhatsApp media and interactive-message delivery through the durable outbound queue.
- Add MCP CRUD for encrypted Meta connections and channel assets, with confirmation for destructive operations.
- Expand the official Meta integration to four dedicated MCP tools.

## 0.8.0 - 2026-09-01

- Add separate read, send, and administration tools for the official Meta integration.
- Read Meta connections, assets, WhatsApp templates, contact identities, message logs, and durable queue jobs.
- Queue WhatsApp and Instagram messages with idempotency, dry-run support, and automatic retries.
- Manage WhatsApp templates, consent, identity/contact links, synchronization, and external connection diagnostics.

## 0.7.1 - 2026-09-01

- Add `mautic_read_email_html` with an explicit output schema for complete HTML source retrieval.
- Clarify that `mautic_read_emails` with `action=get` returns complete editable content.

## 0.7.0 - 2026-09-01

- Return complete HTML, plain text, preheader, visual template, absolute preview URL, preview state, and editing-lock metadata when reading emails.
- Add explicit HTML update, public-preview enable/disable, and editing lock/unlock actions.
- Add dedicated read and write tools for reusable email templates.
- Add create, update, HTML edit, publish, preview control, lock, unlock, and confirmed deletion for email templates.
- Derive the MCP profile endpoint from the current Mautic installation URL.

## 0.6.0 - 2026-08-24

- Add tag CRUD and contact assignment/removal tools.
- Support `lead.changetags` in campaign graphs.
- Add per-user MCP token management to the Mautic profile.

## 0.5.0

- Add complete campaign-flow replacement and event deletion.
- Add email cloning, A/B variants, and confirmed test sends.
