<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\SurveyBundle\Application\Addenda;
use Phil\SurveyBundle\Application\BuiltInTemplates;
use Phil\SurveyBundle\Application\Contract\FeedbackSessions;
use Phil\SurveyBundle\Application\Contract\QuestionnaireTemplates;
use Phil\SurveyBundle\Application\Handler\PurgeSurveyResponsesHandler;
use Phil\SurveyBundle\Application\Import\QuestionImports;
use Phil\SurveyBundle\Application\Message\PurgeSurveyResponses;
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
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalQuestionnaireRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveySchemaListener;
use Phil\SurveyBundle\Infrastructure\Http\SurveyController;
use Phil\SurveyBundle\Infrastructure\Http\SurveyGuard;
use Phil\SurveyBundle\Infrastructure\Privacy\SurveyPrivacy;
use Phil\SurveyBundle\Infrastructure\Scheduler\SurveySchedule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The bundle's services: the repositories on the configured connection, the application services, and each
 * integration — the schema listener, the pages, the commands, the schedule, the privacy requests — only when what it
 * needs is there.
 *
 * @phpstan-type Config array{
 *     connection: string,
 *     secret: string,
 *     redirect_origins: list<string>,
 *     allow_localhost: bool,
 *     respondent: ?string,
 *     sessions: ?string,
 *     pages: bool,
 *     schedule: array{enabled: bool, at: string, timezone: string, lock_factory: ?string, state_pool: ?string},
 * }
 */
final class SurveyServices
{
    public const string TEMPLATE_TAG = 'phil_survey.template_provider';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $dbal = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
        $clock = new Reference('clock');

        $container->register(DbalQuestionnaireRepository::class, DbalQuestionnaireRepository::class)->setArguments([$dbal, $clock]);
        $container->setAlias(QuestionnaireRepository::class, DbalQuestionnaireRepository::class);
        $container->register(DbalResponseRepository::class, DbalResponseRepository::class)->setArguments([$dbal]);
        $container->setAlias(ResponseRepository::class, DbalResponseRepository::class);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(SurveySchemaListener::class, SurveySchemaListener::class)
                ->setArguments([$dbal])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(RedirectPolicy::class, RedirectPolicy::class)->setArguments([$config['redirect_origins'], $config['allow_localhost']]);
        $container->register(Participations::class, Participations::class)->setArguments([$config['secret']]);
        $container->registerForAutoconfiguration(QuestionnaireTemplates::class)->addTag(self::TEMPLATE_TAG);
        $container->register(BuiltInTemplates::class, BuiltInTemplates::class)->addTag(self::TEMPLATE_TAG);
        $container->register(TemplateChain::class, TemplateChain::class)->setArguments([new TaggedIteratorArgument(self::TEMPLATE_TAG)]);
        $container->setAlias(QuestionnaireTemplates::class, TemplateChain::class);

        if (null === $config['sessions']) {
            $container->register(NoSessions::class, NoSessions::class);
        }

        $container->setAlias(FeedbackSessions::class, $config['sessions'] ?? NoSessions::class);

        if (null !== $config['respondent']) {
            $container->setAlias(RespondentPass::RESPONDENT, $config['respondent']);
        }

        self::application($container, $clock);
        self::integrations($container, $config);
    }

    private static function application(ContainerBuilder $container, Reference $clock): void
    {
        $responses = new Reference(ResponseRepository::class);

        $container->register(Questionnaires::class, Questionnaires::class)
            ->setArguments([new Reference(QuestionnaireRepository::class), new Reference(RedirectPolicy::class), new Reference(QuestionnaireTemplates::class)])
            ->setPublic(true);
        $container->register(Submissions::class, Submissions::class)
            ->setArguments([$responses, new Reference(Participations::class), new Reference(RedirectPolicy::class), $clock, new Reference(FeedbackSessions::class)])
            ->setPublic(true);
        $container->register(QuestionImports::class, QuestionImports::class)->setArguments([new Reference(Questionnaires::class)])->setPublic(true);
        $container->register(Results::class, Results::class)->setArguments([$responses])->setPublic(true);
        $container->register(Addenda::class, Addenda::class)->setArguments([$responses, $clock])->setPublic(true);
        $container->register(PurgeSurveyResponsesHandler::class, PurgeSurveyResponsesHandler::class)
            ->setArguments([new Reference(QuestionnaireRepository::class), $responses, $clock])
            ->addTag('messenger.message_handler', ['handles' => PurgeSurveyResponses::class])
            ->setPublic(true);
    }

    /**
     * @param Config $config
     */
    private static function integrations(ContainerBuilder $container, array $config): void
    {
        if ($config['pages'] && self::has($container, 'twig', 'TwigBundle')) {
            $container->register(SurveyGuard::class, SurveyGuard::class)->setArguments([new Reference(CsrfProtectionPass::TOKEN_MANAGER)]);
            $container->register(SurveyController::class, SurveyController::class)
                ->setArguments([
                    new Reference(Questionnaires::class),
                    new Reference(Submissions::class),
                    new Reference(Results::class),
                    new Reference(Addenda::class),
                    new Reference(RespondentPass::RESPONDENT),
                    new Reference(SurveyGuard::class),
                    new Reference('twig'),
                ])
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            foreach ([ExportQuestionnaireCommand::class, ImportQuestionnaireCommand::class] as $command) {
                $container->register($command, $command)->setArguments([new Reference(Questionnaires::class)])->addTag('console.command');
            }

            $container->register(ImportQuestionsCommand::class, ImportQuestionsCommand::class)->setArguments([new Reference(QuestionImports::class)])->addTag('console.command');
        }

        $schedule = $config['schedule'];

        if ($schedule['enabled'] && interface_exists(ScheduleProviderInterface::class)) {
            $container->register(SurveySchedule::class, SurveySchedule::class)
                ->setArguments([
                    $schedule['at'],
                    $schedule['timezone'],
                    null === $schedule['lock_factory'] ? null : new Reference($schedule['lock_factory'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    null === $schedule['state_pool'] ? null : new Reference($schedule['state_pool'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('scheduler.schedule_provider', ['name' => SurveySchedule::NAME]);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(SurveyPrivacy::class, SurveyPrivacy::class)
                ->setArguments([new Reference(ResponseRepository::class), new Reference(Participations::class)])
                ->addTag('phil_privacy.exporter')
                ->addTag('phil_privacy.eraser');
        }
    }

    /**
     * Whether the application has a bundle: its extension, in a container that has them all, else its name among the
     * kernel's bundles, which is all an extension sees of the others while it loads.
     */
    private static function has(ContainerBuilder $container, string $extension, string $bundle): bool
    {
        if ($container->hasExtension($extension)) {
            return true;
        }

        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
