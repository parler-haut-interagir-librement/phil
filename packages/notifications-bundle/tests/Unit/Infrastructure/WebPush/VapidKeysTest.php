<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure\WebPush;

use InvalidArgumentException;
use Phil\NotificationsBundle\Infrastructure\Console\VapidKeysCommand;
use Phil\NotificationsBundle\Infrastructure\WebPush\VapidKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(VapidKeys::class)]
#[CoversClass(VapidKeysCommand::class)]
final class VapidKeysTest extends TestCase
{
    public function testAGeneratedPairIsAPairOfVapidKeys(): void
    {
        $keys = VapidKeys::generate();
        $public = sodium_base642bin($keys['publicKey'], \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        self::assertSame(87, \strlen($keys['publicKey']));
        self::assertSame(43, \strlen($keys['privateKey']));
        self::assertSame("\x04", $public[0]);
        self::assertNotSame($keys, VapidKeys::generate());

        $vapid = new VapidKeys('mailto:ops@example.com', $keys['publicKey'], $keys['privateKey']);
        self::assertSame('mailto:ops@example.com', $vapid->subject);
        self::assertSame($keys['publicKey'], $vapid->publicKey);
        self::assertSame($keys['privateKey'], $vapid->privateKey);
        self::assertSame('https://example.com/contact', new VapidKeys('https://example.com/contact', $keys['publicKey'].'=', $keys['privateKey'].'=')->subject);
        self::assertSame('https://example.com', new VapidKeys('https://example.com', $keys['publicKey'], $keys['privateKey'])->subject);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalid(): iterable
    {
        $keys = VapidKeys::generate();

        yield 'no contact' => ['', $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'plain HTTP' => ['http://example.com', $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'an address without domain' => ['mailto:ops', $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'a contact with a space' => ['mailto:ops@example.com x', $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'a page without host' => ['https:///x', $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'a contact with a line break after it' => ["mailto:ops@example.com\n", $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'a page with a line break after it' => ["https://example.com/x\n", $keys['publicKey'], $keys['privateKey'], 'The VAPID contact is a "mailto:" address or an "https://" page.'];
        yield 'a public key that is no Base64' => ['mailto:ops@example.com', '+/', $keys['privateKey'], 'The VAPID public key is written in URL-safe Base64.'];
        yield 'a short public key' => ['mailto:ops@example.com', substr($keys['publicKey'], 0, 84), $keys['privateKey'], 'The VAPID public key is an uncompressed P-256 point of 65 bytes, in URL-safe Base64.'];
        yield 'a compressed public key' => ['mailto:ops@example.com', 'A'.substr($keys['publicKey'], 1), $keys['privateKey'], 'The VAPID public key is an uncompressed P-256 point of 65 bytes, in URL-safe Base64.'];
        yield 'a private key that is no Base64' => ['mailto:ops@example.com', $keys['publicKey'], '+/', 'The VAPID private key is written in URL-safe Base64.'];
        yield 'a short private key' => ['mailto:ops@example.com', $keys['publicKey'], substr($keys['privateKey'], 0, 40), 'The VAPID private key is 32 bytes, in URL-safe Base64.'];
    }

    #[DataProvider('invalid')]
    public function testWhatIsNoVapidKeyIsRefused(string $subject, string $public, string $private, string $message): void
    {
        $this->expectExceptionObject(new InvalidArgumentException($message));

        new VapidKeys($subject, $public, $private);
    }

    public function testTheSchemeOfTheContactMayBeInCapitals(): void
    {
        $keys = VapidKeys::generate();

        self::assertSame('MAILTO:ops@example.com', new VapidKeys('MAILTO:ops@example.com', $keys['publicKey'], $keys['privateKey'])->subject);
        self::assertSame('HTTPS://example.com/x', new VapidKeys('HTTPS://example.com/x', $keys['publicKey'], $keys['privateKey'])->subject);
    }

    public function testTheCoordinatesAreWrittenOnThirtyTwoBytesEach(): void
    {
        $keys = VapidKeys::fromEc(['x' => 'X', 'y' => 'Y', 'd' => 'D']);

        self::assertSame("\x04".str_repeat("\0", 31).'X'.str_repeat("\0", 31).'Y', sodium_base642bin($keys['publicKey'], \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
        self::assertSame(str_repeat("\0", 31).'D', sodium_base642bin($keys['privateKey'], \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notAKey(): iterable
    {
        yield 'no details' => [null];
        yield 'no coordinate x' => [['y' => 'Y', 'd' => 'D']];
        yield 'no coordinate y' => [['x' => 'X', 'd' => 'D']];
        yield 'no scalar' => [['x' => 'X', 'y' => 'Y']];
        yield 'only x' => [['x' => 'X']];
        yield 'only y' => [['y' => 'Y']];
        yield 'only the scalar' => [['d' => 'D']];
        yield 'a coordinate that is no string' => [['x' => 1, 'y' => 'Y', 'd' => 'D']];
    }

    #[DataProvider('notAKey')]
    public function testWhatIsNoP256KeyIsRefused(mixed $ec): void
    {
        $this->expectExceptionObject(new RuntimeException('OpenSSL cannot make a P-256 key on this system.'));

        VapidKeys::fromEc($ec);
    }

    public function testTheCommandWritesTheTwoVariables(): void
    {
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, new VapidKeysCommand()(new SymfonyStyle(new ArrayInput([]), $output)));

        $text = $output->fetch();
        self::assertMatchesRegularExpression('/^PHIL_NOTIFICATIONS_VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{87}$/m', $text);
        self::assertMatchesRegularExpression('/^PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{43}$/m', $text);
        self::assertStringContainsString('secrets:set', $text);
    }
}
