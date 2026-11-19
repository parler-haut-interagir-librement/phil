<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application\Code;

use Phil\ShortLinkBundle\Application\Code\CodeGenerator;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Exception\InvalidCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(CodeGenerator::class)]
final class CodeGeneratorTest extends TestCase
{
    public function testACodeWritesARandomNumberUpToTheMaximum(): void
    {
        $codec = new SqidCodec(minLength: 6);
        $generator = new CodeGenerator($codec, 1_000_000, new Randomizer(new Mt19937(7)));
        $expected = new Randomizer(new Mt19937(7));

        for ($draw = 0; $draw < 50; ++$draw) {
            $code = $generator->generate();
            $number = $expected->getInt(0, 1_000_000);
            self::assertSame($codec->encode([$number]), $code);
            self::assertSame([$number], $codec->decode($code));
        }
    }

    public function testTheDefaultDrawsFromTheWholeRange(): void
    {
        $codec = new SqidCodec();
        $numbers = [];
        $largest = 0;

        for ($draw = 0; $draw < 20; ++$draw) {
            $numbers[] = $number = $codec->decode(new CodeGenerator($codec)->generate())[0];
            $largest = max($largest, $number);
        }

        self::assertLessThanOrEqual(CodeGenerator::DEFAULT_MAX_NUMBER, $largest);
        self::assertGreaterThan(1_000_000, $largest);
        self::assertCount(20, array_unique($numbers));
        self::assertSame(2 ** 40 - 1, CodeGenerator::DEFAULT_MAX_NUMBER);
    }

    public function testTooFewNumbersAreRefused(): void
    {
        new CodeGenerator(new SqidCodec(), 1_000_000);

        $this->expectException(InvalidCodec::class);
        $this->expectExceptionMessageIsOrContains('The largest number of a code is 1000000 or more.');

        new CodeGenerator(new SqidCodec(), 999_999);
    }
}
