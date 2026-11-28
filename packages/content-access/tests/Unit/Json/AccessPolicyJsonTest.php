<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit\Json;

use Phil\ContentAccess\Audience;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Exception\InvalidPolicy;
use Phil\ContentAccess\Json\AccessPolicyReader;
use Phil\ContentAccess\Json\AccessPolicyWriter;
use Phil\ContentAccess\WhenDenied;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Limits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccessPolicyReader::class)]
#[CoversClass(AccessPolicyWriter::class)]
#[CoversClass(InvalidPolicy::class)]
final class AccessPolicyJsonTest extends TestCase
{
    private function reader(int $maxBytes = 65536): AccessPolicyReader
    {
        return new AccessPolicyReader(new RuleReader(AccessConditions::types(), new Limits(maxDepth: 4)), $maxBytes);
    }

    public function testARestrictedPolicyReadsAndWritesCanonically(): void
    {
        $policy = $this->reader()->fromJson('{"when_denied": "locked", "preview": true, "public_from": "2026-12-01T00:00:00-05:00", "rule": {"version": 1, "condition": {"type": "access.member"}}, "audience": "restricted", "version": 1}');

        self::assertSame(Audience::Restricted, $policy->audience);
        self::assertTrue($policy->preview);
        self::assertSame(WhenDenied::Locked, $policy->whenDenied);
        self::assertSame('2026-12-01T00:00:00-05:00', $policy->publicFrom?->format(\DATE_ATOM));

        $writer = new AccessPolicyWriter();
        $canonical = '{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"type":"access.member","operands":{}}},"public_from":"2026-12-01T05:00:00+00:00","preview":true,"when_denied":"locked"}';

        self::assertSame($canonical, $writer->toJson($policy));
        self::assertSame($canonical, $writer->toJson($this->reader()->fromJson($canonical)));
        self::assertSame(hash('sha256', $canonical), $writer->fingerprint($policy));
        self::assertSame(
            ['version' => 1, 'audience' => 'restricted', 'rule' => ['version' => 1, 'condition' => ['type' => 'access.member', 'operands' => []]], 'public_from' => '2026-12-01T05:00:00+00:00', 'preview' => true, 'when_denied' => 'locked'],
            $writer->toArray($policy),
        );
    }

    public function testTheDefaultsAreWritten(): void
    {
        $writer = new AccessPolicyWriter();

        self::assertSame('{"version":1,"audience":"public","preview":false,"when_denied":"locked"}', $writer->toJson($this->reader()->fromJson('{"version":1,"audience":"public"}')));
        self::assertSame('{"version":1,"audience":"members","preview":false,"when_denied":"hidden"}', $writer->toJson($this->reader()->fromJson('{"version":1,"audience":"members","when_denied":"hidden","preview":false}')));
        self::assertSame(['version' => 1, 'audience' => 'members', 'preview' => false, 'when_denied' => 'locked'], $writer->toArray($this->reader()->fromArray(['version' => 1, 'audience' => 'members'])));
    }

    public function testARuleKeepsItsNumbersInItsCanonicalForm(): void
    {
        $json = '{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"type":"fact.compare","operands":{"fact":"score","operator":"gte","value":2.0}}},"preview":false,"when_denied":"locked"}';

        self::assertSame($json, new AccessPolicyWriter()->toJson($this->reader()->fromJson($json)));
    }

