<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use DateInterval;
use DateTimeInterface;
use Override;
use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\MailDomain;
use Phil\EmailAddressBundle\Infrastructure\Dns\CachedMailDomainResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(CachedMailDomainResolver::class)]
final class CachedMailDomainResolverTest extends TestCase
{
    public function testAnAnswerIsAskedOnceThenKept(): void
    {
        $resolver = $this->counting(['example.org' => MailDomain::Mx, 'refuses.example' => MailDomain::NullMx]);
        $cached = new CachedMailDomainResolver($resolver, new ArrayAdapter());

        self::assertSame(MailDomain::Mx, $cached->resolve('example.org'));
        self::assertSame(MailDomain::Mx, $cached->resolve('EXAMPLE.org'));
        self::assertSame(MailDomain::NullMx, $cached->resolve('refuses.example'));
        self::assertSame(['example.org', 'refuses.example'], $resolver->asked);
    }

    public function testAnAnswerIsKeptForTheTtlAndAFailedLookupForItsOwn(): void
    {
        $cache = $this->recordingCache();
        $cached = new CachedMailDomainResolver($this->counting(['example.org' => MailDomain::Mx]), $cache, 600, 30);

        self::assertSame(MailDomain::Mx, $cached->resolve('example.org'));
        self::assertSame(MailDomain::Unknown, $cached->resolve('down.example'));
        self::assertSame([600, 30], array_values($cache->lifetimes));
        self::assertSame('phil_email_address.mx.'.hash('xxh128', 'example.org'), $cache->keys[0]);

        $defaults = $this->recordingCache();
        $resolver = new CachedMailDomainResolver($this->counting(['example.org' => MailDomain::Mx]), $defaults);
        $resolver->resolve('example.org');
        $resolver->resolve('down.example');
        self::assertSame([3600, 60], array_values($defaults->lifetimes));
    }

    public function testAnAnswerTheCacheSpoiledIsUnknown(): void
    {
        $pool = new ArrayAdapter();
        $pool->get('phil_email_address.mx.'.hash('xxh128', 'example.org'), static fn (): string => 'spoiled');

        self::assertSame(MailDomain::Unknown, new CachedMailDomainResolver($this->counting([]), $pool)->resolve('example.org'));
    }

    /**
     * @return CacheInterface&object{lifetimes: array<string, float|int|null>, keys: list<string>}
     */
    private function recordingCache(): CacheInterface
    {
        return new class implements CacheInterface {
            /** @var array<string, float|int|null> */
            public array $lifetimes = [];

            /** @var list<string> */
            public array $keys = [];

            /**
             * @param array<mixed>|null $metadata
             */
            #[Override]
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                $this->keys[] = $key;
                $item = new class implements ItemInterface {
                    public float|int|null $lifetime = null;

                    public function getKey(): string
                    {
                        return '';
                    }

                    public function get(): mixed
                    {
                        return null;
                    }

                    public function isHit(): bool
                    {
                        return false;
                    }

                    public function set(mixed $value): static
                    {
                        return $this;
                    }

                    public function expiresAt(?DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(DateInterval|int|null $time): static
                    {
                        $this->lifetime = \is_int($time) ? $time : null;

                        return $this;
                    }

                    public function tag(string|iterable $tags): static
                    {
                        return $this;
                    }

                    /**
                     * @return array<string, mixed>
                     */
                    public function getMetadata(): array
                    {
                        return [];
                    }
                };
                $save = true;
                $value = $callback($item, $save);
                $this->lifetimes[$key] = $item->lifetime;

                return $value;
            }

            #[Override]
            public function delete(string $key): bool
            {
                return true;
            }
        };
    }

    /**
     * @param array<string, MailDomain> $answers
     *
     * @return MailDomainResolver&object{asked: list<string>}
     */
    private function counting(array $answers): MailDomainResolver
    {
        return new class($answers) implements MailDomainResolver {
            /** @var list<string> */
            public array $asked = [];

            /**
             * @param array<string, MailDomain> $answers
             */
            public function __construct(
                private readonly array $answers,
            ) {
            }

            #[Override]
            public function resolve(string $asciiDomain): MailDomain
            {
                $this->asked[] = $asciiDomain;

                return $this->answers[$asciiDomain] ?? MailDomain::Unknown;
            }
        };
    }
}
