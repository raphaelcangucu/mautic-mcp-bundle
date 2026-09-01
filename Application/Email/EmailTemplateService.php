<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Email;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EmailTemplateService
{
    public function __construct(
        private MauticManagementService $management,
    ) {}

    public function read(string $action, ?int $id, string $query, int $page, int $limit): array
    {
        if ('get' === $action) {
            return $this->template($id);
        }
        if ('list' !== $action) {
            throw new BadRequestHttpException('Action must be list or get.');
        }

        $matches = [];
        $sourcePage = 1;
        do {
            $result = $this->management->emails('list', null, [], [], [], '', 100, $sourcePage, false);
            foreach ($result['items'] ?? [] as $email) {
                if ('template' !== ($email['emailType'] ?? null)) {
                    continue;
                }
                $haystack = strtolower((string) ($email['name'] ?? '').' '.(string) ($email['subject'] ?? ''));
                if ('' === trim($query) || str_contains($haystack, strtolower(trim($query)))) {
                    $matches[] = $email;
                }
            }
            ++$sourcePage;
        } while (true === ($result['hasMore'] ?? false) && $sourcePage <= 100);

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $total = count($matches);
        $items = array_slice($matches, ($page - 1) * $limit, $limit);
        $hasMore = $page * $limit < $total;

        return ['page' => $page, 'limit' => $limit, 'count' => count($items), 'total' => $total, 'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null, 'items' => $items];
    }

    public function write(string $action, ?int $id, array $data, bool $confirm, ?string $expectedDateModified): array
    {
        if ('create' === $action) {
            $data['emailType'] = 'template';

            return $this->management->emails('create', null, $data, [], [], '', 20, 1, $confirm);
        }
        $this->template($id);
        if (in_array($action, ['update', 'update_html'], true)) {
            $data['emailType'] = 'template';
        }

        return $this->management->emails($action, $id, $data, [], [], '', 20, 1, $confirm, $expectedDateModified);
    }

    private function template(?int $id): array
    {
        $result = $this->management->emails('get', $id, [], [], [], '', 20, 1, false);
        if ('template' !== ($result['email']['emailType'] ?? null)) {
            throw new NotFoundHttpException(sprintf('Email template %d was not found.', $id ?? 0));
        }

        return $result;
    }
}
