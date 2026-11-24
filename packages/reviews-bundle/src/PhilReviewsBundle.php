<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle;

use Override;
use Phil\ReviewsBundle\Application\ErasureMode;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\SubjectType;
use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Phil\ReviewsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ReviewsBundle\Infrastructure\DependencyInjection\ReviewsServices;
use Phil\ReviewsBundle\Infrastructure\Http\ReviewsRequest;
use Phil\ReviewsBundle\Infrastructure\Security\RoleResponders;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Reviews and ratings of a product, a game or a place, moderated: an overall score and the scores of the criteria of
 * its type, a text, up to three superlatives, one review per member and subject, votes of helpfulness, the public
 * response of the merchant or the organiser, a weighted average published with its parameters, the reviews reserved to
 * the participants within a window, withdrawals under published criteria only and their contest, the mention of a
 * compensation, and the reviews of a subject suspended without losing them. Kept through DBAL; reported through
 * phil/moderation-bundle, exported and erased through phil/privacy-bundle, given as `AggregateRating` through
 * phil/structured-data, the form held to phil/spam-protection-bundle and phil/rate-limit-bundle when they are there.
 *
 * ```yaml
 * phil_reviews:
 *     types:
 *         game: { criteria: [rules, replayability], superlatives: [great-for-beginners, gorgeous] }
 * ```
 *
 * @phpstan-import-type Config from ReviewsServices
 */
final class PhilReviewsBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_reviews';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')->info('The DBAL connection of the tables.')->cannotBeEmpty()->defaultValue('default')->end()
                ->scalarNode('current_reviewer')
                    ->info("The CurrentReviewer service naming who is signed in; Symfony Security's user identifier when null, refused when it is an email address.")
                    ->defaultNull()
                ->end()
                ->scalarNode('participations')
                    ->info('The Participations service of the commerce or the ticketing, which marks the verified reviews and admits the participants; none when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('responders')
                    ->info('The ResponderPolicy service saying who responds to the reviews of a subject; Symfony Security and "responder_attribute" when null.')
                    ->defaultNull()
                ->end()
                ->scalarNode('responder_attribute')
                    ->info('The attribute Symfony Security grants to those who respond, the subject given to the voters.')
                    ->cannotBeEmpty()
                    ->defaultValue(RoleResponders::DEFAULT_ATTRIBUTE)
                ->end()
                ->scalarNode('reviewers')->info('The ReviewerDirectory service naming the authors; their identifiers when null.')->defaultNull()->end()
                ->scalarNode('subjects')->info('The SubjectDirectory service naming the subjects and giving their addresses; their type and identifier when null.')->defaultNull()->end()
                ->scalarNode('layout')
                    ->info('The Twig template the pages extend, with the blocks "title" and "main".')
                    ->cannotBeEmpty()
                    ->defaultValue('@PhilReviews/layout.html.twig')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
                ->integerNode('page_size')->info('How many reviews a page shows.')->min(1)->max(100)->defaultValue(10)->end()
                ->enumNode('erasure')
                    ->info('What the erasure of a person does to their reviews: "anonymise" keeps the scores without author, title or text; "remove" deletes them.')
                    ->values(array_map(static fn (ErasureMode $mode): string => $mode->value, ErasureMode::cases()))
                    ->defaultValue(ErasureMode::Anonymise->value)
                ->end()
                ->arrayNode('removal_criteria')
                    ->info('The published criteria under which a review is withdrawn, in the order the rules list them.')
                    ->scalarPrototype()->end()
                    ->requiresAtLeastOneElement()
                    ->defaultValue(RemovalCriteria::DEFAULTS)
                ->end()
                ->booleanNode('spam_protection')
                    ->info('Holds the review form to phil/spam-protection-bundle when it is registered.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('rate_limit')
                    ->info('The rules of phil/rate-limit-bundle the routes carry, as its "_rate_limit" route default reads them: a limiter name, or a map of "limiter", "amount" and "key".')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('write')->info('The rules of the review form.')->variablePrototype()->end()->defaultValue([])->end()
                        ->arrayNode('vote')->info('The rules of the votes, responses and contests.')->variablePrototype()->end()->defaultValue([])->end()
                    ->end()
                ->end()
                ->arrayNode('types')
                    ->info('The types of subject reviewed, by name: product, game, place.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('scale')->info('How many stars.')->min(2)->max(ReviewContent::MAX_SCALE)->defaultValue(SubjectType::DEFAULT_SCALE)->end()
                            ->arrayNode('criteria')->info('The codes of the criteria a review rates, in the order of the form.')->scalarPrototype()->end()->end()
                            ->arrayNode('superlatives')->info('The codes of the positive labels offered as superlatives.')->scalarPrototype()->end()->end()
                            ->integerNode('max_superlatives')->info('How many superlatives a review gives.')->min(0)->max(ReviewContent::MAX_SUPERLATIVES)->defaultValue(ReviewContent::MAX_SUPERLATIVES)->end()
                            ->integerNode('superlatives_shown')->info('How many of the most frequent superlatives the summary shows.')->min(0)->max(64)->defaultValue(ReviewContent::MAX_SUPERLATIVES)->end()
                            ->booleanNode('participants_only')->info('Whether only the participants the Participations service knows review a subject of the type.')->defaultFalse()->end()
                            ->scalarNode('window')
                                ->info('How many days after their participation a participant reviews; null for no limit.')
                                ->defaultValue(SubjectType::DEFAULT_WINDOW)
                                ->validate()
                                    ->ifTrue(static fn (mixed $window): bool => null !== $window && (!\is_int($window) || $window < 1))
                                    ->thenInvalid('The window is a number of days, one or more, or null for no limit: %s.')
                                ->end()
                            ->end()
                            ->arrayNode('prior')
                                ->info('The weighted average, published: fictitious reviews of a neutral value added to the real ones.')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('count')->info('How many fictitious reviews.')->min(0)->max(BayesianPrior::MAX_COUNT)->defaultValue(SubjectType::DEFAULT_PRIOR_COUNT)->end()
                                    ->scalarNode('mean')
                                        ->info('Their value; the middle of the scale when null.')
                                        ->defaultNull()
                                        ->validate()
                                            ->ifTrue(static fn (mixed $mean): bool => null !== $mean && !\is_int($mean) && !\is_float($mean))
                                            ->thenInvalid('The neutral value is a number, or null for the middle of the scale: %s.')
                                        ->end()
                                    ->end()
                                    ->integerNode('minimum')->info('How many reviews before an average is shown.')->min(0)->max(BayesianPrior::MAX_COUNT)->defaultValue(SubjectType::DEFAULT_MINIMUM)->end()
                                ->end()
                            ->end()
                            ->integerNode('body_length')->info('How many characters the text of a review holds.')->min(1)->max(ReviewContent::MAX_BODY_LENGTH)->defaultValue(SubjectType::DEFAULT_BODY_LENGTH)->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $types): bool => [] !== array_filter(array_keys($types), static fn (int|string $name): bool => 1 !== preg_match(SubjectRef::TYPE, (string) $name)))
                        ->thenInvalid('A type of subject is named by a lowercase letter, then up to 63 lowercase letters, digits, ".", "-" and "_": %s.')
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [ReviewsRequest::TOKEN_ID]]]);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/reviews-bundle']]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        /** @var Config $config */
        ReviewsServices::register($builder, $config, \is_array($bundles) ? array_map(strval(...), array_keys($bundles)) : []);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }
}
