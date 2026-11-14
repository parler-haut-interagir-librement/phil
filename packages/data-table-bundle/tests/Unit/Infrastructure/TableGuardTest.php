<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Infrastructure\Http\TableGuard;
use Phil\DataTableBundle\Tests\Fixtures\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableGuard::class)]
#[CoversClass(AccessDenied::class)]
final class TableGuardTest extends TestCase
{
    public function testTheTokenOfTheBundleIsChecked(): void
    {
        $tokens = new Tokens();
        $guard = new TableGuard($tokens, 'secret');

        self::assertSame('good', $guard->token());
        $guard->assertTrusted('good');
        self::assertSame(['phil_data_table'], $tokens->checked);

        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessageIsOrContains('The request did not pass the checks of a change: its origin or its token.');
        $guard->assertTrusted('bad');
    }

    public function testAContextIsEncodedInOneWay(): void
    {
        $guard = new TableGuard(new Tokens(), 'secret');

        self::assertSame('{"a":1,"b":"x"}', $guard->encode(['b' => 'x', 'a' => 1]));
        self::assertSame('[]', $guard->encode([]));
    }

    public function testASealTiesAContextToItsTableAndTheSite(): void
    {
        $guard = new TableGuard(new Tokens(), 'secret');
        $seal = $guard->seal('t', '{"a":1}');

        self::assertSame(hash_hmac('sha256', "t\0{\"a\":1}", 'secret'), $seal);
        self::assertSame(['a' => 1], $guard->opened('t', '{"a":1}', $seal));
        self::assertSame(['a' => 1, 'b' => 2], $guard->opened('t', '{"a":1,"b":2}', $guard->seal('t', '{"a":1,"b":2}')));
        self::assertNotSame($seal, new TableGuard(new Tokens(), 'other')->seal('t', '{"a":1}'));

        foreach ([['u', '{"a":1}', $seal], ['t', '{"a":2}', $seal], ['t', '{"a":1}', 'x']] as [$table, $context, $sealed]) {
            try {
                $guard->opened($table, $context, $sealed);
                self::fail('The context opened.');
            } catch (AccessDenied) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testASealedContextThatIsNotJsonIsRefused(): void
    {
        $guard = new TableGuard(new Tokens(), 'secret');
        $this->expectException(AccessDenied::class);

        $guard->opened('t', 'not json', $guard->seal('t', 'not json'));
    }
}
