<?php

declare(strict_types=1);

namespace Phil\CoursesBundle;

use Override;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\Scorm\ScormTokens;
use Phil\CoursesBundle\Application\Training\TrainingSettings;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\CoursesServices;
use Phil\CoursesBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CoursesBundle\Infrastructure\Http\CourseRequest;
use Phil\CoursesBundle\Infrastructure\Notifications\NotificationsTrainingNotifier;
use Phil\CoursesBundle\Infrastructure\Xapi\HttpLearningRecordStore;
use Phil\ElearningPackages\PackageLimits;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Online courses: courses, modules and activities kept through DBAL, enrollments, progress and completion by activity
 * and by course, release by date or by days after the enrollment in the site's time zone, an imposed order without a
 * dead end — second chances asked for, granted and written to phil/audit-bundle when it is installed —; the import of
 * SCORM 1.2, SCORM 2004, cmi5 and xAPI packages; the SCORM run time, with its attempts and tracking data; cmi5 launches,
 * with their fetch URL and a learning record store inside the bundle that checks the statements against the rules of
 * cmi5, the tree of the blocks kept and their `satisfied` statements issued, or a remote xAPI store; xAPI activities,
 * launched against the same endpoint; the mandatory training — courses assigned by a due date, reminders through
 * phil/notifications-bundle, recertifications, credits and transcripts —; and the administration pages. The pages
 * draw the recipes of the kit.
 *
 * @phpstan-import-type Config from CoursesServices
 */
final class PhilCoursesBundle extends AbstractBundle
{
    /** The Stimulus controller of the SCORM run time, under the name Symfony UX gives the controllers of a package. */
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_courses';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();
        $children
            ->scalarNode('connection')->info('The DBAL connection of the tables.')->cannotBeEmpty()->defaultValue('default')->end()
            ->scalarNode('timezone')->info('The site\'s time zone: a release "3 days after the enrollment" opens at midnight there.')->cannotBeEmpty()->defaultValue('UTC')
                ->validate()->ifNotInArray(timezone_identifiers_list())->thenInvalid('%s is not a time zone.')->end()
            ->end()
            ->scalarNode('current_learner')->info("The CurrentLearner service naming who takes the courses; Symfony Security's user identifier when null, refused when it is an e-mail address.")->defaultNull()->end()
            ->scalarNode('layout')->info('The Twig template the pages extend, with the blocks "title" and "main".')->cannotBeEmpty()->defaultValue('@PhilCourses/layout.html.twig')->end()
            ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')->defaultNull()->end()
            ->booleanNode('self_enrollment')->info('Whether a learner signed in enrolls in a course from its page; when false, the application enrolls them.')->defaultFalse()->end()
            ->enumNode('locked_activities')->info('Whether the outline shows the activities a learner cannot take yet, with what is left to do, or hides them.')->values(['show', 'hide'])->defaultValue('show')->end();

        $this->storage($children);
        $this->content($children);
        $this->import($children);
        $this->cmi5($children);
        $this->scorm($children);
        $this->training($children);

