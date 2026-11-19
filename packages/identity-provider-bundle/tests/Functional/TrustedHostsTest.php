<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional;

use Override;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Without `public_origin`, the links take the host of the request, which `framework.trusted_hosts` bounds: a request
 * that names another host is refused before any link is made.
 */
#[CoversClass(AccountController::class)]
final class TrustedHostsTest extends FunctionalTestCase
{
    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel(framework: ['trusted_hosts' => ['^localhost$']]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Request::setTrustedHosts([]);
    }

    public function testARequestForAnotherHostSendsNoLink(): void
    {
        $this->account();
        $this->client->request('POST', '/forgot-password', ['email' => 'ada@example.com'], server: ['HTTP_HOST' => 'attacker.example']);

        self::assertResponseStatusCodeSame(400);
        self::assertEmailCount(0);
    }
}
