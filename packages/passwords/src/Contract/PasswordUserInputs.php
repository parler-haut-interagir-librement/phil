<?php

declare(strict_types=1);

namespace Phil\Passwords\Contract;

/**
 * Implemented by an object whose password the `MinimumStrength` constraint validates, to give the estimator what the
 * application knows of the person — name, email address, username —, which an attacker who targets them tries first.
 */
interface PasswordUserInputs
{
    /**
     * @return list<string>
     */
    public function passwordUserInputs(): array;
}
