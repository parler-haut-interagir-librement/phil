<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Application\Contract\AddressMasker;
use Phil\TermsBundle\Application\Contract\PolicyTexts;
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Application\Event\TermsAccepted;
use Phil\TermsBundle\Application\Event\VersionPublished;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Contract\AcceptanceRepository;
use Phil\TermsBundle\Domain\Contract\PolicyVersionRepository;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * What the application calls to publish a version and to record an acceptance.
 *
 * A version is numbered after the latest of its policy and takes effect when it is published or at a later moment — a
 * moment already past is the moment of publication —; a material one is announced to the members who have not
 * accepted it. A version of the policy that names the licence of the contributions names one, and only such a version
 * does. An acceptance is of the version in force or of a later one already published, never of a version a
 * later one replaced; the address of the request is kept only as the masker turns it, and not at all without one.
 */
final readonly class Terms
{
    public function __construct(
        private Policies $policies,
        private VersionCatalog $catalog,
        private PolicyVersionRepository $versions,
        private PolicyTexts $texts,
        private AcceptanceRepository $acceptances,
        private ClockInterface $clock,
        private ?AddressMasker $masker = null,
        private ?TermsNotifier $notifier = null,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $texts   the text of each locale, by locale
     * @param string|null             $licence the SPDX identifier of the licence of the contributions
     *
     * @throws InvalidTerms
     */
    public function publish(string $policy, array $texts, TextFormat $format = TextFormat::Text, ?DateTimeImmutable $effectiveAt = null, bool $material = true, ?string $licence = null): PolicyVersion
    {
        $declared = $this->policies->get($policy);
        $policy = $declared->key;

        if ($declared->contributionLicence && null === $licence) {
            throw InvalidTerms::because('licence_required', 'A version of policy "%policy%" names the licence of the contributions.', ['%policy%' => $policy]);
        }

        if (!$declared->contributionLicence && null !== $licence) {
            throw InvalidTerms::because('licence_not_expected', 'Policy "%policy%" does not name the licence of the contributions.', ['%policy%' => $policy]);
        }

        $now = $this->clock->now();
        $number = ($this->catalog->latest($policy)->number ?? 0) + 1;
        $effective = max($now, $effectiveAt ?? $now);
        $version = new PolicyVersion($policy, $number, $texts, $format, $now, $effective, $material || 1 === $number, $licence);

        foreach ($version->texts as $text) {
            $this->texts->check($format, $text);
        }

        $this->versions->add($version);
        $this->catalog->forget($policy);
        $this->events?->dispatch(new VersionPublished($policy, $number, $version->material));

        if ($version->material) {
            $this->notifier?->published($version);
        }

        return $version;
    }

    /**
     * @throws InvalidTerms when the policy, the version or the member is not one to accept
     */
    public function accept(string $member, string $policy, int $version, AcceptanceChannel $channel, ?string $address = null): Acceptance
    {
        $policy = $this->policies->get($policy)->key;
        $now = $this->clock->now();
        $accepted = $this->catalog->find($policy, $version) ?? throw InvalidTerms::because('unknown_version', 'Policy "%policy%" has no version %version%.', ['%policy%' => $policy, '%version%' => (string) $version]);
        $inForce = $this->catalog->inForce($policy, $now);

        if ($inForce instanceof PolicyVersion && $accepted->number < $inForce->number) {
            throw InvalidTerms::because('superseded_version', 'Version %version% of policy "%policy%" was replaced by version %current%.', ['%policy%' => $policy, '%version%' => (string) $version, '%current%' => (string) $inForce->number]);
        }

        $acceptance = new Acceptance(Identifiers::member($member), $policy, $version, $now, $channel, null === $address ? null : $this->masker?->mask($address));
        $this->acceptances->append($acceptance);
        $this->events?->dispatch(new TermsAccepted($member, $policy, $version, $channel));

        return $acceptance;
    }
}
