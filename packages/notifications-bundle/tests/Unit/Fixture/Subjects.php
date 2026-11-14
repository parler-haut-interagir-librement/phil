<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Resolves the subjects of one kind: their label is a prefix, the kind, the identifier and the locale; it counts the
 * calls.
 */
final class Subjects implements SubjectResolver
{
    public int $calls = 0;

    public function __construct(
        private readonly string $type = 'comment',
        private readonly string $prefix = '',
    ) {
    }

    #[Override]
    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject
    {
        ++$this->calls;

        return $this->type === $subject->type ? new ResolvedSubject($this->prefix.$subject->type.' '.$subject->id.(null === $locale ? '' : ' ('.$locale.')'), '/'.$subject->type.'s/'.$subject->id) : null;
    }
}
