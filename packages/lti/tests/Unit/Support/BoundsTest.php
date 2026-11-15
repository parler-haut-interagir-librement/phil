<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\Lti\DeepLinking\ContentItems;
use Phil\Lti\DeepLinking\FileItem;
use Phil\Lti\DeepLinking\HtmlItem;
use Phil\Lti\DeepLinking\ImageItem;
use Phil\Lti\DeepLinking\LineItemRequest;
use Phil\Lti\DeepLinking\LinkItem;
use Phil\Lti\DeepLinking\ResourceLinkItem;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Message\Claim;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\DeepLinkingSettings;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\LaunchPresentation;
use Phil\Lti\Message\NrpsEndpoint;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\Roles;
use Phil\Lti\Message\ToolPlatform;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Ags\Result;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Nrps\Member;
use Phil\Lti\Support\Fields;
use Phil\Lti\Tests\Unit\Message\LaunchMessageTest;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The size every member of a message is read at: the largest is accepted, one more is refused with the limit named.
 */
#[CoversClass(Fields::class)]
#[CoversClass(LaunchMessage::class)]
#[CoversClass(Person::class)]
#[CoversClass(Context::class)]
#[CoversClass(AgsEndpoint::class)]
#[CoversClass(LaunchPresentation::class)]
#[CoversClass(NrpsEndpoint::class)]
#[CoversClass(DeepLinkingSettings::class)]
#[CoversClass(ToolPlatform::class)]
#[CoversClass(Member::class)]
#[CoversClass(LineItem::class)]
#[CoversClass(Result::class)]
#[CoversClass(Score::class)]
#[CoversClass(ContentItems::class)]
#[CoversClass(FileItem::class)]
#[CoversClass(HtmlItem::class)]
#[CoversClass(ImageItem::class)]
#[CoversClass(LinkItem::class)]
#[CoversClass(LineItemRequest::class)]
#[CoversClass(ResourceLinkItem::class)]
final class BoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string, int}>
     */
    public static function launchMembers(): iterable
    {
        yield 'version' => [[Claim::VERSION], 'string', 16];
        yield 'message type' => [[Claim::MESSAGE_TYPE], 'string', 64];
        yield 'deployment' => [[Claim::DEPLOYMENT_ID], 'string', 255];
        yield 'mentored users' => [[Claim::ROLE_SCOPE_MENTOR], 'list', 64];
        yield 'roles' => [[Claim::ROLES], 'list', 64];
        yield 'custom' => [[Claim::CUSTOM], 'map', 100];
        yield 'subject' => [['sub'], 'string', 255];
        yield 'name' => [['name'], 'string', 512];
        yield 'given name' => [['given_name'], 'string', 255];
        yield 'family name' => [['family_name'], 'string', 255];
        yield 'email' => [['email'], 'string', 320];
        yield 'picture' => [['picture'], 'string', 2048];
        yield 'locale' => [['locale'], 'string', 35];
        yield 'context id' => [[Claim::CONTEXT, 'id'], 'string', 255];
        yield 'context types' => [[Claim::CONTEXT, 'type'], 'list', 16];
        yield 'platform guid' => [[Claim::TOOL_PLATFORM, 'guid'], 'string', 255];
        yield 'document target' => [[Claim::LAUNCH_PRESENTATION, 'document_target'], 'string', 16];
        yield 'presentation locale' => [[Claim::LAUNCH_PRESENTATION, 'locale'], 'string', 35];
        yield 'AGS scopes' => [[Claim::ENDPOINT, 'scope'], 'list', 16];
        yield 'NRPS versions' => [[Claim::NAMES_ROLE_SERVICE, 'service_versions'], 'list', 8];
        yield 'accepted types' => [[Claim::DEEP_LINKING_SETTINGS, 'accept_types'], 'list', 16];
        yield 'accepted targets' => [[Claim::DEEP_LINKING_SETTINGS, 'accept_presentation_document_targets'], 'list', 8];
    }

    /**
     * @param list<string> $path
     */
    #[DataProvider('launchMembers')]
    public function testTheMembersOfALaunch(array $path, string $kind, int $max): void
    {
        $claims = LaunchMessageTest::full()->toClaims();
        $claims[Claim::DEEP_LINKING_SETTINGS] = ['deep_link_return_url' => 'https://lms.example.org/dl', 'accept_types' => ['link'], 'accept_presentation_document_targets' => ['iframe']];

        $this->assertBound(static function (mixed $value) use ($claims, $path): void {
            LaunchMessage::fromClaims(self::with($claims, $path, $value));
        }, $kind, $max);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function memberFields(): iterable
    {
        yield 'status' => ['status', 'string', 16];
        yield 'user' => ['user_id', 'string', 255];
        yield 'roles' => ['roles', 'list', 64];
        yield 'name' => ['name', 'string', 512];
        yield 'given name' => ['given_name', 'string', 255];
        yield 'family name' => ['family_name', 'string', 255];
        yield 'email' => ['email', 'string', 320];
        yield 'picture' => ['picture', 'string', 2048];
        yield 'source id' => ['lis_person_sourcedid', 'string', 255];
    }

    #[DataProvider('memberFields')]
    public function testTheMembersOfARoster(string $field, string $kind, int $max): void
    {
        $this->assertBound(static function (mixed $value) use ($field): void {
            Member::fromArray([$field => $value] + ['user_id' => 'u', 'status' => 'Active', 'roles' => []]);
        }, $kind, $max);

        self::assertSame('u', Member::fromArray(['user_id' => 'u'])->userId);
        self::assertSame(str_repeat('u', 255), new Member(str_repeat('u', 255), Roles::of([]))->userId);

        $this->expectException(InvalidData::class);
        new Member(str_repeat('u', 256), Roles::of([]));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function itemFields(): iterable
    {
        yield 'item type' => ['type', 'string', 32];
        yield 'media type of a file' => ['mediaType', 'string', 255];
        yield 'resource id of a line item' => ['resourceId', 'string', 255];
        yield 'tag of a line item' => ['tag', 'string', 255];
    }

    #[DataProvider('itemFields')]
    public function testTheMembersOfTheItems(string $field, string $kind, int $max): void
    {
        $this->assertBound(static function (mixed $value) use ($field): void {
            $item = match ($field) {
                'type' => ['type' => $value],
                'mediaType' => ['type' => 'file', 'url' => 'https://tool.example.com/f', 'mediaType' => $value],
                default => ['type' => 'ltiResourceLink', 'lineItem' => ['scoreMaximum' => 10, $field => $value]],
            };
            ContentItems::fromClaims(['items' => [$item]], 'items');
        }, $kind, $max);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function agsFields(): iterable
    {
        yield 'resource of a line item' => ['lineItem', 'resourceId', 255];
        yield 'resource link of a line item' => ['lineItem', 'resourceLinkId', 255];
        yield 'tag of a line item' => ['lineItem', 'tag', 255];
        yield 'learner of a result' => ['result', 'userId', 255];
        yield 'learner of a score' => ['score', 'userId', 255];
        yield 'activity of a score' => ['score', 'activityProgress', 32];
        yield 'grading of a score' => ['score', 'gradingProgress', 32];
    }

    #[DataProvider('agsFields')]
    public function testTheMembersOfTheDocumentsOfAgs(string $document, string $field, int $max): void
    {
        $this->assertBound(static function (mixed $value) use ($document, $field): void {
            match ($document) {
                'lineItem' => LineItem::fromArray([$field => $value] + ['scoreMaximum' => 1, 'label' => 'l']),
                'result' => Result::fromArray([$field => $value] + ['userId' => 'u']),
                default => Score::fromArray([$field => $value] + ['userId' => 'u', 'activityProgress' => 'Started', 'gradingProgress' => 'Pending', 'timestamp' => '2026-11-14T12:00:00Z']),
            };
        }, 'string', $max);
    }

    public function testItemsOfALocalAddressAreRefusedUnlessLocalhostIsAllowed(): void
    {
        $items = ['items' => [['type' => 'link', 'url' => 'https://tool.localhost/x']]];

        self::assertCount(1, ContentItems::fromClaims($items, 'items', true));

        $this->expectException(InvalidToken::class);
        ContentItems::fromClaims($items, 'items');
    }

    public function testWhatIsAbsentIsAbsentFromTheArrays(): void
    {
        $url = Url::fromString('https://tool.example.com/x');

        self::assertSame(['id' => 'c'], new Context('c')->toClaim());
        self::assertSame(['id' => 'c', 'label' => 'L', 'type' => ['t']], new Context('c', 'L', null, ['t'])->toClaim());
        self::assertSame(['guid' => 'g'], new ToolPlatform('g')->toClaim());
        self::assertSame([], new Person(null)->toClaims());
        self::assertSame(['type' => 'file', 'url' => 'https://tool.example.com/x'], new FileItem($url)->toArray());
        self::assertSame('2026-11-14T12:00:00+00:00', new FileItem($url, expiresAt: new DateTimeImmutable('2026-11-14T12:00:00+00:00'))->toArray()['expiresAt']);
        self::assertSame(['type' => 'html', 'html' => '<p>x</p>'], new HtmlItem('<p>x</p>')->toArray());
        self::assertSame(['type' => 'link', 'url' => 'https://tool.example.com/x'], new LinkItem($url)->toArray());
        self::assertSame(['type' => 'image', 'url' => 'https://tool.example.com/x'], new ImageItem($url)->toArray());
        self::assertSame(['scoreMaximum' => 5.0], new LineItemRequest(5.0)->toArray());
        self::assertSame(['user_id' => 'u', 'status' => 'Active', 'roles' => []], new Member('u', Roles::of([]))->toArray());
    }

    public function testAResourceLinkWithOnlyOneEndOfAPeriodIsAccepted(): void
    {
        $moment = new DateTimeImmutable('2026-11-14T12:00:00+00:00');

        self::assertSame($moment, new ResourceLinkItem(availableFrom: $moment)->availableFrom);
        self::assertSame($moment, new ResourceLinkItem(availableUntil: $moment)->availableUntil);
        self::assertSame($moment, new ResourceLinkItem(submissionFrom: $moment)->submissionFrom);
        self::assertSame($moment, new ResourceLinkItem(submissionUntil: $moment)->submissionUntil);

        $read = ResourceLinkItem::read(new Fields(['title' => 't'], inToken: false));
        self::assertNull($read->availableFrom);
        self::assertNull($read->submissionUntil);
    }

    /**
     * @param array<array-key, mixed> $claims
     * @param list<string>            $path
     *
     * @return array<array-key, mixed>
     */
    private static function with(array $claims, array $path, mixed $value): array
    {
        $key = array_shift($path);

        if (null === $key) {
            return $claims;
        }

        $inner = $claims[$key] ?? [];
        $claims[$key] = [] === $path ? $value : self::with(\is_array($inner) ? $inner : [], $path, $value);

        return $claims;
    }

    private function assertBound(callable $read, string $kind, int $max): void
    {
        $value = static fn (int $size): array|string => match ($kind) {
            'string' => str_repeat('x', $size),
            'list' => array_fill(0, $size, 'x'),
            default => array_combine(array_map(static fn (int $index): string => 'k'.$index, range(1, $size)), array_fill(0, $size, 'v')),
        };

        try {
            $read($value($max));
        } catch (InvalidData|InvalidToken $exception) {
            self::assertStringNotContainsString('at most', $exception->getMessage(), 'The largest value is within the limit.');
        }

        try {
            $read($value($max + 1));
            self::fail('A value over the limit was read.');
        } catch (InvalidData|InvalidToken $exception) {
            self::assertStringContainsString('at most', $exception->getMessage());
        }
    }
}
