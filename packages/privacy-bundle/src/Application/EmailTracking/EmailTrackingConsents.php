<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\EmailTracking;

use Override;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\EmailTracking\EmailTrackingChoice;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Psr\Clock\ClockInterface;

/**
 * Each member's consent to tracked emails: what the sender of an email asks before it counts the opens or follows the
 * clicks of a recipient, and what the preferences page records.
 *
 * Nothing is followed without a choice of the member under the current version of the text: a member who never chose,
 * an identifier no member can have, or a choice made under an earlier version all answer no. The choice is part of
 * the member's export, and their erasure removes it.
 */
final readonly class EmailTrackingConsents implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'email_tracking';

    public function __construct(
        private EmailTrackingStore $store,
        private ClockInterface $clock,
        private string $version,
    ) {
        if (1 !== preg_match(ConsentPolicy::VERSION_PATTERN, $version)) {
            throw InvalidPrivacyValue::because('This is not a version of the text about tracked emails.');
        }
    }

    /**
     * Whether the opens of the emails sent to this member may be counted.
     *
     * @param string $recipient the member's data subject identifier
     */
    public function opens(string $recipient): bool
    {
        return $this->choiceOf($recipient)?->allowsOpens($this->version) ?? false;
    }

    /**
     * Whether the clicks in the emails sent to this member may be followed.
     *
     * @param string $recipient the member's data subject identifier
     */
    public function clicks(string $recipient): bool
    {
        return $this->choiceOf($recipient)?->allowsClicks($this->version) ?? false;
    }

    /**
     * The member's choice under the current version of the text, or null when they have none to show: never asked, or
     * asked about an earlier text.
     */
    public function current(DataSubject $subject): ?EmailTrackingChoice
    {
        $choice = $this->store->find($subject);

        return $choice instanceof EmailTrackingChoice && $choice->version === $this->version ? $choice : null;
    }

    /**
     * Records the member's choice, dated, against the current version of the text, in place of their earlier one.
     */
    public function decide(DataSubject $subject, bool $opens, bool $clicks): EmailTrackingChoice
    {
        $choice = new EmailTrackingChoice($subject, $this->version, $opens, $clicks, $this->clock->now());
        $this->store->save($choice);

        return $choice;
    }

    public function version(): string
    {
        return $this->version;
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        return $this->store->find($subject)?->toArray() ?? [];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->store->forget($subject);

        return ErasureReport::of(self::MODULE, [$this->store->store() => ErasureEffect::Deleted]);
    }

    private function choiceOf(string $recipient): ?EmailTrackingChoice
    {
        try {
            return $this->store->find(DataSubject::fromString($recipient));
        } catch (InvalidPrivacyValue) {
            return null;
        }
    }
}
