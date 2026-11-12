<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use LogicException;
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Deprecation\ObservedDeprecations;
use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContractBundle\Infrastructure\Console\DeprecationsCommand;
use Phil\ApiContractBundle\Infrastructure\DependencyInjection\CheckDeprecatedEndpoints;
use Phil\ApiContractBundle\Infrastructure\Deprecation\CacheObservedDeprecations;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;
use Phil\ApiContractBundle\Infrastructure\Http\DeprecationListener;
use Phil\ApiContractBundle\Infrastructure\HttpClient\DeprecationObservingHttpClient;
use Phil\ApiContractBundle\Tests\Functional\App\ApiController;
use Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture\BackwardsController;
use Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture\MisdatedController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapterEvent;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(DeprecatedEndpoint::class)]
#[CoversClass(DeprecationListener::class)]
#[CoversClass(CacheObservedDeprecations::class)]
#[CoversClass(DeprecationObservingHttpClient::class)]
#[CoversClass(DeprecationsCommand::class)]
#[CoversClass(CheckDeprecatedEndpoints::class)]
final class DeprecationTest extends TestCase
{
    public function testADeprecatedEndpointAnnouncesItself(): void
    {
        $signal = new DeprecatedEndpoint('2026-11-01', '2027-05-01T12:00:00', 'https://example.com/changes')->signal();
        self::assertSame(['2026-11-01T00:00:00+00:00', '2027-05-01T12:00:00+00:00', 'https://example.com/changes'], [$signal->deprecatedAt?->format('c'), $signal->sunsetAt?->format('c'), $signal->link]);
        self::assertSame([null, '2027-05-01T00:00:00+00:00', null], [new DeprecatedEndpoint(sunsetAt: '2027-05-01')->signal()->deprecatedAt, new DeprecatedEndpoint(sunsetAt: '2027-05-01')->signal()->sunsetAt?->format('c'), new DeprecatedEndpoint(sunsetAt: '2027-05-01')->signal()->link]);
        self::assertNull(new DeprecatedEndpoint(deprecatedAt: '2026-11-01')->signal()->sunsetAt);

        $kernel = self::createStub(HttpKernelInterface::class);
        $listener = new DeprecationListener();
        $request = Request::create('/old');
        $event = new ControllerEvent($kernel, static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($event->getController(), [new DeprecatedEndpoint('2026-11-01', '2027-05-01')]);

        $listener->onController($event);
        $response = new Response();
        $listener->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame(['@1793491200', 'Sat, 01 May 2027 00:00:00 GMT'], [$response->headers->get('Deprecation'), $response->headers->get('Sunset')]);

        $plain = Request::create('/new');
        $event = new ControllerEvent($kernel, static fn (): Response => new Response(), $plain, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($event->getController(), []);

        $listener->onController($event);
        $response = new Response();
        $listener->onResponse(new ResponseEvent($kernel, $plain, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertFalse($response->headers->has('Deprecation'));
    }

    public function testAnAttributeThatCannotBeSentIsRefusedWhenBuilt(): void
    {
        foreach ([
            [static fn (): DeprecatedEndpoint => new DeprecatedEndpoint('2027-13-01'), 'The deprecatedAt of #[DeprecatedEndpoint] is not a date: "2027-13-01".'],
            [static fn (): DeprecatedEndpoint => new DeprecatedEndpoint(sunsetAt: 'soon'), 'The sunsetAt of #[DeprecatedEndpoint] is not a date: "soon".'],
            [static fn (): DeprecatedEndpoint => new DeprecatedEndpoint('2027-05-01', '2026-11-01'), 'The sunset date precedes the deprecation date.'],
            [static fn (): DeprecatedEndpoint => new DeprecatedEndpoint('2026-11-01', link: 'https://example.com/a b'), 'The deprecation link is not a URI that fits in a Link header.'],
            [static fn (): DeprecatedEndpoint => new DeprecatedEndpoint(), 'A deprecation signal carries a deprecation date, a sunset date, or both.'],
        ] as [$build, $message]) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidContractValue $invalidContractValue) {
                self::assertSame($message, $invalidContractValue->getMessage());
            }
        }
    }

    public function testTheContainerDoesNotCompileWithAControllerWhoseDeprecationCannotBeSent(): void
    {
        foreach ([
            MisdatedController::class => 'The #[DeprecatedEndpoint] of '.MisdatedController::class.'::old() cannot be sent: The sunsetAt of #[DeprecatedEndpoint] is not a date: "2027-13-01".',
            BackwardsController::class => 'The #[DeprecatedEndpoint] of '.BackwardsController::class.' cannot be sent: The sunset date precedes the deprecation date.',
        ] as $controller => $message) {
            $container = new ContainerBuilder();
            $container->setParameter('controller.class', $controller);
            $container->register('controller', '%controller.class%')->addTag(CheckDeprecatedEndpoints::CONTROLLER_TAG);

            try {
                new CheckDeprecatedEndpoints()->process($container);
                self::fail($message);
            } catch (LogicException $logicException) {
                self::assertSame($message, $logicException->getMessage());
                self::assertSame(0, $logicException->getCode());
                self::assertInstanceOf(InvalidContractValue::class, $logicException->getPrevious());
            }
        }

        $after = new ContainerBuilder();
        $after->register('missing', 'Phil\\Nowhere\\Controller')->addTag(CheckDeprecatedEndpoints::CONTROLLER_TAG);
        $after->register('controller', BackwardsController::class)->addTag(CheckDeprecatedEndpoints::CONTROLLER_TAG);

        try {
            new CheckDeprecatedEndpoints()->process($after);
            self::fail('A controller that cannot be reflected does not end the check of those after it.');
        } catch (LogicException $logicException) {
            self::assertStringStartsWith('The #[DeprecatedEndpoint] of '.BackwardsController::class.' ', $logicException->getMessage());
        }

        $fine = new ContainerBuilder();
        $fine->register(ApiController::class)->addTag(CheckDeprecatedEndpoints::CONTROLLER_TAG);
        $fine->register('untagged', MisdatedController::class);
        $fine->register('missing', 'Phil\\Nowhere\\Controller')->addTag(CheckDeprecatedEndpoints::CONTROLLER_TAG);
        new CheckDeprecatedEndpoints()->process($fine);
        self::assertSame('controller.service_arguments', CheckDeprecatedEndpoints::CONTROLLER_TAG);
    }

    public function testAControllerWhoseClassCannotBeLoadedIsLeftToTheContainer(): void
    {
        // A class whose parent is missing: written at run time, since no analysed file may extend an unknown class.
        $class = 'Phil\\ApiContractBundle\\Tests\\Unit\\Infrastructure\\Fixture\\Orphan'.bin2hex(random_bytes(4));
        $file = tempnam(sys_get_temp_dir(), 'orphan');
        self::assertIsString($file);
        $short = substr($class, (int) strrpos($class, '\\') + 1);
        file_put_contents($file, \sprintf("<?php\nnamespace Phil\\ApiContractBundle\\Tests\\Unit\\Infrastructure\\Fixture;\nfinal class %s extends \\Phil\\Nowhere\\ParentController {}\n", $short));
        $loader = static function (string $name) use ($class, $file): void {
            if ($name === $class) {
                require $file;
            }
        };
        spl_autoload_register($loader);

        try {
            $container = new ContainerBuilder();
            $container->register('orphan', $class)->addTag(CheckDeprecatedEndpoints::CONTROLLER_TAG);
            new CheckDeprecatedEndpoints()->process($container);
            self::assertFalse(class_exists($class, false));
        } finally {
            spl_autoload_unregister($loader);
            unlink($file);
        }
    }

    public function testTheDeprecationLinkIsAddedBesideTheResponsesOwnLinks(): void
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $listener = new DeprecationListener();
        $request = Request::create('/old');
        $event = new ControllerEvent($kernel, static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($event->getController(), [new DeprecatedEndpoint('2026-11-01', '2027-05-01', 'https://example.com/changes')]);

        $listener->onController($event);

        $response = new Response();
        $response->headers->set('Link', '</old?page=2>; rel="next"');
        $response->headers->set('Link', '</style.css>; rel="preload"', false);
        $response->headers->set('Sunset', 'Fri, 01 Jan 2027 00:00:00 GMT');

        $listener->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame(['</old?page=2>; rel="next"', '</style.css>; rel="preload"', '<https://example.com/changes>; rel="deprecation"'], $response->headers->all('link'));
        self::assertSame(['Sat, 01 May 2027 00:00:00 GMT'], $response->headers->all('sunset'), "The signal's own Sunset replaces the one the controller set.");
    }

    public function testSignalsAreRecordedOnceADayPerEndpoint(): void
    {
        $at = new DateTimeImmutable('2026-11-03 12:00 UTC');
        // The pool's clock stands at the recorded day: its items expire eight days after it, not after the machine's time.
        $cache = new ArrayAdapter(clock: new MockClock($at));
        $observed = new CacheObservedDeprecations($cache);
        $signal = DeprecationSignal::of(new DateTimeImmutable('2026-11-01 UTC'), new DateTimeImmutable('2027-05-01 UTC'), 'https://example.com/changes');

        self::assertTrue($observed->record('GET /orders', $signal, $at));
        self::assertFalse($observed->record('GET /orders', $signal, $at->modify('+23 hours')));
        self::assertFalse($observed->record('GET /orders', $signal, $at->modify('+86399 seconds')));
        self::assertTrue(new CacheObservedDeprecations($cache)->record('GET /orders', $signal, $at->modify('+1 day')));
        self::assertTrue($observed->record('GET /items', DeprecationSignal::of(new DateTimeImmutable('2026-11-01 UTC')), $at));

        $all = new CacheObservedDeprecations($cache)->all();
        self::assertSame(['GET /orders', 'GET /items'], array_keys($all));
        self::assertSame(['2027-05-01', 'https://example.com/changes'], [$all['GET /orders']->sunsetAt?->format('Y-m-d'), $all['GET /orders']->link]);
        self::assertCount(2, $observed->all());

        $memory = new CacheObservedDeprecations();
        self::assertTrue($memory->record('GET /a', DeprecationSignal::of(null, new DateTimeImmutable('2027-01-01 UTC')), $at));
        self::assertFalse($memory->record('GET /a', DeprecationSignal::of(null, new DateTimeImmutable('2027-01-01 UTC')), $at));
        self::assertSame([null, '2027-01-01'], [$memory->all()['GET /a']->deprecatedAt, $memory->all()['GET /a']->sunsetAt?->format('Y-m-d')]);
        self::assertSame('phil_api_contract.observed_deprecations', CacheObservedDeprecations::KEY);
    }

    public function testEachEndpointIsAnItemWrittenOnlyWhenItsWarningIsDueOrItsSignalChanged(): void
    {
        $cache = new TraceableAdapter(new ArrayAdapter());
        $signal = DeprecationSignal::of(new DateTimeImmutable('2026-11-01 UTC'));
        $at = new DateTimeImmutable();
        $first = new CacheObservedDeprecations($cache);
        $second = new CacheObservedDeprecations($cache);

        self::assertTrue($first->record('GET /orders', $signal, $at));
        self::assertTrue($second->record('GET /items', $signal, $at));
        self::assertSame(['GET /orders', 'GET /items'], array_keys($first->all()), "Neither process overwrote the other's endpoint.");
        self::assertTrue($cache->hasItem(CacheObservedDeprecations::keyOf('GET /orders')));
        self::assertSame('phil_api_contract.observed_deprecations.'.hash('xxh128', 'GET /orders'), CacheObservedDeprecations::keyOf('GET /orders'));
        self::assertSame(4, $this->saves($cache), 'One item per endpoint, and the index each time it gained a name.');

        $cache->clearCalls();
        self::assertFalse($first->record('GET /orders', $signal, $at->modify('+1 hour')));
        self::assertFalse($second->record('GET /orders', $signal, $at->modify('+2 hours')));
        self::assertSame(0, $this->saves($cache), 'An unchanged signal already warned about today is not written again.');

        $sunset = DeprecationSignal::of(new DateTimeImmutable('2026-11-01 UTC'), new DateTimeImmutable('2027-05-01 UTC'));
        self::assertFalse($first->record('GET /orders', $sunset, $at->modify('+3 hours')));
        self::assertSame(1, $this->saves($cache), 'A changed signal is written, without a new warning.');
        self::assertSame('2027-05-01', $second->all()['GET /orders']->sunsetAt?->format('Y-m-d'));

        $cache->deleteItem(CacheObservedDeprecations::KEY);
        self::assertTrue($first->record('GET /orders', $signal, $at->modify('+1 day')));
        self::assertSame(['GET /orders'], array_keys($second->all()), 'A name lost from the index comes back at the next write of its item.');
    }

    public function testAnEndpointNoLongerRecordedIsForgottenAfterAWeek(): void
    {
        $cache = new ArrayAdapter();
        $observed = new CacheObservedDeprecations($cache);
        $signal = DeprecationSignal::of(null, new DateTimeImmutable('2027-05-01 UTC'));

        self::assertTrue($observed->record('GET /gone', $signal, new DateTimeImmutable(\sprintf('-%d seconds', CacheObservedDeprecations::LIFETIME + 60))));
        self::assertTrue($observed->record('GET /kept', $signal, new DateTimeImmutable('-7 days')));
        self::assertSame(['GET /kept'], array_keys($observed->all()));

        self::assertTrue($observed->record('GET /new', $signal, new DateTimeImmutable()));
        self::assertSame(['GET /kept', 'GET /new'], $cache->getItem(CacheObservedDeprecations::KEY)->get(), 'The index drops expired names when it is rewritten.');

        $cache->save($cache->getItem(CacheObservedDeprecations::keyOf('GET /kept'))->set('not an entry'));
        self::assertSame(['GET /new'], array_keys($observed->all()), 'An endpoint without a record does not hide those after it.');

        $headers = $signal->toHeaders();

        foreach ([
            ['endpoint' => 'GET /other', 'headers' => $headers, 'at' => 1],
            ['endpoint' => 'GET /new', 'headers' => $headers, 'at' => '1'],
            ['endpoint' => 'GET /new', 'headers' => 'Sunset', 'at' => 1],
        ] as $foreign) {
            $cache->save($cache->getItem(CacheObservedDeprecations::keyOf('GET /new'))->set($foreign));
            self::assertSame([], $observed->all(), 'An item this class did not write for that endpoint is ignored.');
        }
    }

    public function testAnIndexOrARecordWithForeignValuesIsReadForWhatItHolds(): void
    {
        $cache = new TraceableAdapter(new ArrayAdapter());
        $observed = new CacheObservedDeprecations($cache);
        $signal = DeprecationSignal::of(null, new DateTimeImmutable('2027-05-01 UTC'));
        $at = new DateTimeImmutable();

        self::assertTrue($observed->record('GET /a', $signal, $at));
        $cache->save($cache->getItem(CacheObservedDeprecations::KEY)->set([42, 'GET /a']));
        self::assertSame(['GET /a'], array_keys($observed->all()), 'A name that is not a string is skipped.');

        $cache->save($cache->getItem(CacheObservedDeprecations::KEY)->set(['first' => 'GET /a']));
        self::assertTrue($observed->record('GET /b', $signal, $at));
        self::assertSame(['GET /a', 'GET /b'], $cache->getItem(CacheObservedDeprecations::KEY)->get(), 'The index is rewritten as a list.');

        $cache->save($cache->getItem(CacheObservedDeprecations::keyOf('GET /a'))->set(['endpoint' => 'GET /a', 'headers' => [...$signal->toHeaders(), 7 => 'stray', 'X-Count' => 3], 'at' => $at->getTimestamp()]));
        $cache->clearCalls();
        self::assertFalse($observed->record('GET /a', $signal, $at->modify('+1 hour')));
        self::assertSame(0, $this->saves($cache), 'Header lines that are not named strings are not part of the signal.');
    }

    public function testTheClientRecordsWhatItIsTold(): void
    {
        $observed = new CacheObservedDeprecations();
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<array-key, mixed>}> */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [\is_string($level) ? $level : '', (string) $message, $context];
            }
        };
        $headers = ['Deprecation' => '@1793491200', 'Sunset' => 'Sat, 01 May 2027 00:00:00 GMT', 'Link' => '<https://example.com/changes>; rel="deprecation"'];
        $client = new DeprecationObservingHttpClient(new MockHttpClient([
            new MockResponse('{}', ['response_headers' => $headers]),
            new MockResponse('{}', ['response_headers' => $headers]),
            new MockResponse('{}'),
            new MockResponse('{}', ['response_headers' => ['Deprecation' => '@1793491200']]),
        ]), $observed, $this->clock(), $logger);

