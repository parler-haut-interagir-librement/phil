<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\NewsletterKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterKey::class)]
final class NewsletterKeyTest extends TestCase
{
    public function testAKeyOfSixteenBytesOrMoreIsKept(): void
    {
        self::assertSame(str_repeat('k', 16), NewsletterKey::check(str_repeat('k', 16)));
        self::assertSame('x-change-me-'.str_repeat('k', 16), NewsletterKey::check('x-change-me-'.str_repeat('k', 16)), 'Only a key that begins as a placeholder is one.');
    }

    public function testTheKeyOfAUseIsAnHmacOfItsName(): void
    {
        $key = str_repeat('k', 32);

        self::assertSame(hash_hmac('sha256', 'verp', $key), NewsletterKey::derive($key, 'verp'));
        self::assertNotSame(NewsletterKey::derive($key, 'verp'), NewsletterKey::derive($key, 'suppression'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedKeys(): iterable
    {
        yield 'empty' => ['', 'The key of the newsletter links holds at least 16 bytes.'];
        yield 'fifteen bytes' => [str_repeat('k', 15), 'The key of the newsletter links holds at least 16 bytes.'];
        yield 'the example of the recipe' => ['change-me-to-a-random-secret-of-32-bytes-or-more', 'The key of the newsletter links is a placeholder: give it random bytes of its own.'];
        yield 'a placeholder in capitals' => ['CHANGEME'.str_repeat('k', 16), 'The key of the newsletter links is a placeholder: give it random bytes of its own.'];
    }

    #[DataProvider('refusedKeys')]
    public function testARefusedKey(string $key, string $message): void
    {
        $this->expectExceptionObject(LinkRefused::because($message));

        NewsletterKey::check($key);
    }

    #[DataProvider('refusedKeys')]
    public function testNoUseIsDerivedFromARefusedKey(string $key, string $message): void
    {
        $this->expectExceptionObject(LinkRefused::because($message));

        NewsletterKey::derive($key, 'verp');
    }
}
