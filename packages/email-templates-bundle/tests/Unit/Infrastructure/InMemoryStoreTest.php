<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

#[CoversClass(InMemoryTemplateStore::class)]
final class InMemoryStoreTest extends StoreTestCase
{
    public function testThePublicationIsDatedByTheClock(): void
    {
        $store = new InMemoryTemplateStore(new MockClock('2026-11-18 10:00:00 UTC'));
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);

        self::assertSame('2026-11-18 10:00:00', $store->publish(self::key(), self::site(), self::locale())->publishedAt?->format('Y-m-d H:i:s'));
    }

    public function testWithoutAClockThePublicationIsDatedNowInUtc(): void
    {
        $store = new InMemoryTemplateStore();
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);

        $before = time();
        $publishedAt = $store->publish(self::key(), self::site(), self::locale())->publishedAt;

        self::assertSame('UTC', $publishedAt?->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $publishedAt->getTimestamp());
        self::assertLessThanOrEqual(time(), $publishedAt->getTimestamp());
    }

    /** The locales of the content store are served ones, none of which ends another: only the memory is free to name any. */
    public function testNothingShiftsTheBoundaryBetweenTheKeyAndTheLocale(): void
    {
        $store = $this->store();
        $at = new DateTimeImmutable('@0');
        $store->saveDraft(TemplateKey::fromString('a'), self::site(), Locale::fromString('bcd'), self::definition('Key a, locale bcd'), $at, null, 0);
        $store->saveDraft(TemplateKey::fromString('ab'), self::site(), Locale::fromString('cd'), self::definition('Key ab, locale cd'), $at, null, 0);

        self::assertSame('Key a, locale bcd', $store->draft(TemplateKey::fromString('a'), self::site(), Locale::fromString('bcd'))?->definition->subject);
        self::assertSame('Key ab, locale cd', $store->draft(TemplateKey::fromString('ab'), self::site(), Locale::fromString('cd'))?->definition->subject);
        self::assertNull($store->draft(TemplateKey::fromString('a'), self::site(), Locale::fromString('cd')));
        self::assertCount(2, $store->catalog(self::site()));
    }

    #[Override]
    protected function store(): TemplateStore
    {
        return new InMemoryTemplateStore();
    }
}
