<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\DependencyInjection;

use LogicException;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Infrastructure\Saml\ProvisionedSamlAccounts;
use Phil\SamlSpBundle\Application\Contract\SamlAccounts;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * `phil_identity_client.saml`: the accounts of `phil/saml-sp-bundle` kept by the {@see UserProvisioner}.
 *
 * @phpstan-import-type Config from ClientConfiguration
 */
final class SamlAccountsComposer
{
    public const string SERVICE = 'phil_identity_client.saml_accounts';

    public const string SAML_EXTENSION = 'phil_saml_sp';

    /**
     * Names the service in `phil_saml_sp.accounts` when the application configures `phil_saml_sp`; an `accounts` the
     * application sets itself is read after it and has the last word.
     *
     * @param Config $config
     */
    public static function prepend(ContainerBuilder $container, array $config): void
    {
        if (!$config['saml']['enabled'] || !$container->hasExtension(self::SAML_EXTENSION) || [] === $container->getExtensionConfig(self::SAML_EXTENSION)) {
            return;
        }

        $container->prependExtensionConfig(self::SAML_EXTENSION, ['accounts' => self::SERVICE]);
    }

    /**
     * @param Config $config
     */
    public static function register(ServicesConfigurator $services, array $config): void
    {
        if (!$config['saml']['enabled']) {
            return;
        }

        if (!interface_exists(SamlAccounts::class)) {
            throw new LogicException('phil_identity_client.saml needs phil/saml-sp-bundle: run "composer require phil/saml-sp-bundle".');
        }

        $services->set(self::SERVICE, ProvisionedSamlAccounts::class)
            ->args([service(UserProvisioner::class), $config['claims'], $config['saml']['verified_email_from']]);
    }
}
