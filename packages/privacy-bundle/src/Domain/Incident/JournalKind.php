<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

/**
 * What an entry of the journal of an incident records, among the steps sections 3.5 and 3.7 of the Québec *Act
 * respecting the protection of personal information in the private sector* (chapter P-39.1) ask to keep a trace of.
 */
enum JournalKind: string
{
    /**
     * Personal information concerned by the incident was communicated, without the consent of the person, to someone
     * who could reduce the risk of injury, and only what that purpose needed (section 3.5): the person in charge of the
     * protection of personal information records the communication.
     */
    case ThirdPartyCommunication = 'third_party_communication';

    /**
     * The notice to the people concerned is put off, because giving it could hamper an investigation by a person or a
     * body in charge by law of the prevention, detection or repression of crime (section 3.5).
     */
    case NoticeDeferral = 'notice_deferral';

    /**
     * The investigation no longer stands in the way of the notice put off, which is now owed promptly.
     */
    case DeferralLifted = 'deferral_lifted';

    /**
     * The person in charge of the protection of personal information was consulted on the risk of injury (section 3.7).
     */
    case OfficerConsultation = 'officer_consultation';

    /**
     * Whether an entry of this kind says what was done and why: the information communicated and the purpose, the
     * reason of the deferral, or the opinion given.
     */
    public function needsDetails(): bool
    {
        return self::DeferralLifted !== $this;
    }

    /**
     * The section of chapter P-39.1 the entry answers.
     */
    public function section(): string
    {
        return self::OfficerConsultation === $this ? '3.7' : '3.5';
    }
}
