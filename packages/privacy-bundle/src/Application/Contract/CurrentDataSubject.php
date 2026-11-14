<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * The member the current request is about, by the identifier every module indexes its records on.
 */
interface CurrentDataSubject
{
    /**
     * @return DataSubject|null null when nobody is signed in
     */
    public function current(): ?DataSubject;
}
