<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;

/**
 * One answer a question offers: its identifier, the translation key of its label, and what choosing it asks the
 * application to grant — roles and subscriptions, named as the application names them. The bundle only passes them on.
 */
final readonly class Choice
{
    /** The most roles, or subscriptions, a choice grants. */
    public const int MAX_GRANTS = 20;

    /** A role or a subscription: a letter or a digit, then up to 99 letters, digits, `_`, `.`, `:` and `-`. */
    public const string GRANT_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,99}$/D';

    /** @var list<string> */
    public array $roles;

    /** @var list<string> */
    public array $subscriptions;

    /**
     * @param list<string> $roles
     * @param list<string> $subscriptions
     *
     * @throws InvalidFlow when the declaration breaks a rule
     */
    public function __construct(
        public string $id,
        public string $label,
        array $roles = [],
        array $subscriptions = [],
    ) {
        Identifier::check($id, 'choice');
        Identifier::text($label, \sprintf('label of the choice "%s"', $id), StepDefinition::MAX_TITLE);
        $this->roles = $this->grants($roles, 'roles', $id);
        $this->subscriptions = $this->grants($subscriptions, 'subscriptions', $id);
    }

    /**
     * Whether choosing it asks the application for anything.
     */
    public function grantsAnything(): bool
    {
        return [] !== $this->roles || [] !== $this->subscriptions;
    }

    /**
     * @param list<string> $grants
     *
     * @return list<string>
     *
     * @throws InvalidFlow when there are too many, or one is malformed
     */
    private function grants(array $grants, string $what, string $id): array
    {
        if (\count($grants) > self::MAX_GRANTS) {
            throw InvalidFlow::because(\sprintf('The choice "%s" grants %d %s, more than %d.', $id, \count($grants), $what, self::MAX_GRANTS));
        }

        foreach ($grants as $grant) {
            if (1 !== preg_match(self::GRANT_PATTERN, $grant)) {
                throw InvalidFlow::because(\sprintf('The choice "%s" grants "%s", which is not made of up to 100 letters, digits, "_", ".", ":" and "-", starting with a letter or a digit.', $id, $grant));
            }
        }

        return array_values(array_unique($grants));
    }
}
