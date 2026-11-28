<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit\KeyManagement;

use Phil\CodeOfConduct\Exception\UnreadableText;
use Phil\CodeOfConduct\KeyManagement\KeyManagementTextSealer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Test\SwitchableKms;

#[CoversClass(KeyManagementTextSealer::class)]
#[CoversClass(UnreadableText::class)]
final class KeyManagementTextSealerTest extends TestCase
{
    public function testWhatItSealsOpensUnderTheSameContext(): void
    {
        $sealer = new KeyManagementTextSealer(new InMemoryKms('kms'), 'reports');
        $sealed = $sealer->seal('What happened', 'context');
        $bytes = base64_decode($sealed, true);

        self::assertIsString($bytes);
        self::assertMatchesRegularExpression('#^[A-Za-z0-9+/]+=*$#', $sealed);
        self::assertStringNotContainsString('What happened', $bytes);
        self::assertSame('What happened', $sealer->open($sealed, 'context'));
    }

    public function testATextSealedBeforeTheKeyChangedStillOpens(): void
    {
        $kms = new InMemoryKms('kms');

        self::assertSame('Text', new KeyManagementTextSealer($kms, 'new')->open(new KeyManagementTextSealer($kms, 'old')->seal('Text', 'c'), 'c'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not base64' => ['%%%'];
        yield 'not an envelope' => [base64_encode('not an envelope')];
        yield 'empty' => [''];
    }

    #[DataProvider('unreadable')]
    public function testGarbageIsUnreadable(string $sealed): void
    {
        $this->expectException(UnreadableText::class);
        $this->expectExceptionMessageIsOrContains('A sealed text of a report does not open');

        new KeyManagementTextSealer(new InMemoryKms('kms'), 'reports')->open($sealed, 'context');
    }

    public function testItDoesNotOpenUnderAnotherContext(): void
    {
        $sealer = new KeyManagementTextSealer(new InMemoryKms('kms'), 'reports');

        try {
            $sealer->open($sealer->seal('Text', 'one'), 'two');
            self::fail('The text opened under another context.');
        } catch (UnreadableText $unreadableText) {
            self::assertNotNull($unreadableText->getPrevious());
        }
    }

    public function testAKmsThatIsDownLeavesTheTextUnreadableWithoutADomainCode(): void
    {
        $kms = new SwitchableKms(new InMemoryKms('kms'));
        $sealer = new KeyManagementTextSealer($kms, 'reports');
        $sealed = $sealer->seal('Text', 'c');
        $kms->down = true;

        try {
            $sealer->open($sealed, 'c');
            self::fail('The text opened while the KMS was down.');
        } catch (UnreadableText $unreadableText) {
            self::assertSame(0, $unreadableText->getCode());
            self::assertInstanceOf(RuntimeException::class, $unreadableText->getPrevious());
        }
    }
}
