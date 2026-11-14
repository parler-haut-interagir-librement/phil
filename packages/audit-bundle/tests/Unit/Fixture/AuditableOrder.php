<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Subject;

/**
 * The subject of a workflow whose transitions are audited: a single state in `state`, several places in `places`.
 */
final class AuditableOrder implements Auditable
{
    public string $state = 'cart';

    /** @var array<string, int> */
    public array $places = ['a' => 1];

    public function __construct(
        private readonly string $id,
    ) {
    }

    #[Override]
    public function auditSubject(): Subject
    {
        return Subject::of('order', $this->id);
    }
}
