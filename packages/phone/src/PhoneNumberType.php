<?php

declare(strict_types=1);

namespace Phil\Phone;

/**
 * What a number is, as its country's numbering plan tells.
 */
enum PhoneNumberType: string
{
    case FixedLine = 'fixed_line';
    case Mobile = 'mobile';
    /** A number of a plan where landlines and mobiles cannot be told apart, as in North America. */
    case FixedLineOrMobile = 'fixed_line_or_mobile';
    case TollFree = 'toll_free';
    case PremiumRate = 'premium_rate';
    /** A number whose cost is shared between the caller and the called. */
    case SharedCost = 'shared_cost';
    case Voip = 'voip';
    /** A number that follows a person to whatever line they are on. */
    case PersonalNumber = 'personal_number';
    case Pager = 'pager';
    /** A universal access number, reaching a business at whatever office. */
    case Uan = 'uan';
    case Voicemail = 'voicemail';
    case Unknown = 'unknown';
}
