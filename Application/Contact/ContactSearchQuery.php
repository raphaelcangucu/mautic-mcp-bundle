<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Contact;

final readonly class ContactSearchQuery
{
    public function __construct(
        private string $search = '',
        private int $limit = 10,
        private int $page = 1,
    ) {
    }

    public function getSearch(): string
    {
        return trim($this->search);
    }

    public function getLimit(): int
    {
        return max(1, min($this->limit, 100));
    }

    public function getPage(): int
    {
        return max(1, $this->page);
    }

    public function getStart(): int
    {
        return ($this->getPage() - 1) * $this->getLimit();
    }
}
