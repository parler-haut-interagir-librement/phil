<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;
use SensitiveParameter;

/**
 * The person a launch is for: the subject, the stable identifier the platform gives them for this tool, and what the
 * platform chose to share of their name, email and picture. The subject is null in an anonymous launch.
 */
final readonly class Person
{
    public function __construct(
        public ?string $subject,
        #[SensitiveParameter]
        public ?string $name = null,
        #[SensitiveParameter]
        public ?string $givenName = null,
        #[SensitiveParameter]
        public ?string $familyName = null,
        #[SensitiveParameter]
        public ?string $email = null,
        public ?string $picture = null,
        public ?string $locale = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claims): self
    {
        return new self(
            $claims->optionalString('sub', 255),
            $claims->optionalString('name', 512),
            $claims->optionalString('given_name', 255),
            $claims->optionalString('family_name', 255),
            $claims->optionalString('email', 320),
            $claims->optionalString('picture', 2048),
            $claims->optionalString('locale', 35),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toClaims(): array
    {
        return array_filter([
            'sub' => $this->subject,
            'name' => $this->name,
            'given_name' => $this->givenName,
            'family_name' => $this->familyName,
            'email' => $this->email,
            'picture' => $this->picture,
            'locale' => $this->locale,
        ], static fn (?string $value): bool => null !== $value);
    }
}
