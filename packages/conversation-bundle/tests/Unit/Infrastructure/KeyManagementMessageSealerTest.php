<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Infrastructure;

use Phil\ConversationBundle\Domain\Exception\UnreadableMessage;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Phil\ConversationBundle\Tests\Unit\Fixture\FailingKms;
use Phil\ConversationBundle\Tests\Unit\Fixture\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException as KeyManagementLogicException;
use Symfony\Component\KeyManagement\SelfContainedFormat;
use Throwable;

#[CoversClass(KeyManagementMessageSealer::class)]
#[CoversClass(UnreadableMessage::class)]
final class KeyManagementMessageSealerTest extends TestCase
{
    public function testATextIsSealedUnderTheCurrentKeyAndOpensOnlyInItsContext(): void
    {
        $sealer = Keys::sealer();
        $sealed = $sealer->seal('See you Friday', 'phil_conversation.message.m1');

        self::assertMatchesRegularExpression('/^pcv1\.a256gcm\.azIwMjY\.A[A-Za-z0-9_-]+$/D', $sealed);
        self::assertStringNotContainsString('Friday', $sealed);
        self::assertNotSame($sealed, $sealer->seal('See you Friday', 'phil_conversation.message.m1'), 'a fresh data key each time');
        self::assertSame('See you Friday', $sealer->open($sealed, 'phil_conversation.message.m1'));
        self::assertSame('k2026', $sealer->currentKey());
        self::assertSame('k2026', Envelope::fromBytes($this->decode(explode('.', $sealed)[3]))->keyId);
        self::assertSame(SelfContainedFormat::ID, \ord($this->decode(explode('.', $sealed)[3])[0]));
        self::assertSame('See you Friday', Keys::sealer('k2025')->open($sealed, 'phil_conversation.message.m1'), 'opened under the key it names, whatever the current key');
        self::assertSame('', $sealer->open($sealer->seal('', 'ctx'), 'ctx'));
        self::assertSame(['pcv1', 'a256gcm'], [KeyManagementMessageSealer::VERSION, KeyManagementMessageSealer::ALGORITHM]);

        try {
            $sealer->open($sealed, 'phil_conversation.message.m2');
            self::fail('A text opened in another context.');
        } catch (UnreadableMessage $unreadableMessage) {
            self::assertSame('The sealed text of phil_conversation.message.m2 does not open.', $unreadableMessage->getMessage());
            self::assertInstanceOf(DecryptionFailedException::class, $unreadableMessage->getPrevious());
        }
    }

