<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\DependencyInjection;

use Doctrine\DBAL\Connection;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Application\FaqMarkdown;
use Phil\FaqBundle\Application\FaqPages;
use Phil\FaqBundle\Application\FaqStructuredData;
use Phil\FaqBundle\Application\FaqTransfer;
use Phil\FaqBundle\Application\HelpfulVotes;
use Phil\FaqBundle\Application\QueryNormalizer;
use Phil\FaqBundle\Application\UnansweredSearches;
use Phil\FaqBundle\Application\VoteTokens;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Contract\SpentVoteTokenRepository;
use Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository;
use Phil\FaqBundle\Infrastructure\Console\ExportCommand;
use Phil\FaqBundle\Infrastructure\Console\ImportCommand;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalSpentVoteTokens;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalUnansweredSearches;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Infrastructure\Http\AdminGuard;
use Phil\FaqBundle\Infrastructure\Http\FaqAdminController;
use Phil\FaqBundle\Infrastructure\Http\FaqController;
use Phil\FaqBundle\Infrastructure\Http\FaqGuard;
use Phil\FaqBundle\Infrastructure\RateLimiter\SymfonyThrottle;
use Phil\FaqBundle\Infrastructure\Twig\Components\FaqComponent;
use Phil\FaqBundle\Infrastructure\Twig\KitComponents;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Yaml\Yaml;

/**
 * The bundle's services: the store in the Doctrine ORM of the configured entity manager, the answers read by
 * `phil/markup`, the votes and the counted searches held back by symfony/rate-limiter, the page, its component and its
 * routes; the commands when the console and the YAML component are installed; the administration when it is enabled.
 * None of them keeps state between requests.
 */
final class FaqServices
{
    public const string MARKUP = 'phil_faq.markup';

    public const string VOTE_THROTTLE = 'phil_faq.throttle.votes';

    public const string SEARCH_THROTTLE = 'phil_faq.throttle.searches';

    private function __construct()
    {
    }

    /**
     * @param array{
     *     entity_manager: string,
     *     contact_url: ?string,
     *     layout: string,
     *     max_questions: int<1, max>,
     *     vote_lifetime: int<60, 604800>,
     *     markup: ?string,
     *     kit_subdirectory: ?string,
     *     rate_limiters: array{votes: string, searches: string},
     *     admin: array{enabled: bool, role: string, layout: string, locales: list<string>},
     * } $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::store($container, $config['entity_manager']);

        if (null === $config['markup']) {
            $purifier = new Definition(SanitizerPurifier::class)->setFactory([SanitizerPurifier::class, 'defaultPolicies']);
            $container->register(self::MARKUP, Markup::class)->setArguments([new Definition(DefaultCompiler::class), $purifier]);
        } else {
            $container->setAlias(self::MARKUP, $config['markup']);
        }

        $clock = new Reference('clock');
        $container->register(QueryNormalizer::class, QueryNormalizer::class);
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(self::VOTE_THROTTLE, SymfonyThrottle::class)->setArguments([new Reference($config['rate_limiters']['votes']), $clock]);
        $container->register(self::SEARCH_THROTTLE, SymfonyThrottle::class)->setArguments([new Reference($config['rate_limiters']['searches']), $clock]);

        $container->register(FaqEditor::class, FaqEditor::class)
            ->setArguments([new Reference(FaqRepository::class), new Reference(self::MARKUP), new Definition(TransliteratingSlugger::class), $clock])
            ->setPublic(true);
        $container->register(FaqPages::class, FaqPages::class)
            ->setArguments([new Reference(FaqRepository::class), new Reference(QueryNormalizer::class), $config['max_questions']])
            ->setPublic(true);
        $container->register(FaqStructuredData::class, FaqStructuredData::class)->setPublic(true);
        $container->register(FaqMarkdown::class, FaqMarkdown::class)->setPublic(true);
        $container->register(FaqTransfer::class, FaqTransfer::class)->setArguments([new Reference(FaqRepository::class), new Reference(FaqEditor::class)])->setPublic(true);
        $container->register(VoteTokens::class, VoteTokens::class)->setArguments(['%kernel.secret%', $clock, $config['vote_lifetime']]);
        $container->register(HelpfulVotes::class, HelpfulVotes::class)
            ->setArguments([new Reference(FaqRepository::class), new Reference(SpentVoteTokenRepository::class), new Reference(VoteTokens::class), new Reference(self::VOTE_THROTTLE), $clock])
            ->setPublic(true);
        $container->register(UnansweredSearches::class, UnansweredSearches::class)
            ->setArguments([new Reference(UnansweredSearchRepository::class), new Reference(QueryNormalizer::class), new Reference(self::SEARCH_THROTTLE), $clock])
            ->setPublic(true);

        $container->register(FaqGuard::class, FaqGuard::class)->setArguments([new Reference(CsrfProtectionPass::TOKEN_MANAGER)]);
        $container->register(FaqController::class, FaqController::class)
            ->setArguments([
                new Reference(FaqPages::class),
                new Reference(HelpfulVotes::class),
                new Reference(UnansweredSearches::class),
                new Reference(FaqGuard::class),
                new Reference('twig'),
                new Reference('translator'),
                new Reference('router'),
                $config['layout'],
            ])
            ->setPublic(true);
        $container->register(FaqComponent::class, FaqComponent::class)
            ->setArguments([
                new Reference(FaqPages::class),
                new Reference(FaqStructuredData::class),
                new Reference(VoteTokens::class),
                new Reference(FaqGuard::class),
                new Reference(KitComponents::class),
                new Reference('request_stack'),
                new Reference('router'),
                $config['contact_url'],
            ])
            ->setAutoconfigured(true);

        if (class_exists(Command::class) && class_exists(Yaml::class)) {
            foreach ([ExportCommand::class, ImportCommand::class] as $command) {
                $container->register($command, $command)->setArguments([new Reference(FaqTransfer::class)])->setAutoconfigured(true);
            }
        }

        if ($config['admin']['enabled']) {
            $container->register(AdminGuard::class, AdminGuard::class)->setArguments([
                new Reference('security.authorization_checker'),
                new Reference(CsrfProtectionPass::TOKEN_MANAGER),
                new Reference('twig'),
                new Reference('router'),
                $config['admin']['role'],
                $config['admin']['layout'],
                AdminGuard::locales($config['admin']['locales']),
            ]);
            $container->register(FaqAdminController::class, FaqAdminController::class)
                ->setArguments([new Reference(AdminGuard::class), new Reference(FaqEditor::class), new Reference(FaqRepository::class), new Reference(UnansweredSearchRepository::class)])
                ->setPublic(true);
        }
    }

    private static function store(ContainerBuilder $container, string $entityManager): void
    {
        $manager = new Reference('doctrine.orm.'.$entityManager.'_entity_manager');
        $connection = new Definition(Connection::class)->setFactory([$manager, 'getConnection']);

        $container->register(DoctrineFaqRepository::class, DoctrineFaqRepository::class)->setArguments([$manager]);
        $container->setAlias(FaqRepository::class, DoctrineFaqRepository::class)->setPublic(true);
        $container->register(DbalUnansweredSearches::class, DbalUnansweredSearches::class)->setArguments([$connection]);
        $container->setAlias(UnansweredSearchRepository::class, DbalUnansweredSearches::class)->setPublic(true);
        $container->register(DbalSpentVoteTokens::class, DbalSpentVoteTokens::class)->setArguments([$connection]);
        $container->setAlias(SpentVoteTokenRepository::class, DbalSpentVoteTokens::class)->setPublic(true);
    }
}
