<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Message;

use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\InstitutionRole;
use Phil\Lti\Message\Roles;
use Phil\Lti\Message\SystemRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Roles::class)]
final class RolesTest extends TestCase
{
    public function testShortContextRolesAreReadAsFullUris(): void
    {
        $roles = Roles::of(['Instructor', ContextRole::Learner, 'Instructor', 'Unknown', 'urn:x:custom', InstitutionRole::Staff]);

        self::assertSame([
            ContextRole::Instructor->value,
            ContextRole::Learner->value,
            'Unknown',
            'urn:x:custom',
            InstitutionRole::Staff->value,
        ], $roles->uris);
        self::assertSame($roles->uris, $roles->toClaim());
        self::assertTrue($roles->has('Instructor'));
        self::assertTrue($roles->has(ContextRole::Instructor->value));
        self::assertTrue($roles->has('urn:x:custom'));
        self::assertTrue($roles->has(InstitutionRole::Staff));
        self::assertFalse($roles->has(InstitutionRole::Student));
    }

    public function testTheRoleHelpers(): void
    {
        self::assertTrue(Roles::of([ContextRole::Instructor])->isInstructor());
        self::assertTrue(Roles::of([ContextRole::TEACHING_ASSISTANT])->isInstructor());
        self::assertTrue(Roles::of([ContextRole::Administrator])->isInstructor());
        self::assertFalse(Roles::of([ContextRole::Learner, InstitutionRole::Instructor])->isInstructor());
        self::assertTrue(Roles::of([ContextRole::Learner])->isLearner());
        self::assertFalse(Roles::of([ContextRole::Instructor])->isLearner());
        self::assertTrue(Roles::of([SystemRole::TestUser])->isTestUser());
        self::assertFalse(Roles::of([])->isTestUser());
    }
}
