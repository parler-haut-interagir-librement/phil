<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit;

use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\BigBlueButton\ChecksumAlgorithm;
use Phil\VirtualClassroom\Http\HttpBudget;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Contract\CallbackUrls;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Application\Contract\CurrentParticipant;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Domain\Event\AttendanceUpdated;
use Phil\VirtualClassroomBundle\Infrastructure\Console\SyncCommand;
use Phil\VirtualClassroomBundle\Infrastructure\Console\WebhookUrlCommand;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\CoursesCompletion;
use Phil\VirtualClassroomBundle\Infrastructure\Courses\VirtualClassroomActivity;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\DependencyInjection\VirtualClassroomServices;
use Phil\VirtualClassroomBundle\Infrastructure\Doctrine\ClassroomSchemaListener;
use Phil\VirtualClassroomBundle\Infrastructure\Http\JoinController;
use Phil\VirtualClassroomBundle\Infrastructure\Http\WebhookController;
use Phil\VirtualClassroomBundle\Infrastructure\Memory\InMemoryClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Messenger\SyncRoom;
use Phil\VirtualClassroomBundle\Infrastructure\Messenger\SyncRoomHandler;
use Phil\VirtualClassroomBundle\Infrastructure\Privacy\ClassroomPrivacy;
use Phil\VirtualClassroomBundle\Infrastructure\Routing\RoutedCallbackUrls;
use Phil\VirtualClassroomBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilVirtualClassroomBundle::class)]
#[CoversClass(VirtualClassroomServices::class)]
final class PhilVirtualClassroomBundleTest extends TestCase
{
    private const array JITSI = ['type' => 'jitsi', 'url' => 'https://meet.example.com', 'app_id' => 'app1', 'secret' => 'application-secret-0123'];

    private const array BBB = ['type' => 'bigbluebutton', 'url' => 'https://bbb.example.com/bigbluebutton/api/', 'secret' => 'shared-secret-of-the-server-0123'];

