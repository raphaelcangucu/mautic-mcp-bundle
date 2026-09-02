<?php

return [
    'name'        => 'Mautic MCP Bundle',
    'description' => 'Full MCP tools for Mautic automation, analytics, CRM, tags, forms, and webhooks.',
    'version'     => '0.11.1',
    'author'      => 'Rahul Shinde',
    'routes'      => [
        'main' => [
            'mautic_mcp_account_rotate' => [
                'path'       => '/account/mcp/token/rotate',
                'controller' => 'MauticPlugin\\MauticMcpBundle\\Controller\\McpAccountController::rotate',
                'method'     => 'POST',
            ],
            'mautic_mcp_account_revoke' => [
                'path'       => '/account/mcp/token/revoke',
                'controller' => 'MauticPlugin\\MauticMcpBundle\\Controller\\McpAccountController::revoke',
                'method'     => 'POST',
            ],
        ],
        'public' => [
            'mautic_mcp_http_endpoint' => [
                'path'       => '/mcp',
                'controller' => 'mcp.server.controller::handle',
                'method'     => ['GET', 'POST', 'DELETE', 'OPTIONS'],
            ],
        ],
    ],
];
