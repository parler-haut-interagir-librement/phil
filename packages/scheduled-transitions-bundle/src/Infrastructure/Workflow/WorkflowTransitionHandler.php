<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Workflow;

use LogicException;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\TransitionHandler;
use Symfony\Component\Workflow\Registry;

/**
 * Applies a scheduled transition through the subject's workflow: `publish` is the workflow's own transition, with its
 * guards and its events. A transition the workflow does not allow now is a failed attempt. Without workflows, it applies
 * nothing.
 */
final readonly class WorkflowTransitionHandler implements TransitionHandler
{
    /**
     * @param iterable<SubjectResolver> $resolvers
     */
    public function __construct(
        private ?Registry $workflows,
        private iterable $resolvers,
    ) {
    }

    #[Override]
    public function supports(ResourceReference $resource, string $transition): bool
    {
        return $this->resolve($resource) instanceof Subject;
    }

    #[Override]
    public function apply(ResourceReference $resource, string $transition): void
    {
        $subject = $this->resolve($resource);

        if (!$subject instanceof Subject) {
            throw new LogicException(\sprintf('%s is gone.', $resource->toString()));
        }

        $subject->workflow->apply($subject->object, $transition);
        $subject->resolver->saved($subject->object);
    }

    private function resolve(ResourceReference $resource): ?Subject
    {
        if (!$this->workflows instanceof Registry) {
            return null;
        }

        foreach ($this->resolvers as $resolver) {
            $object = $resolver->resolve($resource);

            if (null !== $object && $this->workflows->has($object)) {
                return new Subject($object, $this->workflows->get($object), $resolver);
            }
        }

        return null;
    }
}
