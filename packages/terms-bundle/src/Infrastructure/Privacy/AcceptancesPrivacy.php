<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;

/**
 * The terms' part of an access or erasure request: the acceptances of the member the subject names — which policy,
 * which version, when, where, and the network when one was kept. Erasing deletes them.
 */
final readonly class AcceptancesPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'terms';

    public function __construct(
        private AcceptanceRepository $acceptances,
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
        $acceptances = $this->acceptances->ofMember($subject->value);

        return [] === $acceptances ? [] : ['acceptances' => array_map(static fn (Acceptance $acceptance): array => [
            'policy' => $acceptance->policy,
            'version' => $acceptance->version,
            'accepted_at' => $acceptance->acceptedAt->format(DateTimeInterface::ATOM),
            'channel' => $acceptance->channel->value,
            'network' => $acceptance->network,
        ], $acceptances)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->acceptances->erase($subject->value);

        return ErasureReport::of(self::MODULE, ['phil_terms_acceptance' => ErasureEffect::Deleted]);
    }
}
