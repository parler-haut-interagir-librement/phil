<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit;

use Phil\FormsBundle\Application\CurrentSubject;
use Phil\FormsBundle\Infrastructure\Security\TokenCurrentSubject;
use Phil\SurveyBundle\Application\Addenda;
use Phil\SurveyBundle\Application\BuiltInTemplates;
use Phil\SurveyBundle\Application\Contract\FeedbackSessions;
use Phil\SurveyBundle\Application\Contract\QuestionnaireTemplates;
use Phil\SurveyBundle\Application\Handler\PurgeSurveyResponsesHandler;
use Phil\SurveyBundle\Application\Import\QuestionImports;
use Phil\SurveyBundle\Application\Message\PurgeSurveyResponses;
use Phil\SurveyBundle\Application\NoRespondent;
use Phil\SurveyBundle\Application\NoSessions;
use Phil\SurveyBundle\Application\Participations;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Application\RedirectPolicy;
use Phil\SurveyBundle\Application\Results;
use Phil\SurveyBundle\Application\Submissions;
use Phil\SurveyBundle\Application\TemplateChain;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Infrastructure\Console\ExportQuestionnaireCommand;
use Phil\SurveyBundle\Infrastructure\Console\ImportQuestionnaireCommand;
use Phil\SurveyBundle\Infrastructure\Console\ImportQuestionsCommand;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\RespondentPass;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\SurveyServices;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalQuestionnaireRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveySchemaListener;
use Phil\SurveyBundle\Infrastructure\Http\SurveyController;
use Phil\SurveyBundle\Infrastructure\Http\SurveyGuard;
use Phil\SurveyBundle\Infrastructure\Privacy\SurveyPrivacy;
use Phil\SurveyBundle\Infrastructure\Scheduler\SurveySchedule;
use Phil\SurveyBundle\PhilSurveyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSurveyBundle::class)]
#[CoversClass(SurveyServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(RespondentPass::class)]
#[CoversClass(NoRespondent::class)]
final class PhilSurveyBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_survey', $this->extension()->getAlias());
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('doctrine.dbal.default_connection', $this->id($container->getDefinition(DbalResponseRepository::class)->getArgument(0)));
        self::assertSame([[], false], $container->getDefinition(RedirectPolicy::class)->getArguments());
        self::assertSame(['%kernel.secret%'], $container->getDefinition(Participations::class)->getArguments());
        self::assertSame(NoSessions::class, (string) $container->getAlias(FeedbackSessions::class));
        self::assertSame(TemplateChain::class, (string) $container->getAlias(QuestionnaireTemplates::class));
        $templates = $container->getDefinition(TemplateChain::class)->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $templates);
        self::assertSame(SurveyServices::TEMPLATE_TAG, $templates->getTag());
        self::assertArrayHasKey(QuestionnaireTemplates::class, $container->getAutoconfiguredInstanceof());
        self::assertSame([['handles' => PurgeSurveyResponses::class]], $container->getDefinition(PurgeSurveyResponsesHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(SurveySchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertTrue($container->getDefinition(ExportQuestionnaireCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(SurveyPrivacy::class)->hasTag('phil_privacy.exporter'));
        self::assertTrue($container->getDefinition(SurveyPrivacy::class)->hasTag('phil_privacy.eraser'));
        self::assertFalse($container->hasDefinition(SurveyController::class));
        self::assertFalse($container->hasAlias(RespondentPass::RESPONDENT));

        $schedule = $container->getDefinition(SurveySchedule::class);
        self::assertSame(['03:45', 'UTC'], \array_slice($schedule->getArguments(), 0, 2));
        self::assertSame('lock.factory', $this->id($schedule->getArgument(2)));
        self::assertSame('cache.app', $this->id($schedule->getArgument(3)));
        self::assertSame([['name' => 'phil_survey']], $schedule->getTag('scheduler.schedule_provider'));
    }

    public function testTheOptions(): void
    {
        $container = $this->load([[
            'connection' => 'surveys',
            'secret' => 'another secret, long enough',
            'redirect_origins' => ['https://example.org'],
            'allow_localhost' => true,
            'respondent' => 'app.member',
            'sessions' => 'app.sessions',
            'schedule' => ['at' => '01:00', 'timezone' => 'America/Toronto', 'lock_factory' => null, 'state_pool' => null],
        ]], ['TwigBundle' => TwigBundle::class]);

        self::assertSame('doctrine.dbal.surveys_connection', $this->id($container->getDefinition(DbalResponseRepository::class)->getArgument(0)));
        self::assertSame([['https://example.org'], true], $container->getDefinition(RedirectPolicy::class)->getArguments());
        self::assertSame(['another secret, long enough'], $container->getDefinition(Participations::class)->getArguments());
        self::assertSame('app.sessions', (string) $container->getAlias(FeedbackSessions::class));
        self::assertFalse($container->hasDefinition(NoSessions::class));
        self::assertSame('app.member', (string) $container->getAlias(RespondentPass::RESPONDENT));
        self::assertSame(['01:00', 'America/Toronto', null, null], $container->getDefinition(SurveySchedule::class)->getArguments());
        self::assertTrue($container->getDefinition(SurveyController::class)->isPublic());
        self::assertSame(CsrfProtectionPass::TOKEN_MANAGER, $this->id($container->getDefinition(SurveyGuard::class)->getArgument(0)));
        $respondent = $container->getDefinition(SurveyController::class)->getArgument(4);
        self::assertInstanceOf(Reference::class, $respondent);
        self::assertSame(RespondentPass::RESPONDENT, (string) $respondent);
    }

    public function testThePagesAndTheScheduleAreTurnedOff(): void
    {
        $container = $this->load([['pages' => false, 'schedule' => ['enabled' => false]]], ['TwigBundle' => TwigBundle::class]);

        self::assertFalse($container->hasDefinition(SurveyController::class));
        self::assertFalse($container->hasDefinition(SurveySchedule::class));
    }

    public function testTheFrameworkMakesTheTokenStateless(): void
    {
        $container = new ContainerBuilder();
        $framework = new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        };
        $container->registerExtension($framework);
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [SurveyGuard::TOKEN_ID]]]], $container->getExtensionConfig('framework'));

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('framework'));
    }

    public function testThePagesNeedTheCsrfProtection(): void
    {
        $container = new ContainerBuilder();
        new CsrfProtectionPass()->process($container);
        $container->register(SurveyGuard::class, SurveyGuard::class);
        $container->register(CsrfProtectionPass::TOKEN_MANAGER, stdClass::class);
        new CsrfProtectionPass()->process($container);

        $without = new ContainerBuilder();
        $without->register(SurveyGuard::class, SurveyGuard::class);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');
        new CsrfProtectionPass()->process($without);
    }

    public function testTheRespondentIsTheMemberOfSecurityOrNobody(): void
    {
        $nobody = new ContainerBuilder();
        new RespondentPass()->process($nobody);
        self::assertSame(NoRespondent::class, (string) $nobody->getAlias(CurrentSubject::class));
        self::assertSame(CurrentSubject::class, (string) $nobody->getAlias(RespondentPass::RESPONDENT));
        self::assertNull(new NoRespondent()->current());

        $security = new ContainerBuilder();
        $security->register('security.token_storage', stdClass::class);
        new RespondentPass()->process($security);
        self::assertSame(TokenCurrentSubject::class, (string) $security->getAlias(CurrentSubject::class));

        $own = new ContainerBuilder();
        $own->register('security.token_storage', stdClass::class);
        $own->setAlias(CurrentSubject::class, 'app.subject');
        new RespondentPass()->process($own);
        self::assertSame('app.subject', (string) $own->getAlias(CurrentSubject::class));
        self::assertFalse($own->hasDefinition(TokenCurrentSubject::class));

        $named = new ContainerBuilder();
        $named->setAlias(RespondentPass::RESPONDENT, 'app.member');
        new RespondentPass()->process($named);
        self::assertSame('app.member', (string) $named->getAlias(RespondentPass::RESPONDENT));
        self::assertFalse($named->hasAlias(CurrentSubject::class));
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilSurveyBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(CsrfProtectionPass::class, $passes);
        self::assertContains(RespondentPass::class, $passes);
    }

    public function testEveryServiceIsGivenWhatItNeedsAndTheEntryPointsArePublic(): void
    {
        $container = $this->load([[]], ['TwigBundle' => TwigBundle::class]);
        $dbal = 'doctrine.dbal.default_connection';

        self::assertSame([$dbal, 'clock'], $this->ids($container->getDefinition(DbalQuestionnaireRepository::class)));
        self::assertSame([$dbal], $this->ids($container->getDefinition(DbalResponseRepository::class)));
        self::assertSame([$dbal], $this->ids($container->getDefinition(SurveySchemaListener::class)));
        self::assertSame(DbalQuestionnaireRepository::class, (string) $container->getAlias(QuestionnaireRepository::class));
        self::assertSame(DbalResponseRepository::class, (string) $container->getAlias(ResponseRepository::class));
        self::assertTrue($container->getDefinition(BuiltInTemplates::class)->hasTag(SurveyServices::TEMPLATE_TAG));
        self::assertTrue($container->hasDefinition(NoSessions::class));

        $entries = [
            Questionnaires::class => [QuestionnaireRepository::class, RedirectPolicy::class, QuestionnaireTemplates::class],
            Submissions::class => [ResponseRepository::class, Participations::class, RedirectPolicy::class, 'clock', FeedbackSessions::class],
            Results::class => [ResponseRepository::class],
            QuestionImports::class => [Questionnaires::class],
            Addenda::class => [ResponseRepository::class, 'clock'],
            PurgeSurveyResponsesHandler::class => [QuestionnaireRepository::class, ResponseRepository::class, 'clock'],
        ];

        foreach ($entries as $service => $arguments) {
            self::assertSame($arguments, $this->ids($container->getDefinition($service)), $service);
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
        }

        self::assertSame([Questionnaires::class], $this->ids($container->getDefinition(ExportQuestionnaireCommand::class)));
        self::assertSame([Questionnaires::class], $this->ids($container->getDefinition(ImportQuestionnaireCommand::class)));
        self::assertTrue($container->getDefinition(ImportQuestionnaireCommand::class)->hasTag('console.command'));
        self::assertSame([QuestionImports::class], $this->ids($container->getDefinition(ImportQuestionsCommand::class)));
        self::assertTrue($container->getDefinition(ImportQuestionsCommand::class)->hasTag('console.command'));
        self::assertSame([ResponseRepository::class, Participations::class], $this->ids($container->getDefinition(SurveyPrivacy::class)));
        self::assertSame(
            [Questionnaires::class, Submissions::class, Results::class, Addenda::class, RespondentPass::RESPONDENT, SurveyGuard::class, 'twig'],
            $this->ids($container->getDefinition(SurveyController::class)),
        );
        self::assertSame([CsrfProtectionPass::TOKEN_MANAGER], $this->ids($container->getDefinition(SurveyGuard::class)));
    }

    public function testTheRespondentOfSecurityIsGivenItsTokenStorage(): void
    {
        $security = new ContainerBuilder();
        $security->register('security.token_storage', stdClass::class);
        new RespondentPass()->process($security);

        self::assertSame(['security.token_storage'], $this->ids($security->getDefinition(TokenCurrentSubject::class)));

        $nobody = new ContainerBuilder();
        new RespondentPass()->process($nobody);
        self::assertTrue($nobody->hasDefinition(NoRespondent::class));
        self::assertFalse($nobody->hasDefinition(TokenCurrentSubject::class));
    }

    /**
     * @return list<string>
     */
    private function ids(Definition $definition): array
    {
        return array_values(array_map($this->id(...), $definition->getArguments()));
    }

    private function id(mixed $reference): string
    {
        self::assertInstanceOf(Reference::class, $reference);

        return (string) $reference;
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, string>      $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSurveyBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
