<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Domain;

use Phil\ReferencesBundle\Domain\Exception\InvalidReference;
use Phil\ReferencesBundle\Domain\Exception\ReferencesException;
use Phil\ReferencesBundle\Domain\Exception\StillReferenced;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceReference::class)]
#[CoversClass(Usage::class)]
#[CoversClass(InvalidReference::class)]
#[CoversClass(StillReferenced::class)]
final class ValuesTest extends TestCase
{
    public function testUsages(): void
    {
        $page = ResourceReference::of('page', '1');
        $image = ResourceReference::of('image', '9');
        $usage = Usage::of($page, 'blocks[2].hero:image-é', $image);

        self::assertSame([$page, 'blocks[2].hero:image-é', $image], [$usage->source, $usage->field, $usage->target]);
        self::assertSame(str_repeat('é', 255), Usage::of($page, str_repeat('é', 255), $image)->field);

        foreach (['', 'a b', str_repeat('a', 256), "a\n", 'a/b'] as $field) {
            try {
                Usage::of($page, $field, $image);
                self::fail($field);
            } catch (InvalidReference $invalidReference) {
                self::assertSame(\sprintf('A field is 1 to 255 letters, digits, "_", ".", ":", "-" or brackets; "%s" is not.', $field), $invalidReference->getMessage());
                self::assertInstanceOf(ReferencesException::class, $invalidReference);
            }
        }
    }

    public function testAResourceStillUsedNamesItsFirstSources(): void
    {
        $image = ResourceReference::of('image', '9');
        $usages = array_map(static fn (int $n): Usage => Usage::of(ResourceReference::of('page', (string) $n), 'hero', $image), range(1, 5));

        self::assertSame('image:9 is still used by page:1 (hero), page:2 (hero), page:3 (hero) and 2 more.', StillReferenced::by($image, $usages)->getMessage());
        self::assertSame('image:9 is still used by page:1 (hero), page:2 (hero), page:3 (hero).', StillReferenced::by($image, \array_slice($usages, 0, 3))->getMessage());
        self::assertSame('image:9 is still used by page:1 (hero).', StillReferenced::by($image, [$usages[0]])->getMessage());
        self::assertSame([$image, [$usages[0]]], [StillReferenced::by($image, [$usages[0]])->target, StillReferenced::by($image, [$usages[0]])->usages]);
        self::assertInstanceOf(ReferencesException::class, StillReferenced::by($image, [$usages[0]]));
    }
}
