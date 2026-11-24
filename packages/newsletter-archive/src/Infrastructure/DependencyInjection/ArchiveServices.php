<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\DependencyInjection;

use Phil\Markup\Markup;
use Phil\NewsletterArchive\Application\Archive;
use Phil\NewsletterArchive\Application\ArchiveReader;
use Phil\NewsletterArchive\Application\Contract\IssueUrls;
use Phil\NewsletterArchive\Application\Contract\WebVersionRenderer;
use Phil\NewsletterArchive\Application\IssueFeed;
use Phil\NewsletterArchive\Application\NeutralWebVersion;
use Phil\NewsletterArchive\Domain\Contract\ArchiveRepository;
use Phil\NewsletterArchive\Infrastructure\Console\ArchiveCommand;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchemaListener;
use Phil\NewsletterArchive\Infrastructure\Dbal\DbalArchiveRepository;
use Phil\NewsletterArchive\Infrastructure\Http\ArchiveController;
use Phil\NewsletterArchive\Infrastructure\Routing\RouterIssueUrls;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Domain\Contract\CampaignRepository;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\Slug\Slug;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The archive's services, on those of `phil/newsletter-bundle`: its campaigns, lists, templates, renderer, `Markup`
 * and current site.
 */
final class ArchiveServices
{
    public const string SLUGGER = 'phil_newsletter_archive.slugger';

    /**
     * @param array{enabled: bool, connection: string, page_size: int, slugger: string|null, slug_max_length: int} $config
     */
    public static function register(ContainerConfigurator $container, array $config): void
    {
        if (!$config['enabled']) {
            return;
        }

        $services = $container->services();
        $connection = service($config['connection']);

        if (null === $config['slugger']) {
            $services->set(self::SLUGGER, TransliteratingSlugger::class)->args([null, min($config['slug_max_length'], Slug::MAX_LENGTH)]);
        } else {
            $services->alias(self::SLUGGER, $config['slugger']);
        }

        $services->set(ArchiveRepository::class, DbalArchiveRepository::class)->args([$connection]);
        $services->set(WebVersionRenderer::class, NeutralWebVersion::class)
            ->args([service(Markup::class), service(ContentRenderer::class), service(TemplateRepository::class)]);
        $services->set(Archive::class)->args([
            service(ArchiveRepository::class), service(CampaignRepository::class), service(MailingListRepository::class),
            service(WebVersionRenderer::class), service(self::SLUGGER), service('clock'),
        ])->public();
        $services->set(ArchiveReader::class)->args([service(ArchiveRepository::class)])->public();
        $services->set(IssueUrls::class, RouterIssueUrls::class)->args([service('router')]);
        $services->set(IssueFeed::class)->args([service(ArchiveRepository::class), service(IssueUrls::class)])->public();
        $services->set(ArchiveController::class)
            ->args([service(ArchiveReader::class), service(CurrentSite::class), service('twig'), $config['page_size']])
            ->tag('controller.service_arguments')
            ->public();
        $services->set(ArchiveSchemaListener::class)->args([$connection])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema']);

        if (class_exists(Command::class)) {
            $services->set(ArchiveCommand::class)->args([service(Archive::class), service(IssueUrls::class)])
                ->tag('console.command', ['command' => ArchiveCommand::NAME]);
        }
    }
}
