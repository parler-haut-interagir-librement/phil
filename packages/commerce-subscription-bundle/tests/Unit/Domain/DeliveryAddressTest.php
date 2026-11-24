<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use Phil\Address\Address;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryAddress::class)]
final class DeliveryAddressTest extends TestCase
{
    public function testItIsWrittenAndReadBack(): void
    {
        $delivery = DeliveryAddress::of($this->address(), 'courier', '+15145550123');
        $array = $delivery->toArray();

        self::assertSame(['address' => ['country' => 'CA', 'givenName' => 'Ada', 'addressLine1' => '1 rue Saint-Paul', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2Y 1G6'], 'method' => 'courier', 'phone' => '+15145550123'], $array);
        self::assertTrue(DeliveryAddress::fromArray($array)->equals($delivery));
        self::assertSame('courier', $delivery->method);
        self::assertSame('+15145550123', $delivery->phone);
    }

    public function testItDropsItsPhone(): void
    {
        $delivery = DeliveryAddress::of($this->address(), 'post', '+15145550123');
        $without = $delivery->withoutPhone();

        self::assertNull($without->phone);
        self::assertSame('post', $without->method);
        self::assertTrue($without->address->equals($delivery->address));
        self::assertFalse($without->equals($delivery));
        self::assertFalse($delivery->equals(DeliveryAddress::of($this->address(), 'courier', '+15145550123')));
        self::assertFalse($delivery->equals(DeliveryAddress::of(Address::of('CA', ['givenName' => 'Bob']), 'post', '+15145550123')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methods(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Post'];
        yield 'leading dash' => ['-post'];
        yield 'space' => ['post office'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing new line' => ["post\n"];
    }

    #[DataProvider('methods')]
    public function testAWrongMethodIsRefused(string $method): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A delivery method is named by 1 to 64');

        DeliveryAddress::of($this->address(), $method);
    }

    public function testTheLongestMethodsAndShortestNumbersAreKept(): void
    {
        self::assertSame(str_repeat('a', 64), DeliveryAddress::of($this->address(), str_repeat('a', 64))->method);
        self::assertSame('a', DeliveryAddress::of($this->address(), 'a')->method);
        self::assertSame('0_.:-a', DeliveryAddress::of($this->address(), '0_.:-a')->method);
        self::assertSame('+1234567', DeliveryAddress::of($this->address(), 'post', '+1234567')->phone);
        self::assertSame('+123456789012345', DeliveryAddress::of($this->address(), 'post', '+123456789012345')->phone);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function phones(): iterable
    {
        yield 'no plus' => ['15145550123'];
        yield 'leading zero' => ['+05145550123'];
        yield 'too short' => ['+123456'];
        yield 'too long' => ['+1234567890123456'];
        yield 'spaces' => ['+1 514 555 0123'];
        yield 'trailing new line' => ["+15145550123\n"];
    }

    #[DataProvider('phones')]
    public function testAWrongPhoneIsRefused(string $phone): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('written as E.164');

        DeliveryAddress::of($this->address(), 'post', $phone);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function stored(): iterable
    {
        yield 'not an array' => ['post', 'an array with a method'];
        yield 'no method' => [['address' => ['country' => 'CA']], 'an array with a method'];
        yield 'a method that is not a text' => [['method' => 3, 'address' => ['country' => 'CA']], 'an array with a method'];
        yield 'a phone that is not a text' => [['method' => 'post', 'phone' => 5, 'address' => ['country' => 'CA']], 'is a text'];
        yield 'no address' => [['method' => 'post'], 'not an array with a country'];
        yield 'a wrong field' => [['method' => 'post', 'address' => ['country' => 'CA', 'planet' => 'Mars']], 'not a field of an address'];
    }

    #[DataProvider('stored')]
    public function testAWrongStoredAddressIsRefused(mixed $data, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        DeliveryAddress::fromArray($data);
    }

    public function testAStoredAddressWithoutPhoneIsRead(): void
    {
        self::assertNull(DeliveryAddress::fromArray(['method' => 'post', 'address' => ['country' => 'CA']])->phone);
    }

    private function address(): Address
    {
        return Address::of('CA', ['givenName' => 'Ada', 'addressLine1' => '1 rue Saint-Paul', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2Y 1G6']);
    }
}
