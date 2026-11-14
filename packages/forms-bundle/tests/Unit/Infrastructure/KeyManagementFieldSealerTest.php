<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use Phil\FormsBundle\Infrastructure\KeyManagement\KeyManagementFieldSealer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException as KmsRuntimeException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Throwable;

#[CoversClass(KeyManagementFieldSealer::class)]
final class KeyManagementFieldSealerTest extends TestCase
{
    public function testAValueIsSealedUnderTheCurrentKeyAndOpensOnlyInItsContext(): void
    {
        $sealer = new KeyManagementFieldSealer($this->kms(), 'k2026');
        $sealed = $sealer->seal('Asthma', 'phil_forms.entry.e1.health');

        self::assertMatchesRegularExpression('/^phs1\.azIwMjY\.Ag[A-Za-z0-9_-]+$/D', $sealed);
        self::assertStringNotContainsString('Asthma', $sealed);
        self::assertNotSame($sealed, $sealer->seal('Asthma', 'phil_forms.entry.e1.health'), 'a fresh data key each time');
        self::assertSame('Asthma', $sealer->open($sealed, 'phil_forms.entry.e1.health'));
        self::assertSame('k2026', Envelope::fromBytes(substr($this->decode(explode('.', $sealed)[2]), 1))->keyId);
        self::assertSame('Asthma', new KeyManagementFieldSealer($this->kms(), 'k2025')->open($sealed, 'phil_forms.entry.e1.health'), 'opened under the key it names, after the current key changes');
        self::assertSame('', $sealer->open($sealer->seal('', 'ctx'), 'ctx'));
        self::assertSame(['phs1.', 2], [KeyManagementFieldSealer::PREFIX, KeyManagementFieldSealer::FORMAT]);

        try {
            $sealer->open($sealed, 'phil_forms.entry.e2.health');
            self::fail('A value opened in another context.');
        } catch (UnreadableValue $unreadableValue) {
            self::assertSame('The sealed value of phil_forms.entry.e2.health does not open.', $unreadableValue->getMessage());
            self::assertInstanceOf(DecryptionFailedException::class, $unreadableValue->getPrevious());
        }
    }

    public function testAKeyNameThatIsNotTheEnvelopesDoesNotOpen(): void
    {
        $sealer = new KeyManagementFieldSealer($this->kms(), 'k2026');
        $parts = explode('.', $sealer->seal('Asthma', 'ctx'));
        $renamed = $parts[0].'.'.$this->encode('k2025').'.'.$parts[2];

        try {
            $sealer->open($renamed, 'ctx');
            self::fail('A value opened under a name its envelope does not carry.');
        } catch (UnreadableValue $unreadableValue) {
            self::assertNull($unreadableValue->getPrevious());
        }
    }

    public function testSealingUnderAMasterKeyTheKmsDoesNotHoldFails(): void
    {
        $this->expectException(KeyNotFoundException::class);

        new KeyManagementFieldSealer($this->kms(), 'k1999')->seal('Asthma', 'ctx');
    }

    public function testAValueUnderAMasterKeyTheKmsNoLongerHoldsDoesNotOpen(): void
    {
        $sealed = new KeyManagementFieldSealer($this->kms(), 'k2025')->seal('Asthma', 'ctx');

        $this->expectException(UnreadableValue::class);

        new KeyManagementFieldSealer(new SodiumKms(new InMemoryKeyLoader(['k2026' => str_repeat('b', 32)])), 'k2026')->open($sealed, 'ctx');
    }

