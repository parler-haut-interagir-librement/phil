<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Phil\ContentBundle\Infrastructure\Doctrine\ConstructorContentRootFactory;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\InvalidContentMapping;
use Phil\ContentBundle\Tests\Unit\Fixture\SealedRoot;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstructorContentRootFactory::class)]
final class ConstructorContentRootFactoryTest extends TestCase
{
    public function testARootIsBuiltByItsConstructor(): void
    {
        self::assertInstanceOf(Article::class, new ConstructorContentRootFactory(ContentRoots::fromClassNames([Article::class]))->create(Article::resourceKey()));
    }

    public function testARootWhoseConstructorIsPrivateIsRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains('cannot be built without arguments');

        new ConstructorContentRootFactory(ContentRoots::fromClassNames([SealedRoot::class]))->create(SealedRoot::resourceKey());
    }
}
