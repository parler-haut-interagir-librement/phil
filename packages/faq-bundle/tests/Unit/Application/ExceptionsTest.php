<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\UnknownSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnknownQuestion::class)]
#[CoversClass(UnknownSection::class)]
final class ExceptionsTest extends TestCase
{
    public function testAnUnknownNameIsCutAtOneHundredCharacters(): void
    {
        $name = 'a'.str_repeat('é', 120);

        self::assertSame('No question is named "a'.str_repeat('é', 99).'".', UnknownQuestion::named($name)->getMessage());
        self::assertSame('No section is named "a'.str_repeat('é', 99).'".', UnknownSection::named($name)->getMessage());
    }
}
