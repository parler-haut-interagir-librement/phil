<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Benchmark;

use DateTimeImmutable;
use Phil\ContentAccess\AccessDecider;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\ContentAccess\Entitlement\InMemoryEntitlements;
use Phil\ContentAccess\Json\AccessPolicyReader;
use Phil\ContentAccess\Viewer;
use Phil\Rules\Json\RuleReader;
use Symfony\Component\Clock\MockClock;

/**
 * The two hot paths of a listing: reading a stored policy, and deciding it for one member holding twenty grants, as a
 * page of thirty entries does thirty times.
 */
final readonly class DecisionBench
{
    private string $json;

    private AccessPolicyReader $reader;

    private AccessPolicy $policy;

    private AccessDecider $decider;

    private Viewer $viewer;

    public function __construct()
    {
        $branches = [['type' => 'access.role', 'operands' => ['roles' => ['staff', 'editor']]]];

        for ($i = 0; $i < 10; ++$i) {
            $branches[] = ['type' => 'access.feature', 'operands' => ['feature' => 'tier:'.$i, 'after_days' => $i, 'for_days' => 365]];
        }

        $branches[] = ['type' => 'access.institution', 'operands' => ['domains' => ['library.example', 'college.example'], 'networks' => ['192.0.2.0/24', '2001:db8::/32']]];

        $this->json = json_encode(['version' => 1, 'audience' => 'restricted', 'rule' => ['version' => 1, 'condition' => ['any' => $branches]], 'preview' => true], \JSON_THROW_ON_ERROR);
        $this->reader = new AccessPolicyReader(new RuleReader(AccessConditions::types()));
        $this->policy = $this->reader->fromJson($this->json);

        $grants = [];

        for ($i = 0; $i < 20; ++$i) {
            $grants[] = new Grant('other:'.$i, new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        }

        $this->decider = new AccessDecider(new InMemoryEntitlements(['m' => $grants]), new MockClock(new DateTimeImmutable('2026-11-27T12:00:00+00:00')));
        $this->viewer = Viewer::member('m', ['reader'], 'example.org', '198.51.100.1');
    }

    public function benchReadAStoredPolicy(): bool
    {
        return $this->reader->fromJson($this->json)->preview;
    }

    public function benchDecideForOneMember(): bool
    {
        return $this->decider->decide($this->policy, $this->viewer)->isGranted();
    }
}
