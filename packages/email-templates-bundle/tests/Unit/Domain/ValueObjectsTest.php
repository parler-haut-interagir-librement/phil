<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateKey::class)]
#[CoversClass(Site::class)]
#[CoversClass(Locale::class)]
#[CoversClass(TemplateVersion::class)]
#[CoversClass(InvalidTemplate::class)]
final class ValueObjectsTest extends TestCase
{
    public function testAKeyIsLowercaseAsciiStartingWithALetter(): void
    {
        $key = TemplateKey::fromString('order.confirmation-2_b');

        self::assertSame('order.confirmation-2_b', $key->value);
        self::assertSame('order.confirmation-2_b', (string) $key);
        self::assertTrue($key->equals(TemplateKey::fromString('order.confirmation-2_b')));
        self::assertFalse($key->equals(TemplateKey::fromString('order')));
        self::assertSame(str_repeat('a', 64), TemplateKey::fromString(str_repeat('a', 64))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Order'];
        yield 'digit first' => ['1order'];
        yield 'space' => ['order confirmation'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'line break' => ["order\n"];
    }

    #[DataProvider('refusedKeys')]
    public function testARefusedKey(string $key): void
    {
        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('A template key is 1 to 64 lowercase letters');

        TemplateKey::fromString($key);
    }

    public function testARefusedTextIsShortenedInTheMessage(): void
    {
        self::assertSame(str_repeat('x', 80).'…', TemplateKey::shown(str_repeat('x', 81)));
        self::assertSame(str_repeat('x', 80), TemplateKey::shown(str_repeat('x', 80)));

        try {
            TemplateKey::fromString(str_repeat('X', 200));
            self::fail('A key of 200 characters is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertStringContainsString(str_repeat('X', 80).'…', $invalidTemplate->getMessage());
            self::assertSame([$invalidTemplate->getMessage()], $invalidTemplate->errors);
        }
    }

    public function testASiteOrTheSharedScope(): void
    {
        self::assertTrue(Site::shared()->isShared());
        self::assertTrue(Site::fromString('')->isShared());
        self::assertSame('', (string) Site::shared());
        $site = Site::fromString('Tenant:01H.x_y-z');
        self::assertFalse($site->isShared());
        self::assertSame('Tenant:01H.x_y-z', (string) $site);
        self::assertTrue($site->equals(Site::fromString('Tenant:01H.x_y-z')));
        self::assertFalse($site->equals(Site::fromString('tenant:01H.x_y-z')));
        self::assertSame(64, \strlen(Site::fromString(str_repeat('a', 64))->value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedSites(): iterable
    {
        yield 'punctuation first' => ['-site'];
        yield 'at sign' => ['ada@example.com'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'space' => ['my site'];
    }

    #[DataProvider('refusedSites')]
    public function testARefusedSite(string $site): void
    {
        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('A site is 1 to 64 letters');

        Site::fromString($site);
    }

    public function testALocaleAndItsLanguage(): void
    {
        $locale = Locale::fromString('fr-CA');

        self::assertSame('fr_CA', $locale->value);
        self::assertSame('fr_CA', (string) $locale);
        self::assertSame('fr', $locale->language()->value);
        self::assertSame('en', Locale::fromString('en')->language()->value);
        self::assertSame('zh_Hant', Locale::fromString('zh_Hant')->value);
        self::assertSame('ast', Locale::fromString('ast')->value);
        self::assertTrue($locale->equals(Locale::fromString('fr_CA')));
        self::assertFalse($locale->equals(Locale::fromString('fr')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedLocales(): iterable
    {
        yield 'uppercase language' => ['FR'];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'long region' => ['fr_ABCDEFGHI'];
        yield 'two regions' => ['fr_CA_x'];
        yield 'empty' => [''];
    }

    #[DataProvider('refusedLocales')]
    public function testARefusedLocale(string $locale): void
    {
        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('A locale is a language');

        Locale::fromString($locale);
    }

    public function testAVersionHoldsWhatWasSaved(): void
    {
        $definition = TemplateDefinition::create('Hi', '<p>Hi</p>', BodyFormat::Html);
        $version = new TemplateVersion(TemplateKey::fromString('hi'), Site::shared(), Locale::fromString('en'), 3, $definition, new DateTimeImmutable('@0'), 'ada');

        self::assertSame(3, $version->version);
        self::assertSame('ada', $version->author);
        self::assertSame($definition, $version->definition);
        self::assertNull(new TemplateVersion(TemplateKey::fromString('hi'), Site::shared(), Locale::fromString('en'), 1, $definition, new DateTimeImmutable('@0'))->author);
    }
}
