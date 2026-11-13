<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Infrastructure\Storage\CacheJobStore;
use Phil\PagedMediaBundle\Infrastructure\Storage\JobCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Clock\MockClock;
use Throwable;

#[CoversClass(CacheJobStore::class)]
#[CoversClass(StorageFailed::class)]
#[CoversClass(JobUnavailable::class)]
#[UsesClass(JobCodec::class)]
final class CacheJobStoreTest extends TestCase
{
    public function testAJobIsKeptInThePoolUnderARandomIdentifierAndForgotten(): void
    {
        $pool = new ArrayAdapter();
        $store = new CacheJobStore($pool);
        $job = new RenderJob('paged_media.gift_cards', ['card_title' => 'Gift', 'cards' => [['amount' => '$5', 'code' => 'SECRET']]], 'cards/1.pdf');

        $id = $store->keep($job);
        $other = $store->keep($job);

        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $id);
        self::assertNotSame($id, $other);
        self::assertSame(new JobCodec()->encode($job), $pool->getItem($id)->get());
        self::assertEquals($job, $store->find($id));

        $store->remove($id);
        $store->remove('not-an-identifier');

        self::assertFalse($pool->hasItem($id));
        self::assertTrue($pool->hasItem($other));
    }

    public function testAJobExpiresAfterItsLifetime(): void
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(clock: $clock);
        $id = new CacheJobStore($pool, lifetime: 60)->keep(new RenderJob('t', [], 'a.pdf'));
        $kept = new CacheJobStore($pool)->keep(new RenderJob('t', [], 'b.pdf'));

        $clock->sleep(59);
        self::assertTrue($pool->hasItem($id));
        $clock->sleep(2);

        self::assertFalse($pool->hasItem($id));
        self::assertTrue($pool->hasItem($kept));
    }

    public function testAJobThatIsNotThereIsUnavailable(): void
    {
        $pool = new ArrayAdapter();
        $pool->save($pool->getItem(str_repeat('a', 32))->set(42));

        $store = new CacheJobStore($pool);

        foreach ([str_repeat('b', 32), str_repeat('a', 32), '../etc/passwd', str_repeat('A', 32)] as $id) {
            try {
                $store->find($id);
                self::fail('No job is kept under '.$id);
            } catch (JobUnavailable $unavailable) {
                self::assertSame(\sprintf('No job is kept under "%s".', $id), $unavailable->getMessage());
            }
        }
    }

    public function testAPoolThatDoesNotSaveFailsTheStorage(): void
    {
        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains('The job cannot be kept in the pool.');

        new CacheJobStore($this->failing(save: false))->keep(new RenderJob('t', [], 'a.pdf'));
    }

    public function testAPoolThatDoesNotDeleteFailsTheStorage(): void
    {
        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains('The job "'.str_repeat('a', 32).'" cannot be removed.');

        new CacheJobStore($this->failing(delete: false))->remove(str_repeat('a', 32));
    }

    public function testAPoolThatThrowsFailsTheStorageWithItsReason(): void
    {
        $pool = $this->failing(throw: true);
        $store = new CacheJobStore($pool);

        foreach ([
            'The job cannot be kept in the pool: Broken.' => static fn (): string => $store->keep(new RenderJob('t', [], 'a.pdf')),
            'The pool cannot be read: Broken.' => static fn (): object => $store->find(str_repeat('a', 32)),
            'The job "'.str_repeat('a', 32).'" cannot be removed: Broken.' => static function () use ($store): void {
                $store->remove(str_repeat('a', 32));
            },
        ] as $message => $call) {
            try {
                $call();
                self::fail('The pool throws.');
            } catch (StorageFailed $failed) {
                self::assertSame($message, $failed->getMessage());
                self::assertInstanceOf(InvalidArgumentException::class, $failed->getPrevious());
            }
        }
    }

    public function testAPoolThatFailsWithoutAnInvalidKeyFailsTheStorageToo(): void
    {
        $store = new CacheJobStore($this->failing(throw: true, exception: new CacheException('Down.')));

        foreach ([
            'The job cannot be kept in the pool: Down.' => static fn (): string => $store->keep(new RenderJob('t', [], 'a.pdf')),
            'The pool cannot be read: Down.' => static fn (): object => $store->find(str_repeat('a', 32)),
            'The job "'.str_repeat('a', 32).'" cannot be removed: Down.' => static function () use ($store): void {
                $store->remove(str_repeat('a', 32));
            },
        ] as $message => $call) {
            try {
                $call();
                self::fail('The pool throws.');
            } catch (StorageFailed $failed) {
                self::assertSame($message, $failed->getMessage());
                self::assertInstanceOf(CacheException::class, $failed->getPrevious());
            }
        }
    }

    private function failing(bool $save = true, bool $delete = true, bool $throw = false, ?Throwable $exception = null): CacheItemPoolInterface
    {
        return new class($save, $delete, $throw, $exception ?? new InvalidArgumentException('Broken.')) extends ArrayAdapter {
            public function __construct(private readonly bool $saves, private readonly bool $deletes, private readonly bool $throws, private readonly Throwable $failure)
            {
                parent::__construct();
            }

            public function getItem(mixed $key): CacheItem
            {
                return $this->throws ? throw $this->failure : parent::getItem($key);
            }

            public function save(CacheItemInterface $item): bool
            {
                return $this->saves && parent::save($item);
            }

            public function deleteItem(mixed $key): bool
            {
                if ($this->throws) {
                    throw $this->failure;
                }

                return $this->deletes && parent::deleteItem($key);
            }
        };
    }
}
