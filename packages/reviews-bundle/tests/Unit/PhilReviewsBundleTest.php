<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit;

use Closure;
use Phil\ModerationBundle\Application\Event\DecisionTaken;
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
use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Infrastructure\Console\RecomputeCommand;
use Phil\ReviewsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ReviewsBundle\Infrastructure\DependencyInjection\ReviewsServices;
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
use Phil\ReviewsBundle\PhilReviewsBundle;
use Phil\SpamProtectionBundle\Application\RefusalPolicy;
use Phil\SpamProtectionBundle\Application\SubmissionInspector;
use Phil\SpamProtectionBundle\Infrastructure\Http\ClientKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilReviewsBundle::class)]
#[CoversClass(ReviewsServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilReviewsBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_reviews', $this->extension()->getAlias());
        self::assertFalse($container->hasDefinition(RateLimitRoutes::class));
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $container->getDefinition(DbalReviewRepository::class)->getArgument(0));
        self::assertSame([], $container->getDefinition(SubjectTypes::class)->getArguments());
        self::assertSame([RemovalCriteria::DEFAULTS], $container->getDefinition(RemovalCriteria::class)->getArguments());
        self::assertSame(SecurityCurrentReviewer::class, (string) $container->getAlias(CurrentReviewer::class));
        self::assertTrue($container->getAlias(CurrentReviewer::class)->isPublic());
        self::assertSame(NoParticipations::class, (string) $container->getAlias(Participations::class));
        self::assertSame(RoleResponders::class, (string) $container->getAlias(ResponderPolicy::class));
        self::assertEquals([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), RoleResponders::DEFAULT_ATTRIBUTE], $container->getDefinition(RoleResponders::class)->getArguments());
        self::assertSame(IdentifierDirectory::class, (string) $container->getAlias(ReviewerDirectory::class));
        self::assertSame(IdentifierDirectory::class, (string) $container->getAlias(SubjectDirectory::class));
        self::assertSame(ErasureMode::Anonymise, $container->getDefinition(ReviewPrivacy::class)->getArgument(2));
        self::assertTrue($container->getDefinition(ReviewPrivacy::class)->hasTag(ReviewsServices::EXPORTER_TAG));
        self::assertTrue($container->getDefinition(ReviewPrivacy::class)->hasTag(ReviewsServices::ERASER_TAG));
        self::assertTrue($container->getDefinition(RecomputeCommand::class)->hasTag('console.command'));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(SchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertTrue($container->hasDefinition(AggregateRatings::class));
        self::assertTrue($container->hasDefinition(ReviewsExtension::class));
        self::assertSame('@PhilReviews/layout.html.twig', $container->getDefinition(ReviewsController::class)->getArgument(9));
        self::assertSame(10, $container->getDefinition(ReviewsController::class)->getArgument(10));
        self::assertNull($container->getDefinition(ReviewsController::class)->getArgument(11));
        self::assertFalse($container->hasDefinition(ReviewTarget::class), 'The moderation target needs the moderation bundle registered.');
        self::assertFalse($container->hasDefinition(SpamGuard::class), 'The spam guard needs the spam protection bundle registered.');
    }

    public function testAFullConfiguration(): void
    {
        $container = $this->load([[
            'connection' => 'reviews',
            'current_reviewer' => 'app.reviewer',
            'participations' => 'app.participations',
            'responders' => 'app.responders',
            'reviewers' => 'app.names',
            'subjects' => 'app.subjects',
            'layout' => 'base.html.twig',
            'kit_subdirectory' => 'ui',
            'page_size' => 20,
            'erasure' => 'remove',
            'removal_criteria' => ['spam', 'fake'],
            'rate_limit' => ['write' => ['reviews', ['limiter' => 'api', 'key' => 'user']], 'vote' => ['votes']],
            'types' => [
                'game' => ['scale' => 10, 'criteria' => ['rules'], 'superlatives' => ['quick'], 'max_superlatives' => 1, 'superlatives_shown' => 2, 'participants_only' => true, 'window' => 14, 'prior' => ['count' => 10, 'mean' => 6.0, 'minimum' => 4], 'body_length' => 900],
                'place' => [],
            ],
        ]], ['PhilModerationBundle' => true, 'PhilSpamProtectionBundle' => true]);

        self::assertEquals(new Reference('doctrine.dbal.reviews_connection'), $container->getDefinition(DbalRatingRepository::class)->getArgument(0));
        self::assertSame([['reviews', ['limiter' => 'api', 'key' => 'user']], ['votes']], $container->getDefinition(RateLimitRoutes::class)->getArguments());
        self::assertSame([['event' => 'kernel.request']], $container->getDefinition(RateLimitRoutes::class)->getTag('kernel.event_listener'));
        self::assertSame('app.reviewer', (string) $container->getAlias(CurrentReviewer::class));
        self::assertSame('app.participations', (string) $container->getAlias(Participations::class));
        self::assertSame('app.responders', (string) $container->getAlias(ResponderPolicy::class));
        self::assertSame('app.names', (string) $container->getAlias(ReviewerDirectory::class));
        self::assertSame('app.subjects', (string) $container->getAlias(SubjectDirectory::class));
        self::assertFalse($container->hasDefinition(SecurityCurrentReviewer::class));
        self::assertFalse($container->hasDefinition(NoParticipations::class));
        self::assertFalse($container->hasDefinition(RoleResponders::class));
        self::assertFalse($container->hasDefinition(IdentifierDirectory::class));
        self::assertSame([['spam', 'fake']], $container->getDefinition(RemovalCriteria::class)->getArguments());
        self::assertSame(ErasureMode::Remove, $container->getDefinition(ReviewPrivacy::class)->getArgument(2));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'reviews']], $container->getDefinition(SchemaListener::class)->getTag('doctrine.event_listener'));

        $types = $container->getDefinition(SubjectTypes::class)->getArguments();
        self::assertCount(2, $types);
        self::assertInstanceOf(Definition::class, $types[0]);
        self::assertSame(SubjectType::class, $types[0]->getClass());
        self::assertSame(['game', 10, ['rules'], ['quick'], 1, true, 14, 10, 6.0, 4, 900, 2], $types[0]->getArguments());
        self::assertInstanceOf(Definition::class, $types[1]);
        self::assertSame(['place', 5, [], [], 3, false, 30, 5, null, 3, 5000, 3], $types[1]->getArguments());

        self::assertTrue($container->getDefinition(ReviewTarget::class)->hasTag(ReviewsServices::TARGET_TAG));
        self::assertSame([['event' => DecisionTaken::class]], $container->getDefinition(ModerationDecisionListener::class)->getTag('kernel.event_listener'));
        self::assertTrue($container->hasDefinition(SpamGuard::class));
        $spam = $container->getDefinition(ReviewsController::class)->getArgument(11);
        self::assertInstanceOf(Reference::class, $spam);
        self::assertSame(SpamGuard::class, (string) $spam);
        self::assertSame('base.html.twig', $container->getDefinition(ReviewsController::class)->getArgument(9));
        self::assertSame(20, $container->getDefinition(ReviewsController::class)->getArgument(10));
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testEachServiceIsWiredToTheOthersAndTheEntryPointsArePublic(): void
    {
        $container = $this->load([], ['PhilModerationBundle' => true, 'PhilSpamProtectionBundle' => true]);
        $optional = ContainerInterface::NULL_ON_INVALID_REFERENCE;

        foreach ([ReviewRepository::class, RatingRepository::class] as $alias) {
            self::assertTrue($container->getAlias($alias)->isPublic(), $alias);
        }

        self::assertSame(DbalReviewRepository::class, (string) $container->getAlias(ReviewRepository::class));
        self::assertSame(DbalRatingRepository::class, (string) $container->getAlias(RatingRepository::class));

        $services = [
            SubjectTypes::class => [true, []],
            RemovalCriteria::class => [true, [RemovalCriteria::DEFAULTS]],
            Ratings::class => [true, [new Reference(RatingRepository::class), new Reference(SubjectTypes::class)]],
            Reviews::class => [true, [
                new Reference(ReviewRepository::class),
                new Reference(Ratings::class),
                new Reference(SubjectTypes::class),
                new Reference(RemovalCriteria::class),
                new Reference(Participations::class),
                new Reference(ResponderPolicy::class),
                new Reference('clock'),
                new Reference('event_dispatcher', $optional),
            ]],
            RecomputeCommand::class => [false, [new Reference(Ratings::class)]],
            SchemaListener::class => [false, [new Reference('doctrine.dbal.default_connection')]],
            ReviewPrivacy::class => [false, [new Reference(ReviewRepository::class), new Reference(Ratings::class), ErasureMode::Anonymise]],
            ReviewTarget::class => [false, [new Reference(Reviews::class), new Reference(SubjectDirectory::class)]],
            ModerationDecisionListener::class => [false, [new Reference(Reviews::class), new Reference(RemovalCriteria::class), new Reference('logger', $optional)]],
            AggregateRatings::class => [true, [new Reference(Ratings::class), new Reference(SubjectTypes::class)]],
            SecurityCurrentReviewer::class => [false, [new Reference('security.token_storage', $optional)]],
            NoParticipations::class => [false, []],
            IdentifierDirectory::class => [false, []],
            KitComponents::class => [false, [null]],
            ReviewsExtension::class => [false, [
                new Reference(KitComponents::class),
                new Reference(ReviewerDirectory::class),
                new Reference(Ratings::class),
                new Reference(SubjectTypes::class),
                new Reference(AggregateRatings::class, $optional),
            ]],
            SpamGuard::class => [false, [new Reference(SubmissionInspector::class), new Reference(RefusalPolicy::class), new Reference(ClientKey::class, $optional)]],
            ReviewsRequest::class => [false, [new Reference('security.csrf.token_manager')]],
            ReviewsController::class => [true, [
                new Reference(Reviews::class),
                new Reference(Ratings::class),
                new Reference(SubjectTypes::class),
                new Reference(RemovalCriteria::class),
                new Reference(CurrentReviewer::class),
                new Reference(SubjectDirectory::class),
                new Reference(ReviewsRequest::class),
                new Reference('twig'),
                new Reference('router'),
                '@PhilReviews/layout.html.twig',
                10,
                new Reference(SpamGuard::class),
            ]],
        ];

        foreach ($services as $id => [$public, $arguments]) {
            self::assertTrue($container->hasDefinition($id), $id);
            self::assertSame($public, $container->getDefinition($id)->isPublic(), $id);
            self::assertEquals($arguments, $container->getDefinition($id)->getArguments(), $id);
        }
    }

    public function testTheDirectoryOfIdentifiersIsKeptWhileOneOfTheTwoIsLeftToIt(): void
    {
        self::assertTrue($this->load([['reviewers' => 'app.names']])->hasDefinition(IdentifierDirectory::class));
        self::assertTrue($this->load([['subjects' => 'app.subjects']])->hasDefinition(IdentifierDirectory::class));
        self::assertFalse($this->load([['reviewers' => 'app.names', 'subjects' => 'app.subjects']])->hasDefinition(IdentifierDirectory::class));
    }

    public function testTheBoundsOfTheNumbers(): void
    {
        $page = static fn (int $value): array => ['page_size' => $value];
        $type = static fn (string $key): Closure => static fn (int $value): array => ['types' => ['place' => [$key => $value]]];
        $prior = static fn (string $key): Closure => static fn (int $value): array => ['types' => ['place' => ['prior' => [$key => $value]]]];
        $bounds = [
            'page_size' => [$page, 1, 100],
            'scale' => [$type('scale'), 2, ReviewContent::MAX_SCALE],
            'max_superlatives' => [$type('max_superlatives'), 0, ReviewContent::MAX_SUPERLATIVES],
            'superlatives_shown' => [$type('superlatives_shown'), 0, 64],
            'body_length' => [$type('body_length'), 1, ReviewContent::MAX_BODY_LENGTH],
            'window' => [$type('window'), 1, 3650],
            'prior count' => [$prior('count'), 0, BayesianPrior::MAX_COUNT],
            'prior minimum' => [$prior('minimum'), 0, BayesianPrior::MAX_COUNT],
        ];

        foreach ($bounds as $name => [$build, $lowest, $highest]) {
            foreach ([$lowest, $highest] as $accepted) {
                $this->load([$build($accepted)]);
                self::assertTrue(true, $name);
            }

            foreach ([$lowest - 1, $highest + 1] as $refused) {
                if ('window' === $name && $refused > $highest) {
                    continue;
                }

                try {
                    $this->load([$build($refused)]);
                    self::fail(\sprintf('%s of %d was taken.', $name, $refused));
                } catch (InvalidConfigurationException) {
                    self::assertTrue(true, $name);
                }
            }
        }
    }

    public function testOneListOfRulesIsEnough(): void
    {
        self::assertSame([[], ['votes']], $this->load([['rate_limit' => ['vote' => ['votes']]]])->getDefinition(RateLimitRoutes::class)->getArguments());
        self::assertSame([['reviews'], []], $this->load([['rate_limit' => ['write' => ['reviews']]]])->getDefinition(RateLimitRoutes::class)->getArguments());
    }

    public function testTheSpamProtectionCanBeTurnedOff(): void
    {
        $container = $this->load([['spam_protection' => false]], ['PhilSpamProtectionBundle' => true]);

        self::assertFalse($container->hasDefinition(SpamGuard::class));
    }

    public function testAWindowCanBeLifted(): void
    {
        $types = $this->load([['types' => ['place' => ['window' => null]]]])->getDefinition(SubjectTypes::class)->getArguments();

        self::assertInstanceOf(Definition::class, $types[0]);
        self::assertNull($types[0]->getArgument(6));
    }

    public function testAWholeNeutralValueIsTakenAsANumber(): void
    {
        $types = $this->load([['types' => ['place' => ['prior' => ['mean' => 4]]]]])->getDefinition(SubjectTypes::class)->getArguments();

        self::assertInstanceOf(Definition::class, $types[0]);
        self::assertSame(4.0, $types[0]->getArgument(8));
    }

    public function testAMalformedWindowIsRefused(): void
    {
        foreach ([0, '30', 1.5] as $window) {
            try {
                $this->load([['types' => ['place' => ['window' => $window]]]]);
                self::fail('A malformed window was taken.');
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString('The window is a number of days, one or more, or null for no limit', $invalid->getMessage());
            }
        }
    }

    public function testAMalformedNeutralValueIsRefused(): void
    {
        try {
            $this->load([['types' => ['place' => ['prior' => ['mean' => 'three']]]]]);
            self::fail('A malformed neutral value was taken.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('The neutral value is a number, or null for the middle of the scale', $invalidConfigurationException->getMessage());
        }
    }

    public function testAMalformedTypeNameIsRefused(): void
    {
        try {
            $this->load([['types' => ['Board Game' => []]]]);
            self::fail('A malformed type name was taken.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('A type of subject is named by a lowercase letter', $invalidConfigurationException->getMessage());
        }
    }

    public function testNoRemovalCriterionIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([['removal_criteria' => []]]);
    }

    public function testAnUnknownErasureIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([['erasure' => 'keep']]);
    }

    public function testTheKitSubdirectoryComesFromTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'kit/ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('kit/ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $named = $this->load([['kit_subdirectory' => 'mine']]);
        $named->setParameter('.ux_toolkit.components.subdirectory', 'kit/ui');
        new KitSubdirectoryPass()->process($named);
        self::assertSame('mine', $named->getDefinition(KitComponents::class)->getArgument(0));

        $without = $this->load([]);
        new KitSubdirectoryPass()->process($without);
        self::assertSame('', $without->getDefinition(KitComponents::class)->getArgument(0));

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
    }

    public function testTheBundleAddsItsPassAndPrependsItsSettings(): void
    {
        $container = new ContainerBuilder();
        new PhilReviewsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
        self::assertDirectoryExists(PhilReviewsBundle::CONTROLLERS_DIR);
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, bool>        $bundles
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
        $extension = new PhilReviewsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
