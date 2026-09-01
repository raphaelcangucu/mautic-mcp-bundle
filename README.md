# Mautic MCP Bundle

An authenticated Model Context Protocol server embedded in Mautic 7. It exposes structured, permission-aware tools for contacts, segments, campaigns, campaign graphs, emails and A/B tests, analytics, CRM, tags, forms, webhooks, and incremental events.

This is an extended fork of [`shinde-rahul/plugin-mautic-mcp`](https://github.com/shinde-rahul/plugin-mautic-mcp), originally created by [Rahul Shinde](https://github.com/shinde-rahul). The original project provided the foundation for exposing Mautic through MCP. This fork preserves that attribution and the GPL-3.0-or-later license while expanding the server into a broader Mautic automation interface.

## Requirements

- Mautic 7
- PHP 8.2 or newer
- `symfony/mcp-bundle` 0.6
- HTTPS in production

## Installation

Install as a Mautic plugin using Composer:

```bash
composer require raphaelcangucu/mautic-mcp-bundle
php bin/console mautic:plugins:reload
php bin/console cache:clear --env=prod
```

The Streamable HTTP endpoint is available at:

```text
https://your-mautic.example/mcp
```

Each logged-in user can manage their personal bearer token under the Mautic profile page in the **MCP Access** panel. Tokens inherit that user's Mautic permissions.

## Codex configuration

Store the token in an environment variable:

```bash
export MAUTIC_MCP_TOKEN='your-personal-token'
```

Add this to `~/.codex/config.toml`:

```toml
[mcp_servers.mautic]
url = "https://your-mautic.example/mcp"
bearer_token_env_var = "MAUTIC_MCP_TOKEN"
default_tools_approval_mode = "writes"
tool_timeout_sec = 120
enabled = true
```

Restart Codex and ask it to run `mautic_health`.

## Capabilities

The server currently publishes 26 tools, split into dedicated read and write operations:

- contacts, timelines, deduplication, merge, points, stages, companies, fields, and tags;
- segments and contact membership;
- campaigns and complete graph editing, including event deletion and `lead.changetags` actions;
- emails with complete HTML read/write, absolute preview URLs, public-preview and editing-lock controls, safe send preview, test sends, cloning, and A/B variants;
- reusable email-template creation, HTML editing, preview control, and deletion;
- campaign and email analytics;
- forms and submissions;
- webhooks and cursor-based incremental events.

All tools publish MCP annotations and output schemas. Mutations support controls such as `dryRun`, `confirm`, `idempotencyKey`, and optimistic concurrency where applicable.

## Campaign tag action

Create the tag with `mautic_manage_tags`, then include an action in the campaign graph:

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

## Security

- Use HTTPS and keep bearer tokens out of source control and logs.
- Give users only the Mautic permissions they need.
- Keep write approvals enabled in MCP clients.
- Preview sends and use `dryRun` before bulk mutations.
- Rotate or revoke a token immediately if it is exposed.

## Development checks

From a Mautic installation containing the plugin:

```bash
bin/ecs check plugins/MauticMcpBundle
bin/phpstan analyse -c plugins/MauticMcpBundle/phpstan.neon plugins/MauticMcpBundle --no-progress
php bin/console lint:twig plugins/MauticMcpBundle/Resources/views
```

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).

## Credits

- [Rahul Shinde](https://github.com/shinde-rahul) — creator of the [original Mautic MCP plugin](https://github.com/shinde-rahul/plugin-mautic-mcp) on which this project is based.
- [Raphael Cangucu](https://github.com/raphaelcangucu) — maintainer of this extended fork and its campaign-flow, analytics, CRM, tags, email HTML/templates, token-management, and safety-control features.
- [Mautic](https://github.com/mautic/mautic) and its contributors — the open-source marketing automation platform integrated by this plugin.
- [Model Context Protocol](https://modelcontextprotocol.io/) and the Symfony MCP ecosystem — the protocol and server components used by the integration.

Contributions from the upstream project remain governed by their original authorship and Git history. New contributions are credited through commits and pull requests.
