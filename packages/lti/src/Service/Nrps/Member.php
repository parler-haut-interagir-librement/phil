<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Nrps;

use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Message\Roles;
use Phil\Lti\Support\Fields;
use SensitiveParameter;

/**
 * A member of a context: the identifier the platform gives them for the tool — the `sub` of their launches —, their
 * roles and status, and what the platform chose to share of their name, email and picture.
 */
final readonly class Member
{
    public function __construct(
        public string $userId,
        public Roles $roles,
        public MemberStatus $status = MemberStatus::Active,
        #[SensitiveParameter]
        public ?string $name = null,
        #[SensitiveParameter]
        public ?string $givenName = null,
        #[SensitiveParameter]
        public ?string $familyName = null,
        #[SensitiveParameter]
        public ?string $email = null,
        public ?string $picture = null,
        public ?string $sourcedId = null,
    ) {
        if ('' === $userId || \strlen($userId) > 255) {
            throw InvalidData::because('A member is named by 1 to 255 bytes.');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidData
     */
    public static function fromArray(array $data): self
    {
        $fields = new Fields($data, inToken: false);
        $status = $fields->optionalString('status', 16);

        return new self(
            $fields->string('user_id', 255),
            Roles::of($fields->stringList('roles', 64)),
            null === $status ? MemberStatus::Active : (MemberStatus::tryFrom($status) ?? throw $fields->fail('The status of a member is not one of NRPS.')),
            $fields->optionalString('name', 512),
            $fields->optionalString('given_name', 255),
            $fields->optionalString('family_name', 255),
            $fields->optionalString('email', 320),
            $fields->optionalString('picture', 2048),
            $fields->optionalString('lis_person_sourcedid', 255),
        );
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'status' => $this->status->value,
            'roles' => $this->roles->toClaim(),
            'name' => $this->name,
            'given_name' => $this->givenName,
            'family_name' => $this->familyName,
            'email' => $this->email,
            'picture' => $this->picture,
            'lis_person_sourcedid' => $this->sourcedId,
        ], static fn (string|array|null $value): bool => null !== $value);
    }
}
