<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * An activity of an xAPI package: its IRI and type, its names and descriptions by language tag, the file or address
 * that launches it, and for a question its interaction type, its correct responses and its lists of components.
 */
final readonly class XapiActivity
{
    public const string COURSE = 'http://adlnet.gov/expapi/activities/course';

    public const string INTERACTION = 'http://adlnet.gov/expapi/activities/cmi.interaction';

    /**
     * @param array<string, string>                     $names            the names by language tag, in lowercase; `und` when none is written
     * @param array<string, string>                     $descriptions     the descriptions by language tag, in lowercase
     * @param ?string                                   $launch           a file of the package, its query or fragment kept, or an absolute
     *                                                                    address in its canonical form; null for an activity that is not
     *                                                                    launched
     * @param list<string>                              $correctResponses the correct response patterns of a question, as written
     * @param array<string, list<InteractionComponent>> $components       the lists of components of a question, by their
     *                                                                    `ComponentList` value, each in order
     */
    public function __construct(
        public string $id,
        public ?string $type = null,
        public array $names = [],
        public array $descriptions = [],
        public ?string $launch = null,
        public ?InteractionType $interactionType = null,
        public array $correctResponses = [],
        public array $components = [],
    ) {
    }

    /**
     * @return list<InteractionComponent> the components of that list, in order; empty when the question has none
     */
    public function components(ComponentList $list): array
    {
        return $this->components[$list->value] ?? [];
    }

    /**
     * The activity definition of xAPI, as a statement or a learning record store carries it: `name` and `description`
     * as language maps, `type`, and for a question `interactionType`, `correctResponsesPattern` and its lists of
     * components; what the package does not give is left out.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $definition = array_filter([
            'name' => $this->names,
            'description' => $this->descriptions,
            'type' => $this->type,
            'interactionType' => $this->interactionType?->value,
        ], static fn (array|string|null $value): bool => null !== $value && [] !== $value);

        if ([] !== $this->correctResponses) {
            $definition['correctResponsesPattern'] = $this->correctResponses;
        }

        foreach (ComponentList::cases() as $list) {
            $components = $this->components($list);

            if ([] !== $components) {
                $definition[$list->value] = array_map(static fn (InteractionComponent $component): array => [] === $component->descriptions
                    ? ['id' => $component->id]
                    : ['id' => $component->id, 'description' => $component->descriptions], $components);
            }
        }

        return $definition;
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
