<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * The roles of the person a launch is for, as full URIs. A context role sent by its short name, `Instructor`, is read
 * as the full URI of its vocabulary, as LTI 1.3 still allows; a role of another vocabulary is kept as given.
 */
final readonly class Roles
{
    /**
     * @param list<string> $uris
     */
    private function __construct(
        public array $uris,
    ) {
    }

    /**
     * @param list<string|ContextRole|InstitutionRole|SystemRole> $roles
     */
    public static function of(array $roles): self
    {
        $uris = [];

        foreach ($roles as $role) {
            $uri = \is_string($role) ? self::full($role) : $role->value;
            $uris[$uri] = $uri;
        }

        return new self(array_values($uris));
    }

    public function has(ContextRole|InstitutionRole|SystemRole|string $role): bool
    {
        return \in_array(\is_string($role) ? self::full($role) : $role->value, $this->uris, true);
    }

    /**
     * Whether the person teaches in the context: an instructor, a teaching assistant, or an administrator of the context.
     */
    public function isInstructor(): bool
    {
        return $this->has(ContextRole::Instructor) || $this->has(ContextRole::TEACHING_ASSISTANT) || $this->has(ContextRole::Administrator);
    }

    public function isLearner(): bool
    {
        return $this->has(ContextRole::Learner);
    }

    /**
     * Whether the launch is a test of the platform rather than a person.
     */
    public function isTestUser(): bool
    {
        return $this->has(SystemRole::TestUser);
    }

    /**
     * @return list<string>
     */
    public function toClaim(): array
    {
        return $this->uris;
    }

    private static function full(string $role): string
    {
        if (str_contains($role, ':')) {
            return $role;
        }

        return ContextRole::tryFrom('http://purl.imsglobal.org/vocab/lis/v2/membership#'.$role)->value ?? $role;
    }
}
