<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\OnboardingBundle\Application\FlowCatalog;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Infrastructure\Doctrine\DoctrineProgressRepository;
use Phil\OnboardingBundle\Tests\Unit\Fixture\FixedChecker;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use Phil\OnboardingBundle\Tests\Unit\Infrastructure\DoctrineRepositoryTest;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The hot path, run on every page that shows a checklist or a tour: the progress of a user in a flow of twenty steps,
 * read from a table of a thousand users, one checker asked; and the catalog built from its declarations, once per
 * process.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"onboarding"})
 */
final class OnboardingBench
{
    private EntityManager $entityManager;

    private Onboarding $onboarding;

    /** @var list<array{flow: string, id: string, title: string, completion: string, description: ?string, route: ?string, route_parameters: array<string, scalar>, target: ?string, event: ?string, checker: ?string, skippable: bool, before: list<string>, after: list<string>, question?: array{multiple?: bool, choices: array<array-key, array{label: string, roles?: list<string>, subscriptions?: list<string>}>}|null}> */
    private array $steps;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $this->steps = array_values(array_map(static fn (int $i): array => Kit::step('author', 's'.$i, 0 === $i ? ['completion' => 'checker', 'checker' => 'c'] : ['after' => 1 === $i % 2 ? ['s'.($i - 1)] : []]), range(0, 19)));
        $this->entityManager = DoctrineRepositoryTest::entityManager();
        $repository = new DoctrineProgressRepository($this->entityManager);
        $at = new DateTimeImmutable('2026-11-08 00:00 UTC');

        for ($user = 0; $user < 1000; ++$user) {
            $progress = Progress::start('u'.$user, 'author', $at);

            for ($step = 1; $step < 10; ++$step) {
                $progress->complete('s'.$step, $at);
            }

            $this->entityManager->persist($progress);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->onboarding = new Onboarding($this->catalog(), $repository, new ServiceLocator(['c' => static fn (): FixedChecker => new FixedChecker()]), new MockClock());
    }

    /**
     * A new request: the progress read from the table, the checker asked.
     *
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchProgress(): void
    {
        $this->entityManager->clear();
        $this->onboarding->progress('u500', 'author');
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchCatalog(): void
    {
        $this->catalog();
    }

    private function catalog(): FlowCatalog
    {
        return FlowCatalog::fromDeclarations(['author' => ['title' => 'Author', 'description' => null, 'translation_domain' => 'messages']], $this->steps);
    }
}
