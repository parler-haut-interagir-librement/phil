<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Application\Channels;
use Phil\CommerceBundle\Domain\Exception\UnknownChannel;
use Phil\CommerceBundle\Domain\Model\Channel;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Channel::class)]
#[CoversClass(Channels::class)]
#[CoversClass(UnknownChannel::class)]
final class ChannelTest extends TestCase
{
    public function testAChannelHoldsHowItSells(): void
    {
        $channel = Channel::create('second-shop', 'La deuxième boutique', Currency::of('CAD'), 'fr_CA', 'second-shop', 'QC');

        self::assertSame('second-shop', $channel->code);
        self::assertSame('La deuxième boutique', $channel->name());
        self::assertSame('CAD', $channel->currency->code);
        self::assertSame('fr_CA', $channel->locale());
        self::assertSame('second-shop', $channel->theme());
        self::assertSame('QC', $channel->taxZone());
        self::assertSame([], $channel->hostnames());
        self::assertTrue($channel->isActive());

        $plain = Channel::create('store', 'Store', Currency::of('CAD'), 'fr');
        self::assertNull($plain->theme());
        self::assertNull($plain->taxZone());
    }

    public function testItAnswersOnItsHostnames(): void
    {
        $channel = Channel::create('web', 'Web', Currency::of('CAD'), 'fr_CA');
        $channel->answerOn('Boutique.Example.ca');
        $channel->answerOn('boutique.example.ca');
        $channel->answerOn('www.example.ca');
        $channel->answerOn('localhost');

        self::assertSame(['boutique.example.ca', 'www.example.ca', 'localhost'], $channel->hostnames());
        self::assertTrue($channel->answersOn('BOUTIQUE.example.ca'));
        self::assertFalse($channel->answersOn('example.ca'));

        $channel->stopAnsweringOn('WWW.example.ca');
        self::assertSame(['boutique.example.ca', 'localhost'], $channel->hostnames());
    }

