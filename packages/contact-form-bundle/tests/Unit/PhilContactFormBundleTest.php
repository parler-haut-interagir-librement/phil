<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit;

use LogicException;
use Phil\ContactFormBundle\Application\ConfiguredRecipients;
use Phil\ContactFormBundle\Application\Recipients;
use Phil\ContactFormBundle\Infrastructure\Form\ContactFormType;
use Phil\ContactFormBundle\Infrastructure\Http\ContactFormController;
use Phil\ContactFormBundle\Infrastructure\Mailer\ContactMailer;
use Phil\ContactFormBundle\PhilContactFormBundle;
use Phil\SpamProtectionBundle\PhilSpamProtectionBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilContactFormBundle::class)]
final class PhilContactFormBundleTest extends TestCase
{
    private const array CONFIG = ['sender' => 'contact@example.com', 'recipients' => ['info@example.com']];

    public function testTheExtensionAliasIsPhilContactForm(): void
    {
        self::assertSame('phil_contact_form', $this->extension()->getAlias());
        self::assertSame('@PhilContactForm/contact.html.twig', PhilContactFormBundle::TEMPLATE);
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([self::CONFIG]);

        self::assertSame(['service_container', ConfiguredRecipients::class, ContactFormType::class, ContactMailer::class, ContactFormController::class], array_keys($container->getDefinitions()));
        self::assertSame(ConfiguredRecipients::class, (string) $container->getAlias(Recipients::class));
        self::assertSame([['info@example.com']], $container->getDefinition(ConfiguredRecipients::class)->getArguments());
        self::assertTrue($container->getDefinition(ContactFormType::class)->hasTag('form.type'));

        $mailer = $container->getDefinition(ContactMailer::class)->getArguments();
        self::assertEquals([new Reference('mailer'), new Reference(Recipients::class), 'contact@example.com', ''], $mailer);

        $controller = $container->getDefinition(ContactFormController::class);
        self::assertEquals([new Reference('form.factory'), new Reference(ContactMailer::class), new Reference('twig'), new Reference('logger'), PhilContactFormBundle::TEMPLATE], $controller->getArguments());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());
    }

    public function testThePrefixAndTheTemplateAreTaken(): void
    {
        $container = $this->load([[...self::CONFIG, 'subject_prefix' => '[Contact] ', 'template' => 'contact.html.twig']]);

        self::assertSame('[Contact] ', $container->getDefinition(ContactMailer::class)->getArgument(3));
        self::assertSame('contact.html.twig', $container->getDefinition(ContactFormController::class)->getArgument(4));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigs(): iterable
    {
        yield 'no sender' => [['recipients' => ['info@example.com']], 'The child config "sender" under "phil_contact_form" must be configured'];
        yield 'a sender that is no address' => [['sender' => 'contact', 'recipients' => ['info@example.com']], 'The sender is an email address: "contact".'];
        yield 'a sender that is no text' => [['sender' => 12, 'recipients' => ['info@example.com']], 'The sender is an email address: 12.'];
        yield 'no recipients' => [['sender' => 'contact@example.com'], 'The child config "recipients" under "phil_contact_form" must be configured'];
        yield 'empty recipients' => [['sender' => 'contact@example.com', 'recipients' => []], 'should have at least 1 element'];
        yield 'a recipient that is no address' => [['sender' => 'contact@example.com', 'recipients' => ['info']], 'A recipient is an email address: "info".'];
        yield 'an empty template' => [[...self::CONFIG, 'template' => ''], 'cannot contain an empty value'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigs')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load([$config]);
    }

    public function testTheSpamProtectionBundleIsRequired(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new PhilSpamProtectionBundle()->getContainerExtension() ?? throw new LogicException());

        $extension = $this->extension();
        $container->registerExtension($extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('PhilContactFormBundle protects its form with PhilSpamProtectionBundle, from phil/spam-protection-bundle: register it too.');

        $extension->prepend(new ContainerBuilder());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilContactFormBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
