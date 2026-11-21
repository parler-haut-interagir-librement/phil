<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Http;

use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Every occurrence of `Sec-GPC` is read, and a `1` among its comma-separated values is the signal.
 */
#[CoversClass(ConsentContext::class)]
final class GlobalPrivacyControlTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function headers(): iterable
    {
        yield 'absent' => [[], false];
        yield 'one' => [['1'], true];
        yield 'zero' => [['0'], false];
        yield 'doubled header' => [['0', '1'], true];
        yield 'doubled header, one first' => [['1', '0'], true];
        yield 'list' => [['0, 1'], true];
        yield 'list without spaces' => [['1,0'], true];
        yield 'spaces' => [[" \t1 "], true];
        yield 'empty value' => [[''], false];
        yield 'empty values of a list' => [[', ,'], false];
        yield 'zeros only' => [['0', '0, 0'], false];
        yield 'one inside a value' => [['10, 01'], false];
        yield 'unrecognized value' => [['true'], false];
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('headers')]
    public function testEveryValueOfEveryHeaderIsRead(array $values, bool $sent): void
    {
        $request = Request::create('/');

        if ([] !== $values) {
            $request->headers->set('Sec-GPC', $values);
        }

        self::assertSame($sent, ConsentContext::sendsGlobalPrivacyControl($request));
    }
}
