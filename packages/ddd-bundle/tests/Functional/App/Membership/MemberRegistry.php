<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Membership;

use Countable;

/**
 * The members, in memory. An e-mail address is registered once: the second registration is refused by the domain.
 */
final class MemberRegistry implements Countable
{
    /**
     * @var array<string, string>
     */
    private array $emails = [];

    /**
     * @throws EmailAlreadyRegistered
     */
    public function add(MemberId $id, string $email): void
    {
        if (\in_array($email, $this->emails, true)) {
            throw EmailAlreadyRegistered::for($email);
        }

        $this->emails[$id->toString()] = $email;
    }

    /**
     * @throws UnknownMember
     */
    public function email(MemberId $id): string
    {
        return $this->emails[$id->toString()] ?? throw UnknownMember::for($id);
    }

    public function count(): int
    {
        return \count($this->emails);
    }
}
