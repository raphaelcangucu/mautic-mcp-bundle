<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Presentation\Normalizer;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\StageBundle\Entity\Stage;
use Mautic\UserBundle\Entity\User;

final class ContactNormalizer
{
    public function normalizeSummary(Lead $contact): array
    {
        return [
            'id'                 => $contact->getId(),
            'primary_identifier' => $contact->getPrimaryIdentifier(),
            'name'               => $contact->getName(),
            'email'              => $contact->getEmail(),
            'company'            => $contact->getCompany(),
            'points'             => $contact->getPoints(),
            'stage'              => $this->normalizeStage($contact->getStage()),
            'date_identified'    => $this->formatDate($contact->getDateIdentified()),
            'last_active'        => $this->formatDate($contact->getLastActive()),
        ];
    }

    public function normalizeDetails(Lead $contact): array
    {
        return [
            'id'                 => $contact->getId(),
            'primary_identifier' => $contact->getPrimaryIdentifier(),
            'name'               => $contact->getName(),
            'firstname'          => $contact->getFirstname(),
            'lastname'           => $contact->getLastname(),
            'email'              => $contact->getEmail(),
            'company'            => $contact->getCompany(),
            'points'             => $contact->getPoints(),
            'stage'              => $this->normalizeStage($contact->getStage()),
            'owner'              => $this->normalizeUser($contact->getOwner()),
            'created_by'         => $this->normalizeUser($contact->getCreatedBy()),
            'date_added'         => $this->formatDate($contact->getDateAdded()),
            'date_modified'      => $this->formatDate($contact->getDateModified()),
            'date_identified'    => $this->formatDate($contact->getDateIdentified()),
            'last_active'        => $this->formatDate($contact->getLastActive()),
            'tags'               => $this->normalizeTags($contact),
            'fields'             => $this->normalizeFields($contact),
        ];
    }

    private function normalizeFields(Lead $contact): array
    {
        $fields = $contact->getProfileFields();
        unset($fields['id']);

        return $fields;
    }

    private function normalizeTags(Lead $contact): array
    {
        $tags = [];

        foreach ($contact->getTags() as $tag) {
            $tags[] = $tag->getTag();
        }

        sort($tags);

        return $tags;
    }

    private function normalizeStage(?Stage $stage): ?array
    {
        if (!$stage instanceof Stage) {
            return null;
        }

        return [
            'id'   => $stage->getId(),
            'name' => $stage->getName(),
        ];
    }

    private function normalizeUser(mixed $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id'   => $user->getId(),
            'name' => $user->getName(),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        return $value->format(\DateTimeInterface::ATOM);
    }
}
