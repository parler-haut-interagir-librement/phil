<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateTimeZone;
use Phil\GuardianConsent\Exception\InvalidConfiguration;
use Phil\GuardianConsent\Exception\UnknownJurisdiction;

/**
 * The jurisdictions an application serves, by code, with the one that applies when none is named.
 */
final readonly class Jurisdictions
{
    public const int MAX_JURISDICTIONS = 300;

    /** @var array<string, Jurisdiction> */
    private array $byCode;

    /**
     * @param Jurisdiction $default   the rules applied when a member's jurisdiction is not named
     * @param Jurisdiction ...$others the other jurisdictions served
     *
     * @throws InvalidConfiguration when a code is given twice, or more than 300 jurisdictions are given
     */
    public function __construct(
        public Jurisdiction $default = new Jurisdiction(Jurisdiction::QUEBEC, 14, 18, new DateTimeZone('America/Toronto')),
        Jurisdiction ...$others,
    ) {
        $byCode = [$default->code => $default];

        foreach ($others as $jurisdiction) {
            if (isset($byCode[$jurisdiction->code])) {
                throw InvalidConfiguration::because(\sprintf('The jurisdiction "%s" is given twice.', $jurisdiction->code));
            }

            $byCode[$jurisdiction->code] = $jurisdiction;
        }

        if (\count($byCode) > self::MAX_JURISDICTIONS) {
            throw InvalidConfiguration::because(\sprintf('At most %d jurisdictions are served.', self::MAX_JURISDICTIONS));
        }

        $this->byCode = $byCode;
    }

    /**
     * @throws UnknownJurisdiction
     */
    public function get(?string $code = null): Jurisdiction
    {
        if (null === $code) {
            return $this->default;
        }

        return $this->byCode[$code] ?? throw UnknownJurisdiction::withCode($code);
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->byCode);
    }
}
