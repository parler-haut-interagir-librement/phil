<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;

/**
 * The access model of the site, declared as relations of `phil/resource-permissions`:
 *
 * - `member` and `visitor`, the subjects; `visibility`, the marker of an item's visibility;
 * - `space`, the site or one of its spaces: `admin`, and `member`, which every admin is;
 * - `group`, an access group: `member`;
 * - each kind of item: `owner`, `space`, `visibility`, and one relation per sharing level, the widest first, each
 *   including the next: the owner holds the widest; a member, every member, every visitor, the members of a group, the
 *   members or the admins of a space may be given any of them.
 *
 * The contributors then add their kinds and relations.
 */
final readonly class AccessModel
{
    public const string VISITOR = 'visitor';

    public const string VISIBILITY = 'visibility';

    public const string SPACE = 'space';

    public const array RESERVED_KINDS = [MemberId::KIND, self::VISITOR, self::VISIBILITY, self::SPACE, AccessGroup::KIND];

    private function __construct()
    {
    }

    /**
     * @param list<string>               $itemKinds
     * @param iterable<ModelContributor> $contributors
     */
    public static function create(array $itemKinds, SharingLevels $levels, iterable $contributors = []): Model
    {
        $grantees = ['member', 'member:*', 'visitor:*', 'group#member', 'space#member', 'space#admin'];
        $types = [
            MemberId::KIND => TypeDefinition::of(MemberId::KIND),
            self::VISITOR => TypeDefinition::of(self::VISITOR),
            self::VISIBILITY => TypeDefinition::of(self::VISIBILITY),
            AccessGroup::KIND => TypeDefinition::of(AccessGroup::KIND, ['member' => Rewrite::direct('member')]),
            self::SPACE => TypeDefinition::of(self::SPACE, [
                'admin' => Rewrite::direct('member', 'group#member'),
                'member' => Rewrite::union(Rewrite::direct('member', 'group#member'), Rewrite::computed('admin')),
            ]),
        ];

        foreach ($itemKinds as $kind) {
            if (\in_array($kind, self::RESERVED_KINDS, true) || isset($types[$kind])) {
                throw InvalidAccess::because(\sprintf('"%s" cannot be a kind of item: it is reserved, or listed twice.', $kind));
            }

            $relations = ['owner' => Rewrite::direct('member'), 'space' => Rewrite::direct('space'), 'visibility' => Rewrite::direct('visibility')];
            $wider = 'owner';

            foreach ($levels->names as $level) {
                $relations[$level] = Rewrite::union(Rewrite::direct(...$grantees), Rewrite::computed($wider));
                $wider = $level;
            }

            $types[$kind] = TypeDefinition::of($kind, $relations);
        }

        foreach ($contributors as $contributor) {
            $types = $contributor->contribute($types);
        }

        return new Model(...array_values($types));
    }
}
