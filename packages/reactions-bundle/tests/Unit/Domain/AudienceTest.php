<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Domain;

use Phil\ReactionsBundle\Domain\Status\Audience;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Audience::class)]
final class AudienceTest extends TestCase
{
    public function testWhoIsAdmitted(): void
    {
        self::assertSame([true, true], [Audience::Everyone->admits(true), Audience::Everyone->admits(false)]);
        self::assertSame([true, false], [Audience::Members->admits(true), Audience::Members->admits(false)]);
        self::assertSame([false, false], [Audience::Nobody->admits(true), Audience::Nobody->admits(false)]);
    }
}
