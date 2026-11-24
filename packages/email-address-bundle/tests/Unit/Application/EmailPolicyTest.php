<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use Phil\EmailAddressBundle\Application\EmailPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailPolicy::class)]
final class EmailPolicyTest extends TestCase
{
    public function testAPolicyThatLeavesEveryChoiceOpenGivesTheConstraintDefaults(): void
    {
        $constraint = new EmailPolicy()->constraint();

        self::assertFalse($constraint->checkMailServer);
        self::assertFalse($constraint->allowDisposable);
        self::assertTrue($constraint->knownTopLevelDomain);
        self::assertFalse($constraint->internationalLocalPart);
        self::assertSame([], $constraint->deniedDomains);
        self::assertSame([], $constraint->allowedDomains);
        self::assertSame(['Default'], $constraint->groups);
    }

    public function testAPolicyGivesItsChoicesAndGroupsToTheConstraint(): void
    {
        $constraint = new EmailPolicy(true, true, false, true, ['spam.example'], ['example.org'])->constraint(['signup']);

        self::assertTrue($constraint->checkMailServer);
        self::assertTrue($constraint->allowDisposable);
        self::assertFalse($constraint->knownTopLevelDomain);
        self::assertTrue($constraint->internationalLocalPart);
        self::assertSame(['spam.example'], $constraint->deniedDomains);
        self::assertSame(['example.org'], $constraint->allowedDomains);
        self::assertSame(['signup'], $constraint->groups);
    }

    public function testAPolicyLaidOverAnotherKeepsTheChoicesItLeavesOpen(): void
    {
        $base = new EmailPolicy(true, false, true, false, ['a.example', 'b.example'], ['example.org']);

        self::assertEquals($base, new EmailPolicy()->over($base));
        self::assertEquals(
            new EmailPolicy(false, true, false, true, ['a.example', 'b.example', 'c.example'], ['example.com']),
            new EmailPolicy(false, true, false, true, ['b.example', 'c.example'], ['example.com'])->over($base),
        );
        self::assertSame([], new EmailPolicy(allowedDomains: [])->over($base)->allowedDomains);
    }
}
