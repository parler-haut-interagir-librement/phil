<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Functional\App;

use Override;
use Phil\EmailAddress\Dns\StaticResolver;
use Phil\EmailAddress\MailDomain;
use Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator, the translator and
 * Messenger on an in-memory transport that serializes; a resolver that knows `example.org` receives mail and
 * `nomail.example` none; a `staff` list kept to `example.org`; the bounces of the webhook `provider`, vouched-for
 * abuse reports and the decisions sent on to the transport; the lists served under `/email-address`.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-email-address-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-email-address-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a secret of the test application',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'en',
            'form' => ['csrf_protection' => false],
            'validation' => true,
            'translator' => ['fallbacks' => ['en']],
            'cache' => ['pools' => ['cache.bounces' => ['adapter' => 'cache.adapter.array']]],
            'messenger' => [
                'transports' => ['async' => 'in-memory://?serialize=true'],
                'routing' => [BounceDecidedMessage::class => 'async'],
            ],
        ]);
        $container->extension('phil_email_address', [
            'denied_domains' => ['spam.example'],
            'lists' => ['staff' => ['allowed_domains' => ['example.org', 'nomail.example'], 'check_mail_server' => true]],
            'dns' => ['resolver' => 'test.resolver', 'cache' => null],
            'suggestions' => ['url' => '/email-address/domains.json'],
            'bounces' => [
                'history' => 'cache.bounces',
                'authserv_ids' => ['mail.example.net'],
                'feedback_domains' => ['fbl.example.com'],
                'remote_event_consumers' => ['provider'],
                'recipient_metadata' => 'recipient',
                'message_bus' => 'messenger.default_bus',
            ],
        ]);
        $container->services()->set('test.resolver', StaticResolver::class)->args([['example.org' => MailDomain::Mx, 'nomail.example' => MailDomain::NoRecord]]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/email-address');
    }
}
