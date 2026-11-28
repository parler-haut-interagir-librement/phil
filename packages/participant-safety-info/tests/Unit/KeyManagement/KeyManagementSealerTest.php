<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit\KeyManagement;

use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use Phil\ParticipantSafetyInfo\KeyManagement\KeyManagementSealer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

#[CoversClass(KeyManagementSealer::class)]
final class KeyManagementSealerTest extends TestCase
{
    public function testWhatItSealsOpensUnderTheSameContext(): void
    {
        $sealer = new KeyManagementSealer(new InMemoryKms('kms'), 'safety');
        $sealed = $sealer->seal('Peanuts', 'context');

        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]+=*$#', $sealed);
        $bytes = base64_decode($sealed, true);
        self::assertIsString($bytes);
        self::assertStringNotContainsString('Peanuts', $bytes);
        self::assertSame('Peanuts', $sealer->open($sealed, 'context'));
        self::assertNotSame($sealed, $sealer->seal('Peanuts', 'context'));
    }

    public function testATextSealedBeforeTheKeyChangedStillOpens(): void
    {
        $kms = new InMemoryKms('kms');
        $sealed = new KeyManagementSealer($kms, 'old')->seal('Peanuts', 'context');

        self::assertSame('Peanuts', new KeyManagementSealer($kms, 'new')->open($sealed, 'context'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contexts(): iterable
    {
        yield 'another context' => ['other'];
        yield 'no context' => [''];
    }

    #[DataProvider('contexts')]
    public function testItDoesNotOpenUnderAnotherContext(string $context): void
    {
        $sealer = new KeyManagementSealer(new InMemoryKms('kms'), 'safety');

        $this->expectException(UnreadableSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('Sealed safety information does not open');

        $sealer->open($sealer->seal('Peanuts', 'context'), $context);
    }

    public function testItDoesNotOpenThroughAnotherKms(): void
    {
        $sealed = new KeyManagementSealer(new InMemoryKms('one'), 'safety')->seal('Peanuts', 'context');

        $this->expectException(UnreadableSafetyInfo::class);

        new KeyManagementSealer(new InMemoryKms('two'), 'safety')->open($sealed, 'context');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function garbage(): iterable
    {
        yield 'not base64' => ['%%%'];
        yield 'base64 of nothing useful' => [base64_encode('not an envelope')];
        yield 'empty' => [''];
    }

    #[DataProvider('garbage')]
    public function testGarbageIsUnreadable(string $sealed): void
    {
        $this->expectException(UnreadableSafetyInfo::class);

        new KeyManagementSealer(new InMemoryKms('kms'), 'safety')->open($sealed, 'context');
    }

    public function testAnAlteredTextIsUnreadable(): void
    {
        $sealer = new KeyManagementSealer(new InMemoryKms('kms'), 'safety');
        $bytes = base64_decode($sealer->seal('Peanuts', 'context'), true);
        self::assertIsString($bytes);
        $bytes[\strlen($bytes) - 1] = \chr(\ord($bytes[\strlen($bytes) - 1]) ^ 1);

        try {
            $sealer->open(base64_encode($bytes), 'context');
            self::fail('An altered text opened.');
        } catch (UnreadableSafetyInfo $unreadableSafetyInfo) {
            self::assertNotNull($unreadableSafetyInfo->getPrevious());
        }
    }

    public function testABackendThatFailsWithAPlainRuntimeExceptionLeavesItUnreadable(): void
    {
        $kms = new InMemoryKms('kms');
        $sealed = new KeyManagementSealer($kms, 'safety')->seal('Peanuts', 'context');
        $failing = new readonly class($kms) implements DataKeyGeneratorInterface {
            public function __construct(private InMemoryKms $kms)
            {
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                return $this->kms->generateDataKey($keyId, $length, $aad);
            }

            public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
            {
                return $this->kms->wrapDataKey($keyId, $dataKey, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                throw new RuntimeException('The backend is unreachable.');
            }
        };

        try {
            new KeyManagementSealer($failing, 'safety')->open($sealed, 'context');
            self::fail('A failing backend opened the text.');
        } catch (UnreadableSafetyInfo $unreadableSafetyInfo) {
            self::assertInstanceOf(RuntimeException::class, $unreadableSafetyInfo->getPrevious());
            self::assertSame('The backend is unreachable.', $unreadableSafetyInfo->getPrevious()->getMessage());
        }
    }
}
