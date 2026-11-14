<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;

/**
 * What one module did to a person's records, store by store.
 *
 * **A module that erased nothing still reports**, with every store it holds and `Survives` or `AwaitsExpiry`
 * where that is the answer. An empty report is refused: it cannot be told apart from a module that forgot a store.
 */
final readonly class ErasureReport
{
    /**
     * @param array<string, ErasureEffect> $effects by store
     */
    private function __construct(
        public ModuleName $module,
        public array $effects,
    ) {
    }

    /**
     * @param array<string, ErasureEffect> $effects by store
     */
    public static function of(string $module, array $effects): self
    {
        $name = ModuleName::fromString($module);

        if ([] === $effects) {
            throw InvalidPrivacyValue::because(\sprintf('The erasure report of module "%s" names no store.', $module));
        }

        foreach (array_keys($effects) as $store) {
            if ('' === trim($store)) {
                throw InvalidPrivacyValue::because(\sprintf('The erasure report of module "%s" has a store with no name.', $module));
            }
        }

        return new self($name, $effects);
    }
}
