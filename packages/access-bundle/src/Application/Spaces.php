<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\RelationCheck;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Subject;
use Throwable;

/**
 * The members and admins of the site's spaces: the space the configuration names, or another one an application
 * splits its site into. A member, or the members of an access group, may be given either relation.
 */
final readonly class Spaces
{
    public function __construct(
        private RelationWriter $writer,
        private RelationCheck $check,
        private string $default,
    ) {
        self::reference($default);
    }

    public static function reference(string $space): ResourceReference
    {
        try {
            $reference = ResourceReference::of(AccessModel::SPACE, $space);
            Subject::of($reference);
        } catch (Throwable $throwable) {
            throw InvalidAccess::because(\sprintf('"%s" cannot name a space: %s', $space, $throwable->getMessage()));
        }

        return $reference;
    }

    public function space(?string $space = null): ResourceReference
    {
        return self::reference($space ?? $this->default);
    }

    public function addAdmin(MemberId|AccessGroup $grantee, ?string $space = null): void
    {
        $this->writer->grant($this->space($space), 'admin', $this->subject($grantee));
    }

    public function removeAdmin(MemberId|AccessGroup $grantee, ?string $space = null): void
    {
        $this->writer->revoke($this->space($space), 'admin', $this->subject($grantee));
    }

    public function addMember(MemberId|AccessGroup $grantee, ?string $space = null): void
    {
        $this->writer->grant($this->space($space), 'member', $this->subject($grantee));
    }

    public function removeMember(MemberId|AccessGroup $grantee, ?string $space = null): void
    {
        $this->writer->revoke($this->space($space), 'member', $this->subject($grantee));
    }

    public function isAdmin(MemberId $member, ?string $space = null): bool
    {
        return $this->check->check($member->reference(), 'admin', $this->space($space));
    }

    public function isMember(MemberId $member, ?string $space = null): bool
    {
        return $this->check->check($member->reference(), 'member', $this->space($space));
    }

    private function subject(MemberId|AccessGroup $grantee): Subject
    {
        return $grantee instanceof MemberId
            ? Subject::of($grantee->reference())
            : Subject::set(ResourceReference::of(AccessGroup::KIND, $grantee->name), 'member');
    }
}
