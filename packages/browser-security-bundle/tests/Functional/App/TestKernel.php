<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Functional\App;

use Override;
use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;
use Phil\BrowserSecurityBundle\Application\Attribute\ContentSecurityPolicy;
use Phil\BrowserSecurityBundle\Application\Attribute\UserContent;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\Event\ViolationReported;
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * An application as small as the bundle needs: FrameworkBundle with AssetMapper, TwigBundle, DoctrineBundle on SQLite
 * for the store of the reports, the bundles it bridges to, a page rendered with the nonces, a preview with the attribute of the bundle, a page
 * whose route overrides the policy and a page that writes its own.
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
        return sys_get_temp_dir().'/phil-browser-security-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-browser-security-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    public function page(): Response
    {
        $tests = $this->getContainer()->get('test.service_container');
        \assert($tests instanceof ContainerInterface);
        $twig = $tests->get('twig');
        \assert($twig instanceof Environment);

        return new Response($twig->render('page.html.twig'));
    }

    #[ContentSecurityPolicy(policy: 'preview')]
    public function preview(): Response
    {
        return new Response('<p>preview</p>');
    }

    public function inline(): Response
    {
        $context = $this->getContainer()->get(ContentSecurityPolicyContext::class);
        \assert($context instanceof ContentSecurityPolicyContext);
        $context->allowInline('script', "console.log('inline')");

        return new Response("<script>console.log('inline')</script>");
    }

    public function described(): Response
    {
        return $this->rendered('described.html.twig');
    }

    public function describedByHand(): Response
    {
        return $this->rendered('described_by_hand.html.twig');
    }

    private function rendered(string $template): Response
    {
        $tests = $this->getContainer()->get('test.service_container');
        \assert($tests instanceof ContainerInterface);
        $twig = $tests->get('twig');
        \assert($twig instanceof Environment);

        return new Response($twig->render($template), Response::HTTP_OK, ['Cache-Control' => 'public, max-age=600']);
    }

    public function plain(): Response
    {
        return new Response('plain');
    }

    public function upload(): Response
    {
        return new Response('<svg onload="alert(1)"/>', Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
    }

    #[UserContent]
    public function picture(): Response
    {
        return new Response('png', Response::HTTP_OK, ['Content-Type' => 'image/png']);
    }

    public function served(): Response
    {
        $response = new Response('text', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        $policy = $this->getContainer()->get(UserContentPolicy::class);
        \assert($policy instanceof UserContentPolicy);
        $policy->apply($response, 'notes.txt');

        return $response;
    }

    public function own(): Response
    {
        return new Response('own', Response::HTTP_OK, ['Content-Security-Policy' => "default-src 'none'"]);
    }

    #[BrowserSecurity(replace: ['X-Frame-Options' => 'DENY'], remove: ['Referrer-Policy'])]
    public function strict(): Response
    {
        return new Response('strict');
    }

    public function plainText(Request $request): Response
    {
        $response = new Response($request->cookies->get('theme', 'none'));
        $response->headers->setCookie(Cookie::create('theme', 'dark'));

        return $response;
    }

    public function leave(): RedirectResponse
    {
        return new RedirectResponse('https://elsewhere.example.net/');
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'asset_mapper' => ['paths' => ['assets/']],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(RecordedViolations::class)->public()->tag('kernel.event_listener', ['event' => ViolationReported::class]);
        $container->extension('security', [
            'providers' => ['none' => ['memory' => null]],
            'firewalls' => ['main' => ['stateless' => true, 'provider' => 'none', 'logout' => ['path' => '/logout', 'enable_csrf' => false]]],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/csp.sqlite']]);
        $container->extension('twig', ['strict_variables' => true, 'default_path' => __DIR__.'/templates']);
        // The test pages are on http://localhost, which the structured data take in tests only.
        $container->extension('phil_structured_data', ['website' => ['name' => 'Example'], 'allow_localhost' => true]);
        $container->extension('phil_browser_security', [
            'policies' => [
                'default' => ['directives' => ['default-src' => ["'self'"], 'object-src' => ["'none'"]], 'strict_dynamic' => true],
                'preview' => ['directives' => ['default-src' => ["'none'"], 'style-src' => ["'unsafe-inline'"], 'sandbox' => true, 'frame-ancestors' => ["'self'"]], 'report' => false],
            ],
            'reporting' => ['storage' => true, 'rate_limit' => ['limit' => 2, 'cache_pool' => 'cache.app']],
            'headers' => [
                'permissions_policy' => ['camera' => []],
                'cross_origin_opener_policy' => 'same-origin',
                'cross_origin_opener_policy_report_only' => 'same-origin',
                'frame_options' => ['paths' => ['/embed' => null]],
            ],
            'https' => ['redirect' => ['enabled' => false, 'exclude_paths' => ['/health']], 'strict_transport_security' => ['enabled' => true]],
            'signed_cookies' => ['names' => ['theme']],
            'external_redirects' => ['allow' => ['allowed.example.com']],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilBrowserSecurityBundle/config/routes.php');
        $routes->add('page', '/page')->controller($this->page(...));
        $routes->add('described', '/described')->controller($this->described(...));
        $routes->add('described_by_hand', '/described-by-hand')->controller($this->describedByHand(...));
        $routes->add('preview', '/preview')->controller($this->preview(...));
        $routes->add('inline', '/inline')->controller($this->inline(...));
        $routes->add('own', '/own')->controller($this->own(...));
        $routes->add('upload', '/upload')->controller($this->upload(...))->defaults([UserContentPolicy::ROUTE_DEFAULT => true]);
        $routes->add('picture', '/picture')->controller($this->picture(...));
        $routes->add('served', '/served')->controller($this->served(...));
        $routes->add('images', '/images')->controller($this->plain(...))->defaults(['_csp' => ['add' => ['img-src' => ['https://images.example.com']]]]);
        $routes->add('embed', '/embed/map')->controller($this->plain(...));
        $routes->add('strict', '/strict')->controller($this->strict(...));
        $routes->add('cookie', '/cookie')->controller($this->plainText(...));
        $routes->add('leave', '/leave')->controller($this->leave(...));
        $routes->add('leave_allowed', '/leave-allowed')->controller($this->leave(...))->defaults(['_browser_security' => ['add' => ['external-redirects' => ['elsewhere.example.net']]]]);
        $routes->add('forced', '/forced')->controller($this->plain(...))->defaults(['_browser_security' => ['replace' => ['https-redirect' => true]]]);
        $routes->add('no_policy', '/no-policy')->controller($this->plain(...))->defaults(['_browser_security' => ['remove' => ['Content-Security-Policy']]]);
        $routes->add('logout', '/logout')->controller($this->plain(...));
        $routes->add('relaxed', '/relaxed')->controller($this->preview(...))->defaults(['_csp' => ['report_only' => true]]);
    }
}
