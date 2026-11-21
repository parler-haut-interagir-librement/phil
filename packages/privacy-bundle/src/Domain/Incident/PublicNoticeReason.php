<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

/**
 * Why a public notice was given about an incident, among the cases of section 6 of the Québec *Regulation respecting
 * confidentiality incidents* (chapter A-2.1, r. 3.1).
 *
 * In the first three cases the public notice takes the place of the notice to each person; a public notice given to
 * act quickly does not, and the notice to each person is still owed.
 */
enum PublicNoticeReason: string
{
    /**
     * Sending the notice to each person would cause them greater injury.
     */
    case IncreasedInjury = 'increased_injury';

    /**
     * Sending the notice to each person would be excessively difficult.
     */
    case ExcessiveHardship = 'excessive_hardship';

    /**
     * The organisation has no contact details for the people concerned.
     */
    case NoContactDetails = 'no_contact_details';

    /**
     * The public notice was given to act quickly; the notice to each person is still owed.
     */
    case PromptAction = 'prompt_action';

    public function replacesIndividualNotice(): bool
    {
        return self::PromptAction !== $this;
    }
}
