<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

/**
 * One page of a dynamic segment: the members found, and the cursor to pass for the next page, null at the end of the
 * audience. A page may hold fewer members than asked and still have a next one: the scan of one call is bounded.
 */
final readonly class MemberPage
{
    /**
     * @param list<string> $members
     */
    public function __construct(
        public array $members,
        public ?string $next,
    ) {
    }
}
