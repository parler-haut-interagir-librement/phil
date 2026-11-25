<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Functional;

use Override;
use Phil\ReactionsBundle\Infrastructure\Twig\Components\ReactionsComponent;
use Phil\ReactionsBundle\Tests\Functional\App\LiveRequestSwitches;
use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The component through the requests of LiveComponent, as strict as 3.8.1 is: served under its name only, never
 * rendered on a 422 raised before its action, and no `X-Live-Url` on a redirection.
 */
#[CoversClass(ReactionsComponent::class)]
final class LiveRequestsTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string NAME = 'PhilReactions';

    private const string PAGE = '/article';

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        $kernel = $this->client->getKernel();
        self::assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
        $this->client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $this->client->setServerParameter('HTTP_X_MEMBER', 'ada');
    }

    public function testTheComponentIsServedUnderItsNameOnly(): void
    {
        $this->props();
        self::assertResponseIsSuccessful();

        $response = $this->send('GET', $this->url(ReactionsComponent::class), ['props' => '{}']);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringNotContainsString('data-live-props-value', (string) $response->getContent());
    }

    public function testAnUnprocessableRequestBeforeTheActionRendersNothing(): void
    {
        $props = $this->props();
        $this->switches()->refuseBeforeTheAction = true;

        $response = $this->action($props, 'react', ['code' => 'spark', 'token' => 'csrf-token']);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringNotContainsString('data-live-props-value', (string) $response->getContent());
        self::assertStringNotContainsString('phil-reactions', (string) $response->getContent());
    }

    public function testARedirectionCarriesNoLiveUrl(): void
    {
        $props = $this->props();

        $moved = $this->action($props, 'react', ['code' => 'spark', 'token' => 'csrf-token']);
        self::assertSame(Response::HTTP_OK, $moved->getStatusCode());
        self::assertStringContainsString('/article', (string) $moved->headers->get('X-Live-Url'), 'the address of the page is kept');

        $this->switches()->redirectTo = '/login';

        $redirected = $this->action($props, 'react', ['code' => 'spark', 'token' => 'csrf-token']);

        self::assertSame(Response::HTTP_NO_CONTENT, $redirected->getStatusCode());
        self::assertSame('1', $redirected->headers->get('X-Live-Redirect'));
        self::assertSame('/login', $redirected->headers->get('Location'));
        self::assertFalse($redirected->headers->has('X-Live-Url'));
    }

    /**
     * The props of the component, as the page holds them.
     *
     * @return array<string, mixed>
     */
    private function props(): array
    {
        $html = $this->createLiveComponent(self::NAME, ['content' => 'article:1'], $this->client)->render()->crawler();
        $props = json_decode((string) $html->filter('[data-live-props-value]')->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($props);

        /** @var array<string, mixed> $props */
        return $props;
    }

    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $args
     * @param array<string, mixed> $updated
     */
    private function action(array $props, string $action, array $args = [], array $updated = []): Response
    {
        return $this->send('POST', $this->url(self::NAME, $action), [
            'data' => json_encode(['props' => $props, 'args' => (object) $args, 'updated' => (object) $updated], \JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * A request as the browser sends it. The kernel of the tests has no error page: a refusal no listener answers
     * comes out of the client as its exception, and is turned here into a response without a body.
     *
     * @param array<string, string> $parameters
     */
    private function send(string $method, string $url, array $parameters): Response
    {
        try {
            $this->client->request($method, $url, $parameters, server: [
                'HTTP_ACCEPT' => 'application/vnd.live-component+html',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'HTTP_X_LIVE_URL' => self::PAGE,
            ]);
        } catch (HttpExceptionInterface $httpException) {
            return new Response(null, $httpException->getStatusCode());
        }

        return $this->client->getResponse();
    }

    private function url(string $component, string $action = 'get'): string
    {
        $router = $this->client->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router->generate('ux_live_component', ['_live_component' => $component, '_live_action' => $action]);
    }

    private function switches(): LiveRequestSwitches
    {
        $switches = $this->client->getContainer()->get(LiveRequestSwitches::class);
        self::assertInstanceOf(LiveRequestSwitches::class, $switches);

        return $switches;
    }
}
