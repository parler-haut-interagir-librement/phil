<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\CheckedCurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckedCurrentRecipient::class)]
final class CheckedCurrentRecipientTest extends TestCase
{
    public function testAStableIdentifierIsTheRecipient(): void
    {
        self::assertSame('member-42', $this->checked('member-42')->id());
        self::assertSame('0190f3c2-7d4e-7b1a-9c3f-2b6d8e1a4f55', $this->checked('0190f3c2-7d4e-7b1a-9c3f-2b6d8e1a4f55')->id());
    }

    public function testAVisitorIsNoOne(): void
    {
        self::assertNull($this->checked(null)->id());
    }

    #[DataProvider('addresses')]
    public function testAnIdentifierThatLooksLikeAnEmailAddressIsRefused(string $id): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('The current recipient looks like an email address: name members by a stable identifier');

        $this->checked($id)->id();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addresses(): iterable
    {
        yield 'address' => ['ana@example.com'];
        yield 'local part only' => ['ana@'];
        yield 'domain only' => ['@example.com'];
    }

    public function testWhatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('The current recipient (length 9) is not an identifier');

        $this->checked('Ana Lopez')->id();
    }

    private function checked(?string $id): CheckedCurrentRecipient
    {
        return new CheckedCurrentRecipient(new readonly class($id) implements CurrentRecipient {
            public function __construct(private ?string $id)
            {
            }

            public function id(): ?string
            {
                return $this->id;
            }
        });
    }
}
