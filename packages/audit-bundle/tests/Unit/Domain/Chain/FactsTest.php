<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Chain;

use Closure;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Facts::class)]
#[CoversClass(Subject::class)]
final class FactsTest extends TestCase
{
    public function testFactsHoldIdentifiers(): void
    {
        $subject = Subject::of('order', 'O-42:a_b.c');
        $facts = Facts::of('workflow.order.ship', 'acct_7', $subject, 'Boutique de l’été');

        self::assertSame(['workflow.order.ship', 'acct_7', 'Boutique de l’été'], [$facts->action, $facts->actor, $facts->tenant]);
        self::assertSame($subject, $facts->subject);
        self::assertSame(['order', 'O-42:a_b.c'], [$subject->type, $subject->id]);
        self::assertSame(['workflow.order.ship', 'acct_7', 'order', 'O-42:a_b.c', 'Boutique de l’été'], $facts->hashLines());
        self::assertSame(['', '', '', '', ''], Facts::none()->hashLines());
        self::assertSame(['a', '', '', '', ''], Facts::of('a')->hashLines());
        self::assertNull(Facts::none()->action);

        $moved = $facts->inTenant('shop-b');
        self::assertSame(['shop-b', 'Boutique de l’été'], [$moved->tenant, $facts->tenant]);
        self::assertSame([$facts->action, $facts->actor, $facts->subject], [$moved->action, $moved->actor, $moved->subject]);
        self::assertNull($facts->inTenant(null)->tenant);

        self::assertTrue($subject->equals(Subject::of('order', 'O-42:a_b.c')));
        self::assertFalse($subject->equals(Subject::of('order', 'O-43')));
        self::assertFalse($subject->equals(Subject::of('invoice', 'O-42:a_b.c')));
        self::assertSame(['Order', 'a b'], [Subject::restore('Order', 'a b')->type, Subject::restore('Order', 'a b')->id]);

        // The longest values each check accepts.
        self::assertSame('a'.str_repeat('1', 63), Facts::of('a'.str_repeat('1', 63))->action);
        self::assertSame(str_repeat('A', 128), Facts::of('a', str_repeat('A', 128))->actor);
        self::assertSame('a'.str_repeat('b', 63), Subject::of('a'.str_repeat('b', 63), '1')->type);
        self::assertSame(str_repeat('é', 127).'a', Facts::of('a', tenant: str_repeat('é', 127).'a')->tenant);
        self::assertSame('x', Facts::of('a', tenant: 'x')->tenant);
    }

    public function testAReadIsAnActionUnderItsPrefix(): void
    {
        $subject = Subject::of('account', 'acct-9');
        $read = Facts::read('account.profile', $subject, 'acct-7', 'shop-a');

        self::assertSame('read.', Facts::READ_PREFIX);
        self::assertSame(['read.account.profile', 'acct-7', 'shop-a'], [$read->action, $read->actor, $read->tenant]);
        self::assertSame($subject, $read->subject);
        self::assertTrue($read->isRead());
        self::assertNull(Facts::read('account.profile', $subject)->actor);
        self::assertNull(Facts::read('account.profile', $subject)->tenant);
        self::assertSame('read.a'.str_repeat('b', 58), Facts::read('a'.str_repeat('b', 58), $subject)->action, 'the longest: 64 characters with the prefix');

        self::assertFalse(Facts::of('account.updated')->isRead());
        self::assertFalse(Facts::of('reader.added')->isRead());
        self::assertFalse(Facts::of('read')->isRead());
        self::assertFalse(Facts::none()->isRead());
        self::assertTrue(Facts::of('read.x')->isRead(), 'any action under the prefix');
        self::assertTrue(Facts::restore('read.x', null, null, null)->isRead());
    }

    public function testTheCheckersAreOpenToWhoReadsTheLog(): void
    {
        Facts::assertAction('order.paid');
        Facts::assertIdentifier('An actor', 'acct-7');
        Facts::assertTenant('shop-a');
        Subject::assertType('order');

        $this->expectException(InvalidValue::class);
        Subject::assertType('Order');
    }

