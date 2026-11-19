<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Override;
use Phil\UserSecretsBundle\Application\Audit\SecretEvent;
use Phil\UserSecretsBundle\Application\Contract\SecretAuditor;

/**
 * Keeps every event, and tells them as `kind owner/name [purpose]` lines.
 */
final class RecordingAuditor implements SecretAuditor
{
    /**
     * @var list<SecretEvent>
     */
    public array $events = [];

    #[Override]
    public function record(SecretEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (SecretEvent $event): string => trim(\sprintf('%s %s/%s %s', $event->kind->value, $event->owner->value, $event->name->value, $event->purpose ?? '')), $this->events);
    }
}
