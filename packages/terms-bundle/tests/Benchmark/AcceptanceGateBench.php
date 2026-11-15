<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalAcceptances;
use Phil\TermsBundle\Infrastructure\Doctrine\DbalPolicyVersions;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceGate;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TermsBundle\Tests\Unit\Fixture\FixedMember;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * What the gate adds to every request of a signed-in member in order with three required policies, on SQLite in
 * memory with 10,000 acceptances: the versions read once per request, then one question per policy; and a visitor,
 * who costs nothing.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"terms"})
 */
final class AcceptanceGateBench
{
    private AcceptanceGate $gate;

    private AcceptanceGate $visitorGate;

    private VersionCatalog $catalog;

    private HttpKernelInterface $kernel;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (TermsTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $clock = new MockClock('2026-05-01 10:00:00');
        $policies = new Policies(new Policy('terms'), new Policy('privacy', renewAfterDays: 365), new Policy('conduct'));
        $versions = new DbalPolicyVersions($connection);
        $acceptances = new DbalAcceptances($connection);
        $this->catalog = new VersionCatalog($versions);
        $terms = new Terms($policies, $this->catalog, $versions, new PolicyTextRenderer(), $acceptances, $clock);

        foreach (['terms', 'privacy', 'conduct'] as $policy) {
            for ($i = 1; $i <= 5; ++$i) {
                $terms->publish($policy, ['en' => str_repeat('A rule. ', 500)], material: 0 === $i % 2);
            }
        }

        $connection->beginTransaction();

        for ($member = 1; $member <= 3334; ++$member) {
            foreach (['terms', 'privacy', 'conduct'] as $policy) {
                $terms->accept('m'.$member, $policy, 5, AcceptanceChannel::Form);
            }
        }

        $connection->commit();
        $checker = new AcceptanceChecker($policies, $this->catalog, $acceptances);
        $urls = new UrlGenerator(new RouteCollection(), new RequestContext());
        $this->gate = new AcceptanceGate($checker, new FixedMember('m1000'), $clock, $urls, [], ['/_profiler']);
        $this->visitorGate = new AcceptanceGate($checker, new FixedMember(), $clock, $urls, [], ['/_profiler']);
        $this->kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }

    public function benchAMemberInOrder(): void
    {
        $this->catalog->reset();
        $this->gate->onKernelRequest(new RequestEvent($this->kernel, Request::create('/forum'), HttpKernelInterface::MAIN_REQUEST));
    }

    public function benchAVisitor(): void
    {
        $this->visitorGate->onKernelRequest(new RequestEvent($this->kernel, Request::create('/forum'), HttpKernelInterface::MAIN_REQUEST));
    }
}