    /**
     * @param Closure(): mixed $call
     */
    #[DataProvider('refusals')]
    public function testANonIdentifierIsRefused(Closure $call, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $call();
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function refusals(): iterable
    {
        $action = 'An action is 1 to 64 lower-case letters, digits, ".", "_", ":" or "-", starting with a letter; "%s" is not.';
        $actor = 'An actor is 1 to 128 letters, digits, ".", "_", ":" or "-", starting with a letter or a digit; "%s" is not.';
        $type = 'A subject type is 1 to 64 lower-case letters, digits, ".", "_" or "-", starting with a letter; "%s" is not.';
        $id = 'A subject identifier is 1 to 128 letters, digits, ".", "_", ":" or "-", starting with a letter or a digit; "%s" is not.';
        $tenant = 'A tenant reference is 1 to 255 bytes of UTF-8, without control characters or surrounding white space.';

        yield 'empty action' => [static fn (): Facts => Facts::of(''), \sprintf($action, '')];
        yield 'upper-case action' => [static fn (): Facts => Facts::of('Order.paid'), \sprintf($action, 'Order.paid')];
        yield 'action starting with a digit' => [static fn (): Facts => Facts::of('1order'), \sprintf($action, '1order')];
        yield 'action with a line break' => [static fn (): Facts => Facts::of("order\n"), \sprintf($action, "order\n")];
        yield 'action too long' => [static fn (): Facts => Facts::of('a'.str_repeat('b', 64)), \sprintf($action, 'a'.str_repeat('b', 64))];
        yield 'e-mail as actor' => [static fn (): Facts => Facts::of('a', 'ada@example.org'), \sprintf($actor, 'ada@example.org')];
        yield 'empty actor' => [static fn (): Facts => Facts::of('a', ''), \sprintf($actor, '')];
        yield 'actor starting with a dash' => [static fn (): Facts => Facts::of('a', '-a'), \sprintf($actor, '-a')];
        yield 'actor with a line break' => [static fn (): Facts => Facts::of('a', "a\n"), \sprintf($actor, "a\n")];
        yield 'actor too long' => [static fn (): Facts => Facts::of('a', str_repeat('a', 129)), \sprintf($actor, str_repeat('a', 129))];
        yield 'upper-case type' => [static fn (): Subject => Subject::of('Order', '1'), \sprintf($type, 'Order')];
        yield 'type starting with a digit' => [static fn (): Subject => Subject::of('1order', '1'), \sprintf($type, '1order')];
        yield 'type with a colon' => [static fn (): Subject => Subject::of('a:b', '1'), \sprintf($type, 'a:b')];
        yield 'type with a line break' => [static fn (): Subject => Subject::of("order\n", '1'), \sprintf($type, "order\n")];
        yield 'type too long' => [static fn (): Subject => Subject::of('a'.str_repeat('b', 64), '1'), \sprintf($type, 'a'.str_repeat('b', 64))];
        yield 'identifier with a space' => [static fn (): Subject => Subject::of('order', 'a b'), \sprintf($id, 'a b')];
        yield 'empty tenant' => [static fn (): Facts => Facts::of('a', tenant: ''), $tenant];
        yield 'tenant with a leading space' => [static fn (): Facts => Facts::of('a', tenant: ' a'), $tenant];
        yield 'tenant with a trailing no-break space' => [static fn (): Facts => Facts::of('a', tenant: "a\u{a0}"), $tenant];
        yield 'tenant with a line break' => [static fn (): Facts => Facts::of('a', tenant: "a\nb"), $tenant];
        yield 'tenant with a trailing line break' => [static fn (): Facts => Facts::of('a', tenant: "ab\n"), $tenant];
        yield 'tenant with a control character' => [static fn (): Facts => Facts::of('a', tenant: "a\x7Fb"), $tenant];
        yield 'tenant too long' => [static fn (): Facts => Facts::of('a', tenant: str_repeat('a', 256)), $tenant];
        yield 'tenant of invalid UTF-8' => [static fn (): Facts => Facts::of('a', tenant: "\xFF"), $tenant];
        yield 'tenant moved to a bad reference' => [static fn (): Facts => Facts::none()->inTenant(' '), $tenant];
        yield 'empty read' => [static fn (): Facts => Facts::read('', Subject::of('a', '1')), \sprintf($action, '')];
        yield 'read starting with a dot' => [static fn (): Facts => Facts::read('.a', Subject::of('a', '1')), \sprintf($action, '.a')];
        yield 'read too long with its prefix' => [static fn (): Facts => Facts::read('a'.str_repeat('b', 59), Subject::of('a', '1')), \sprintf($action, 'read.a'.str_repeat('b', 59))];
        yield 'read by an e-mail address' => [static fn (): Facts => Facts::read('a', Subject::of('a', '1'), 'ada@example.org'), \sprintf($actor, 'ada@example.org')];
        yield 'read in a bad tenant' => [static fn (): Facts => Facts::read('a', Subject::of('a', '1'), tenant: ' a'), $tenant];
    }
}
