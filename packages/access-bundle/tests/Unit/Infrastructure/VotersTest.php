<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Override;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Infrastructure\EasyAdmin\EasyAdminAccessVoter;
use Phil\AccessBundle\Infrastructure\Security\AccessVoter;
use Phil\AccessBundle\Tests\Fixtures\Page;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(AccessVoter::class)]
#[CoversClass(EasyAdminAccessVoter::class)]
final class VotersTest extends TestCase
{
    private Stage $stage;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addAdmin(MemberId::of('ada'));
        $this->stage->items->register(new Page('1'), MemberId::of('olga'), Visibility::Admins);
        $this->stage->sharing->share(new Page('1'), 'edit', MemberId::of('eve'));
    }

    public function testTheLevelsAsAttributes(): void
    {
        $voter = new AccessVoter($this->stage->access, $this->stage->levels, $this->stage->viewAs);

        self::assertTrue($voter->supportsAttribute('access.edit'));
        self::assertTrue($voter->supportsAttribute('access.view_as'));
        self::assertFalse($voter->supportsAttribute('access.publish'));
        self::assertFalse($voter->supportsAttribute('edit'));
        self::assertFalse($voter->supportsAttribute('xaccess.edit'));

        $this->stage->member->signIn('eve');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, 'access.edit', new Page('1')));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, 'access.view', ResourceReference::of('page', '1')));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, 'access.manage', new Page('1')));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, 'access.edit', 'page:1'));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, 'access.publish', new Page('1')));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, 'ROLE_USER', new Page('1')));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, 'access.view_as', null));

        $vote = new Vote();
        $voter->vote(new NullToken(), new Page('1'), ['access.edit'], $vote);
        self::assertSame(['The relation "edit" is given.'], $vote->reasons);
        $vote = new Vote();
        $voter->vote(new NullToken(), new Page('1'), ['access.manage'], $vote);
        self::assertSame(['The relation "manage" is not given.'], $vote->reasons);
    }

    public function testViewAs(): void
    {
        $voter = new AccessVoter($this->stage->access, $this->stage->levels, $this->stage->viewAs);
        $this->stage->member->signIn('ada');

        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), null, ['access.view_as'], $vote));
        self::assertSame(['The member is an admin of the space.'], $vote->reasons);

        $this->stage->member->signIn('eve');
        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, ['access.view_as'], $vote));
        self::assertSame(['Only an admin of the space views the site as a member.'], $vote->reasons);
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new AccessVoter($this->stage->access, $this->stage->levels), 'access.view_as', null));
    }

    public function testEasyAdminEntitiesAndActions(): void
    {
        $voter = new EasyAdminAccessVoter($this->stage->access, $this->stage->levels, ['detail' => 'view', 'edit' => 'edit', 'delete' => 'manage']);
        $entity = $this->entity(new Page('1'));

        self::assertTrue($voter->supportsAttribute(Permission::EA_ACCESS_ENTITY));
        self::assertTrue($voter->supportsAttribute(Permission::EA_EXECUTE_ACTION));
        self::assertFalse($voter->supportsAttribute(Permission::EA_VIEW_FIELD));

        $this->stage->member->signIn('eve');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, Permission::EA_ACCESS_ENTITY, $entity));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => 'edit', 'entity' => $entity]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => 'delete', 'entity' => $entity]));
        $action = new ActionDto();
        $action->setName('delete');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => $action, 'entity' => $entity]));
        $action->setName('detail');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => $action, 'entity' => $entity]));

        $this->stage->member->signIn('zed');
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($voter, Permission::EA_ACCESS_ENTITY, $entity));

        $vote = new Vote();
        $voter->vote(new NullToken(), $entity, [Permission::EA_ACCESS_ENTITY], $vote);
        self::assertSame(['The relation "view" is not given.'], $vote->reasons);
        $this->stage->member->signIn('eve');
        $vote = new Vote();
        $voter->vote(new NullToken(), $entity, [Permission::EA_ACCESS_ENTITY], $vote);
        self::assertSame(['The relation "view" is given.'], $vote->reasons);
    }

    public function testEasyAdminAbstainsOnWhatItDoesNotDecide(): void
    {
        $voter = new EasyAdminAccessVoter($this->stage->access, $this->stage->levels, ['edit' => 'edit']);
        $entity = $this->entity(new Page('1'));
        $other = $this->entity(new stdClass());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_ACCESS_ENTITY, $other));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_ACCESS_ENTITY, new Page('1')));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => 'index', 'entity' => $entity]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => 'edit', 'entity' => $other]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => 'edit', 'entity' => null]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => 'edit']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['action' => null, 'entity' => $entity]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, ['entity' => $entity]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_EXECUTE_ACTION, $entity));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_VIEW_FIELD, $entity));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($voter, Permission::EA_ACCESS_ENTITY, ['action' => 'edit', 'entity' => $entity]));
    }

    public function testEasyAdminLevelsAreChecked(): void
    {
        $this->expectException(InvalidAccess::class);

        new EasyAdminAccessVoter($this->stage->access, $this->stage->levels, ['publish' => 'publish']);
    }

    private function vote(VoterInterface $voter, string $attribute, mixed $subject): int
    {
        return $voter->vote(new NullToken(), $subject, [$attribute]);
    }

    /**
     * @return EntityDto<object>
     */
    private function entity(object $instance): EntityDto
    {
        $metadata = new ClassMetadata($instance::class);

        return new EntityDto($instance::class, $metadata, null, $instance);
    }
}
