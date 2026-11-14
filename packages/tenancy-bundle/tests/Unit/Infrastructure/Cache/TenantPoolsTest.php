<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache;

use Override;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantCacheNamespace;
use Phil\TenancyBundle\Infrastructure\Cache\TenantPools;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture\RecordingPool;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * The pools of the tenants and of the global namespace over one root pool: the sub-namespaces asked below the
 * tenant's, what is kept, what is committed.
 */
#[CoversClass(TenantPools::class)]
#[CoversClass(TenantCacheNamespace::class)]
final class TenantPoolsTest extends TestCase
{
    private TemporaryDirectory $temporary;

    #[Override]
    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testEachAskAddsItsSubNamespacesBelowThoseAlreadyAsked(): void
    {
        $root = new FilesystemAdapter('app', 0, $this->temporary->path);
        $pool = new TenantPools($root)->below(['a'])->below(['b', 'c'])->of(null);

        $pool->save($pool->getItem('k')->set('deep'));

        self::assertSame('deep', $root->withSubNamespace('a')->withSubNamespace('b')->withSubNamespace('c')->getItem('k')->get());
        self::assertFalse($root->withSubNamespace('a')->withSubNamespace('b')->hasItem('k'));
        self::assertFalse($root->withSubNamespace('b')->withSubNamespace('c')->hasItem('k'));
    }

    public function testTheGlobalPoolBelowSubNamespacesIsKept(): void
    {
        $pools = new TenantPools(new FilesystemAdapter('app', 0, $this->temporary->path), ['reports']);

        self::assertSame($pools->of(null), $pools->of(null));
    }

    public function testNoSubNamespaceLeavesTheRootAloneAndAnyCommitsItFirst(): void
    {
        $root = new RecordingPool();
        $pools = new TenantPools($root);

        self::assertSame($root, $pools->of(null));
        self::assertSame(0, $root->commits, 'Nothing is derived from the root, so nothing of it is committed.');

        new TenantPools($root, ['reports'])->of(null);

        self::assertSame(1, $root->commits, 'What the root holds deferred is committed before a pool is derived from it.');
    }

    public function testACommitThatFailsFailsTheCommitOfTheKeptPools(): void
    {
        $pools = new TenantPools(new RecordingPool(false));
        $pools->of(TenantId::fromString('retail'));
        $pools->of(TenantId::fromString('wholesale'));

        self::assertFalse($pools->commit());

        $pools = new TenantPools(new RecordingPool());
        $pools->of(TenantId::fromString('retail'));
        $pools->of(null);

        self::assertTrue($pools->commit());
    }

    public function testATenantWhoseReferenceIsTheLargestIntegerLeavesRoomForTheGlobalPool(): void
    {
        $pools = new TenantPools(new RecordingPool());
        $pools->of(TenantId::fromString((string) \PHP_INT_MAX));
        $pools->of(null);

        self::assertTrue($pools->commit());
    }
}
