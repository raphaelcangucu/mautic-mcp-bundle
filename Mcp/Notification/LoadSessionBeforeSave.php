<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Notification;

use Mcp\Schema\JsonRpc\Notification;
use Mcp\Server\Handler\Notification\NotificationHandlerInterface;
use Mcp\Server\Session\SessionInterface;

/**
 * mcp/sdk 0.4 saves the session after every message, but Session::save() reads a property that
 * only a get or set fills. A notification no handler touches, like notifications/cancelled, turned
 * that save into a fatal error and a 500. Loading the session here avoids it; mcp/sdk 0.5 fixes
 * save() itself, so this class can go when the plugin moves past 0.4.
 */
final class LoadSessionBeforeSave implements NotificationHandlerInterface
{
    public function supports(Notification $notification): bool
    {
        return true;
    }

    public function handle(Notification $notification, SessionInterface $session): void
    {
        $session->all();
    }
}
