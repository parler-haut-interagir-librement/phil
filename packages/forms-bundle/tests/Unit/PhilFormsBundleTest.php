<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit;

use LogicException;
use Phil\FormsBundle\Application\CurrentSubject;
use Phil\FormsBundle\Application\EntryNotifier;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\Retention;
use Phil\FormsBundle\Application\SealedValues;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Domain\Contract\ChoicePlaces;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Infrastructure\Console\PurgeEntriesCommand;
use Phil\FormsBundle\Infrastructure\DependencyInjection\FieldSealerPass;
use Phil\FormsBundle\Infrastructure\DependencyInjection\FormsServices;
use Phil\FormsBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Infrastructure\Form\EntryType;
use Phil\FormsBundle\Infrastructure\Http\FormController;
use Phil\FormsBundle\Infrastructure\KeyManagement\KeyManagementFieldSealer;
use Phil\FormsBundle\Infrastructure\Mailer\EmailNotifier;
use Phil\FormsBundle\Infrastructure\Privacy\FormEntriesPrivacy;
use Phil\FormsBundle\Infrastructure\Security\TokenCurrentSubject;
use Phil\FormsBundle\Infrastructure\Webhook\WebhookNotifier;
use Phil\FormsBundle\PhilFormsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

#[CoversClass(PhilFormsBundle::class)]
#[CoversClass(FormsServices::class)]
#[CoversClass(FieldSealerPass::class)]
final class PhilFormsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([['sender' => 'site@example.com', 'subject_prefix' => '[Site] ', 'webhook_secret' => 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', 'template' => 'forms/page.html.twig']]);
        $clock = new Reference('clock');
        $repository = new Reference(FormRepository::class);

        self::assertSame('phil_forms', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineFormRepository::class, BinaryKeysListener::class, BasicConditions::class, HmacEmailDigester::class, SealedValues::class, Submissions::class, Retention::class, EntryType::class, FormController::class, EmailNotifier::class, PhilFormsBundle::WEBHOOK_CLIENT, WebhookNotifier::class, FormEntriesPrivacy::class, PurgeEntriesCommand::class], array_keys($container->getDefinitions()));
        self::assertSame(['doctrine.event_listener' => [['event' => 'loadClassMetadata']]], $container->getDefinition(BinaryKeysListener::class)->getTags());
        self::assertSame(['%kernel.secret%'], $container->getDefinition(HmacEmailDigester::class)->getArguments());
        $digester = new Reference(HmacEmailDigester::class);
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineFormRepository::class)->getArguments());
        self::assertSame(DoctrineFormRepository::class, (string) $container->getAlias(FormRepository::class));
        self::assertTrue($container->getAlias(FormRepository::class)->isPublic());
        self::assertSame([DoctrineFormRepository::class, true], [(string) $container->getAlias(ChoicePlaces::class), $container->getAlias(ChoicePlaces::class)->isPublic()]);
        self::assertSame([BasicConditions::class, false], [(string) $container->getAlias(ConditionEvaluator::class), $container->getAlias(ConditionEvaluator::class)->isPublic()]);
        $logic = new Reference(ConditionEvaluator::class);
        $sealer = new Reference(FieldSealer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals([$sealer], $container->getDefinition(SealedValues::class)->getArguments());
        self::assertTrue($container->getDefinition(SealedValues::class)->isPublic());
        self::assertEquals([$repository, $clock, $digester, new TaggedIteratorArgument('phil_forms.notifier'), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $logic, $sealer, new Reference(ChoicePlaces::class), false], $container->getDefinition(Submissions::class)->getArguments());
        self::assertTrue($container->getDefinition(Submissions::class)->isPublic());
        self::assertEquals([$repository, $clock], $container->getDefinition(Retention::class)->getArguments());
        self::assertTrue($container->getDefinition(EntryType::class)->hasTag('form.type'));
        self::assertEquals([$sealer, false, true, $logic], $container->getDefinition(EntryType::class)->getArguments(), 'phil/form-logic installed, the conditions apply in the browser too');
        $controller = $container->getDefinition(FormController::class);
        self::assertEquals([$repository, new Reference(Submissions::class), new Reference('form.factory'), new Reference('twig'), 'forms/page.html.twig', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(CurrentSubject::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $controller->getArguments());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->isPublic());
        self::assertEquals([new Reference('mailer'), 'site@example.com', '[Site] ', $logic], $container->getDefinition(EmailNotifier::class)->getArguments());
        self::assertTrue($container->getDefinition(EmailNotifier::class)->hasTag('phil_forms.notifier'));
        self::assertEquals([new Reference('phil_forms.webhook_client'), 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', $clock], $container->getDefinition(WebhookNotifier::class)->getArguments());
        self::assertSame(NoPrivateNetworkHttpClient::class, $container->getDefinition(PhilFormsBundle::WEBHOOK_CLIENT)->getClass());
        self::assertEquals([new Reference('http_client')], $container->getDefinition(PhilFormsBundle::WEBHOOK_CLIENT)->getArguments());
        self::assertTrue($container->getDefinition(WebhookNotifier::class)->hasTag('phil_forms.notifier'));
        $privacy = $container->getDefinition(FormEntriesPrivacy::class);
        self::assertEquals([$repository, $digester, new Reference(SealedValues::class)], $privacy->getArguments());
        self::assertSame([true, true], [$privacy->hasTag('phil_privacy.exporter'), $privacy->hasTag('phil_privacy.eraser')]);
        self::assertEquals([new Reference(Retention::class)], $container->getDefinition(PurgeEntriesCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PurgeEntriesCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getAutoconfiguredInstanceof()[EntryNotifier::class]->hasTag(PhilFormsBundle::NOTIFIER_TAG));
        self::assertSame(['phil_forms.notifier', 'phil_forms.webhook_client'], [PhilFormsBundle::NOTIFIER_TAG, PhilFormsBundle::WEBHOOK_CLIENT]);
        self::assertSame([false, false], [$container->getDefinition(BasicConditions::class)->isPublic(), $container->getDefinition(HmacEmailDigester::class)->isPublic()]);
    }

    public function testSensitiveValuesAreSealedThroughKeyManagementWhenAMasterKeyIsNamed(): void
    {
        $default = $this->compiled(static function (ContainerBuilder $container): void {
            $container->setParameter(FormsServices::SEALING_KEY, 'k2026');
            $container->setParameter(FormsServices::SEALING_KMS, null);
            $container->register('key_management.local', SodiumKms::class);
            $container->setAlias(DataKeyGeneratorInterface::class, 'key_management.local');
        });
        self::assertSame(KeyManagementFieldSealer::class, (string) $default->getAlias(FieldSealer::class));
        self::assertEquals([new Reference(DataKeyGeneratorInterface::class), 'k2026'], $default->getDefinition(KeyManagementFieldSealer::class)->getArguments());

        $named = $this->compiled(static function (ContainerBuilder $container): void {
            $container->setParameter(FormsServices::SEALING_KEY, 'forms');
            $container->setParameter(FormsServices::SEALING_KMS, 'key_management.vault');
            $container->register('key_management.vault', SodiumKms::class);
        });
        self::assertEquals([new Reference('key_management.vault'), 'forms'], $named->getDefinition(KeyManagementFieldSealer::class)->getArguments());

        $own = $this->compiled(static function (ContainerBuilder $container): void {
            $container->setParameter(FormsServices::SEALING_KEY, 'k2026');
            $container->setParameter(FormsServices::SEALING_KMS, 'key_management.missing');
            $container->register('app.sealer', KeyManagementFieldSealer::class);
            $container->setAlias(FieldSealer::class, 'app.sealer');
        });
        self::assertSame('app.sealer', (string) $own->getAlias(FieldSealer::class));
        self::assertFalse($own->hasDefinition(KeyManagementFieldSealer::class));

        $none = $this->compiled(static function (ContainerBuilder $container): void {
            $container->setParameter(FormsServices::SEALING_KEY, null);
            $container->setParameter(FormsServices::SEALING_KMS, 'key_management.missing');
        });
        self::assertFalse($none->has(FieldSealer::class));

        $unconfigured = $this->compiled(static function (ContainerBuilder $container): void {
        });
        self::assertFalse($unconfigured->has(FieldSealer::class));
        self::assertFalse($unconfigured->hasDefinition(KeyManagementFieldSealer::class));

        $loaded = $this->load([['sealing' => ['key' => 'k2026', 'kms' => 'key_management.vault']]]);
        self::assertSame(['k2026', 'key_management.vault'], [$loaded->getParameter(FormsServices::SEALING_KEY), $loaded->getParameter(FormsServices::SEALING_KMS)]);
        self::assertSame([null, null], [$this->load([])->getParameter(FormsServices::SEALING_KEY), $this->load([])->getParameter(FormsServices::SEALING_KMS)]);
    }

    public function testAMasterKeyWithoutItsClientStopsTheContainer(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(FormsServices::SEALING_KEY, 'k2026');
        $container->setParameter(FormsServices::SEALING_KMS, null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"phil_forms.sealing.key" seals the values of sensitive fields through the KMS "Symfony\\Component\\KeyManagement\\DataKeyGeneratorInterface", which is not defined: register Symfony\\Component\\KeyManagement\\KeyManagementBundle and configure "key_management.clients", or set "phil_forms.sealing.kms" to the service of a client.');

        new FieldSealerPass()->process($container);
    }

    public function testAMasterKeyWithoutTheComponentStopsTheContainer(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(FormsServices::SEALING_KEY, 'k2026');
        $container->setParameter(FormsServices::SEALING_KMS, null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"phil_forms.sealing.key" seals the values of sensitive fields through Symfony\'s KeyManagement component, which is not installed: run "composer require symfony/key-management", or remove the option and set "phil_forms.allow_unsealed_sensitive: true" to keep the values as sent.');

        new FieldSealerPass(false)->process($container);
    }

    public function testTheApplicationMayChooseToKeepSensitiveValuesAsSent(): void
    {
        $allowed = $this->load([['allow_unsealed_sensitive' => true]]);

        self::assertTrue($allowed->getDefinition(Submissions::class)->getArgument(8));
        self::assertTrue($allowed->getDefinition(EntryType::class)->getArgument(1));
        self::assertFalse($this->load([])->getDefinition(Submissions::class)->getArgument(8));
        self::assertFalse($this->load([])->getDefinition(EntryType::class)->getArgument(1));
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['allow_unsealed_sensitive' => 'yes']]);
    }

    public function testAnEmptySealingOptionIsRefused(): void
    {
        foreach ([['key' => ''], ['key' => 'k2026', 'kms' => '']] as $sealing) {
            try {
                $this->load([['sealing' => $sealing]]);
                self::fail(json_encode($sealing, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_forms.sealing', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testANullSealingOptionIsTheDefault(): void
    {
        $container = $this->load([['sealing' => ['key' => null, 'kms' => null]]]);

        self::assertFalse($container->hasAlias(FieldSealer::class));
        self::assertFalse($container->hasDefinition(KeyManagementFieldSealer::class));
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(EmailNotifier::class));
        self::assertFalse($container->hasDefinition(WebhookNotifier::class));
        self::assertSame('@PhilForms/form.html.twig', $container->getDefinition(FormController::class)->getArgument(4));
        self::assertSame('', $this->load([['sender' => 'site@example.com']])->getDefinition(EmailNotifier::class)->getArgument(2));
        self::assertSame(['s3cret'], $this->load([['digest_key' => 's3cret']])->getDefinition(HmacEmailDigester::class)->getArguments());
        $this->expectExceptionMessageIsOrContains('The path "phil_forms.template" cannot contain an empty value, but got "".');

        $this->load([['template' => '']]);
    }

    public function testTheSignedInUserIsTheSubjectUnlessTheApplicationNamesAnother(): void
    {
        $withSecurity = $this->compiled(static function (ContainerBuilder $container): void {
            $container->register('security.token_storage', TokenStorage::class);
        });
        self::assertSame(TokenCurrentSubject::class, (string) $withSecurity->getAlias(CurrentSubject::class));
        self::assertEquals([new Reference('security.token_storage')], $withSecurity->getDefinition(TokenCurrentSubject::class)->getArguments());

        $own = $this->compiled(static function (ContainerBuilder $container): void {
            $container->register('security.token_storage', TokenStorage::class);
            $container->register('app.subject', TokenCurrentSubject::class);
            $container->setAlias(CurrentSubject::class, 'app.subject');
        });
        self::assertSame('app.subject', (string) $own->getAlias(CurrentSubject::class));
        self::assertFalse($own->hasDefinition(TokenCurrentSubject::class));

        $ownDefinition = $this->compiled(static function (ContainerBuilder $container): void {
            $container->register('security.token_storage', TokenStorage::class);
            $container->register(CurrentSubject::class, TokenCurrentSubject::class);
        });
        self::assertFalse($ownDefinition->hasAlias(CurrentSubject::class));

        $anonymous = $this->compiled(static function (ContainerBuilder $container): void {
        });
        self::assertFalse($anonymous->hasAlias(CurrentSubject::class));
        self::assertFalse($anonymous->hasDefinition(TokenCurrentSubject::class));
    }

    /**
     * The bundle's compiler passes run over a container the callback prepares, before optimization.
     *
     * @param callable(ContainerBuilder): void $prepare
     */
    private function compiled(callable $prepare): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $prepare($container);
        new PhilFormsBundle()->build($container);

        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            $pass->process($container);
        }

        return $container;
    }

    public function testSpamProtectionIsRequiredAndTheMappingPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $with = $this->builder('phil_spam_protection', 'doctrine');
        $extension->prepend($with);
        self::assertSame([['orm' => ['mappings' => ['PhilFormsBundle' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => \dirname(__DIR__, 2).'/src/Domain/Model', 'prefix' => 'Phil\FormsBundle\Domain\Model']]]]], $with->getExtensionConfig('doctrine'));

        $withoutDoctrine = $this->builder('phil_spam_protection');
        $extension->prepend($withoutDoctrine);
        self::assertSame([], $withoutDoctrine->getExtensionConfig('doctrine'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('PhilFormsBundle protects its forms with PhilSpamProtectionBundle, from phil/spam-protection-bundle: register it too.');
        $extension->prepend($this->builder('doctrine'));
    }

    private function builder(string ...$aliases): ContainerBuilder
    {
        $builder = new ContainerBuilder();

        foreach ($aliases as $alias) {
            $builder->registerExtension(new class($alias) extends Extension {
                public function __construct(
                    private readonly string $alias,
                ) {
                }

                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        return $builder;
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
        $extension = new PhilFormsBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
