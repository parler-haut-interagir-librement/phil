<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;

/**
 * The publication of a `Publishable` record as a state machine: a draft, published, then withdrawn and published
 * again. An application can write its own with these places, or add to them.
 */
final class PublicationWorkflow
{
    public const string NAME = 'phil_publication';

    public const string MARKING_PROPERTY = 'publicationState';

    public const string DRAFT = 'draft';

    public const string PUBLISHED = 'published';

    public const string UNPUBLISHED = 'unpublished';

    public const string PUBLISH = 'publish';

    public const string UNPUBLISH = 'unpublish';

    public const array PLACES = [self::DRAFT, self::PUBLISHED, self::UNPUBLISHED];

    /**
     * @var array<string, array{from: list<string>, to: string}>
     */
    public const array TRANSITIONS = [
        self::PUBLISH => ['from' => [self::DRAFT, self::UNPUBLISHED], 'to' => self::PUBLISHED],
        self::UNPUBLISH => ['from' => [self::PUBLISHED], 'to' => self::UNPUBLISHED],
    ];

    public static function definition(): Definition
    {
        $transitions = [];

        foreach (self::TRANSITIONS as $name => $transition) {
            foreach ($transition['from'] as $from) {
                $transitions[] = new Transition($name, $from, $transition['to']);
            }
        }

        // The first place, the draft, is the initial one, as Symfony makes it.
        return new Definition(self::PLACES, $transitions);
    }

    public static function markingStore(): MethodMarkingStore
    {
        return new MethodMarkingStore(true, self::MARKING_PROPERTY);
    }
}
