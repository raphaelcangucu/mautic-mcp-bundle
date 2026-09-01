<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Analytics;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AnalyticsService
{
    public function __construct(
        private Connection $connection,
        private CorePermissions $permissions,
    ) {}

    public function query(string $report, ?string $from, ?string $to, string $groupBy, int $limit, int $page): array
    {
        $this->assertCanView($report);
        $fromDate = new \DateTimeImmutable($from ?: '-30 days');
        $toDate   = new \DateTimeImmutable($to ?: 'now');
        $limit    = max(1, min(500, $limit));
        $page     = max(1, $page);
        if ($fromDate > $toDate) {
            throw new BadRequestHttpException('from must be earlier than to.');
        }

        $rows = match ($report) {
            'campaign_performance' => $this->campaignPerformance($fromDate, $toDate),
            'email_performance'    => $this->emailPerformance($fromDate, $toDate),
            'contact_growth'       => $this->contactGrowth($fromDate, $toDate, $groupBy),
            'segment_growth'       => $this->segmentGrowth(),
            'contacts_by_source'   => $this->contactsBySource($fromDate, $toDate),
            'contacts_by_tag'      => $this->contactsByTag($fromDate, $toDate),
            default                => throw new BadRequestHttpException('Unsupported report.'),
        };

        $total = count($rows);
        $rows = array_slice($rows, ($page - 1) * $limit, $limit);
        $hasMore = $page * $limit < $total;

        return [
            'report' => $report, 'from' => $fromDate->format(DATE_ATOM), 'to' => $toDate->format(DATE_ATOM),
            'groupBy' => $groupBy, 'page' => $page, 'limit' => $limit, 'count' => count($rows), 'total' => $total,
            'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null, 'rows' => $rows,
        ];
    }

    private function campaignPerformance(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $sql = 'SELECT c.id, c.name,
            COUNT(DISTINCT clel.lead_id) contacts,
            COUNT(DISTINCT clel.id) triggeredEvents,
            COUNT(DISTINCT es.id) emailDeliveries,
            SUM(CASE WHEN es.is_read = 1 THEN 1 ELSE 0 END) emailReads,
            SUM(CASE WHEN es.is_failed = 1 THEN 1 ELSE 0 END) emailFailures,
            COUNT(DISTINCT ph.id) clicks
            FROM '.MAUTIC_TABLE_PREFIX.'campaigns c
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'campaign_lead_event_log clel ON clel.campaign_id = c.id AND clel.date_triggered BETWEEN :from AND :to
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'campaign_events ce ON ce.id = clel.event_id
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'email_stats es ON es.source = :source AND es.source_id = ce.id AND es.date_sent BETWEEN :from AND :to
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'page_hits ph ON ph.email_id = es.email_id AND ph.lead_id = es.lead_id AND ph.date_hit BETWEEN :from AND :to
            GROUP BY c.id, c.name ORDER BY emailDeliveries DESC';

        return $this->connection->fetchAllAssociative($sql, $this->dates($from, $to) + ['source' => 'campaign.event']);
    }

    private function emailPerformance(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $sql = 'SELECT e.id, e.name, e.subject, COUNT(DISTINCT es.id) sent,
            SUM(CASE WHEN es.is_read = 1 THEN 1 ELSE 0 END) opened,
            SUM(CASE WHEN es.is_failed = 1 THEN 1 ELSE 0 END) failed,
            SUM(es.open_count) totalOpens, COUNT(DISTINCT ph.id) clicks,
            SUM(CASE WHEN dnc.reason = 1 THEN 1 ELSE 0 END) unsubscribed,
            SUM(CASE WHEN dnc.reason = 2 THEN 1 ELSE 0 END) bounced
            FROM '.MAUTIC_TABLE_PREFIX.'emails e
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'email_stats es ON es.email_id = e.id AND es.date_sent BETWEEN :from AND :to
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'page_hits ph ON ph.email_id = e.id AND ph.lead_id = es.lead_id AND ph.date_hit BETWEEN :from AND :to
            LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_donotcontact dnc ON dnc.lead_id = es.lead_id AND dnc.channel = :channel
            GROUP BY e.id, e.name, e.subject ORDER BY sent DESC';

        return $this->connection->fetchAllAssociative($sql, $this->dates($from, $to) + ['channel' => 'email']);
    }

    private function contactGrowth(\DateTimeImmutable $from, \DateTimeImmutable $to, string $groupBy): array
    {
        $period = $this->periodExpression('date_added', $groupBy);

        return $this->connection->fetchAllAssociative(
            'SELECT '.$period.' period, COUNT(*) contacts FROM '.MAUTIC_TABLE_PREFIX.'leads WHERE date_added BETWEEN :from AND :to GROUP BY period ORDER BY period',
            $this->dates($from, $to),
        );
    }

    private function segmentGrowth(): array
    {
        return $this->connection->fetchAllAssociative('SELECT ll.id, ll.name, COUNT(DISTINCT lll.lead_id) contacts FROM '.MAUTIC_TABLE_PREFIX.'lead_lists ll LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_lists_leads lll ON lll.leadlist_id = ll.id AND lll.manually_removed = 0 GROUP BY ll.id, ll.name ORDER BY contacts DESC');
    }

    private function contactsBySource(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->connection->fetchAllAssociative('SELECT COALESCE(created_by_user, :unknown) source, COUNT(*) contacts FROM '.MAUTIC_TABLE_PREFIX.'leads WHERE date_added BETWEEN :from AND :to GROUP BY source ORDER BY contacts DESC', $this->dates($from, $to) + ['unknown' => 'unknown']);
    }

    private function contactsByTag(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->connection->fetchAllAssociative('SELECT t.id, t.tag, COUNT(DISTINCT tx.lead_id) contacts FROM '.MAUTIC_TABLE_PREFIX.'lead_tags t JOIN '.MAUTIC_TABLE_PREFIX.'lead_tags_xref tx ON tx.tag_id = t.id JOIN '.MAUTIC_TABLE_PREFIX.'leads l ON l.id = tx.lead_id WHERE l.date_added BETWEEN :from AND :to GROUP BY t.id, t.tag ORDER BY contacts DESC', $this->dates($from, $to));
    }

    private function periodExpression(string $column, string $groupBy): string
    {
        return match ($groupBy) {
            'day'   => 'DATE('.$column.')',
            'week'  => 'DATE_FORMAT('.$column.', \'%x-W%v\')',
            'month' => 'DATE_FORMAT('.$column.', \'%Y-%m\')',
            default => throw new BadRequestHttpException('groupBy must be day, week, or month.'),
        };
    }

    private function dates(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return ['from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s')];
    }

    private function assertCanView(string $report): void
    {
        $permissions = str_starts_with($report, 'campaign_') ? ['campaign:campaigns:viewown', 'campaign:campaigns:viewother'] : ['lead:leads:viewown', 'lead:leads:viewother', 'email:emails:viewown', 'email:emails:viewother'];
        foreach ($permissions as $permission) {
            if ($this->permissions->checkPermissionExists($permission) && $this->permissions->isGranted($permission)) {
                return;
            }
        }
        throw new AccessDeniedException('Permission denied for analytics.');
    }
}
