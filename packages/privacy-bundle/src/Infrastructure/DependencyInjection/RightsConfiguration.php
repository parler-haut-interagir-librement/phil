<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * The configuration of what the bundle does on its own: the retention schedule, the account deletions, the consent to
 * cookies and their inventory, the requests of people without an account, the register of incidents, the rights of a
 * member and the pages.
 */
final class RightsConfiguration
{
    private function __construct()
    {
    }

    public static function retention(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('retention')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('frequency')->info('How often the schedule phil_privacy applies the retention schedule, such as "1 day".')->defaultValue('1 day')->cannotBeEmpty()->end()
                ->arrayNode('rules')
                    ->info('The retention schedule, by rule identifier; leave it empty when retention_schedule_source names one.')
                    ->useAttributeAsKey('id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('store')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('categories')->scalarPrototype()->end()->defaultValue([])->end()
                            ->variableNode('period')->info('An ISO 8601 duration such as P90D, "indefinite", or { until_event: … } or { not_yet_fixed: … }.')->isRequired()->end()
                            ->enumNode('mechanism')->values(['job', 'ttl', 'rotation', 'event', 'manual'])->defaultValue('job')->end()
                            ->enumNode('erasure')->values(['deleted', 'pseudonymised', 'redacted', 'awaits_expiry', 'survives'])->defaultValue('deleted')->end()
                            ->enumNode('access')->values(['returned', 'as_category', 'not_returned'])->defaultValue('returned')->end()
                            ->scalarNode('purpose')->info('Why the store holds the records, for the register of processing.')->defaultNull()->end()
                            ->arrayNode('recipients')->info('Who receives the records: a team, a partner, an authority.')->scalarPrototype()->end()->defaultValue([])->end()
                            ->arrayNode('processors')
                                ->info('The service providers that handle the records, each with where it keeps them.')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('location')->isRequired()->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                                ->defaultValue([])
                            ->end()
                            ->scalarNode('location')->info('Where the store keeps the records: a country, a region, a data centre.')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    public static function accountDeletion(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('account_deletion')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('delay')->info('ISO 8601 duration during which a request can be cancelled.')->defaultValue('P30D')->cannotBeEmpty()->end()
                ->scalarNode('frequency')->info('How often the schedule phil_privacy looks for requests whose delay is over.')->defaultValue('15 minutes')->cannotBeEmpty()->end()
                ->scalarNode('store')->info('Service id of an AccountDeletionStore; null, the table of the bundle.')->defaultNull()->end()
                ->scalarNode('export_archive')->info('Service id of an ExportArchive; null, encrypted files in export_directory.')->defaultNull()->end()
                ->scalarNode('export_directory')->info('The private directory of the exports written before an erasure.')->defaultValue('%kernel.project_dir%/var/privacy-exports')->cannotBeEmpty()->end()
                ->scalarNode('export_retention')->info('ISO 8601 duration an export is kept for the person to fetch it.')->defaultValue('P7D')->cannotBeEmpty()->end()
            ->end();
    }

    public static function consent(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('consent')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('version')->info('The version of the text the person reads: change it and everyone is asked again.')->defaultValue('1')->cannotBeEmpty()->end()
                ->scalarNode('cookie_name')->info('The name of the cookie that keeps the choice.')->defaultValue('phil_privacy_consent')->cannotBeEmpty()->end()
                ->scalarNode('lifetime')->info('ISO 8601 duration a choice is kept.')->defaultValue('P6M')->cannotBeEmpty()->end()
                ->scalarNode('proofs')->info('Service id of a ConsentProofs; null, the table of the bundle, or the log without a database.')->defaultNull()->end()
                ->arrayNode('categories')
                    ->info('The categories asked about, by name; those that are not necessary are refused until the person says yes.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('necessary')->defaultFalse()->end()
                            ->arrayNode('labels')->useAttributeAsKey('locale')->normalizeKeys(false)->scalarPrototype()->end()->defaultValue([])->end()
                            ->arrayNode('descriptions')->useAttributeAsKey('locale')->normalizeKeys(false)->scalarPrototype()->end()->defaultValue([])->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    public static function cookies(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('cookies')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('inventory')
                    ->info('The YAML file of the cookie inventory: each consent category to its cookies, with their purpose and duration; null, config/privacy/cookies.yaml of the project. A file that does not exist declares none.')
                    ->defaultNull()
                ->end()
                ->booleanNode('watch')->info('Logs a warning for a response that sets an undeclared cookie; null, in debug only.')->defaultNull()->end()
            ->end();
    }

    public static function guestRequests(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('guest_requests')
            ->info('The access and erasure requests of people without an account, through a link sent to their address.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('link_lifetime')->info('ISO 8601 duration a link is valid.')->defaultValue('PT1H')->cannotBeEmpty()->end()
                ->scalarNode('from')->info('The sender of the link, such as "privacy@example.com"; required by the mailer sender.')->defaultNull()->end()
                ->scalarNode('sender')->info("Service id of a GuestLinkSender; null, the mailer's transports.")->defaultNull()->end()
                ->scalarNode('subjects')->info('Service id of a GuestSubjects; null, the verified address is the data subject.')->defaultNull()->end()
                ->scalarNode('store')->info('Service id of a GuestRequestStore; null, the table of the bundle.')->defaultNull()->end()
                ->scalarNode('cache')->info('Service id of the cache pool the limits are counted in.')->defaultValue('cache.app')->cannotBeEmpty()->end()
                ->append(self::limit('per_client', 5, '1 hour'))
                ->append(self::limit('per_address', 3, '1 day'))
            ->end();
    }

    public static function incidents(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('incidents')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('store')->info('Service id of an IncidentRegister; null, the table of the bundle.')->defaultNull()->end()
            ->end();
    }

    public static function emailTracking(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('email_tracking')
            ->info("Each member's consent to tracked emails: whether their opens may be counted and their clicks followed, asked on their preferences page. Off, the page does not ask and no one is tracked.")
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('version')->info('The version of the text the member reads: change it and every member is asked again, nothing tracked meanwhile.')->defaultValue('1')->cannotBeEmpty()->end()
                ->scalarNode('store')->info('Service id of an EmailTrackingStore; null, the table of the bundle.')->defaultNull()->end()
            ->end();
    }

    public static function rights(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('rights')
            ->info('DANGER: the export and the erasure of a member are on by default, and the law of most places requires both. Turning one off takes away a right of every member: write the full sentence the build error shows under its "disable" key, and every build and console command then warns about it.')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('export')->addDefaultsIfNotSet()->children()
                    ->scalarNode('disable')->info('DANGER: null keeps the export on. Any other value must be the full sentence, and turns the export of every member off.')->defaultNull()->end()
                ->end()->end()
                ->arrayNode('erasure')->addDefaultsIfNotSet()->children()
                    ->scalarNode('disable')->info('DANGER: null keeps the erasure on. Any other value must be the full sentence, and turns the erasure of every member off.')->defaultNull()->end()
                ->end()->end()
            ->end();
    }

    private static function limit(string $name, int $limit, string $interval): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition($name)
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('limit')->defaultValue($limit)->min(1)->end()
                ->scalarNode('interval')->info('A relative time, such as "1 hour".')->defaultValue($interval)->cannotBeEmpty()->end()
            ->end();
    }

    public static function pages(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('pages')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('auto_mount')->info('Mounts the pages under the prefix; false, the application imports @PhilPrivacyBundle/config/routes.php.')->defaultTrue()->end()
                ->scalarNode('prefix')->info('The path of the pages.')->defaultValue('/privacy-choices')->cannotBeEmpty()->end()
                ->scalarNode('layout')->info('The Twig template the pages extend.')->defaultValue('@PhilPrivacy/layout.html.twig')->cannotBeEmpty()->end()
                ->scalarNode('current_subject')->info('Service id of a CurrentDataSubject; null, the signed-in user, through DataSubjectUser.')->defaultNull()->end()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
            ->end();
    }
}
