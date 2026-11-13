<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;
use Phil\PagedMedia\Gotenberg\Profile;

/**
 * What `/forms/pdf/validate` found in a PDF: whether it conforms to every profile checked, and the violations.
 */
final readonly class ValidationReport
{
    /**
     * @param non-empty-list<Profile> $profiles
     * @param list<Violation>         $violations
     */
    public function __construct(
        public array $profiles,
        public bool $compliant,
        public array $violations,
    ) {
    }

    /**
     * The violations that make the PDF non-compliant.
     *
     * @return list<Violation>
     */
    public function fatal(): array
    {
        return array_values(array_filter($this->violations, static fn (Violation $violation): bool => Severity::Fatal === $violation->severity));
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        $names = $json->stringOrStrings('profile');
        $profiles = array_map(
            static fn (string $name): Profile => Profile::tryFrom($name) ?? throw UnexpectedResponse::because(\sprintf('Gotenberg validated against an unknown profile "%s".', $name)),
            \is_string($names) ? [$names] : $names,
        );

        if ([] === $profiles) {
            throw UnexpectedResponse::because('Gotenberg validated against no profile.');
        }

        return new self($profiles, $json->bool('compliant'), array_map(Violation::fromJson(...), $json->optionalList('violations')));
    }
}
