<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Generator;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;
use Phil\ScheduledTransitionsBundle\Domain\Transition\TransitionStatus;
use Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine\DoctrineScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\WorkflowTransitionHandler;
use Phil\ScheduledTransitionsBundle\Tests\Functional\App\Entity\Page;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\Clock;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\RecordingHandler;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

/**
 * A cron run applies every due transition one by one: an `UPDATE` to claim it, the handler, a flush and an `UPDATE` to
 * record it. With workflows, the handler also resolves the subject twice, once to say it supports it and once to apply.
 *
 * Each revolution first puts the transitions and pages back as due drafts with two `UPDATE` statements and clears the
 * entity manager: that constant share is in the times, so that every revolution runs a full batch.
 *
 * The growth is the cost to watch, so the batches stop at 250: one revolution of 500 takes seconds.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(1)
 *
 * @Iterations(5)
 *
 * @Groups({"scheduler"})
 */
final readonly class SchedulerBench
{
    private EntityManager $entityManager;

    private Scheduler $recording;

    private Scheduler $workflow;

    private DateTimeImmutable $now;

    /**
     * The bundle's table and the functional tests' `Page` in SQLite in memory.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__, 2).'/src/Domain/Model', \dirname(__DIR__).'/Functional/App/Entity']));
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->now = new DateTimeImmutable('2026-11-05 12:00 UTC');
        $clock = new Clock($this->now);
        $repository = new DoctrineScheduledTransitionRepository($this->entityManager);

        $registry = new Registry();
        $registry->addWorkflow(new Workflow(new Definition(['draft', 'published'], [new Transition('publish', 'draft', 'published')]), new MethodMarkingStore(true), name: 'page'), new InstanceOfSupportStrategy(Page::class));

        $pages = new readonly class($this->entityManager) implements SubjectResolver {
            public function __construct(
                private EntityManager $entityManager,
            ) {
            }

            public function resolve(ResourceReference $resource): ?object
            {
                return 'page' === $resource->key ? $this->entityManager->find(Page::class, $resource->id) : null;
            }

            public function saved(object $subject): void
            {
                $this->entityManager->flush();
            }
        };

        $this->recording = new Scheduler($repository, [new RecordingHandler()], $clock);
        $this->workflow = new Scheduler($repository, [new WorkflowTransitionHandler($registry, [$pages])], $clock);
    }

    /**
     * That many pages due to be published an hour ago.
     *
     * @param array{transitions: int} $params
     */
    public function setUp(array $params): void
    {
        for ($i = 0; $i < $params['transitions']; ++$i) {
            $this->entityManager->persist(new Page((string) $i));
            $this->entityManager->persist(ScheduledTransition::of(ResourceReference::of('page', (string) $i), 'publish', $this->now->modify('-1 hour')));
        }

        $this->entityManager->flush();
    }

    /**
     * The run with a handler that does nothing but note the transition: the scheduler's own cost per transition.
     *
     * @ParamProviders("batches")
     *
     * @param array{transitions: int} $params
     */
    public function benchRunDue(array $params): void
    {
        $this->reset();
        $this->recording->runDue($params['transitions']);
    }

    /**
     * The run through a Symfony workflow, each page loaded by Doctrine and flushed once published.
     *
     * @ParamProviders("batches")
     *
     * @param array{transitions: int} $params
     */
    public function benchRunDueThroughWorkflow(array $params): void
    {
        $this->reset();
        $this->workflow->runDue($params['transitions']);
    }

    /**
     * @return Generator<string, array{transitions: int}>
     */
    public function batches(): Generator
    {
        yield '10 due' => ['transitions' => 10];
        yield '100 due' => ['transitions' => 100];
        yield '250 due' => ['transitions' => 250];
    }

    private function reset(): void
    {
        $this->entityManager->clear();
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('UPDATE phil_scheduled_transition SET status = ?, attempts = 0, applied_at = NULL, claimed_until = NULL', [TransitionStatus::Pending->value]);
        $connection->executeStatement("UPDATE page SET marking = 'draft'");
    }
}
