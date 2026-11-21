<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\Event\CertificateAwarded;
use Phil\CertificatesBundle\Domain\Event\CertificateRevoked;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Infrastructure\Console\AwardCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CourseCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RevokeCommand;
use Phil\CertificatesBundle\Infrastructure\Console\StatusCommand;
use Phil\CertificatesBundle\Tests\Functional\App\RecordedEvents;
use Phil\CertificatesBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * The commands of a small application, on Doctrine, with FrameworkBundle's lock factory and event dispatcher.
 */
#[CoversClass(CourseCommand::class)]
#[CoversClass(AwardCommand::class)]
#[CoversClass(RevokeCommand::class)]
#[CoversClass(StatusCommand::class)]
final class ConsoleTest extends KernelTestCase
{
    private Application $application;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $courses = self::getContainer()->get('test.'.Courses::class);
        self::assertInstanceOf(Courses::class, $courses);
        $courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more.', new PassCriteria(70), 12);
    }

    public function testACertificateIsAwardedShownAndRevokedFromTheConsole(): void
    {
        $award = $this->execute('phil:certificates:award', ['course' => 'first-aid', 'name' => 'Ada Lovelace', 'identity' => 'ada@example.org', '--score' => '90']);
        self::assertSame(Command::SUCCESS, $award->getStatusCode());
        self::assertSame(1, preg_match('#certificates/(?<code>[0-9A-Z]{12})\n$#', $award->getDisplay(), $match));

        self::assertStringContainsString("status             valid\n", $this->execute('phil:certificates:status', ['code' => $match['code']])->getDisplay());
        self::assertSame(Command::SUCCESS, $this->execute('phil:certificates:revoke', ['code' => $match['code'], 'reason' => 'Issued in error.'])->getStatusCode());
        self::assertStringContainsString("revocation_reason  Issued in error.\n", $this->execute('phil:certificates:status', ['code' => $match['code']])->getDisplay());

        $recorded = self::getContainer()->get(RecordedEvents::class);
        self::assertInstanceOf(RecordedEvents::class, $recorded);
        $events = $recorded->events;
        self::assertCount(2, $events);
        self::assertInstanceOf(CertificateAwarded::class, $events[0]);
        self::assertSame([$match['code'], 'first-aid'], [$events[0]->code, $events[0]->course]);
        self::assertInstanceOf(CertificateRevoked::class, $events[1]);
        self::assertSame($events[0]->certificateId, $events[1]->certificateId);
    }

    public function testACourseDefinedFromTheConsoleAwardsItsCertificates(): void
    {
        $course = $this->execute('phil:certificates:course', ['slug' => 'botany', 'name' => 'Botany', 'narrative' => 'Score 80 or more.', '--min-score' => '80', '--validity' => '6']);
        self::assertSame(Command::SUCCESS, $course->getStatusCode());
        self::assertSame("Defined the course \"botany\", Botany.\nhttps://example.com/certificates/courses/botany\n", $course->getDisplay());

        self::assertSame(Command::FAILURE, $this->execute('phil:certificates:award', ['course' => 'botany', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '79'])->getStatusCode());
        self::assertSame(Command::SUCCESS, $this->execute('phil:certificates:award', ['course' => 'botany', 'name' => 'Ada', 'identity' => 'ada@example.org', '--score' => '80'])->getStatusCode());
    }

    public function testACourseAwardedOnCompletionAsksTheApplicationsSource(): void
    {
        self::assertSame(Command::SUCCESS, $this->execute('phil:certificates:course', ['slug' => 'cpr', 'name' => 'CPR', 'narrative' => 'Complete the course.', '--completion-source' => 'first-aid-2027'])->getStatusCode());

        $input = ['course' => 'cpr', 'name' => 'Ada', 'identity' => 'ada@example.org', '--holder' => 'member-42', '--on-completion' => true];
        $first = $this->execute('phil:certificates:award', $input);
        self::assertSame(Command::SUCCESS, $first->getStatusCode());
        self::assertStringEndsWith("\nCompletion: enrolment-7\n", $first->getDisplay());
        self::assertSame($first->getDisplay(), $this->execute('phil:certificates:award', $input)->getDisplay());

        self::assertSame(Command::FAILURE, $this->execute('phil:certificates:award', [...$input, '--holder' => 'member-43'])->getStatusCode());

        $recorded = self::getContainer()->get(RecordedEvents::class);
        self::assertInstanceOf(RecordedEvents::class, $recorded);
        self::assertCount(1, $recorded->events);
    }

    public function testTheLockFactoryOfTheFrameworkGuardsTheStatusIndex(): void
    {
        $locks = self::getContainer()->get('lock.factory');
        self::assertInstanceOf(LockFactory::class, $locks);
        $lock = $locks->createLock(Awarding::LOCK);
        self::assertTrue($lock->acquire());

        try {
            self::assertFalse($locks->createLock(Awarding::LOCK)->acquire());
        } finally {
            $lock->release();
        }
    }

    /**
     * The locks of the status index belong to this application alone: another application of the same project — another
     * process of ParaTest running its own tests — never holds them. A store shared by the project, the semaphore or the
     * lock file Symfony picks by default, would refuse the second lock here as it refused this test's lock while
     * another process awarded a certificate.
     */
    public function testAnotherApplicationOfTheProjectNeverHoldsTheLockOfTheStatusIndex(): void
    {
        $locks = self::getContainer()->get('lock.factory');
        self::assertInstanceOf(LockFactory::class, $locks);
        $lock = $locks->createLock(Awarding::LOCK);
        self::assertTrue($lock->acquire());

        $other = new TestKernel('test', true);
        $other->boot();

        try {
            $services = $other->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $services);
            $otherLocks = $services->get('lock.factory');
            self::assertInstanceOf(LockFactory::class, $otherLocks);
            $otherLock = $otherLocks->createLock(Awarding::LOCK);
            self::assertTrue($otherLock->acquire());
            $otherLock->release();
        } finally {
            $lock->release();
            $other->shutdown();
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(string $command, array $input): CommandTester
    {
        $tester = new CommandTester($this->application->find($command));
        $tester->execute($input);

        return $tester;
    }
}
