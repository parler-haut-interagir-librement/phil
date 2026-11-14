<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\AccountDeletion;

use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The life of an account deletion, on `symfony/workflow`:
 *
 * | Transition | From | To |
 * |---|---|---|
 * | `cancel` | `requested` | `cancelled` |
 * | `export` | `requested` | `exported` |
 * | `erase` | `exported` | `erased` |
 *
 * With an event dispatcher, the events of `symfony/workflow` are dispatched under the name
 * `phil_privacy_account_deletion`: `workflow.phil_privacy_account_deletion.completed.export` is where an application
 * hands the export to the person, before the erasure.
 */
final class DeletionWorkflow
{
    public const string NAME = 'phil_privacy_account_deletion';

    public const string SERVICE = 'phil_privacy.account_deletion_workflow';

    public const string CANCEL = 'cancel';

    public const string EXPORT = 'export';

    public const string ERASE = 'erase';

    private function __construct()
    {
    }

    public static function create(?EventDispatcherInterface $dispatcher = null): StateMachine
    {
        return new StateMachine(self::definition(), new MethodMarkingStore(true, 'place'), $dispatcher, self::NAME);
    }

    public static function definition(): Definition
    {
        $requested = DeletionPlace::Requested->value;
        $exported = DeletionPlace::Exported->value;
        $erased = DeletionPlace::Erased->value;
        $cancelled = DeletionPlace::Cancelled->value;

        return new Definition(
            [$requested, $exported, $erased, $cancelled],
            [
                new Transition(self::CANCEL, $requested, $cancelled),
                new Transition(self::EXPORT, $requested, $exported),
                new Transition(self::ERASE, $exported, $erased),
            ],
            $requested,
        );
    }
}
