<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Consent;

/**
 * What a site may do for one visitor, right now.
 *
 * - **Nothing optional until a person says yes**: without a choice, every category that is not necessary is refused.
 * - **A choice counts for the version it was made on**: one made on another text is as good as none.
 * - **The Global Privacy Control wins**: a browser that sends the signal has every optional category refused, whatever
 *   a cookie says, and its user is not asked, the signal being their answer.
 */
final readonly class ConsentState
{
    public ?ConsentChoice $choice;

    public function __construct(
        public ConsentPolicy $policy,
        ?ConsentChoice $stored,
        public bool $globalPrivacyControl,
    ) {
        $this->choice = $stored?->version === $policy->version ? $stored : null;
    }

    public function isGranted(string $category): bool
    {
        if ($this->policy->isNecessary($category)) {
            return true;
        }

        return !$this->globalPrivacyControl && true === $this->choice?->grants($category);
    }

    /**
     * Whether the person still has to be asked.
     */
    public function isPending(): bool
    {
        return !$this->globalPrivacyControl && !$this->choice instanceof ConsentChoice && [] !== $this->policy->optional();
    }

    /**
     * The optional categories in effect, in the order of the declaration.
     *
     * @return list<string>
     */
    public function granted(): array
    {
        return array_values(array_filter($this->policy->optional(), $this->isGranted(...)));
    }
}