    public function testItIsRenamedRepresentedAndClosed(): void
    {
        $channel = Channel::create('web', 'Web', Currency::of('CAD'), 'fr_CA');
        $channel->rename('Boutique');
        $channel->present('en_CA', 'dark');
        $channel->startOrdersIn('ON');

        self::assertSame(['Boutique', 'en_CA', 'dark', 'ON'], [$channel->name(), $channel->locale(), $channel->theme(), $channel->taxZone()]);

        $channel->present('sr_Latn_RS', null);
        $channel->startOrdersIn(null);
        self::assertSame(['sr_Latn_RS', null, null], [$channel->locale(), $channel->theme(), $channel->taxZone()]);

        $channel->close();
        self::assertFalse($channel->isActive());
        $channel->reopen();
        self::assertTrue($channel->isActive());
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusals(): iterable
    {
        $cad = Currency::of('CAD');

        yield 'uppercase code' => [static fn (): Channel => Channel::create('Web', 'W', $cad, 'fr'), '"Web" is not a channel code: up to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'code too long' => [static fn (): Channel => Channel::create(str_repeat('c', 65), 'W', $cad, 'fr'), 'is not a channel code'];
        yield 'code with a trailing newline' => [static fn (): Channel => Channel::create("web\n", 'W', $cad, 'fr'), 'is not a channel code'];
        yield 'blank name' => [static fn (): Channel => Channel::create('web', ' ', $cad, 'fr'), 'A channel has a name.'];
        yield 'blank rename' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->rename(''), 'A channel has a name.'];
        yield 'locale with a dash' => [static fn (): Channel => Channel::create('web', 'W', $cad, 'fr-CA'), '"fr-CA" is not a locale: "fr", "fr_CA" or "sr_Latn_RS".'];
        yield 'uppercase language' => [static fn (): Channel => Channel::create('web', 'W', $cad, 'FR'), 'is not a locale'];
        yield 'something before the language' => [static fn (): Channel => Channel::create('web', 'W', $cad, 'Xfr_CA'), 'is not a locale'];
        yield 'locale with a trailing newline' => [static fn (): Channel => Channel::create('web', 'W', $cad, "fr\n"), 'is not a locale'];
        yield 'theme with a space' => [static fn (): Channel => Channel::create('web', 'W', $cad, 'fr', 'dark mode'), '"dark mode" is not a theme: 1 to 64 visible ASCII characters.'];
        yield 'empty tax zone' => [static fn (): Channel => Channel::create('web', 'W', $cad, 'fr', null, ''), '"" is not a tax zone: 1 to 64 visible ASCII characters.'];
        yield 'tax zone with a trailing newline' => [static fn (): Channel => Channel::create('web', 'W', $cad, 'fr', null, "QC\n"), 'is not a tax zone'];
        yield 'hostname with a scheme' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->answerOn('https://example.ca'), '"https://example.ca" is not a hostname.'];
        yield 'hostname ending with a dash' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->answerOn('shop-.example.ca'), 'is not a hostname'];
        yield 'hostname with an empty label' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->answerOn('shop..ca'), 'is not a hostname'];
        yield 'label too long' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->answerOn(str_repeat('a', 64).'.ca'), 'is not a hostname'];
        yield 'hostname too long' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->answerOn(implode('.', array_fill(0, 5, str_repeat('a', 63)))), 'is not a hostname'];
        yield 'hostname with a trailing newline' => [static fn () => Channel::create('web', 'W', $cad, 'fr')->answerOn("example.ca\n"), 'is not a hostname'];
    }

    /**
     * @param callable(): mixed $refused
     */
    #[DataProvider('refusals')]
    public function testAnInvalidChannelIsRefused(callable $refused, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $refused();
    }

    public function testTheBoundsAreAccepted(): void
    {
        $channel = Channel::create(str_repeat('c', 64), 'C', Currency::of('CAD'), 'fil', str_repeat('t', 64), str_repeat('z', 64));
        $channel->answerOn(implode('.', array_fill(0, 3, str_repeat('a', 63))).'.'.str_repeat('b', 61));

        self::assertSame(253, \strlen($channel->hostnames()[0]));
        self::assertSame('fil', $channel->locale());
    }

    public function testTheChannelOfARequestIsFoundByItsHost(): void
    {
        $web = Channel::create('web', 'Web', Currency::of('CAD'), 'fr_CA', null, 'QC');
        $web->answerOn('boutique.example.ca');

        $closed = Channel::create('old', 'Old', Currency::of('CAD'), 'fr_CA');
        $closed->answerOn('old.example.ca');
        $closed->close();

        $channels = new Channels($this->repository($web, $closed));

        self::assertSame($web, $channels->forHost('Boutique.Example.ca:8443'));
        self::assertSame($web, $channels->open('web'));

        foreach (['old.example.ca' => 'No open channel answers on "old.example.ca".', 'NoWhere.ca:80' => 'No open channel answers on "nowhere.ca".'] as $host => $message) {
            try {
                $channels->forHost($host);
                self::fail('A channel answered.');
            } catch (UnknownChannel $unknownChannel) {
                self::assertSame($message, $unknownChannel->getMessage());
            }
        }

        foreach (['old', 'gone'] as $code) {
            try {
                $channels->open($code);
                self::fail('A channel opened.');
            } catch (UnknownChannel $unknownChannel) {
                self::assertSame(\sprintf('No open channel is known as "%s".', $code), $unknownChannel->getMessage());
            }
        }
    }

    public function testAChannelOpensItsOrders(): void
    {
        $channel = Channel::create('store-mtl', 'Store', Currency::of('CAD'), 'fr_CA', null, 'QC');
        $order = new Channels($this->repository($channel))->newOrder($channel, 'S-1');

        self::assertInstanceOf(Order::class, $order);
        self::assertSame(['S-1', 'CAD', 'store-mtl', 'QC'], [$order->number, $order->currency->code, $order->channel(), $order->taxZone()]);
        self::assertInstanceOf(DomainError::class, UnknownChannel::code('x'));
    }

    public function testTheChannelTable(): void
    {
        $metadata = new ClassMetadata(Channel::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/Domain/Model'])->loadMetadataForClass(Channel::class, $metadata);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame('phil_commerce_channel', $metadata->getTableName());
        self::assertSame(['code'], $metadata->getIdentifierFieldNames());
        self::assertSame([
            'hostnames' => ['hostnames', 'json', null, false, null],
            'active' => ['active', 'boolean', null, false, null],
            'unpaidExpiryHours' => ['unpaid_expiry_hours', 'integer', null, true, null],
            'expiredRetentionDays' => ['expired_retention_days', 'integer', null, true, null],
            'code' => ['code', 'string', 64, false, null],
            'name' => ['name', 'string', 255, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'locale' => ['locale', 'string', 16, false, null],
            'theme' => ['theme', 'string', 64, true, null],
            'taxZone' => ['tax_zone', 'string', 64, true, null],
        ], $columns);
        self::assertSame(64, \strlen(Channel::create(str_repeat('c', 64), 'C', Currency::of('CAD'), 'fr')->code));
    }

    private function repository(Channel ...$channels): ChannelRepository
    {
        return new class(array_values($channels)) implements ChannelRepository {
            /** @param list<Channel> $channels */
            public function __construct(private array $channels)
            {
            }

            public function find(string $code): ?Channel
            {
                return array_find($this->channels, static fn (Channel $channel): bool => $channel->code === $code);
            }

            public function all(): array
            {
                return $this->channels;
            }

            public function add(Channel $channel): void
            {
                $this->channels[] = $channel;
            }
        };
    }
}
