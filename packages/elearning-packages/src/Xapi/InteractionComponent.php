<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * A component of a question: a choice, a point of a scale, a source or a target to match, a step of a performance.
 * Its identifier is what a correct response, and a learner's response, name it by.
 */
final readonly class InteractionComponent
{
    /**
     * @param array<string, string> $descriptions the descriptions by language tag, in lowercase
     */
    public function __construct(
        public string $id,
        public array $descriptions = [],
    ) {
    }

    /**
     * The description in that language, chosen as the name of an activity is; empty when there is none.
     */
    public function description(?string $language = null): string
    {
        return new XapiActivity($this->id, descriptions: $this->descriptions)->description($language);
    }
}
