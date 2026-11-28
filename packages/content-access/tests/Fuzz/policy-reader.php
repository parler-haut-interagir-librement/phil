<?php

declare(strict_types=1);

/*
 * The policy reader on policies nobody wrote: whatever it reads, it gives a policy or refuses with InvalidPolicy, never
 * throws anything else nor warns; a policy it gives writes a canonical form that reads back to the same form, and is
 * decided for a member and a visitor without throwing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ContentAccess\AccessDecider;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\ContentAccess\Entitlement\InMemoryEntitlements;
use Phil\ContentAccess\Exception\InvalidPolicy;
use Phil\ContentAccess\Json\AccessPolicyReader;
use Phil\ContentAccess\Json\AccessPolicyWriter;
use Phil\ContentAccess\Viewer;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Limits;
use Symfony\Component\Clock\MockClock;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new AccessPolicyReader(new RuleReader(AccessConditions::types(), new Limits(maxBytes: 4096, maxDepth: 12, maxNodes: 200, maxEvaluations: 200)), 4096);
$writer = new AccessPolicyWriter();
$decider = new AccessDecider(
    new InMemoryEntitlements(['m' => [new Grant('tier:gold', new DateTimeImmutable('2026-01-01T00:00:00+00:00'))]]),
    new MockClock(new DateTimeImmutable('2026-11-27T12:00:00+00:00')),
);
$member = Viewer::member('m', ['staff'], 'library.example', '192.0.2.1');
$visitor = Viewer::anonymous('2001:db8::1');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $writer, $decider, $member, $visitor): void {
    try {
        $policy = $reader->fromJson($input);
    } catch (InvalidPolicy) {
        return;
    }

    $canonical = $writer->toJson($policy);

    if ($writer->toJson($reader->fromJson($canonical)) !== $canonical) {
        throw new LogicException('A canonical policy does not read back to itself.');
    }

    $decider->decide($policy, $member);
    $decider->decide($policy, $visitor);
});
$config->setMaxLen(4096);
