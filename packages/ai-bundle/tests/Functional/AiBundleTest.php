<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\AiBundle\Application\AiNotice;
use Phil\AiBundle\Application\AiTasks;
use Phil\AiBundle\Application\AiUsageRegister;
use Phil\AiBundle\Application\AiUse;
use Phil\AiBundle\Application\TaskRequest;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\PhilAiBundle;
use Phil\AiBundle\Tests\Functional\App\TestKernel;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\UsageMeter;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Credential\ApiKey;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tasks through the container: prompts from the translator's catalogues, Symfony AI's mock platform on the
 * application's key and on a person's key kept by the secrets bundle, the journal in Doctrine on SQLite, and the
 * tokens counted by a usage meter until the quota refuses.
 */
#[CoversClass(PhilAiBundle::class)]
final class AiBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testATaskProposesThenTheApplicationDecides(): void
    {
        $tasks = $this->service(AiTasks::class, AiTasks::class);
        $this->service(UserSecrets::class, UserSecrets::class)->store(Owner::fromString('member-1'), SecretName::fromString('main-key'), ServiceName::fromString('main'), ApiKey::of('sk-member'));

        $application = $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'fr', ['text' => 'Il était une fois']));
        $own = $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['text' => 'Once'], 'member-1'));

        self::assertSame('application, model-a: [{"role":"system","content":"You edit prose."},{"role":"user","content":"Réécris : Il était une fois"}]', $application->text);
        self::assertSame(['key sk-member, model-a: [{"role":"system","content":"You edit prose."},{"role":"user","content":"Rewrite: Once"}]', KeySource::User], [$own->text, $own->keySource]);
        self::assertSame([15, 105, 'USD'], [$application->tokens->total(), $application->cost->micros, $application->cost->currency]);

        $tasks->acceptEdited($application->id);
        $tasks->reject($own->id);
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        self::assertSame(Decision::Edited, $this->service(JournalRepository::class, JournalRepository::class)->get($application->id)?->decision());
        $totals = $tasks->totals('project-1', new DateTimeImmutable('2026-11-01 UTC'), new DateTimeImmutable('2026-12-01 UTC'));
        self::assertSame([2, 30, ['USD' => 210], ['pending' => 0, 'accepted' => 0, 'edited' => 1, 'rejected' => 1]], [$totals->tasks, $totals->tokens->total(), $totals->costs, $totals->decisions]);
    }

    public function testTheQuotaRefusesWhatWouldGoPastIt(): void
    {
        $tasks = $this->service(AiTasks::class, AiTasks::class);
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['text' => 'a']));
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['text' => 'b']));

        self::assertSame(30, $this->service(UsageMeter::class, UsageMeter::class)->usage('project-1', 'ai_tokens')->used);

        $this->expectException(QuotaExceeded::class);
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['text' => 'c']));
    }

    public function testTheNoticesComeFromTheBundlesTranslationsAndTheRegisterFromTheTasks(): void
    {
        $notice = $this->service(AiNotice::class, AiNotice::class);

        self::assertSame('Vous parlez à une IA. Ses réponses sont des suggestions : vérifiez-les avant de vous en servir.', $notice->interaction('fr'));
        self::assertSame('This image was generated by AI. Nothing is applied until you accept it.', $notice->generated(Output::Image, 'en'));
        self::assertSame(['draft.rewrite'], array_map(static fn (AiUse $use): string => $use->kind, $this->service(AiUsageRegister::class, AiUsageRegister::class)->uses()));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
