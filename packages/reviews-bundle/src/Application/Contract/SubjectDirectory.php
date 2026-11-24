<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application\Contract;

use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * What the application tells of a subject: the name the pages and the moderation show, and the address of its page.
 */
interface SubjectDirectory
{
    public function title(SubjectRef $subject): string;

    public function url(SubjectRef $subject): ?string;
}