    public function testAValueDoesNotOpenUnderAnotherKeyOfTheSameName(): void
    {
        $sealed = new KeyManagementFieldSealer($this->kms(), 'k2026')->seal('Asthma', 'ctx');

        try {
            new KeyManagementFieldSealer(new SodiumKms(new InMemoryKeyLoader(['k2026' => str_repeat('z', 32)])), 'k2026')->open($sealed, 'ctx');
            self::fail('A value opened under the wrong key.');
        } catch (UnreadableValue $unreadableValue) {
            self::assertInstanceOf(DecryptionFailedException::class, $unreadableValue->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'a failure of the component' => [new KmsRuntimeException('The KMS is unreachable.')];
        yield 'a failure of its client' => [new RuntimeException('The KMS is unreachable.')];
        yield 'a refusal of the component' => [new InvalidArgumentException('The KMS refuses.')];
    }

    #[DataProvider('failures')]
    public function testAKmsThatFailsWhileOpeningGivesAnUnreadableValueThatKeepsTheReason(Throwable $failure): void
    {
        $sealed = new KeyManagementFieldSealer($this->kms(), 'k2026')->seal('Asthma', 'ctx');
        $failing = new readonly class($failure) implements DataKeyGeneratorInterface {
            public function __construct(
                private Throwable $failure,
            ) {
            }

            #[Override]
            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                throw $this->failure;
            }

            #[Override]
            public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
            {
                throw $this->failure;
            }

            #[Override]
            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                throw $this->failure;
            }
        };

        try {
            new KeyManagementFieldSealer($failing, 'k2026')->open($sealed, 'ctx');
            self::fail('A value opened without its KMS.');
        } catch (UnreadableValue $unreadableValue) {
            self::assertSame($failure, $unreadableValue->getPrevious());
        }
    }

    public function testAnEnvelopeThatDoesNotParseKeepsTheReason(): void
    {
        try {
            new KeyManagementFieldSealer($this->kms(), 'k2026')->open('phs1.'.$this->encode('k2026').'.'.$this->encode("\2\x09junk"), 'ctx');
            self::fail('A malformed envelope opened.');
        } catch (UnreadableValue $unreadableValue) {
            self::assertInstanceOf(InvalidArgumentException::class, $unreadableValue->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        $sealed = new KeyManagementFieldSealer(new SodiumKms(new InMemoryKeyLoader(['k2026' => str_repeat('b', 32)])), 'k2026')->seal('Asthma', 'ctx');
        [, $key, $envelope] = explode('.', $sealed);
        $raw = base64_decode(strtr($envelope, '-_', '+/'), true);
        \assert(\is_string($raw));
        $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        yield 'empty' => [''];
        yield 'another prefix' => ['phs2.'.$key.'.'.$envelope];
        yield 'no prefix' => [$key.'.'.$envelope.'.x'];
        yield 'a prefix without its dot' => ['phs1'.$key.'.'.$envelope.'.x'];
        yield 'two parts' => ['phs1.'.$key];
        yield 'four parts' => ['phs1.'.$key.'.'.$envelope.'.'.$envelope];
        yield 'key not base64' => ['phs1.***.'.$envelope];
        yield 'key in standard base64' => ['phs1.'.$key.'+.'.$envelope];
        yield 'key of one character' => ['phs1.A.'.$envelope];
        yield 'empty key' => ['phs1..'.$envelope];
        yield 'envelope not base64' => ['phs1.'.$key.'.***'];
        yield 'envelope with padding' => ['phs1.'.$key.'.'.$envelope.'='];
        yield 'envelope with a space' => ['phs1.'.$key.'. '.$envelope];
        yield 'envelope of one character' => ['phs1.'.$key.'.A'];
        yield 'empty envelope' => ['phs1.'.$key.'.'];
        yield 'another format' => ['phs1.'.$key.'.'.$encode("\3".substr($raw, 1))];
        yield 'no format' => ['phs1.'.$key.'.'.$encode(substr($raw, 1))];
        yield 'truncated envelope' => ['phs1.'.$key.'.'.$encode(substr($raw, 0, 20))];
        yield 'altered envelope' => ['phs1.'.$key.'.'.$encode(substr($raw, 0, -1).\chr(\ord($raw[\strlen($raw) - 1]) ^ 1))];
    }

    #[DataProvider('malformed')]
    public function testAMalformedValueDoesNotOpen(string $sealed): void
    {
        $this->expectException(UnreadableValue::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of ctx does not open.');

        new KeyManagementFieldSealer($this->kms(), 'k2026')->open($sealed, 'ctx');
    }

    private function kms(): SodiumKms
    {
        return new SodiumKms(new InMemoryKeyLoader(['k2025' => str_repeat('a', 32), 'k2026' => str_repeat('b', 32)]));
    }

    private function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function decode(string $text): string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);
        self::assertIsString($bytes);

        return $bytes;
    }
}