        $children
            ->arrayNode('admin')
                ->info('The administration page of the second chances.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('role')->info('The role a teacher holds to decide the second chances.')->cannotBeEmpty()->defaultValue('ROLE_COURSES_TEACHER')->end()
                ->end()
            ->end()
            ->arrayNode('audit')
                ->info('The second chances written to the trail of phil/audit-bundle, when it is registered.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->scalarNode('segment')->info('The segment of the trail.')->cannotBeEmpty()->defaultValue('courses')->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $container->extension('framework', ['csrf_protection' => ['stateless_token_ids' => [CourseRequest::TOKEN_ID]]]);
        }

        if ($builder->hasExtension('phil_notifications')) {
            $container->extension('phil_notifications', ['types' => NotificationsTrainingNotifier::types()]);
        }

        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/courses-bundle']]);
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
        CoursesServices::register($builder, $config, \is_array($bundles) ? array_keys($bundles) : []);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    private function storage(NodeBuilder $children): void
    {
        $children
            ->arrayNode('storage')
                ->info('Where the files of the imported packages are kept, outside the public directory.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('directory')->info('The directory of the storage on the filesystem.')->cannotBeEmpty()->defaultValue('%kernel.project_dir%/var/phil_courses/content')->end()
                    ->scalarNode('service')->info('A ContentStorage service of the application, instead of the directory.')->defaultNull()->end()
                ->end()
            ->end();
    }

    private function content(NodeBuilder $children): void
    {
        $children
            ->arrayNode('content')
                ->info("The content origin that serves the files of the imported packages: another origin than the application's.")
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('base_url')->info('The base URL of the content origin, such as "https://content.example.org"; null leaves the imported content without a place to launch from.')->defaultNull()->end()
                    ->scalarNode('application_origin')->info('The origin of the site that frames the content, such as "https://www.example.org"; the origin of "cmi5.actor_home_page" when null.')->defaultNull()->end()
                    ->booleanNode('allow_localhost')->info('Whether the content origin and the home page of the accounts may be on localhost, in development.')->defaultFalse()->end()
                ->end()
            ->end();
    }

    private function import(NodeBuilder $children): void
    {
        $children
            ->arrayNode('import')
                ->info('The import of SCORM and cmi5 packages.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('language')->info('The language of the titles read from a cmi5 course structure, such as "fr"; its first title when null.')->defaultNull()->end()
                    ->scalarNode('xapi_reader')->info('An XapiPackageReader service that reads the tincan.xml of xAPI packages; such packages are refused when null.')->defaultNull()->end()
                    ->arrayNode('limits')
                        ->info('The bounds a package must stay within.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('archive_bytes')->info('The size of the archive.')->min(1)->defaultValue(PackageLimits::ARCHIVE_BYTES)->end()
                            ->integerNode('entries')->info('How many entries the archive holds.')->min(1)->defaultValue(PackageLimits::ENTRIES)->end()
                            ->integerNode('entry_bytes')->info('The size of one file, uncompressed.')->min(1)->defaultValue(PackageLimits::ENTRY_BYTES)->end()
                            ->integerNode('total_bytes')->info('The size of every file, uncompressed.')->min(1)->defaultValue(PackageLimits::TOTAL_BYTES)->end()
                            ->integerNode('ratio')->info('How many times a file larger than a megabyte may be compressed.')->min(1)->defaultValue(PackageLimits::RATIO)->end()
                            ->integerNode('manifest_bytes')->info('The size of imsmanifest.xml or cmi5.xml.')->min(1)->defaultValue(PackageLimits::MANIFEST_BYTES)->end()
                            ->integerNode('depth')->info('How deep the items or blocks nest.')->min(1)->max(64)->defaultValue(PackageLimits::DEPTH)->end()
                            ->integerNode('items')->info('How many items, resources, blocks or units a manifest describes.')->min(1)->defaultValue(PackageLimits::ITEMS)->end()
                            ->integerNode('manifest_nodes')->info('How many XML nodes a manifest holds, counted before it is loaded.')->min(1)->defaultValue(PackageLimits::MANIFEST_NODES)->end()
                            ->integerNode('manifest_depth')->info('How deep the XML elements of a manifest nest, checked before it is loaded.')->min(1)->defaultValue(PackageLimits::MANIFEST_DEPTH)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function scorm(NodeBuilder $children): void
    {
        $children
            ->arrayNode('scorm')
                ->info('The SCORM run time: the content of a SCORM activity reports its own progress through the run-time API; when disabled, the learner marks it done.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->integerNode('token_ttl')->info('How many seconds an attempt token holds after the last commit.')->min(300)->max(86_400)->defaultValue(ScormTokens::TTL)->end()
                    ->scalarNode('secret')->info('The secret the attempt tokens are signed with.')->cannotBeEmpty()->defaultValue('%kernel.secret%')->end()
                ->end()
            ->end();
    }

    private function training(NodeBuilder $children): void
    {
        $children
            ->arrayNode('training')
                ->info('The mandatory training: courses assigned by a due date, their reminders and recertifications, the credits and the transcripts.')
                ->addDefaultsIfNotSet()
                ->canBeDisabled()
                ->children()
                    ->integerNode('reminder_days')->info('How many days before the due moment a course is due soon and its first reminder sent.')->min(1)->max(90)->defaultValue(TrainingSettings::REMINDER_DAYS)->end()
                    ->integerNode('renewal_grace_days')->info('How many days after its renewal a recertification is due again.')->min(1)->max(365)->defaultValue(TrainingSettings::GRACE_DAYS)->end()
                    ->integerNode('max_members')->info('How many members of a group or unit an assignment reaches.')->min(1)->defaultValue(TrainingSettings::MAX_MEMBERS)->end()
                    ->scalarNode('run_at')->info("The time of the daily run of the schedule, HH:MM in the site's time zone.")->cannotBeEmpty()->defaultValue('06:00')
                        ->validate()->ifTrue(static fn (mixed $at): bool => !\is_string($at) || 1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $at))->thenInvalid('%s is not a time written HH:MM.')->end()
                    ->end()
                    ->booleanNode('schedule')->info('Whether the schedule "phil_courses_training" runs every day, with Symfony Scheduler.')->defaultTrue()->end()
                    ->scalarNode('manager_role')->info('The role a manager holds to see the training of every learner and their transcripts.')->cannotBeEmpty()->defaultValue('ROLE_COURSES_MANAGER')->end()
                    ->scalarNode('notifier')->info('A TrainingNotifier service; the notifications of phil/notifications-bundle when null and it is registered, none otherwise.')->defaultNull()->end()
                    ->booleanNode('keep_transcripts')->info('Whether the transcripts survive an erasure request, under a legal obligation to keep them.')->defaultFalse()->end()
                ->end()
            ->end();
    }

    private function cmi5(NodeBuilder $children): void
    {
        $children
            ->arrayNode('cmi5')
                ->info('The launches of the cmi5 assignable units.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('actor_home_page')->info("The site's address, home page of the accounts that name the learners in the statements and root of the activities' IRIs; null leaves cmi5 launches refused.")->defaultNull()->end()
                    ->integerNode('session_ttl')->info('How many seconds the token of a session holds.')->min(60)->max(86_400)->defaultValue(Cmi5Settings::SESSION_TTL)->end()
                    ->scalarNode('secret')->info('The secret the tokens are derived from.')->cannotBeEmpty()->defaultValue('%kernel.secret%')->end()
                    ->arrayNode('allowed_origins')->info('The origins, besides the content origin, whose units may call the xAPI endpoint: those of units on absolute URLs.')->scalarPrototype()->end()->end()
                    ->scalarNode('lrs')->info('A LearningRecordStore service of the application that keeps the statements; the store inside the bundle, or the remote store below, when null.')->defaultNull()->end()
                    ->arrayNode('remote_lrs')
                        ->info('A remote xAPI learning record store the statements are sent to, through phil/safe-http; the state documents stay in the store inside the bundle.')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('endpoint')->info('The xAPI endpoint of the store, an https address without a query, such as "https://lrs.example.org/xapi/".')->defaultNull()->end()
                            ->scalarNode('username')->info('The user name of HTTP Basic authentication, the key of the store; none when null.')->defaultNull()->end()
                            ->scalarNode('password')->info('The password of HTTP Basic authentication, the secret of the store, read from an environment variable.')->defaultNull()->end()
                            ->enumNode('version')->info('The xAPI version the store speaks.')->values(HttpLearningRecordStore::VERSIONS)->defaultValue('1.0.3')->end()
                            ->floatNode('timeout')->info('How many seconds the store may stay silent.')->min(0.1)->max(60.0)->defaultValue(5.0)->end()
                            ->floatNode('max_duration')->info('How many seconds one statement may take to send, in all.')->min(0.1)->max(120.0)->defaultValue(10.0)->end()
                        ->end()
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $cmi5): bool => null !== $cmi5['lrs'] && \is_array($cmi5['remote_lrs']) && true === $cmi5['remote_lrs']['enabled'])
                    ->thenInvalid('Name a LearningRecordStore service in "lrs" or enable "remote_lrs", not both.')
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $cmi5): bool => \is_array($cmi5['remote_lrs']) && true === $cmi5['remote_lrs']['enabled'] && !\is_string($cmi5['remote_lrs']['endpoint']))
                    ->thenInvalid('The remote learning record store needs its "endpoint".')
                ->end()
            ->end();
    }
}
