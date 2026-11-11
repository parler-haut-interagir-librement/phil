<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * A face of the product — the front, the back, a sleeve — and its print zones, the first one the default.
 */
final readonly class Face
{
    /**
     * @param non-empty-array<string, PrintZone> $zones by name, in order
     */
    private function __construct(
        public string $code,
        public Label $label,
        private array $zones,
    ) {
    }

    /**
     * @param string $code up to 32 lowercase letters, digits or "_", starting with a letter
     */
    public static function of(string $code, Label $label, PrintZone ...$zones): self
    {
        Check::identifier($code, 'a face');

        if ([] === $zones) {
            throw InvalidDefinition::because(\sprintf('The face %s has one print zone at least.', $code));
        }

        $keyed = [];

        foreach ($zones as $zone) {
            if (isset($keyed[$zone->name])) {
                throw InvalidDefinition::because(\sprintf('The face %s lists the zone %s twice.', $code, $zone->name));
            }

            $keyed[$zone->name] = $zone;
        }

        return new self($code, $label, $keyed);
    }

    /**
     * @return non-empty-list<PrintZone> in order
     */
    public function zones(): array
    {
        return array_values($this->zones);
    }

    /**
     * The zone of that name, or the first one for `null`; `null` for a name the face does not have.
     */
    public function zone(?string $name = null): ?PrintZone
    {
        return null === $name ? array_first($this->zones) : ($this->zones[$name] ?? null);
    }
}
