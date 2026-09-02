# Changelog

## 0.13.2 - 2026-09-02

- Reactivate an archived Meta identity when an explicit MCP identity upsert restores it.

## 0.13.1 - 2026-09-02

- Pass the top-level `idempotencyKey` through the management closure to `upsert_identity`; the key remains forbidden inside `data`.

## 0.13.0 - 2026-09-02

- Add explicit `upsert_identity` creation/update semantics to `mautic_manage_meta`.
- Publish typed identity fields and preserve `link_identity` for existing Meta Identity IDs only.
- Validate channel/asset and E.164 consistency, return structured conflicts, preserve later opt-outs, and atomically persist identity plus WhatsApp consent audit.

## 0.12.0 - 2026-09-02

- Add `register_landing_whatsapp_opt_in` for strict single and batch consent registration with dry-run and idempotency.
- Add preview/start/status/rejections/cancel tools for historical WhatsApp consent synchronization.
- Publish explicit Meta management data properties and structured identity/consent errors.

## 0.11.2 - 2026-09-02

- Use canonical Instagram Graph IDs resolved from linked Pages or Business Manager asset metadata.
- Remove the unsupported Instagram profile `user_id` field.

## 0.11.1 - 2026-09-02

- Return the original structured Graph API error from live Instagram reads.
- Expose strict connection diagnostics for required Instagram permissions and assigned profiles.

## 0.11.0 - 2026-09-02

- Add omnichannel webhook adapter configuration to Meta connection create/update operations.
- Redact encrypted adapter secrets from MCP read responses while reporting whether a secret is configured.

## 0.10.0 - 2026-09-01

- Add `mautic_meta_setup`, a read-only configuration assistant for the optional official Meta bundle.
- Report the current connection/asset readiness state and safe per-connection webhook URLs without exposing credentials.
- Document installation, Meta App setup, permissions, assets, webhooks, campaigns, queues, MCP workflows, and troubleshooting as structured MCP output.

## 0.9.1 - 2026-09-01

- Report `replayed=false` on the first idempotent mutation and `replayed=true` only when the cached result is actually reused.

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
