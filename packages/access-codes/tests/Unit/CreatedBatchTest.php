<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use DateTimeImmutable;
use LogicException;
use Phil\AccessCodes\Batch;
use Phil\AccessCodes\CodeFormat;
use Phil\AccessCodes\CreatedBatch;
use Phil\AccessCodes\Exception\CodesAlreadyTaken;
use Phil\AccessCodes\RedemptionLinks;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

#[CoversClass(CreatedBatch::class)]
#[CoversClass(CodesAlreadyTaken::class)]
final class CreatedBatchTest extends TestCase
{
    public function testTheCodesAreTakenOnceAsPeopleReadThem(): void
    {
        $created = $this->created();

        self::assertTrue($created->hasCodes());
        self::assertSame(['7KQ2-M9XD-H4TB-H', '0000-0000-0000-0'], $created->takeCodes());
        self::assertFalse($created->hasCodes());

        $this->expectException(CodesAlreadyTaken::class);
        $this->expectExceptionMessageIsOrContains('The codes of batch "b1" were already taken: they are given once, at its creation.');

        $created->takeCodes();
    }

    public function testTheCodesAreTakenInCanonicalForm(): void
    {
        self::assertSame(['7KQ2M9XDH4TBH', '0000000000000'], $this->created()->takeCodes(display: false));
    }

    public function testTheCsvHoldsTheCodesOnce(): void
    {
        $created = $this->created();
        $file = new SplTempFileObject();

        self::assertSame(2, $created->writeCsv($file));
        self::assertSame("code\n7KQ2-M9XD-H4TB-H\n0000-0000-0000-0\n", $this->contents($file));
        self::assertFalse($created->hasCodes());

        $this->expectException(CodesAlreadyTaken::class);

        $created->writeCsv($file);
    }

    public function testTheCsvCarriesThePrefilledLinks(): void
    {
        $file = new SplTempFileObject();

        $this->created()->writeCsv($file, new RedemptionLinks(Url::fromString('https://shop.example/redeem?a=1')));

        self::assertSame(
            "code,link\n7KQ2-M9XD-H4TB-H,https://shop.example/redeem?a=1&code=7KQ2M9XDH4TBH\n0000-0000-0000-0,https://shop.example/redeem?a=1&code=0000000000000\n",
            $this->contents($file),
        );
    }

    public function testACodeWithASeparatorIsQuoted(): void
    {
        $file = new SplTempFileObject();
        new CreatedBatch(new Batch('b1', 'Cards', 1, new DateTimeImmutable('2026-11-13')), new CodeFormat(groupSize: 0), ['A B'])->writeCsv($file);

        self::assertSame("code\n\"A B\"\n", $this->contents($file));
    }

    public function testTheCodesNeverShowInADump(): void
    {
        $created = $this->created();

        self::assertSame(['batch' => $created->batch, 'codes' => '2 codes, hidden'], $created->__debugInfo());
        $created->takeCodes();
        self::assertSame(['batch' => $created->batch, 'codes' => 'taken'], $created->__debugInfo());
    }

    public function testItIsNeverSerialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A created batch holds codes in clear: it is never serialized.');

        serialize($this->created());
    }

    public function testItIsNeverCloned(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A created batch holds codes in clear: it is never cloned.');

        $created = $this->created();
        $copy = clone $created;
        self::assertNotSame($created, $copy);
    }

    private function contents(SplTempFileObject $file): string
    {
        $file->rewind();
        $contents = '';

        while (!$file->eof()) {
            $contents .= $file->fgets();
        }

        return $contents;
    }

    private function created(): CreatedBatch
    {
        return new CreatedBatch(new Batch('b1', 'Cards', 2, new DateTimeImmutable('2026-11-13')), new CodeFormat(), ['7KQ2M9XDH4TBH', '0000000000000']);
    }
}
