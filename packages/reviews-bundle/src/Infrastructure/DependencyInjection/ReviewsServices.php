<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\Event\DecisionTaken;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\ReviewsBundle\Application\Contract\CurrentReviewer;
use Phil\ReviewsBundle\Application\Contract\Participations;
use Phil\ReviewsBundle\Application\Contract\ResponderPolicy;
use Phil\ReviewsBundle\Application\Contract\ReviewerDirectory;
use Phil\ReviewsBundle\Application\Contract\SubjectDirectory;
use Phil\ReviewsBundle\Application\ErasureMode;
use Phil\ReviewsBundle\Application\IdentifierDirectory;
use Phil\ReviewsBundle\Application\NoParticipations;
use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\Reviews;
use Phil\ReviewsBundle\Application\SubjectType;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Contract\RatingRepository;
use Phil\ReviewsBundle\Domain\Contract\ReviewRepository;
use Phil\ReviewsBundle\Infrastructure\Console\RecomputeCommand;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalRatingRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalReviewRepository;
use Phil\ReviewsBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ReviewsBundle\Infrastructure\Http\RateLimitRoutes;
use Phil\ReviewsBundle\Infrastructure\Http\ReviewsController;
use Phil\ReviewsBundle\Infrastructure\Http\ReviewsRequest;
use Phil\ReviewsBundle\Infrastructure\Http\SpamGuard;
use Phil\ReviewsBundle\Infrastructure\Moderation\ModerationDecisionListener;
use Phil\ReviewsBundle\Infrastructure\Moderation\ReviewTarget;
use Phil\ReviewsBundle\Infrastructure\Privacy\ReviewPrivacy;
use Phil\ReviewsBundle\Infrastructure\Security\RoleResponders;
use Phil\ReviewsBundle\Infrastructure\Security\SecurityCurrentReviewer;
use Phil\ReviewsBundle\Infrastructure\StructuredData\AggregateRatings;
use Phil\ReviewsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReviewsBundle\Infrastructure\Twig\ReviewsExtension;
use Phil\SpamProtectionBundle\Application\RefusalPolicy;
use Phil\SpamProtectionBundle\Application\SubmissionInspector;
use Phil\SpamProtectionBundle\Infrastructure\Http\ClientKey;
use Phil\StructuredData\Node;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The bundle's services: the DBAL stores, the types of subject, the entry points and their ports, the command; the
 * moderation target when phil/moderation-bundle is registered, the privacy part when phil/privacy-bundle is installed,
 * the structured data when phil/structured-data is, and the pages with their Twig functions, the review form held to
 * phil/spam-protection-bundle when it is registered.
 *
 * @phpstan-type TypeConfig array{scale: int, criteria: list<string>, superlatives: list<string>, max_superlatives: int, superlatives_shown: int, participants_only: bool, window: ?int, prior: array{count: int, mean: int|float|null, minimum: int}, body_length: int}
 * @phpstan-type Config array{connection: string, current_reviewer: ?string, participations: ?string, responders: ?string, responder_attribute: string, reviewers: ?string, subjects: ?string, layout: string, kit_subdirectory: ?string, page_size: positive-int, erasure: string, removal_criteria: list<string>, spam_protection: bool, rate_limit: array{write: list<mixed>, vote: list<mixed>}, types: array<string, TypeConfig>}
 */
final class ReviewsServices
{
    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    public const string TARGET_TAG = 'phil_moderation.target';

