<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The editorial workflow of a localized draft row, on `symfony/workflow`.
 *
 * | Place | Means |
 * |---|---|
 * | `unpublished` | never published, or taken offline: readers get nothing in this locale |
 * | `draft` | published, with changes readers do not see yet |
 * | `published` | readers are served exactly this draft |
 *
 * | Transition | From | To |
 * |---|---|---|
 * | `publish` | `unpublished`, `draft` | `published` |
 * | `unpublish` | `published`, `draft` | `unpublished` |
 * | `create_draft` | `published` | `draft` |
 * | `remove_draft` | `draft` | `published` |
 *
 * A state machine, since a row stands in one place at a time, stored through the row's `workflowPlace` by the
 * method marking store. The workflow only says what may happen; `ContentPublisher` and `ContentEditor` do the
 * copying. With an event dispatcher, the usual events of `symfony/workflow` are dispatched under the name
 * `phil_content` (`workflow.phil_content.guard.publish`, …), so the application guards a transition with a
 * listener.
 */
final readonly class ContentWorkflow
{
    public const string NAME = 'phil_content';

    public const string UNPUBLISHED = 'unpublished';

    public const string DRAFT = 'draft';

    public const string PUBLISHED = 'published';

    public const string PUBLISH = 'publish';

    public const string UNPUBLISH = 'unpublish';

    public const string CREATE_DRAFT = 'create_draft';

    public const string REMOVE_DRAFT = 'remove_draft';

    public function __construct(
        private WorkflowInterface $workflow,
    ) {
    }

    public static function create(?EventDispatcherInterface $dispatcher = null): self
    {
        return new self(new StateMachine(
            self::definition(),
            new MethodMarkingStore(true, 'workflowPlace'),
            $dispatcher,
            self::NAME,
        ));
    }

    public static function definition(): Definition
    {
        return new Definition(
            [self::UNPUBLISHED, self::DRAFT, self::PUBLISHED],
            [
                new Transition(self::PUBLISH, self::UNPUBLISHED, self::PUBLISHED),
                new Transition(self::PUBLISH, self::DRAFT, self::PUBLISHED),
                new Transition(self::UNPUBLISH, self::PUBLISHED, self::UNPUBLISHED),
                new Transition(self::UNPUBLISH, self::DRAFT, self::UNPUBLISHED),
                new Transition(self::CREATE_DRAFT, self::PUBLISHED, self::DRAFT),
                new Transition(self::REMOVE_DRAFT, self::DRAFT, self::PUBLISHED),
            ],
            self::UNPUBLISHED,
        );
    }

    public function workflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    public function can(DimensionContent $row, string $transition): bool
    {
        return $row instanceof WorkflowCapable && $this->workflow->can($row, $transition);
    }

    /**
     * @throws TransitionRefused when the row is not `WorkflowCapable`, or the transition is not enabled
     */
    public function apply(DimensionContent $row, string $transition): void
    {
        $capable = $this->workflowCapable($row);

        if (!$this->workflow->can($row, $transition)) {
            throw TransitionRefused::from($transition, $capable->getWorkflowPlace(), $row->dimension()->toString());
        }

        $this->workflow->apply($row, $transition);
    }

    private function workflowCapable(DimensionContent $row): WorkflowCapable
    {
        if (!$row instanceof WorkflowCapable) {
            throw TransitionRefused::withoutWorkflow($row::class);
        }

        return $row;
    }
}
