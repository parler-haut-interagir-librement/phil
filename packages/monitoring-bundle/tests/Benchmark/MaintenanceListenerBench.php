<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Benchmark;

use Phil\MonitoringBundle\Infrastructure\Http\MaintenanceListener;
use Phil\MonitoringBundle\Infrastructure\Store\CacheMaintenanceStore;
use Phil\MonitoringBundle\Infrastructure\Store\FileMaintenanceStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The maintenance listener runs on every request: what an open site pays for it is the read of its store — a stat of
 * the flag file, or a get on a pool — and what a site under maintenance pays to turn a visitor away, the allow-lists
 * asked and the plain-text answer built.
 *
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 *
 * @Revs(1000)
 *
 * @Iterations(5)
 *
 * @Groups({"monitoring"})
 */
final readonly class MaintenanceListenerBench
{
    private MaintenanceListener $openFile;

    private MaintenanceListener $openCache;

    private MaintenanceListener $closed;

    private HttpKernelInterface $kernel;

    private string $flag;

    public function __construct()
    {
        $this->flag = sys_get_temp_dir().'/phil-monitoring-bench-'.getmypid().'.json';
        $this->openFile = new MaintenanceListener(new FileMaintenanceStore($this->flag, 300));
        $this->openCache = new MaintenanceListener(new CacheMaintenanceStore(new ArrayAdapter(), new MockClock(), 300));
        $this->closed = new MaintenanceListener(new FileMaintenanceStore($this->flag.'.closed', 300), ['10.0.0.0/8', '192.168.0.0/16']);
        $this->kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }

    public function setUp(): void
    {
        new Filesystem()->dumpFile($this->flag.'.closed', '{"since":"2026-11-08T10:00:00+00:00","retry_after":300}');
    }

    public function tearDown(): void
    {
        new Filesystem()->remove([$this->flag, $this->flag.'.closed']);
    }

    public function benchOpenSiteWithAFlagFile(): void
    {
        ($this->openFile)($this->event());
    }

    public function benchOpenSiteWithACachePool(): void
    {
        ($this->openCache)($this->event());
    }

    public function benchVisitorTurnedAway(): void
    {
        ($this->closed)($this->event());
    }

    private function event(): RequestEvent
    {
        return new RequestEvent($this->kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
    }
}
