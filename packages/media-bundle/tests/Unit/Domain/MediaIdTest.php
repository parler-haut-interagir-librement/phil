<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Domain;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaId::class)]
final class MediaIdTest extends TestCase
{
    public function testAMediumIsIdentifiedByALowerCaseUuid(): void
    {
        $id = MediaId::fromString('019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f');

        self::assertSame(['019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f', '019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f'], [$id->value, (string) $id]);
        self::assertTrue($id->equals(MediaId::fromString('019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f')));
        self::assertFalse($id->equals(MediaId::fromString('019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e50')));

        foreach (['', '019A5C3E-1F2B-7C4D-8E9F-0A1B2C3D4E5F', '019a5c3e1f2b7c4d8e9f0a1b2c3d4e5f', 'x019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f', "019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f\n", '019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5'] as $value) {
            try {
                MediaId::fromString($value);
                self::fail($value);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame(\sprintf('A medium is identified by a lower-case UUID; "%s" is not one.', $value), $invalidMedia->getMessage());
            }
        }
    }
}
