<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\DependencyInjection;

use Phil\FormLogic\Logic\FormLogic;
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
use Phil\FormsBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Infrastructure\Form\EntryType;
use Phil\FormsBundle\Infrastructure\Http\FormController;
use Phil\FormsBundle\Infrastructure\Mailer\EmailNotifier;
use Phil\FormsBundle\Infrastructure\Privacy\FormEntriesPrivacy;
use Phil\FormsBundle\Infrastructure\Webhook\WebhookNotifier;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

/**
 * The services of the configuration: the store, which is also the count of places, the conditions — the bundle's
 * `BasicConditions` unless the application aliases `ConditionEvaluator` to its own —, the digests, the submissions,
 * the page — its conditions applied in the browser too when `phil/form-logic` is installed —, the notifiers that are
 * configured, the privacy module when `phil/privacy-bundle` is installed, and the command when the console is. The
 * sealer of sensitive values is wired by `FieldSealerPass`, from the parameters of `sealing`.
 *
 * @phpstan-type Config array{sender: ?string, subject_prefix: string, webhook_secret: ?string, template: string, digest_key: string, allow_unsealed_sensitive: bool, sealing: array{key: ?string, kms: ?string}}
 */
final class FormsServices
{
    public const string NOTIFIER_TAG = 'phil_forms.notifier';

    public const string WEBHOOK_CLIENT = 'phil_forms.webhook_client';

    /** Symfony Mailer's transports, which send at once, never through a Messenger queue. */
    public const string MAILER_TRANSPORTS = 'mailer.transports';

    /** The master key that seals sensitive values, or null; read by `FieldSealerPass`. */
    public const string SEALING_KEY = 'phil_forms.sealing.key';

    /** The service of the KMS that holds it, or null for the default client; read by `FieldSealerPass`. */
    public const string SEALING_KMS = 'phil_forms.sealing.kms';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $clock = new Reference('clock');
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $sealer = new Reference(FieldSealer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $container->registerForAutoconfiguration(EntryNotifier::class)->addTag(self::NOTIFIER_TAG);
        $container->setParameter(self::SEALING_KEY, $config['sealing']['key']);
        $container->setParameter(self::SEALING_KMS, $config['sealing']['kms']);

        $container->register(DoctrineFormRepository::class, DoctrineFormRepository::class)->setArguments([new Reference('doctrine.orm.entity_manager')]);
        $container->register(BinaryKeysListener::class, BinaryKeysListener::class)->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
        $container->setAlias(FormRepository::class, DoctrineFormRepository::class)->setPublic(true);
        $container->setAlias(ChoicePlaces::class, DoctrineFormRepository::class)->setPublic(true);
        $container->register(BasicConditions::class, BasicConditions::class);
        $container->setAlias(ConditionEvaluator::class, BasicConditions::class);

        $container->register(HmacEmailDigester::class, HmacEmailDigester::class)->setArguments([$config['digest_key']]);
        $digester = new Reference(HmacEmailDigester::class);
        $container->register(SealedValues::class, SealedValues::class)->setArguments([$sealer])->setPublic(true);

        $container->register(Submissions::class, Submissions::class)
            ->setArguments([
                new Reference(FormRepository::class),
                $clock,
                $digester,
                new TaggedIteratorArgument(self::NOTIFIER_TAG),
                $logger,
                new Reference(ConditionEvaluator::class),
                $sealer,
                new Reference(ChoicePlaces::class),
                $config['allow_unsealed_sensitive'],
            ])
            ->setPublic(true);
        $container->register(Retention::class, Retention::class)->setArguments([new Reference(FormRepository::class), $clock]);
        $container->register(EntryType::class, EntryType::class)
            ->setArguments([$sealer, $config['allow_unsealed_sensitive'], class_exists(FormLogic::class), new Reference(ConditionEvaluator::class)])
            ->addTag('form.type');
        $container->register(FormController::class, FormController::class)
            ->setArguments([
                new Reference(FormRepository::class),
                new Reference(Submissions::class),
                new Reference('form.factory'),
                new Reference('twig'),
                $config['template'],
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(CurrentSubject::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (null !== $config['sender']) {
            $container->register(EmailNotifier::class, EmailNotifier::class)
                ->setArguments([new Reference(self::MAILER_TRANSPORTS), $config['sender'], $config['subject_prefix'], new Reference(ConditionEvaluator::class)])
                ->addTag(self::NOTIFIER_TAG);
        }

        if (null !== $config['webhook_secret']) {
            // A host whose name resolves to the server's own network is refused when the request is made.
            if (class_exists(NoPrivateNetworkHttpClient::class)) {
                $container->register(self::WEBHOOK_CLIENT, NoPrivateNetworkHttpClient::class)->setArguments([new Reference('http_client')]);
            } else {
                $container->setAlias(self::WEBHOOK_CLIENT, 'http_client');
            }

            $container->register(WebhookNotifier::class, WebhookNotifier::class)
                ->setArguments([new Reference(self::WEBHOOK_CLIENT), $config['webhook_secret'], $clock])
                ->addTag(self::NOTIFIER_TAG);
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(FormEntriesPrivacy::class, FormEntriesPrivacy::class)
                ->setArguments([new Reference(FormRepository::class), $digester, new Reference(SealedValues::class)])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }

        if (class_exists(Command::class)) {
            $container->register(PurgeEntriesCommand::class, PurgeEntriesCommand::class)->setArguments([new Reference(Retention::class)])->addTag('console.command');
        }
    }
}
