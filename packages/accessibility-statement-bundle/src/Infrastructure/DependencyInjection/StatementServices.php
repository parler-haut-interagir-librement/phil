<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Infrastructure\DependencyInjection;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityStatement\Audit\ReportReader;
use Phil\AccessibilityStatement\Contract\AuditSource;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Application\StatementPublisher;
use Phil\AccessibilityStatementBundle\Application\StatementRegenerator;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Infrastructure\Audit\ReportDirectory;
use Phil\AccessibilityStatementBundle\Infrastructure\Config\ConfiguredStatements;
use Phil\AccessibilityStatementBundle\Infrastructure\Console\RegenerateCommand;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\DbalStatements;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementSchemaListener;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementAdminController;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Phil\AccessibilityStatementBundle\Infrastructure\LegalNotices\StatementFooterLink;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\StatementExtension;
use Phil\LegalNoticesBundle\Application\Contract\FooterLinkProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The services of the configuration. The statement of the configuration is read here, so that a statement that cannot
 * be published fails the container build rather than the first request; an option that needs a package which is not
 * installed fails it too.
 *
 * @phpstan-type Config array{
 *     source: string,
 *     connection: string,
 *     clock: string,
 *     default_locale: string,
 *     wording: string,
 *     layout: string,
 *     cache_max_age: int,
 *     feedback_route: string|null,
 *     feedback_form: string|null,
 *     kit_subdirectory: string|null,
 *     admin: array{enabled: bool, role: string},
 *     statement: array<array-key, mixed>|null,
 *     audit: array{enabled: bool, source: string|null, directory: string, minimum_severity: string, method: string, report_url: string|null},
 * }
 */
final class StatementServices
{
    public const string FOOTER_LINK_TAG = 'phil_legal_notices.footer_link';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $statement = null === $config['statement'] ? null : self::statement($config['statement']);

        if ('doctrine' === $config['source']) {
            if (!class_exists(Connection::class)) {
                throw new InvalidConfigurationException('phil_accessibility_statement.source "doctrine" needs doctrine/dbal and doctrine/orm: composer require doctrine/orm.');
            }

            $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
            $container->register(DbalStatements::class, DbalStatements::class)->setArguments([$connection, $statement, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
            $container->setAlias(StatementRepository::class, DbalStatements::class)->setPublic(true);
            $container->register(StatementSchemaListener::class, StatementSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        } else {
            // Without a statement, none is published yet: the page is not found until one is written.
            $container->register(ConfiguredStatements::class, ConfiguredStatements::class)->setArguments([$statement]);
            $container->setAlias(StatementRepository::class, ConfiguredStatements::class)->setPublic(true);
        }

        $container->register(StatementController::class, StatementController::class)
            ->setArguments([
                new Reference(StatementRepository::class),
                new Reference('twig'),
                $config['layout'],
                $config['wording'],
                $config['feedback_route'],
                $config['cache_max_age'],
                $config['feedback_form'],
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(StatementExtension::class, StatementExtension::class)
            ->setArguments([
                new Reference('router'),
                new Reference('request_stack'),
                $config['default_locale'],
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(KitComponents::class),
            ])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        if (interface_exists(FooterLinkProvider::class)) {
            $container->register(StatementFooterLink::class, StatementFooterLink::class)
                ->setArguments([new Reference(StatementRepository::class), new Reference('router')])
                ->addTag(self::FOOTER_LINK_TAG);
        }

        self::audit($container, $config);
        self::admin($container, $config);
    }

    /**
     * @param Config $config
     *
     * @throws InvalidConfigurationException when Symfony Security's authorization or CSRF protection is not installed
     */
    private static function admin(ContainerBuilder $container, array $config): void
    {
        if (!$config['admin']['enabled']) {
            return;
        }

        if (!interface_exists(AuthorizationCheckerInterface::class) || !interface_exists(CsrfTokenManagerInterface::class)) {
            throw new InvalidConfigurationException('phil_accessibility_statement.admin needs Symfony Security and its CSRF protection: composer require symfony/security-bundle symfony/security-csrf.');
        }

        $container->register(StatementPublisher::class, StatementPublisher::class)
            ->setArguments([
                new Reference(StatementRepository::class),
                new Reference($config['clock']),
                new Reference(StatementRegenerator::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(StatementAdminController::class, StatementAdminController::class)
            ->setArguments([
                new Reference(StatementRepository::class),
                new Reference(StatementPublisher::class),
                new Reference('twig'),
                new Reference('router'),
                $config['layout'],
                $config['admin']['role'],
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('security.csrf.token_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }

    /**
     * The statement of the configuration, checked. A moment YAML read as a timestamp — a date left unquoted — is
     * written back as the moment it is.
     *
     * @param array<array-key, mixed> $statement
     *
     * @return array<array-key, mixed>
     */
    public static function statement(array $statement): array
    {
        if (\is_int($statement['published_at'] ?? null)) {
            $statement['published_at'] = self::moment($statement['published_at']);
        }

        if (\is_array($statement['evaluation'] ?? null) && \is_int($statement['evaluation']['evaluated_at'] ?? null)) {
            $statement['evaluation']['evaluated_at'] = self::moment($statement['evaluation']['evaluated_at']);
        }

        if (\is_array($statement['non_conformities'] ?? null)) {
            $statement['non_conformities'] = array_map(static function (mixed $nonConformity): mixed {
                if (\is_array($nonConformity) && \is_int($nonConformity['fix_by'] ?? null)) {
                    $nonConformity['fix_by'] = self::moment($nonConformity['fix_by']);
                }

                return $nonConformity;
            }, $statement['non_conformities']);
        }

        try {
            StatementReader::fromArray($statement);
        } catch (InvalidStatement $invalidStatement) {
            throw new InvalidConfigurationException('phil_accessibility_statement.statement: '.$invalidStatement->getMessage(), $invalidStatement->getCode(), previous: $invalidStatement);
        }

        return $statement;
    }

    /**
     * @param Config $config
     */
    private static function audit(ContainerBuilder $container, array $config): void
    {
        $audit = $config['audit'];

        if (!$audit['enabled']) {
            return;
        }

        if (null === $audit['source']) {
            $container->register(ReportDirectory::class, ReportDirectory::class)->setArguments([
                $audit['directory'],
                new Definition(ReportReader::class),
                EvaluationMethod::from($audit['method']),
                $audit['report_url'],
            ]);
        }

        $container->setAlias(AuditSource::class, $audit['source'] ?? ReportDirectory::class);
        $container->register(StatementRegenerator::class, StatementRegenerator::class)
            ->setArguments([
                new Reference(StatementRepository::class),
                new Reference(AuditSource::class),
                new Definition(StatementGenerator::class, [Severity::from($audit['minimum_severity'])]),
                new Reference($config['clock']),
            ])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(RegenerateCommand::class, RegenerateCommand::class)
                ->setArguments([new Reference(StatementRegenerator::class), new Reference(StatementRepository::class)])
                ->addTag('console.command');
        }
    }

    private static function moment(int $timestamp): string
    {
        return new DateTimeImmutable('@'.$timestamp)->format(DateTimeInterface::ATOM);
    }
}