    public function testTheVersionAndTheAlgorithmAreAuthenticated(): void
    {
        $sealer = Keys::sealer();
        $parts = explode('.', $sealer->seal('Hi', 'ctx'));

        // A text sealed with the same envelope under another label: the label is part of what the tag covers.
        $encrypter = new EnvelopeEncrypter(Keys::kms());
        $other = (string) $encrypter->encrypt('k2026', 'Hi', "pcv1.other\nctx");
        $relabelled = 'pcv1.a256gcm.'.$parts[2].'.'.sodium_bin2base64($other, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        $this->expectException(UnreadableMessage::class);

        $sealer->open($relabelled, 'ctx');
    }

    /**
     * @param callable(list<string>): string $alter
     */
    #[DataProvider('altered')]
    public function testAnAlteredTextDoesNotOpen(callable $alter, bool $withCause): void
    {
        $sealer = Keys::sealer();
        $parts = explode('.', $sealer->seal('Hi', 'ctx'));

        try {
            $sealer->open($alter($parts), 'ctx');
            self::fail('An altered text opened.');
        } catch (UnreadableMessage $unreadableMessage) {
            self::assertSame('The sealed text of ctx does not open.', $unreadableMessage->getMessage());
            self::assertSame($withCause, $unreadableMessage->getPrevious() instanceof Throwable);
        }
    }

    /**
     * @return iterable<string, array{callable(list<string>): string, bool}>
     */
    public static function altered(): iterable
    {
        $encode = static fn (string $bytes): string => sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $decode = static fn (string $text): string => sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        yield 'not in the format' => [static fn (array $parts): string => 'hello', false];
        yield 'one part less' => [static fn (array $parts): string => implode('.', \array_slice($parts, 0, 3)), false];
        yield 'one part more' => [static fn (array $parts): string => implode('.', [...$parts, 'x']), false];
        yield 'another version' => [static fn (array $parts): string => implode('.', ['pcv2', ...\array_slice($parts, 1)]), false];
        yield 'another algorithm' => [static fn (array $parts): string => implode('.', [$parts[0], 'a128gcm', $parts[2], $parts[3]]), false];
        yield 'a key name that is not base64url' => [static fn (array $parts): string => implode('.', [$parts[0], $parts[1], '***', $parts[3]]), false];
        yield 'an envelope that is not base64url' => [static fn (array $parts): string => implode('.', [$parts[0], $parts[1], $parts[2], '***']), false];
        yield 'an empty envelope' => [static fn (array $parts): string => implode('.', [$parts[0], $parts[1], $parts[2], '']), false];
        yield 'another envelope format' => [static fn (array $parts): string => implode('.', [$parts[0], $parts[1], $parts[2], $encode("\x02".substr($decode($parts[3]), 1))]), false];
        yield 'a truncated envelope' => [static fn (array $parts): string => implode('.', [$parts[0], $parts[1], $parts[2], $encode(substr($decode($parts[3]), 0, 10))]), true];
        yield 'a key name the envelope does not carry' => [static fn (array $parts): string => implode('.', [$parts[0], $parts[1], $encode('k2025'), $parts[3]]), false];
        yield 'a flipped byte of the ciphertext' => [static function (array $parts) use ($encode, $decode): string {
            $bytes = $decode($parts[3]);
            $bytes[-1] = \chr(\ord($bytes[-1]) ^ 1);

            return implode('.', [$parts[0], $parts[1], $parts[2], $encode($bytes)]);
        }, true];
    }

    public function testSealingUnderAKeyTheServiceDoesNotHoldFails(): void
    {
        $this->expectException(KeyNotFoundException::class);

        Keys::sealer('k1999')->seal('Hi', 'ctx');
    }

    public function testATextUnderAKeyTheServiceNoLongerHoldsDoesNotOpen(): void
    {
        $sealed = Keys::sealer('k2025')->seal('Hi', 'ctx');

        try {
            Keys::sealer('k2026', ['k2026'])->open($sealed, 'ctx');
            self::fail('A text opened without its key.');
        } catch (UnreadableMessage $unreadableMessage) {
            self::assertNotNull($unreadableMessage->getPrevious());
        }
    }

    public function testAnEnvelopeThatDoesNotParseIsUnreadable(): void
    {
        $this->expectException(UnreadableMessage::class);

        try {
            Keys::sealer()->open('pcv1.a256gcm.azIwMjY.'.sodium_bin2base64("\x01\x00", \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 'ctx');
        } catch (UnreadableMessage $unreadableMessage) {
            self::assertInstanceOf(InvalidArgumentException::class, $unreadableMessage->getPrevious());

            throw $unreadableMessage;
        }
    }

    public function testTheContextIsBoundWithTheVersionAndTheAlgorithmOnTwoLines(): void
    {
        $encrypter = new EnvelopeEncrypter(Keys::kms());
        $envelope = (string) $encrypter->encrypt('k2026', 'Hi', "pcv1.a256gcm\nctx");
        $sealed = 'pcv1.a256gcm.azIwMjY.'.sodium_bin2base64($envelope, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        self::assertSame('Hi', Keys::sealer()->open($sealed, 'ctx'));

        $sealer = Keys::sealer();
        $envelope = Envelope::fromBytes($this->decode(explode('.', $sealer->seal('Hi', 'ctx'))[3]));
        self::assertSame('Hi', $encrypter->decrypt($envelope, "pcv1.a256gcm\nctx"));
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'a failure of the component that is not a runtime one' => [new KeyManagementLogicException('refused')];
        yield 'a runtime failure of the service' => [new RuntimeException('unreachable')];
    }

    #[DataProvider('failures')]
    public function testAServiceThatFailsToUnwrapLeavesTheTextUnreadable(Throwable $failure): void
    {
        $sealed = Keys::sealer()->seal('Hi', 'ctx');

        try {
            new KeyManagementMessageSealer(new FailingKms($failure), 'k2026')->open($sealed, 'ctx');
            self::fail('A text opened though its key was not unwrapped.');
        } catch (UnreadableMessage $unreadableMessage) {
            self::assertSame($failure, $unreadableMessage->getPrevious());
        }
    }

    private function decode(string $text): string
    {
        return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
