<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryProgressRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function store(): ProgressRepository
    {
        return new InMemoryProgressRepository();
    }

    public function testAUserAndAFlowDoNotRunTogether(): void
    {
        $store = new InMemoryProgressRepository();
        $store->save(Progress::start('bc', 'a', new DateTimeImmutable()));

        self::assertNull($store->find('c', 'ab'));
        self::assertNotNull($store->find('bc', 'a'));
    }

    public function testTheProgressOfAUserIsAList(): void
    {
        $store = new InMemoryProgressRepository();
        $store->save(Progress::start('bob', 'a', new DateTimeImmutable()));
        $store->save(Progress::start('ada', 'a', new DateTimeImmutable()));

        self::assertSame([0], array_keys($store->ofUser('ada')));
    }
}
