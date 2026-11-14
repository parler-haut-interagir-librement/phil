<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Override;
use Phil\TenancyBundle\Application\Shop\ShopContext;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Http\ConfiguredHostTenants;
use Phil\TenancyBundle\Infrastructure\Http\ResolveHostTenant;
use Phil\TenancyBundle\Infrastructure\Mailer\TenantTransports;
use Phil\TenancyBundle\Infrastructure\Security\RememberSignInTenant;
use Phil\TenancyBundle\Infrastructure\Security\TenantScopedUserProvider;
use Phil\TenancyBundle\Tests\Functional\Sites\SitesKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

/**
 * Shops by domain in one application, through a browser that keeps the session cookie the shops share: each host
 * enters its shop before the firewall, an account signed in on one shop is signed in on the shops linked to it and on
 * no other, and each shop's mail leaves its own way, from the queue's worker too.
 */
#[CoversClass(ResolveHostTenant::class)]
#[CoversClass(ConfiguredHostTenants::class)]
#[CoversClass(TenantScopedUserProvider::class)]
#[CoversClass(RememberSignInTenant::class)]
#[CoversClass(TenantTransports::class)]
#[CoversClass(ShopContext::class)]
final class SitesTest extends WebTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return SitesKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testEachHostServesItsShop(): void
    {
        $browser = self::createClient();
        $browser->disableReboot();

        self::assertSame(['account' => null, 'shop' => 'rose'], $this->json($browser, 'rose'));
        self::assertSame(['general', 'rose'], $this->json($browser, 'general', '/catalogues'));
        self::assertSame(['account' => null, 'shop' => null], $this->json($browser, 'elsewhere'));
        $context = self::getContainer()->get(TenantContext::class);
        self::assertInstanceOf(TenantContext::class, $context);
        self::assertNull($context->current());
    }

    public function testAnAccountSignedInOnAShopIsSignedInOnTheShopsLinkedToIt(): void
    {
        $browser = self::createClient();

        self::assertSame(['account' => 'ana', 'shop' => 'rose'], $this->json($browser, 'rose', '/whoami', ['HTTP_X_LOGIN' => 'ana']));
        self::assertSame(['account' => 'ana', 'shop' => 'rose'], $this->json($browser, 'rose'));
        self::assertSame(['account' => 'ana', 'shop' => 'general'], $this->json($browser, 'general'));
        $browser->request('GET', 'http://general.shop.test/account');
        self::assertResponseIsSuccessful();
    }

    public function testAnAccountSignedInOnAShopIsNotSignedInOnAnother(): void
    {
        $browser = self::createClient();
        $this->json($browser, 'rose', '/whoami', ['HTTP_X_LOGIN' => 'ana']);

        $browser->request('GET', 'http://tavern.shop.test/account');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['account' => null, 'shop' => 'tavern'], $this->json($browser, 'tavern'));
    }

    /**
     * A session opened on a host of no tenant — a central domain whose cookie the shops share — is signed in there
     * only: without reachable tenants nor shared sessions, no shop takes it.
     */
    public function testAnAccountSignedInOnNoShopIsSignedInOnNoShop(): void
    {
        $browser = self::createClient();

        self::assertSame(['account' => 'ana', 'shop' => null], $this->json($browser, 'elsewhere', '/whoami', ['HTTP_X_LOGIN' => 'ana']));
        self::assertSame(['account' => 'ana', 'shop' => null], $this->json($browser, 'elsewhere'));
        self::assertSame(['account' => null, 'shop' => 'rose'], $this->json($browser, 'rose'));
    }

    public function testEachShopsMailLeavesItsOwnWayFromTheQueue(): void
    {
        // One container for both requests, as a long-running server keeps it: the services are reset between them.
        $browser = self::createClient();
        $browser->disableReboot();

        $tavern = $this->mailFrom($browser, 'tavern');
        self::assertSame('"The Tavern" <mail@platform.test>', $tavern->getFrom()[0]->toString());
        self::assertSame('tavern@mail.test', $tavern->getReplyTo()[0]->toString());

        $rose = $this->mailFrom($browser, 'rose');
        self::assertSame('"Rose" <hello@rose.test>', $rose->getFrom()[0]->toString());
        self::assertSame([], $rose->getReplyTo());
    }

    /**
     * Sends the shop's mail, which waits in the queue with the shop's stamp, then hands it to a worker that acts in no
     * tenant, and returns it as the worker sent it.
     */
    private function mailFrom(KernelBrowser $browser, string $shop): Email
    {
        $browser->request('GET', 'http://'.$shop.'.shop.test/mail');
        self::assertResponseStatusCodeSame(204);
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $bus = self::getContainer()->get('messenger.bus.default');
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        [$envelope] = array_values([...$transport->getSent()]);
        self::assertInstanceOf(Envelope::class, $envelope);
        // The worker is another process, in no tenant.
        $context = self::getContainer()->get(TenantContext::class);
        self::assertInstanceOf(TenantContext::class, $context);
        $context->leave();

        $bus->dispatch($envelope->with(new ReceivedStamp('async')));

        $message = $envelope->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $message);
        $email = $message->getMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertFalse($email->getHeaders()->has('X-Transport'));
        self::assertNull($context->current());

        return $email;
    }

    /**
     * @param array<string, string> $server
     *
     * @return array<array-key, mixed>
     */
    private function json(KernelBrowser $browser, string $shop, string $path = '/whoami', array $server = []): array
    {
        $browser->request('GET', 'http://'.$shop.'.shop.test'.$path, server: $server);
        self::assertResponseIsSuccessful();
        $decoded = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