        self::assertSame('{}', $client->request('GET', 'https://api.example.com/orders/42/lines?page=2')->getContent());
        $client->request('GET', 'https://api.example.com/orders/43/lines')->getContent();
        $client->request('GET', 'https://api.example.com/fine')->getContent();
        $client->request('POST', 'https://api.example.com/x', ['extra' => ['endpoint' => 'create-x']])->getContent();

        self::assertSame(['GET /orders/{id}/lines', 'create-x'], array_keys($observed->all()));
        self::assertSame([['warning', 'The endpoint {endpoint} is deprecated{sunset}.', ['endpoint' => 'GET /orders/{id}/lines', 'sunset' => ' and goes away on 2027-05-01T00:00:00+00:00', 'link' => 'https://example.com/changes']], ['warning', 'The endpoint {endpoint} is deprecated{sunset}.', ['endpoint' => 'create-x', 'sunset' => '', 'link' => null]]], $logger->logs);

        $bare = new DeprecationObservingHttpClient(new MockHttpClient(new MockResponse('', ['response_headers' => $headers])), $observed, new NativeClock());
        $bare->request('GET', 'https://api.example.com/other', ['extra' => ['trace' => true]])->getContent();
        self::assertArrayHasKey('GET /other', $observed->all());
    }

    public function testTheSignalIsReadOnceAtTheFirstChunkAtTheClocksTime(): void
    {
        $counting = new class implements ObservedDeprecations {
            /** @var list<string> */
            public array $at = [];

            public function record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool
            {
                $this->at[] = $observedAt->format('Y-m-d');

                return true;
            }

            public function all(): array
            {
                return [];
            }
        };
        $past = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2000-01-01 UTC');
            }
        };
        $client = new DeprecationObservingHttpClient(new MockHttpClient(new MockResponse(['a', 'b', 'c'], ['response_headers' => ['Sunset' => 'Sat, 01 May 2027 00:00:00 GMT']])), $counting, clock: $past);

        self::assertSame('abc', $client->request('GET', 'https://api.example.com/x')->getContent());
        self::assertSame(['2000-01-01'], $counting->at);
    }

    public function testAnEndpointHasNoIdentifier(): void
    {
        self::assertSame([
            'GET /orders/{id}',
            'DELETE /orders/{id}/lines/{id}',
            'GET /',
            'GET /',
            'GET /v2/orders/a42',
            'GET /customers/{id}/orders',
            'GET /files/{id}/{id}',
            'GET /tokens/{id}',
            'GET /search/{id}/line-items/oauth2',
            'GET /users/jdoe',
        ], [
            DeprecationObservingHttpClient::endpoint('get', 'https://x.test/orders/42'),
            DeprecationObservingHttpClient::endpoint('DELETE', 'https://x.test/orders/0190B5E2-3C4D-7E8F-9A0B-1C2D3E4F5A6B/lines/7'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/v2/orders/a42'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/customers/jane.doe@example.com/orders'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/files/report.pdf/caf%C3%A9'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/tokens/9f86d081884c7d659a2feaa0c55ad015'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/search/'.str_repeat('a', 33).'/line-items/oauth2'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/users/jdoe'),
        ]);
    }

    public function testTheEndpointIsThePathABrowserReads(): void
    {
        self::assertSame([
            'GET /orders/{id}',
            'GET /orders/{id}',
            'GET /admin/{id}',
            'GET /b',
            'GET /orders/{id}/lines',
            'GET /orders/{id}',
            'GET /{id}/x',
            'GET /a/{id}',
            'GET /b',
            'GET /z/{id}',
            'GET /',
            'GET /',
            'GET /',
            'GET /',
        ], [
            DeprecationObservingHttpClient::endpoint('GET', 'https://jane:secret@x.test/orders/42'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test\\orders\\42'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/orders/../admin/42'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test/a/%2e%2E/b'),
            DeprecationObservingHttpClient::endpoint('GET', 'orders/7/lines'),
            DeprecationObservingHttpClient::endpoint('GET', '/orders/7?page=2#top'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://bücher.example/café/x'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://127.0.0.1:8080/a/1'),
            DeprecationObservingHttpClient::endpoint('GET', 'http://localhost/b'),
            DeprecationObservingHttpClient::endpoint('GET', 'https://[::1]/z/9'),
            DeprecationObservingHttpClient::endpoint('GET', 'mailto:jane@example.com'),
            DeprecationObservingHttpClient::endpoint('GET', 'http://'),
            DeprecationObservingHttpClient::endpoint('GET', ''),
            DeprecationObservingHttpClient::endpoint('GET', 'https://x.test?q=1'),
        ]);
    }

    private function saves(TraceableAdapter $cache): int
    {
        $saves = 0;

        foreach ($cache->getCalls() as $call) {
            $saves += $call instanceof TraceableAdapterEvent && 'save' === $call->name ? 1 : 0;
        }

        return $saves;
    }

    public function testTheCommandFailsWhileAnEndpointHasASunset(): void
    {
        $observed = new CacheObservedDeprecations();
        $empty = new CommandTester(new DeprecationsCommand($observed, $this->clock()));
        self::assertSame(Command::SUCCESS, $empty->execute([]));
        self::assertSame("No deprecated endpoint was called.\n", $empty->getDisplay());

        $at = new DateTimeImmutable('2026-11-03 UTC');
        $observed->record('GET /b', DeprecationSignal::of(new DateTimeImmutable('2026-12-01 UTC')), $at);
        $observed->record('GET /a', DeprecationSignal::of(new DateTimeImmutable('2026-10-01 UTC'), null, 'https://example.com/a'), $at);

        $deprecated = new CommandTester(new DeprecationsCommand($observed, $this->clock()));
        self::assertSame(Command::SUCCESS, $deprecated->execute([]));
        self::assertSame("GET /a: deprecated since 2026-10-01 — https://example.com/a\nGET /b: deprecated from 2026-12-01\n", $deprecated->getDisplay());

        $observed->record('GET /c', DeprecationSignal::of(null, new DateTimeImmutable('2026-11-01 UTC')), $at);
        $observed->record('GET /d', DeprecationSignal::of(new DateTimeImmutable('2026-10-01 UTC'), new DateTimeImmutable('2027-01-01 UTC')), $at);

        $sunset = new CommandTester(new DeprecationsCommand($observed, $this->clock()));
        self::assertSame(Command::FAILURE, $sunset->execute([]));
        self::assertStringContainsString("GET /c: deprecation announced, gone since 2026-11-01\nGET /d: deprecated since 2026-10-01, goes away on 2027-01-01\n", $sunset->getDisplay());
        $command = new DeprecationsCommand($observed, new NativeClock());
        self::assertSame('phil:api-contract:deprecations', $command->getName());
        self::assertSame('Lists the deprecated endpoints this application calls', $command->getDescription());

        $later = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2030-01-01 UTC');
            }
        };
        $future = new CommandTester(new DeprecationsCommand($observed, $later));
        $future->execute([]);
        self::assertStringContainsString('GET /b: deprecated since 2026-12-01', $future->getDisplay());

        $now = new CommandTester(new DeprecationsCommand($observed, new NativeClock()));
        self::assertNotSame(Command::SUCCESS, $now->execute([]));
        self::assertStringContainsString('GET /c: deprecation announced, gone since 2026-11-01', $now->getDisplay());

        $three = new CacheObservedDeprecations();
        foreach (['GET /x', 'GET /y', 'GET /z'] as $endpoint) {
            $three->record($endpoint, DeprecationSignal::of(new DateTimeImmutable('2026-10-01 UTC')), $at);
        }

        self::assertSame(Command::SUCCESS, new CommandTester(new DeprecationsCommand($three, $this->clock()))->execute([]));
    }

    private function clock(): ClockInterface
    {
        return new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-03 12:00 UTC');
            }
        };
    }
}
