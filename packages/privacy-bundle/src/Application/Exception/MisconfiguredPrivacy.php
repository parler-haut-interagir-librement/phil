<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use LogicException;
use Phil\PrivacyBundle\Domain\Exception\PrivacyError;

/**
 * The modules, purgers or schedule given to the bundle do not fit together. Raised before anything is exported,
 * erased or purged: a request half answered is worse than one refused.
 */
final class MisconfiguredPrivacy extends LogicException implements PrivacyError
{
    public static function duplicateModule(string $module): self
    {
        return new self(\sprintf('Two modules answer subject requests under the name "%s".', $module));
    }

    public static function duplicatePurger(string $store): self
    {
        return new self(\sprintf('Two retention purgers answer for the store "%s".', $store));
    }

    /**
     * @param list<string> $rules
     */
    public static function missingPurger(string $store, array $rules): self
    {
        return new self(\sprintf('No retention purger answers for the store "%s", which rule(s) %s enforce by a job.', $store, implode(', ', $rules)));
    }

    /**
     * @param class-string $user
     */
    public static function userWithoutSubject(string $user): self
    {
        return new self(\sprintf('The user class "%s" does not implement DataSubjectUser: say which stable identifier designates the person, or name another phil_privacy.pages.current_subject.', $user));
    }

    public static function erasureLease(int $lease): self
    {
        return new self(\sprintf('An erasure step is leased from 1 to 86400 seconds, not %d.', $lease));
    }
}
