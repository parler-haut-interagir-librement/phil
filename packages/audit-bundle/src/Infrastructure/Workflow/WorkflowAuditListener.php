<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Workflow;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Facts;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Transition;

/**
 * Each completed transition of a workflow whose subject is `Auditable`, recorded in one segment: the action
 * `workflow.<workflow>.<transition>`, the subject the record names, the current actor, and in the payload the
 * workflow, the transition and the places it went from and to — names of the workflow, never a value of the record.
 */
final readonly class WorkflowAuditListener
{
    public function __construct(
        private AuditTrail $trail,
        private string $segment,
        private ?ActorResolver $actors = null,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        $transition = $event->getTransition();

        if (!$subject instanceof Auditable || !$transition instanceof Transition) {
            return;
        }

        $workflow = $event->getWorkflowName();
        /** @var list<string> $froms */
        $froms = $transition->getFroms();
        /** @var list<string> $tos */
        $tos = $transition->getTos();

        $this->trail->record($this->segment, [
            'workflow' => $workflow,
            'transition' => $transition->getName(),
            'from' => implode(',', $froms),
            'to' => implode(',', $tos),
        ], Facts::of($this->action($workflow, $transition->getName()), $this->actors?->current(), $subject->auditSubject()));
    }

    /**
     * `workflow.<workflow>.<transition>`, in lower case, every character an action refuses replaced by `_`, cut to
     * the 64 characters of an action.
     */
    private function action(string $workflow, string $transition): string
    {
        return substr((string) preg_replace('/[^a-z0-9._:-]/', '_', strtolower(\sprintf('workflow.%s.%s', $workflow, $transition))), 0, 64);
    }
}
