<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Render;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\StatusPageBundle\Domain\ValueObject\ComponentState;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\IncidentUpdate;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;

/**
 * The page as JSON, for tools and other pages to read: the overall state, each component, the incidents going on,
 * the maintenances in progress and upcoming, and the history. Dates are ISO 8601 in UTC; keys are snake case; a value
 * that is not known is `null`. The shape is described in the documentation and kept within a major version.
 */
final readonly class JsonDocument
{
    public const string VERSION = '1';

    public function render(StatusPage $page, PageLinks $links): string
    {
        return json_encode([
            'version' => self::VERSION,
            'page' => ['title' => $page->title, 'url' => $links->page, 'generated_at' => self::date($page->generatedAt)],
            'status' => $page->status()->value,
            'components' => array_map(static fn (ComponentState $state): array => [
                'id' => $state->component->id,
                'name' => $state->component->name,
                'description' => $state->component->description,
                'group' => $state->component->group,
                'status' => $state->status->value,
            ], $page->components),
            'incidents' => array_map($this->incident(...), $page->activeIncidents),
            'maintenances' => [
                ...array_map(static fn (ScheduledMaintenance $maintenance): array => self::maintenance($maintenance, 'in_progress'), $page->maintenancesInProgress),
                ...array_map(static fn (ScheduledMaintenance $maintenance): array => self::maintenance($maintenance, 'upcoming'), $page->upcomingMaintenances),
            ],
            'history' => array_map($this->incident(...), $page->history),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function incident(Incident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'impact' => $incident->impact->value,
            'state' => $incident->state()->value,
            'components' => $incident->components,
            'started_at' => self::date($incident->startedAt()),
            'updated_at' => self::date($incident->updatedAt()),
            'resolved_at' => $incident->resolvedAt() instanceof DateTimeImmutable ? self::date($incident->resolvedAt()) : null,
            'updates' => array_map(static fn (IncidentUpdate $update): array => [
                'at' => self::date($update->at),
                'state' => $update->state->value,
                'message' => $update->message,
            ], $incident->updates),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function maintenance(ScheduledMaintenance $maintenance, string $state): array
    {
        return [
            'id' => $maintenance->id,
            'title' => $maintenance->title,
            'description' => $maintenance->description,
            'state' => $state,
            'components' => $maintenance->components,
            'starts_at' => self::date($maintenance->startsAt),
            'ends_at' => self::date($maintenance->endsAt),
        ];
    }

    private static function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}
