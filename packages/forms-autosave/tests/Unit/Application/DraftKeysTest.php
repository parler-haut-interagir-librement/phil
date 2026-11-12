<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Application;

use Phil\FormsAutosave\Application\DraftKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DraftKeys::class)]
final class DraftKeysTest extends TestCase
{
    public function testAKeyIsThirtyTwoUrlSafeCharactersOfAKeyedHash(): void
    {
        $keys = new DraftKeys('secret');
        $expected = substr(sodium_bin2base64(hash_hmac('sha256', '["key","App\\\\Form#post","42","ada"]', 'secret', true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, 32);

        self::assertSame($expected, $keys->key('App\Form#post', '42', 'ada')->value);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/D', $keys->key('f', null, null)->value);
    }

    public function testEachPartChangesTheKey(): void
    {
        $keys = new DraftKeys('secret');
        $all = [
            $keys->key('form', '1', 'ada')->value,
            $keys->key('other', '1', 'ada')->value,
            $keys->key('form', '2', 'ada')->value,
            $keys->key('form', null, 'ada')->value,
            $keys->key('form', '', 'ada')->value,
            $keys->key('form', '1', 'bob')->value,
            $keys->key('form', '1', null)->value,
            new DraftKeys('other secret')->key('form', '1', 'ada')->value,
        ];

        self::assertSame($all, array_values(array_unique($all)));
        self::assertSame($all[0], $keys->key('form', '1', 'ada')->value);
    }

    public function testAnOwnerIsAKeyedHashOfSixtyFourHexadecimalDigits(): void
    {
        $keys = new DraftKeys('secret');

        self::assertSame(hash_hmac('sha256', '["owner","ada"]', 'secret'), $keys->owner('ada'));
        self::assertNotSame($keys->owner('ada'), $keys->owner('bob'));
        self::assertNotSame($keys->owner('ada'), new DraftKeys('other')->owner('ada'));
    }
}
