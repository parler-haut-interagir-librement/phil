<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;

/**
 * No draft of a notice was written: the register does not record what the notice asked for needs.
 */
final class NoticeRefused extends RuntimeException implements PrivacyError
{
    public static function publicWithoutReason(): self
    {
        return new self('A public notice is drafted only for an incident with a public notice reason (section 6 of the regulation): sending the notice to each person would cause them greater injury (increased_injury), would be excessively difficult (excessive_hardship), or the organisation has no contact details for them (no_contact_details); a public notice may also be given to act quickly (prompt_action). Record the reason with phil:privacy:incident --id=… --public-notice=… --public-notice-reason=…, or draft the notice to each person with --to=subject.');
    }

    public static function followUpWithoutNotice(): self
    {
        return new self('A follow-up completes a notice already given to the Commission (section 4 of the regulation), and the register records none for this incident: draft the notice first, or give the date of the last notice with --since.');
    }

    public static function followUpWithoutHistory(): self
    {
        return new self('A follow-up lists what changed since the last notice, and this register does not keep its corrections (IncidentHistory).');
    }

    public static function invalidSince(): self
    {
        return new self('--since is a date, such as 2026-11-10 or "2026-11-10 14:30".');
    }
}
