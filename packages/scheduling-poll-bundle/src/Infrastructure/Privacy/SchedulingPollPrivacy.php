<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;

/**
 * The scheduling polls' part of an access or erasure request, through the contracts of `phil/privacy-bundle`. The data
 * subject is a member — a reference of the account kind configured, `user` by default — or, for a person without an
 * account, their address, as the privacy bundle names a guest by default.
 *
 * The export lists every answer of the person: the poll, the name and address they gave, their answers and when. The
 * erasure deletes their respondents; the links, which name only a respondent, open nothing more. Running it again finds
 * nothing and reports the same.
 */
final readonly class SchedulingPollPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'scheduling_poll';

    public function __construct(
        private RespondentRepository $respondents,
        private SchedulingPollRepository $polls,
        private string $accountKind = 'user',
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        [$account, $email] = $this->person($subject);
        $answers = array_map(fn (Respondent $respondent): array => array_filter([
            'poll' => $respondent->poll,
            'title' => $this->polls->get($respondent->poll)?->title(),
            'name' => $respondent->name(),
            'email' => $respondent->email(),
            'account' => $respondent->account()?->toString(),
            'locale' => $respondent->locale,
            'timezone' => $respondent->timezone(),
            'joined_at' => $respondent->joinedAt()->format(DateTimeInterface::ATOM),
            'answered_at' => $respondent->answeredAt()?->format(DateTimeInterface::ATOM),
            'answers' => array_map(static fn (Availability $answer): string => $answer->value, $respondent->answers()),
            'revoked_at' => $respondent->revokedAt()?->format(DateTimeInterface::ATOM),
        ], static fn (mixed $value): bool => null !== $value && [] !== $value), $this->respondents->ofPerson($account, $email));

        return [] === $answers ? [] : ['respondents' => $answers];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        [$account, $email] = $this->person($subject);
        $this->respondents->forgetPerson($account, $email);

        return ErasureReport::of(self::MODULE, [Respondent::TABLE => ErasureEffect::Deleted]);
    }

    /**
     * The account and the address a subject may be.
     *
     * @return array{?ResourceReference, ?string}
     */
    private function person(DataSubject $subject): array
    {
        if (str_contains($subject->value, '@')) {
            try {
                return [null, EmailAddress::parse($subject->value)->toAscii()];
            } catch (InvalidEmailAddress) {
                return [null, null];
            }
        }

        return [ResourceReference::of($this->accountKind, $subject->value), null];
    }
}
