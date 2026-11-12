<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Override;
use Phil\IdentityClientBundle\Tests\Functional\App\EventLog;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use Phil\IdentityClientBundle\Tests\Functional\App\RecordingProvisioner;
use Phil\IdentityClientBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

abstract class FunctionalTestCase extends TestCase
{
    private ?TestKernel $kernel = null;

    private ?KernelBrowser $client = null;

    /**
     * @param array<string, mixed> $client
     */
    protected function boot(array $client = []): void
    {
        $this->kernel = new TestKernel($client);
        $this->kernel->boot();

        $this->client = new KernelBrowser($this->kernel);
        $this->client->disableReboot();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    protected function browser(): KernelBrowser
    {
        self::assertNotNull($this->client);

        return $this->client;
    }

    protected function container(): ContainerInterface
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    protected function provider(): FakeOidcProvider
    {
        $provider = $this->container()->get(FakeOidcProvider::class);
        self::assertInstanceOf(FakeOidcProvider::class, $provider);

        return $provider;
    }

    protected function provisioner(): RecordingProvisioner
    {
        $provisioner = $this->container()->get(RecordingProvisioner::class);
        self::assertInstanceOf(RecordingProvisioner::class, $provisioner);

        return $provisioner;
    }

    protected function events(): EventLog
    {
        $log = $this->container()->get(EventLog::class);
        self::assertInstanceOf(EventLog::class, $log);

        return $log;
    }

    protected function clock(): MockClock
    {
        $clock = $this->container()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    protected function securityToken(): ?TokenInterface
    {
        $storage = $this->container()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $storage);

        return $storage->getToken();
    }

    /**
     * @param array<string, mixed> $server
     */
    protected function get(string $url, array $server = []): Response
    {
        $this->browser()->request('GET', $url, [], [], $server);

        return $this->browser()->getResponse();
    }

    protected function postLogoutToken(string $token): Response
    {
        $this->browser()->request('POST', '/oidc/back-channel-logout', ['logout_token' => $token]);

        return $this->browser()->getResponse();
    }

    /**
     * @return string the address of the authorization request
     */
    protected function startSignIn(string $start = '/oidc/start'): string
    {
        $location = $this->get($start)->headers->get('Location');
        self::assertIsString($location);
        self::assertStringStartsWith(FakeOidcProvider::ISSUER.'/authorize', $location);

        return $location;
    }

    /**
     * @param array<string, mixed> $claims
     */
    protected function finishSignIn(string $authorization, array $claims = []): Response
    {
        return $this->get($this->provider()->authorize($authorization, $claims));
    }

    /**
     * @param array<string, mixed> $claims
     */
    protected function signIn(array $claims = [], string $start = '/oidc/start'): Response
    {
        return $this->finishSignIn($this->startSignIn($start), $claims);
    }
}
