<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Contract;

use Phil\OnboardingBundle\Application\Answer;

/**
 * What the application grants a user who answered a question: the roles and the subscriptions of the choices they
 * picked. The bundle never writes a role or a subscription itself; it hands them to this port, before it records the
 * step done, so a grant that fails leaves the question to answer again.
 *
 * A grant is idempotent: the same answer may come twice, when two requests race or a flow is started again.
 */
interface AnswerGrants
{
    public function grant(Answer $answer): void;
}