    public function testSlashesAndAccentsAreWrittenAsTheyAre(): void
    {
        $json = '{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"type":"fact.equals","operands":{"fact":"course/level","value":"élevé"}}},"preview":false,"when_denied":"locked"}';

        self::assertSame($json, new AccessPolicyWriter()->toJson(self::reader()->fromJson($json)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'not JSON' => ['{', 'The access policy is not valid JSON: Syntax error'];
        yield 'a text' => ['"public"', 'The access policy is malformed at "": a policy is an object.'];
        yield 'a list' => ['[1]', 'The access policy is malformed at "": a policy is an object.'];
        yield 'an empty object' => ['{}', 'The access policy is malformed at "": a policy is an object.'];
        yield 'an unknown member' => ['{"version":1,"audience":"public","colour":"red"}', 'The access policy is malformed at "/colour": is not a member of a policy.'];
        yield 'an unknown member with a control character' => ['{"version":1,"audience":"public","a\nb":1}', 'The access policy is malformed at "/a?b": is not a member of a policy.'];
        yield 'a long unknown member' => ['{"version":1,"audience":"public","A'.str_repeat('x', 69).'":1}', 'The access policy is malformed at "/A'.str_repeat('x', 63).'…": is not a member of a policy.'];
        yield 'an unknown member of sixty-four bytes' => ['{"version":1,"audience":"public","'.str_repeat('y', 64).'":1}', 'The access policy is malformed at "/'.str_repeat('y', 64).'": is not a member of a policy.'];
        yield 'a numeric member' => ['{"version":1,"audience":"public","7":1}', 'The access policy is malformed at "/7": is not a member of a policy.'];
        yield 'no version' => ['{"audience":"public"}', 'The access policy is of version null; this library reads version 1.'];
        yield 'another version' => ['{"version":2,"audience":"public"}', 'The access policy is of version 2; this library reads version 1.'];
        yield 'a text version' => ['{"version":"1","audience":"public"}', 'The access policy is of version string; this library reads version 1.'];
        yield 'no audience' => ['{"version":1}', 'The access policy is malformed at "/audience": is "public", "members" or "restricted".'];
        yield 'an unknown audience' => ['{"version":1,"audience":"friends"}', 'The access policy is malformed at "/audience": is "public", "members" or "restricted".'];
        yield 'a numeric audience' => ['{"version":1,"audience":1}', 'The access policy is malformed at "/audience": is "public", "members" or "restricted".'];
        yield 'a restricted policy without rule' => ['{"version":1,"audience":"restricted"}', 'The access policy is malformed at "/rule": a restricted policy holds its rule as an object.'];
        yield 'a rule as text' => ['{"version":1,"audience":"restricted","rule":"member"}', 'The access policy is malformed at "/rule": a restricted policy holds its rule as an object.'];
        yield 'a rule on a members policy' => ['{"version":1,"audience":"members","rule":{"version":1,"condition":{"type":"access.member"}}}', 'The access policy is contradictory: only a restricted content has a rule.'];
        yield 'a null rule on a public policy' => ['{"version":1,"audience":"public","rule":null}', 'The access policy is contradictory: only a restricted content has a rule.'];
        yield 'a refused rule' => ['{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"type":"access.nobody"}}}', 'The rule of the access policy at "/rule" is refused: The condition at "/condition" is of type "access.nobody", which no package registered.'];
        yield 'a rule too deep' => ['{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"not":{"not":{"not":{"not":{"not":{"type":"access.member"}}}}}}}}', 'The rule of the access policy at "/rule" is refused:'];
        yield 'a public early access' => ['{"version":1,"audience":"public","public_from":"2026-12-01T00:00:00+00:00"}', 'The access policy is contradictory: a public content has no early access, preview or hiding.'];
        yield 'an instant without offset' => ['{"version":1,"audience":"members","public_from":"2026-12-01T00:00:00"}', 'The access policy is malformed at "/public_from": is an RFC 3339 instant with a numeric offset, such as "2026-12-01T00:00:00+00:00".'];
        yield 'an instant in Z' => ['{"version":1,"audience":"members","public_from":"2026-12-01T00:00:00Z"}', 'The access policy is malformed at "/public_from"'];
        yield 'an impossible date' => ['{"version":1,"audience":"members","public_from":"2026-02-30T00:00:00+00:00"}', 'The access policy is malformed at "/public_from"'];
        yield 'a numeric instant' => ['{"version":1,"audience":"members","public_from":1764547200}', 'The access policy is malformed at "/public_from"'];
        yield 'a text preview' => ['{"version":1,"audience":"members","preview":"yes"}', 'The access policy is malformed at "/preview": is true or false.'];
        yield 'an unknown denial' => ['{"version":1,"audience":"members","when_denied":"blurred"}', 'The access policy is malformed at "/when_denied": is "locked" or "hidden".'];
        yield 'a numeric denial' => ['{"version":1,"audience":"members","when_denied":0}', 'The access policy is malformed at "/when_denied": is "locked" or "hidden".'];
        yield 'a hidden preview' => ['{"version":1,"audience":"members","preview":true,"when_denied":"hidden"}', 'The access policy is contradictory: a hidden content shows no preview.'];
    }

    #[DataProvider('refused')]
    public function testARefusedPolicySaysWhere(string $json, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->reader()->fromJson($json);
    }

    public function testARefusedRuleIsKeptAsThePrevious(): void
    {
        try {
            $this->reader()->fromJson('{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"type":"access.nobody"}}}');
            self::fail('Refused.');
        } catch (InvalidPolicy $invalidPolicy) {
            self::assertInstanceOf(InvalidRule::class, $invalidPolicy->getPrevious());
        }

        try {
            $this->reader()->fromJson('{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"not":{"not":{"not":{"not":{"not":{"type":"access.member"}}}}}}}}');
            self::fail('Refused.');
        } catch (InvalidPolicy $invalidPolicy) {
            self::assertInstanceOf(LimitExceeded::class, $invalidPolicy->getPrevious());
        }
    }

    public function testAPolicyLargerThanTheLimitIsRefusedBeforeDecoding(): void
    {
        $json = '{"version":1,"audience":"public"}';

        self::assertSame(Audience::Public, $this->reader(\strlen($json))->fromJson($json)->audience);

        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The access policy holds %d bytes; at most %d are read.', \strlen($json), \strlen($json) - 1));

        $this->reader(\strlen($json) - 1)->fromJson($json);
    }

    public function testADeepDocumentIsRefusedAsNotJson(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The access policy is not valid JSON: Maximum stack depth exceeded');

        $this->reader()->fromJson(str_repeat('[', 128).str_repeat(']', 128));
    }

    public function testADocumentAtTheDepthLimitIsDecoded(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('a policy is an object');

        $this->reader()->fromJson(str_repeat('[', 128 - 1).str_repeat(']', 128 - 1));
    }

    public function testTheDefaultLimitIsSixtyFiveKibibytes(): void
    {
        $reader = new AccessPolicyReader(new RuleReader(AccessConditions::types()));
        $json = '{"version":1,"audience":"public"}';

        self::assertSame(Audience::Public, $reader->fromJson(str_pad($json, 65536))->audience);

        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The access policy holds 65537 bytes; at most 65536 are read.');

        $reader->fromJson(str_pad($json, 65537));
    }

    public function testARefusedRuleHasNoErrorCode(): void
    {
        try {
            $this->reader()->fromJson('{"version":1,"audience":"restricted","rule":{"version":1,"condition":{"type":"access.nobody"}}}');
            self::fail('Refused.');
        } catch (InvalidPolicy $invalidPolicy) {
            self::assertSame(0, $invalidPolicy->getCode());
        }
    }
}
