<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle;

use LogicException;
use Override;
use Phil\ContactFormBundle\Application\ConfiguredRecipients;
use Phil\ContactFormBundle\Application\Recipients;
use Phil\ContactFormBundle\Infrastructure\Form\ContactFormType;
use Phil\ContactFormBundle\Infrastructure\Http\ContactFormController;
use Phil\ContactFormBundle\Infrastructure\Mailer\ContactMailer;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A "write to us" form: the form type, the mailer, and the controller of the contact page, whose route
 * `config/routes.php` declares.
 *
 * The form is protected by `phil/spam-protection-bundle`: register `PhilSpamProtectionBundle` too.
 */
final class PhilContactFormBundle extends AbstractBundle
{
    public const string TEMPLATE = '@PhilContactForm/contact.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_contact_form';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $email = static fn (mixed $address): bool => !\is_string($address) || false === filter_var($address, \FILTER_VALIDATE_EMAIL);

        $definition->rootNode()
            ->children()
                ->scalarNode('sender')
                    ->info('The address messages are sent from, one of the site\'s own domain: "contact@example.com".')
                    ->isRequired()
                    ->validate()->ifTrue($email)->thenInvalid('The sender is an email address: %s.')->end()
                ->end()
                ->arrayNode('recipients')
                    ->info('The addresses messages are sent to.')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->scalarPrototype()
                        ->validate()->ifTrue($email)->thenInvalid('A recipient is an email address: %s.')->end()
                    ->end()
                ->end()
                ->scalarNode('subject_prefix')
                    ->info('What the subject of every message starts with: "[Contact] ".')
                    ->defaultValue('')
                ->end()
                ->scalarNode('template')
                    ->info('The template of the contact page, given form, sent and failed.')
                    ->defaultValue(self::TEMPLATE)
                    ->cannotBeEmpty()
                ->end()
            ->end();
    }

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_spam_protection')) {
            throw new LogicException('PhilContactFormBundle protects its form with PhilSpamProtectionBundle, from phil/spam-protection-bundle: register it too.');
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(ConfiguredRecipients::class)->args([$config['recipients']]);
        $services->alias(Recipients::class, ConfiguredRecipients::class);
        $services->set(ContactFormType::class)->tag('form.type');
        $services->set(ContactMailer::class)->args([new Reference('mailer'), new Reference(Recipients::class), $config['sender'], $config['subject_prefix']]);
        $services->set(ContactFormController::class)
            ->args([new Reference('form.factory'), new Reference(ContactMailer::class), new Reference('twig'), new Reference('logger'), $config['template']])
            ->tag('controller.service_arguments')
            ->public();
    }
}
