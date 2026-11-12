<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The publication workflow: a content in one locale (`Publication`) published or taken offline through
 * `symfony/workflow`, so that anything applying workflows — a scheduler, an admin — publishes a content without
 * knowing the bundle.
 *
 * | Transition | From | To | Does |
 * |---|---|---|---|
 * | `publish` | `unpublished`, `draft` | `published` | `ContentManager::publish()` |
 * | `unpublish` | `published`, `draft` | `unpublished` | `ContentManager::unpublish()` |
 *
 * The places are those of the `phil_content` workflow of the draft row, read and stored by
 * `PublicationMarkingStore`, and `no_draft`, the initial place, where a locale without a draft row stands: no
 * transition leaves it. With an event dispatcher, the events of `symfony/workflow` are dispatched under the name
 * `phil_content_publication`; the container's `workflow.registry`, when there is one, gets the workflow for
 * `Publication` subjects.
 */
final class PublicationWorkflow
{
    public const string NAME = 'phil_content_publication';

    public const string SERVICE = 'phil_content.publication_workflow';

    public const string NO_DRAFT = 'no_draft';

    private function __construct()
    {
    }

    public static function create(PublicationMarkingStore $places, ?EventDispatcherInterface $dispatcher = null): StateMachine
    {
        return new StateMachine(self::definition(), $places, $dispatcher, self::NAME);
    }

    public static function definition(): Definition
    {
        return new Definition(
            [self::NO_DRAFT, ContentWorkflow::UNPUBLISHED, ContentWorkflow::DRAFT, ContentWorkflow::PUBLISHED],
            [
                new Transition(ContentWorkflow::PUBLISH, ContentWorkflow::UNPUBLISHED, ContentWorkflow::PUBLISHED),
                new Transition(ContentWorkflow::PUBLISH, ContentWorkflow::DRAFT, ContentWorkflow::PUBLISHED),
                new Transition(ContentWorkflow::UNPUBLISH, ContentWorkflow::PUBLISHED, ContentWorkflow::UNPUBLISHED),
                new Transition(ContentWorkflow::UNPUBLISH, ContentWorkflow::DRAFT, ContentWorkflow::UNPUBLISHED),
            ],
            self::NO_DRAFT,
        );
    }
}
