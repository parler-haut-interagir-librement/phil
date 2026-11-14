<?php

declare(strict_types=1);

namespace Phil\FormsBundle;

use LogicException;
use Override;
use Phil\FormsBundle\Application\CurrentSubject;
use Phil\FormsBundle\Infrastructure\DependencyInjection\FieldSealerPass;
use Phil\FormsBundle\Infrastructure\DependencyInjection\FormsServices;
use Phil\FormsBundle\Infrastructure\Security\TokenCurrentSubject;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Forms defined in the database: a page for each, protected against spam by `phil/spam-protection-bundle`, their
 * entries kept in Doctrine no longer than each form says, and sent by email, to a webhook signed in Standard
 * Webhooks, or to the application's own `EntryNotifier`s.
 */
final class PhilFormsBundle extends AbstractBundle
{
    public const string NOTIFIER_TAG = FormsServices::NOTIFIER_TAG;

    /** The HTTP client of webhooks: the application's, refusing hosts on the server's own network. */
    public const string WEBHOOK_CLIENT = FormsServices::WEBHOOK_CLIENT;

    #[Override]
    protected string $extensionAlias = 'phil_forms';

    /**
     * The person sending a form is the one signed in through Symfony Security, unless the application aliases
     * `CurrentSubject` to its own service; sensitive values are sealed through Symfony's KeyManagement component when
     * `sealing.key` names a master key, and a form with a sensitive field is refused without a sealer unless
     * `allow_unsealed_sensitive` is true.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasAlias(CurrentSubject::class) || $container->hasDefinition(CurrentSubject::class) || !$container->hasDefinition('security.token_storage') || !interface_exists(TokenStorageInterface::class)) {
                    return;
                }

                $container->register(TokenCurrentSubject::class, TokenCurrentSubject::class)->setArguments([new Reference('security.token_storage')]);
                $container->setAlias(CurrentSubject::class, TokenCurrentSubject::class);
            }
        });
        $container->addCompilerPass(new FieldSealerPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('sender')
                    ->info("The address entries are emailed from: the site's own. Null sends no email.")
                    ->defaultNull()
                ->end()
                ->scalarNode('subject_prefix')
                    ->info('Put before the form\'s title in the subject: "[Site] ".')
                    ->defaultValue('')
                ->end()
                ->scalarNode('webhook_secret')
                    ->info('The Standard Webhooks secret, "whsec_…", entries are signed with. Null posts to no webhook.')
                    ->defaultNull()
                ->end()
                ->scalarNode('template')
                    ->info('The page of a form.')
                    ->defaultValue('@PhilForms/form.html.twig')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('digest_key')
                    ->info('The key of the digests of the addresses that name the person an entry is about. Changing it loses the entries already kept to access and erasure requests by address.')
                    ->defaultValue('%kernel.secret%')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('allow_unsealed_sensitive')
                    ->info('Keeps the values of sensitive fields as sent when no sealer is configured, instead of refusing a form with a sensitive field. A conscious choice: those values then lie in the database in the clear.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('sealing')
                    ->info("How the values of sensitive fields are sealed, through Symfony's KeyManagement component.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('key')
                            ->info('The master key of the KMS that seals the values of sensitive fields; null configures no sealer, unless the application aliases FieldSealer to its own service: a form with a sensitive field is then refused, unless allow_unsealed_sensitive is true.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $key): bool => '' === $key)
                                ->thenInvalid('A master key is named by a non-empty text; null configures no sealer.')
                            ->end()
                        ->end()
                        ->scalarNode('kms')
                            ->info('The service of a client of the KeyManagement component, such as "key_management.<client>"; null for its default client.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $kms): bool => '' === $kms)
                                ->thenInvalid('A client is named by its service; null takes the default client.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension; the
     * entities are mapped then too.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_spam_protection')) {
            throw new LogicException('PhilFormsBundle protects its forms with PhilSpamProtectionBundle, from phil/spam-protection-bundle: register it too.');
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilFormsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\FormsBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{sender: ?string, subject_prefix: string, webhook_secret: ?string, template: string, digest_key: string, allow_unsealed_sensitive: bool, sealing: array{key: ?string, kms: ?string}} $config */
        FormsServices::register($builder, $config);
    }
}