    /**
     * @param Config        $config
     * @param array<string> $bundles the names of the registered bundles
     */
    public static function register(ContainerBuilder $container, array $config, array $bundles): void
    {
        $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');

        $container->register(DbalReviewRepository::class, DbalReviewRepository::class)->setArguments([$connection]);
        $container->setAlias(ReviewRepository::class, DbalReviewRepository::class)->setPublic(true);
        $container->register(DbalRatingRepository::class, DbalRatingRepository::class)->setArguments([$connection]);
        $container->setAlias(RatingRepository::class, DbalRatingRepository::class)->setPublic(true);

        $types = [];

        foreach ($config['types'] as $name => $type) {
            $types[] = new Definition(SubjectType::class, [
                $name,
                $type['scale'],
                $type['criteria'],
                $type['superlatives'],
                $type['max_superlatives'],
                $type['participants_only'],
                $type['window'],
                $type['prior']['count'],
                null === $type['prior']['mean'] ? null : (float) $type['prior']['mean'],
                $type['prior']['minimum'],
                $type['body_length'],
                $type['superlatives_shown'],
            ]);
        }

        $container->register(SubjectTypes::class, SubjectTypes::class)->setArguments($types)->setPublic(true);
        $container->register(RemovalCriteria::class, RemovalCriteria::class)->setArguments([$config['removal_criteria']])->setPublic(true);

        self::ports($container, $config);

        $container->register(Ratings::class, Ratings::class)
            ->setArguments([new Reference(RatingRepository::class), new Reference(SubjectTypes::class)])
            ->setPublic(true);
        $container->register(Reviews::class, Reviews::class)
            ->setArguments([
                new Reference(ReviewRepository::class),
                new Reference(Ratings::class),
                new Reference(SubjectTypes::class),
                new Reference(RemovalCriteria::class),
                new Reference(Participations::class),
                new Reference(ResponderPolicy::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(RecomputeCommand::class, RecomputeCommand::class)
                ->setArguments([new Reference(Ratings::class)])
                ->addTag('console.command');
        }

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(SchemaListener::class, SchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(ReviewPrivacy::class, ReviewPrivacy::class)
                ->setArguments([new Reference(ReviewRepository::class), new Reference(Ratings::class), ErasureMode::from($config['erasure'])])
                ->addTag(self::EXPORTER_TAG)
                ->addTag(self::ERASER_TAG);
        }

        if (interface_exists(ModerationTarget::class) && \in_array('PhilModerationBundle', $bundles, true)) {
            $container->register(ReviewTarget::class, ReviewTarget::class)
                ->setArguments([new Reference(Reviews::class), new Reference(SubjectDirectory::class)])
                ->addTag(self::TARGET_TAG);
            $container->register(ModerationDecisionListener::class, ModerationDecisionListener::class)
                ->setArguments([new Reference(Reviews::class), new Reference(RemovalCriteria::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('kernel.event_listener', ['event' => DecisionTaken::class]);
        }

        if (class_exists(Node::class)) {
            $container->register(AggregateRatings::class, AggregateRatings::class)
                ->setArguments([new Reference(Ratings::class), new Reference(SubjectTypes::class)])
                ->setPublic(true);
        }

        self::pages($container, $config, $bundles);
    }

    /**
     * @param Config $config
     */
    private static function ports(ContainerBuilder $container, array $config): void
    {
        if (null === $config['current_reviewer']) {
            $container->register(SecurityCurrentReviewer::class, SecurityCurrentReviewer::class)
                ->setArguments([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        }

        $container->setAlias(CurrentReviewer::class, $config['current_reviewer'] ?? SecurityCurrentReviewer::class)->setPublic(true);

        if (null === $config['participations']) {
            $container->register(NoParticipations::class, NoParticipations::class);
        }

        $container->setAlias(Participations::class, $config['participations'] ?? NoParticipations::class);

        if (null === $config['responders']) {
            $container->register(RoleResponders::class, RoleResponders::class)
                ->setArguments([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $config['responder_attribute']]);
        }

        $container->setAlias(ResponderPolicy::class, $config['responders'] ?? RoleResponders::class);

        if (null === $config['reviewers'] || null === $config['subjects']) {
            $container->register(IdentifierDirectory::class, IdentifierDirectory::class);
        }

        $container->setAlias(ReviewerDirectory::class, $config['reviewers'] ?? IdentifierDirectory::class);
        $container->setAlias(SubjectDirectory::class, $config['subjects'] ?? IdentifierDirectory::class);
    }

    /**
     * @param Config        $config
     * @param array<string> $bundles
     */
    private static function pages(ContainerBuilder $container, array $config, array $bundles): void
    {
        if (!class_exists(Environment::class) || !interface_exists(UrlGeneratorInterface::class) || !interface_exists(CsrfTokenManagerInterface::class)) {
            return;
        }

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(ReviewsExtension::class, ReviewsExtension::class)
            ->setArguments([
                new Reference(KitComponents::class),
                new Reference(ReviewerDirectory::class),
                new Reference(Ratings::class),
                new Reference(SubjectTypes::class),
                new Reference(AggregateRatings::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        $spam = null;

        if ($config['spam_protection'] && class_exists(SubmissionInspector::class) && \in_array('PhilSpamProtectionBundle', $bundles, true)) {
            $container->register(SpamGuard::class, SpamGuard::class)
                ->setArguments([new Reference(SubmissionInspector::class), new Reference(RefusalPolicy::class), new Reference(ClientKey::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
            $spam = new Reference(SpamGuard::class);
        }

        if ([] !== $config['rate_limit']['write'] || [] !== $config['rate_limit']['vote']) {
            $container->register(RateLimitRoutes::class, RateLimitRoutes::class)
                ->setArguments([$config['rate_limit']['write'], $config['rate_limit']['vote']])
                ->addTag('kernel.event_listener', ['event' => 'kernel.request']);
        }

        $container->register(ReviewsRequest::class, ReviewsRequest::class)
            ->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(ReviewsController::class, ReviewsController::class)
            ->setArguments([
                new Reference(Reviews::class),
                new Reference(Ratings::class),
                new Reference(SubjectTypes::class),
                new Reference(RemovalCriteria::class),
                new Reference(CurrentReviewer::class),
                new Reference(SubjectDirectory::class),
                new Reference(ReviewsRequest::class),
                new Reference('twig'),
                new Reference('router'),
                $config['layout'],
                $config['page_size'],
                $spam,
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }
}