    public function testTheDefaults(): void
    {
        $container = $this->load(['providers' => ['main' => self::BBB]]);

        self::assertSame(DbalClassroomStore::class, (string) $container->getAlias(ClassroomStore::class));
        self::assertSame(['doctrine.dbal.default_connection', 'phil_virtual_classroom'], array_map($this->text(...), $container->getDefinition(DbalClassroomStore::class)->getArguments()));
        self::assertTrue($container->hasDefinition(ClassroomSchemaListener::class));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(ClassroomSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame(SecurityCurrentParticipant::class, (string) $container->getAlias(CurrentParticipant::class));
        self::assertSame('ROLE_VIRTUAL_CLASSROOM_MODERATOR', $container->getDefinition(SecurityCurrentParticipant::class)->getArgument(2));
        self::assertSame('the-kernel-secret-0123', $container->getDefinition(WebhookSigner::class)->getArgument(0));
        self::assertFalse($container->getDefinition(RoutedCallbackUrls::class)->getArgument(2));
        self::assertSame(['main'], $container->getDefinition(Providers::class)->getArgument(1));
        self::assertSame('main', $container->getDefinition(Providers::class)->getArgument(2));
        self::assertSame('clock', $this->text($container->getDefinition(Classrooms::class)->getArgument(3)));
        self::assertTrue($container->getDefinition(Classrooms::class)->isPublic());
        self::assertTrue($container->hasDefinition(SyncCommand::class));
        self::assertTrue($container->hasDefinition(WebhookUrlCommand::class));
        self::assertSame([['handles' => SyncRoom::class]], $container->getDefinition(SyncRoomHandler::class)->getTag('messenger.message_handler'));
        self::assertFalse($container->hasDefinition(VirtualClassroomActivity::class), 'No courses without PhilCoursesBundle.');
        self::assertFalse($container->hasDefinition(ClassroomPrivacy::class), 'No privacy without PhilPrivacyBundle.');

        $bbb = $container->getDefinition('phil_virtual_classroom.provider.main');
        self::assertSame(BigBlueButton::class, $bbb->getClass());
        self::assertSame('http_client', $this->text($bbb->getArgument(0)));
        self::assertSame('shared-secret-of-the-server-0123', $bbb->getArgument(2));
        self::assertTrue($bbb->getArgument(6));
        self::assertTrue($bbb->getArgument(7));
        $budget = $bbb->getArgument(5);
        self::assertInstanceOf(Definition::class, $budget);
        self::assertSame([5.0, 10.0, 1_048_576], $budget->getArguments());
    }

    public function testEveryOption(): void
    {
        $container = $this->load([
            'default_provider' => 'jaas',
            'providers' => [
                'main' => [...self::BBB, 'checksum' => 'sha512', 'analytics' => false, 'signed_analytics' => false, 'timeout' => 2.5, 'max_duration' => 4.0, 'max_response_bytes' => 4096],
                'jitsi' => ['type' => 'jitsi', 'url' => 'https://meet.example.com', 'app_id' => 'app1', 'secret' => 'application-secret-0123', 'webhook_secret' => 'hooks-secret-0123456', 'token_ttl' => 600, 'audience' => 'meet', 'subject' => '*'],
                'jaas' => ['type' => 'jaas', 'app_id' => 'vpaas-magic-cookie-1', 'key_id' => 'vpaas-magic-cookie-1/k', 'private_key' => '-----BEGIN PRIVATE KEY-----'],
                'own' => ['type' => 'service', 'service' => 'app.classroom'],
            ],
            'store' => 'memory',
            'clock' => 'app.clock',
            'http_client' => 'app.http',
            'webhook_secret' => 'my-webhook-secret-123',
            'allow_localhost' => true,
            'moderator_role' => 'ROLE_TEACHER',
            'current_participant' => 'app.participant',
            'courses' => ['enabled' => true, 'threshold' => 80],
        ], ['PhilCoursesBundle', 'PhilPrivacyBundle']);

        self::assertSame(InMemoryClassroomStore::class, (string) $container->getAlias(ClassroomStore::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryClassroomStore::class)->getTag('kernel.reset'));
        self::assertFalse($container->hasDefinition(ClassroomSchemaListener::class));
        self::assertSame('app.participant', (string) $container->getAlias(CurrentParticipant::class));
        self::assertFalse($container->hasDefinition(SecurityCurrentParticipant::class));
        self::assertSame('my-webhook-secret-123', $container->getDefinition(WebhookSigner::class)->getArgument(0));
        self::assertTrue($container->getDefinition(RoutedCallbackUrls::class)->getArgument(2));
        self::assertSame(['main', 'jitsi', 'jaas', 'own'], $container->getDefinition(Providers::class)->getArgument(1));
        self::assertSame('jaas', $container->getDefinition(Providers::class)->getArgument(2));
        self::assertSame('app.clock', $this->text($container->getDefinition(Classrooms::class)->getArgument(3)));

        $bbb = $container->getDefinition('phil_virtual_classroom.provider.main');
        self::assertSame('app.http', $this->text($bbb->getArgument(0)));
        self::assertFalse($bbb->getArgument(6));
        self::assertFalse($bbb->getArgument(7));
        $checksum = $bbb->getArgument(4);
        self::assertInstanceOf(Definition::class, $checksum);
        self::assertSame(['sha512'], $checksum->getArguments());
        $budget = $bbb->getArgument(5);
        self::assertInstanceOf(Definition::class, $budget);
        self::assertSame([2.5, 4.0, 4096], $budget->getArguments());

        $jitsi = $container->getDefinition('phil_virtual_classroom.provider.jitsi');
        self::assertSame([Jitsi::class, 'selfHosted'], $jitsi->getFactory());
        self::assertSame(['app1', 'application-secret-0123'], [$jitsi->getArgument(1), $jitsi->getArgument(2)]);
        self::assertSame([600, 'hooks-secret-0123456', 'meet', '*'], [$jitsi->getArgument(4), $jitsi->getArgument(5), $jitsi->getArgument(6), $jitsi->getArgument(7)]);
        $jaas = $container->getDefinition('phil_virtual_classroom.provider.jaas');
        self::assertSame([Jitsi::class, 'jaas'], $jaas->getFactory());
        self::assertSame(['vpaas-magic-cookie-1', 'vpaas-magic-cookie-1/k', '-----BEGIN PRIVATE KEY-----', 10_800, null, null], [$jaas->getArgument(0), $jaas->getArgument(1), $jaas->getArgument(2), $jaas->getArgument(4), $jaas->getArgument(5), $jaas->getArgument(6)]);
        self::assertSame('app.classroom', (string) $container->getAlias('phil_virtual_classroom.provider.own'));

        self::assertSame([[]], $container->getDefinition(VirtualClassroomActivity::class)->getTag(ActivityType::TAG));
        self::assertSame(80, $container->getDefinition(CoursesCompletion::class)->getArgument(3));
        self::assertSame([['event' => AttendanceUpdated::class]], $container->getDefinition(CoursesCompletion::class)->getTag('kernel.event_listener'));
        self::assertTrue($container->hasDefinition(ClassroomPrivacy::class));
        self::assertSame([[]], $container->getDefinition(ClassroomPrivacy::class)->getTag('phil_privacy.exporter'));
        self::assertSame([[]], $container->getDefinition(ClassroomPrivacy::class)->getTag('phil_privacy.eraser'));
    }

    public function testAStoreOfTheApplicationAndTheOptionalModulesTurnedOff(): void
    {
        $container = $this->load(['providers' => ['main' => self::BBB], 'store' => 'app.store', 'connection' => 'other', 'table_prefix' => 'lms', 'courses' => ['enabled' => false], 'privacy' => ['enabled' => false]], ['PhilCoursesBundle', 'PhilPrivacyBundle']);

        self::assertSame('app.store', (string) $container->getAlias(ClassroomStore::class));
        self::assertFalse($container->hasDefinition(DbalClassroomStore::class));
        self::assertFalse($container->hasDefinition(VirtualClassroomActivity::class));
        self::assertFalse($container->hasDefinition(ClassroomPrivacy::class));
    }

    public function testAnotherConnectionAndPrefix(): void
    {
        $container = $this->load(['providers' => ['main' => self::BBB], 'connection' => 'other', 'table_prefix' => 'lms']);

        $store = $container->getDefinition(DbalClassroomStore::class);
        self::assertSame('doctrine.dbal.other_connection', $this->text($store->getArgument(0)));
        self::assertInstanceOf(Reference::class, $store->getArgument(0));
        self::assertSame('lms', $store->getArgument(1));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'other']], $container->getDefinition(ClassroomSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testADisabledBundleRegistersNothing(): void
    {
        $container = $this->load(['enabled' => false, 'providers' => ['main' => self::BBB]]);

        self::assertFalse($container->hasDefinition(Classrooms::class));
    }

    public function testNoServerIsAConfigurationWithoutDefault(): void
    {
        $container = $this->load([]);

        self::assertSame([], $container->getDefinition(Providers::class)->getArgument(1));
        self::assertNull($container->getDefinition(Providers::class)->getArgument(2));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'an unknown default' => [['default_provider' => 'other', 'providers' => ['main' => self::BBB]], 'The default provider is one of the providers of the configuration.'];
        yield 'a provider with an uppercase name' => [['providers' => ['Main' => self::BBB]], 'A provider is named by 1 to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'a service without service' => [['providers' => ['main' => ['type' => 'service']]], 'A provider of type "service" names its service.'];
        yield 'a BigBlueButton without secret' => [['providers' => ['main' => ['type' => 'bigbluebutton', 'url' => 'https://bbb.example.com/api/']]], 'A BigBlueButton or Jitsi server has its "url" and its "secret".'];
        yield 'a BigBlueButton without address' => [['providers' => ['main' => ['type' => 'bigbluebutton', 'secret' => 's']]], 'A BigBlueButton or Jitsi server has its "url" and its "secret".'];
        yield 'a Jitsi without application' => [['providers' => ['main' => ['type' => 'jitsi', 'url' => 'https://meet.example.com', 'secret' => 's']]], 'A Jitsi server has its "app_id".'];
        yield 'a JaaS without application' => [['providers' => ['main' => ['type' => 'jaas', 'key_id' => 'k', 'private_key' => 'p']]], 'A Jitsi server has its "app_id".'];
        yield 'a JaaS without key' => [['providers' => ['main' => ['type' => 'jaas', 'app_id' => 'a', 'private_key' => 'p']]], 'A JaaS account has its "key_id" and its "private_key".'];
        yield 'a JaaS without private key' => [['providers' => ['main' => ['type' => 'jaas', 'app_id' => 'a', 'key_id' => 'k']]], 'A JaaS account has its "key_id" and its "private_key".'];
        yield 'a prefix with a dash' => [['table_prefix' => 'my-lms'], 'A table prefix is 1 to 41 lowercase letters, digits or "_", starting with a letter'];
        yield 'a prefix too long' => [['table_prefix' => 'a'.str_repeat('b', 41)], 'A table prefix is 1 to 41 lowercase letters'];
        yield 'a prefix with a line break' => [['table_prefix' => "lms\n"], 'A table prefix is 1 to 41 lowercase letters'];
        yield 'a provider named with a line break' => [['providers' => ["main\n" => self::BBB]], 'A provider is named by 1 to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'a provider with a name of 65 characters' => [['providers' => [str_repeat('a', 65) => self::BBB]], 'A provider is named by 1 to 64 lowercase letters, digits, "_", "." or "-".'];
        yield 'a threshold above a hundred' => [['courses' => ['threshold' => 101]], 'threshold'];
        yield 'a link of 59 seconds' => [['providers' => ['main' => self::JITSI + ['token_ttl' => 59]]], 'token_ttl'];
        yield 'a link of over a day' => [['providers' => ['main' => self::JITSI + ['token_ttl' => 86_401]]], 'token_ttl'];
        yield 'a timeout over two minutes' => [['providers' => ['main' => self::BBB + ['timeout' => 121]]], 'timeout'];
        yield 'a call over two minutes' => [['providers' => ['main' => self::BBB + ['max_duration' => 121]]], 'max_duration'];
        yield 'an answer under a kibibyte' => [['providers' => ['main' => self::BBB + ['max_response_bytes' => 1023]]], 'max_response_bytes'];
        yield 'an answer over sixteen mebibytes' => [['providers' => ['main' => self::BBB + ['max_response_bytes' => 16_777_217]]], 'max_response_bytes'];
        yield 'a threshold of zero' => [['courses' => ['threshold' => 0]], 'threshold'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testTheBoundsOfTheOptionsAreAccepted(): void
    {
        $container = $this->load([
            'providers' => [
                'main' => self::BBB + ['timeout' => 120, 'max_duration' => 120, 'max_response_bytes' => 1024],
                'big' => ['max_response_bytes' => 16_777_216, 'checksum' => 'sha256'] + self::BBB,
                'sha' => ['checksum' => 'sha384'] + self::BBB,
                'short' => self::JITSI + ['token_ttl' => 60],
                'long' => self::JITSI + ['token_ttl' => 86_400],
            ],
            'courses' => ['threshold' => 100],
        ], ['PhilCoursesBundle']);

        $budget = $container->getDefinition('phil_virtual_classroom.provider.main')->getArgument(5);
        self::assertInstanceOf(Definition::class, $budget);
        self::assertEquals([120.0, 120.0, 1024], $budget->getArguments());
        self::assertSame(100, $container->getDefinition(CoursesCompletion::class)->getArgument(3));
        self::assertSame([60, 86_400], [$container->getDefinition('phil_virtual_classroom.provider.short')->getArgument(4), $container->getDefinition('phil_virtual_classroom.provider.long')->getArgument(4)]);

        foreach (['big' => 'sha256', 'sha' => 'sha384'] as $name => $algorithm) {
            $checksum = $container->getDefinition('phil_virtual_classroom.provider.'.$name)->getArgument(4);
            self::assertInstanceOf(Definition::class, $checksum);
            self::assertSame([$algorithm], $checksum->getArguments());
        }

        $least = $this->load(['courses' => ['threshold' => 1]], ['PhilCoursesBundle']);
        self::assertSame(1, $least->getDefinition(CoursesCompletion::class)->getArgument(3));
    }

    public function testTheDefaultThresholdIsThreeQuarters(): void
    {
        $container = $this->load(['providers' => ['main' => self::BBB]], ['PhilCoursesBundle']);

        self::assertSame(75, $container->getDefinition(CoursesCompletion::class)->getArgument(3));
    }

    public function testTheServicesAreWiredToEachOther(): void
    {
        $container = $this->load(['providers' => ['main' => self::BBB]], ['PhilCoursesBundle', 'PhilPrivacyBundle']);

        self::assertSame(RoutedCallbackUrls::class, (string) $container->getAlias(CallbackUrls::class));
        self::assertTrue($container->getAlias(ClassroomStore::class)->isPublic());
        self::assertTrue($container->getAlias(CurrentParticipant::class)->isPublic());
        self::assertTrue($container->getDefinition(Providers::class)->isPublic());
        self::assertSame(['Reference:'.Classrooms::class, 'Reference:'.CurrentParticipant::class], $this->wiring($container->getDefinition(JoinController::class)));
        self::assertSame(['Reference:'.Classrooms::class, 'Reference:'.WebhookSigner::class], $this->wiring($container->getDefinition(WebhookController::class)));
        self::assertSame(['Reference:'.Classrooms::class, 'Reference:'.ClassroomStore::class], $this->wiring($container->getDefinition(SyncCommand::class)));
        self::assertSame(['Reference:'.Providers::class, 'Reference:'.WebhookSigner::class, 'Reference:router'], $this->wiring($container->getDefinition(WebhookUrlCommand::class)));
        self::assertSame(['Reference:'.Classrooms::class], $this->wiring($container->getDefinition(SyncRoomHandler::class)));
        self::assertSame(['Reference:'.Classrooms::class, 'Reference:'.Providers::class, 'Reference:router'], $this->wiring($container->getDefinition(VirtualClassroomActivity::class)));
        self::assertSame(['Reference:doctrine.dbal.default_connection', 'phil_virtual_classroom'], $this->wiring($container->getDefinition(ClassroomSchemaListener::class)));
        self::assertSame(['Reference:'.ClassroomStore::class, 'phil_virtual_classroom'], $this->wiring($container->getDefinition(ClassroomPrivacy::class)));

        foreach ([JoinController::class, WebhookController::class, VirtualClassroomActivity::class, CoursesCompletion::class, ClassroomPrivacy::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
        }

        foreach ([JoinController::class, WebhookController::class] as $controller) {
            self::assertTrue($container->getDefinition($controller)->hasTag('controller.service_arguments'), $controller);
        }

        $bbb = $container->getDefinition('phil_virtual_classroom.provider.main');
        $url = $bbb->getArgument(1);
        $checksum = $bbb->getArgument(4);
        self::assertInstanceOf(Definition::class, $url);
        self::assertInstanceOf(Definition::class, $checksum);
        self::assertSame([Url::class, 'fromString'], $url->getFactory());
        self::assertSame(['https://bbb.example.com/bigbluebutton/api/'], $url->getArguments());
        self::assertSame([ChecksumAlgorithm::class, 'from'], $checksum->getFactory());
        self::assertSame(['sha256'], $checksum->getArguments());
        $budget = $bbb->getArgument(5);
        self::assertInstanceOf(Definition::class, $budget);
        self::assertSame(HttpBudget::class, $budget->getClass());
    }

    public function testTheAliasesOfAStoreAndAParticipantOfTheApplicationAreAsPublic(): void
    {
        $memory = $this->load(['providers' => ['main' => self::BBB], 'store' => 'memory', 'current_participant' => 'app.participant']);
        $own = $this->load(['providers' => ['main' => self::BBB], 'store' => 'app.store']);

        self::assertTrue($memory->getAlias(ClassroomStore::class)->isPublic());
        self::assertTrue($memory->getAlias(CurrentParticipant::class)->isPublic());
        self::assertTrue($own->getAlias(ClassroomStore::class)->isPublic());
    }

    public function testTheLongestPrefixAndProviderName(): void
    {
        $container = $this->load(['providers' => [str_repeat('a', 64) => self::BBB], 'table_prefix' => 'a'.str_repeat('b', 40)]);

        self::assertSame('a'.str_repeat('b', 40), $container->getDefinition(DbalClassroomStore::class)->getArgument(1));
    }

    /**
     * @return list<string> the arguments of a service, a reference as `Reference:` and its identifier
     */
    private function wiring(Definition $definition): array
    {
        return array_values(array_map(static fn (mixed $argument): string => $argument instanceof Reference ? 'Reference:'.$argument : (\is_string($argument) ? $argument : get_debug_type($argument)), $definition->getArguments()));
    }

    private function text(mixed $value): string
    {
        self::assertTrue(\is_string($value) || $value instanceof Reference);

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $bundles
     */
    private function load(array $config, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', array_fill_keys($bundles, 'x'));
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.secret', 'the-kernel-secret-0123');

        $bundle = new PhilVirtualClassroomBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_virtual_classroom', $config);
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile(true);

        return $container;
    }
}
