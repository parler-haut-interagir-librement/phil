<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables;

use Phil\BenefitDeliverables\Exception\InvalidDeliverable;

/**
 * The checks every member identifier passes: the stable identifier the application gives a member, 1 to 255
 * characters, never an email address, which would change and would land in the files.
 *
 * @internal
 */
final class Member
{
    public static function check(string $member): string
    {
        if ('' === trim($member) || \strlen($member) > 255) {
            throw InvalidDeliverable::because('A member is named by 1 to 255 characters.');
        }

        if (str_contains($member, '@')) {
            throw InvalidDeliverable::because('A member is named by a stable identifier, never an email address.');
        }

        return $member;
    }
}
