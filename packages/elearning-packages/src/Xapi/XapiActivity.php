<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * An activity of an xAPI package: its IRI and type, its names and descriptions by language tag, the file or address
 * that launches it, and the interaction type of a question.
 */
final readonly class XapiActivity
{
    public const string COURSE = 'http://adlnet.gov/expapi/activities/course';

    public const string INTERACTION = 'http://adlnet.gov/expapi/activities/cmi.interaction';

    /**
     * @param array<string, string> $names        the names by language tag, in lowercase; `und` when none is written
     * @param array<string, string> $descriptions the descriptions by language tag, in lowercase
     * @param ?string               $launch       a file of the package, its query or fragment kept, or an absolute
     *                                            address in its canonical form; null for an activity that is not
     *                                            launched
     */
    public function __construct(
        public string $id,
        public ?string $type = null,
        public array $names = [],
        public array $descriptions = [],
        public ?string $launch = null,
        public ?InteractionType $interactionType = null,
    ) {
    }

    /**
     * The name in that language — the tag itself, else the first of its primary language —, else the first written,
     * else the IRI.
     */
    public function name(?string $language = null): string
    {
        return $this->pick($this->names, $language) ?? $this->id;
    }

    /**
     * The description in that language, chosen as the name is; empty when there is none.
     */
    public function description(?string $language = null): string
    {
        return $this->pick($this->descriptions, $language) ?? '';
    }

    public function isLaunchable(): bool
    {
        return null !== $this->launch;
    }

    /**
     * @param array<string, string> $texts
     */
    private function pick(array $texts, ?string $language): ?string
    {
        if (null !== $language) {
            $tag = strtolower($language);

            if (isset($texts[$tag])) {
                return $texts[$tag];
            }

            $primary = explode('-', $tag)[0].'-';

            foreach ($texts as $key => $text) {
                if (str_starts_with($key.'-', $primary)) {
                    return $text;
                }
            }
        }

        return array_first($texts);
    }
}
