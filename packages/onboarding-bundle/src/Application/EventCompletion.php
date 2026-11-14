<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Phil\OnboardingBundle\Application\Contract\CurrentUser;
use Phil\OnboardingBundle\Application\Contract\NamesOnboardingUser;
use Phil\OnboardingBundle\Domain\Exception\InvalidProgress;
use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;

/**
 * The listener of the events that complete steps: the user is the one the event names when it implements
 * `NamesOnboardingUser`, else the user of the current request; nobody's event completes nothing. A progress that
 * cannot be saved is passed over, so that the application's own action, which dispatched the event, never fails for
 * the onboarding: the step is completed by the next such event.
 */
final readonly class EventCompletion
{
    public function __construct(
        private FlowCatalog $catalog,
        private Onboarding $onboarding,
        private CurrentUser $currentUser,
    ) {
    }

    public function __invoke(object $event, string $eventName): void
    {
        $user = $event instanceof NamesOnboardingUser ? $event->onboardingUser() : $this->currentUser->id();

        if (null === $user) {
            return;
        }

        foreach ($this->catalog->onEvent($eventName) as $step) {
            try {
                $this->onboarding->complete($user, $step['flow'], $step['step']);
            } catch (InvalidProgress|ProgressNotSaved) {
                // Passed over: see the class's documentation.
            }
        }
    }
}
