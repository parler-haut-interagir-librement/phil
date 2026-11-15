<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Service;

use DateTimeImmutable;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;
use Phil\Lti\Http\UrlPath;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\AgsClient;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Ags\LineItemPage;
use Phil\Lti\Service\Ags\MediaType;
use Phil\Lti\Service\Ags\Result;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;
use Phil\Lti\Service\ServiceCall;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tests\Fixture\RecordingServiceClient;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(AgsClient::class)]
#[CoversClass(ServiceCall::class)]
#[CoversClass(LineItem::class)]
#[CoversClass(LineItemPage::class)]
#[CoversClass(Score::class)]
#[CoversClass(Result::class)]
#[CoversClass(UrlPath::class)]
#[CoversClass(ServiceResponse::class)]
final class AgsClientTest extends TestCase
{
    private const string ITEMS = 'https://lms.example.org/c/1/lineitems?tenant=a';

    private const string ITEM = 'https://lms.example.org/c/1/lineitems/9?tenant=a';

    private Lti $lti;

    /** @var list<ServiceResponse> */
    private array $answers = [];

    private RecordingServiceClient $client;

    protected function setUp(): void
    {
        $this->lti = new Lti();
        $this->client = new RecordingServiceClient(function (ServiceRequest $request): ServiceResponse {
            if ('https://lms.example.org/token' === $request->url->toString()) {
                parse_str((string) $request->body, $body);

                return RecordingServiceClient::json(200, ['access_token' => 'token:'.(\is_string($body['scope'] ?? null) ? $body['scope'] : ''), 'token_type' => 'Bearer', 'expires_in' => 3600]);
            }

            return array_shift($this->answers) ?? new ServiceResponse(500, [], '');
        });
    }

    public function testAScoreIsSentToTheLineItemOfTheLaunch(): void
    {
        $this->answers[] = new ServiceResponse(204, [], '');
        $score = new Score('user-7', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00.123+00:00'), 8.5, 10.0, 'Well done');

        $this->ags()->publishScore($this->lti->platform, $this->endpoint(), $score);

        $request = $this->serviceRequests()[0];
        self::assertSame('POST', $request->method);
        self::assertSame('https://lms.example.org/c/1/lineitems/9/scores?tenant=a', $request->url->toString());
        self::assertSame(['Authorization' => 'Bearer token:'.Scope::SCORE, 'Accept' => 'application/json', 'Content-Type' => MediaType::SCORE], $request->headers);
        self::assertSame('{"userId":"user-7","scoreGiven":8.5,"scoreMaximum":10,"comment":"Well done","timestamp":"2026-11-14T12:00:00.123+00:00","activityProgress":"Completed","gradingProgress":"FullyGraded"}', $request->body);
    }

    public function testAScoreGoesToTheLineItemGiven(): void
    {
        $this->answers[] = new ServiceResponse(200, [], '');
        $this->ags()->publishScore($this->lti->platform, $this->endpoint(), $this->score(), Url::fromString('https://lms.example.org/c/1/lineitems/3/'));

        self::assertSame('https://lms.example.org/c/1/lineitems/3/scores', $this->serviceRequests()[0]->url->toString());
    }

    public function testLineItemsAreListedAndPaged(): void
    {
        $this->answers[] = RecordingServiceClient::json(200, [['id' => self::ITEM, 'scoreMaximum' => 10, 'label' => 'Quiz', 'resourceLinkId' => 'link-1', 'tag' => 'quiz', 'resourceId' => 'r', 'startDateTime' => '2026-11-14T00:00:00Z', 'endDateTime' => '2026-11-20T00:00:00+02:00', 'gradesReleased' => false]], ['link' => ['<https://lms.example.org/c/1/lineitems?page=2>; rel="next"']]);
        $this->answers[] = RecordingServiceClient::json(200, []);
        $page = $this->ags()->lineItems($this->lti->platform, $this->endpoint(), 'link-1', 'r', 'quiz', 5);

        self::assertCount(1, $page->lineItems);
        $item = $page->lineItems[0];
        self::assertSame([10.0, 'Quiz', self::ITEM, 'link-1', 'quiz', 'r', false], [$item->scoreMaximum, $item->label, $item->id?->toString(), $item->resourceLinkId, $item->tag, $item->resourceId, $item->gradesReleased]);
        self::assertSame('2026-11-20T00:00:00+02:00', $item->toArray()['endDateTime']);
        self::assertSame('https://lms.example.org/c/1/lineitems?page=2', $page->next?->toString());
        self::assertSame('https://lms.example.org/c/1/lineitems?tenant=a&resource_link_id=link-1&resource_id=r&tag=quiz&limit=5', $this->serviceRequests()[0]->url->toString());
        self::assertSame(['Authorization' => 'Bearer token:'.Scope::LINE_ITEM_READ_ONLY, 'Accept' => MediaType::LINE_ITEM_CONTAINER], $this->serviceRequests()[0]->headers);

        $next = $this->ags()->lineItems($this->lti->platform, $this->endpoint(), 'ignored', page: $page->next);
        self::assertSame([], $next->lineItems);
        self::assertNull($next->next);
        self::assertSame('https://lms.example.org/c/1/lineitems?page=2', $this->serviceRequests()[1]->url->toString());
    }

    public function testTheFullScopeServesTheReads(): void
    {
        $this->answers[] = RecordingServiceClient::json(200, ['id' => self::ITEM, 'scoreMaximum' => 1, 'label' => 'L']);
        $this->answers[] = RecordingServiceClient::json(200, []);
        $endpoint = $this->endpoint([Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY]);

        self::assertSame('L', $this->ags()->lineItem($this->lti->platform, $endpoint, Url::fromString(self::ITEM))->label);
        $this->ags()->lineItems($this->lti->platform, $endpoint);
        self::assertSame('Bearer token:'.Scope::LINE_ITEM, $this->serviceRequests()[0]->headers['Authorization']);
        self::assertSame(MediaType::LINE_ITEM, $this->serviceRequests()[0]->headers['Accept']);
        self::assertSame(self::ITEMS, $this->serviceRequests()[1]->url->toString());
    }

    public function testLineItemsAreCreatedChangedAndDeleted(): void
    {
        $created = ['id' => self::ITEM, 'scoreMaximum' => 20, 'label' => 'Essay'];
        $this->answers[] = RecordingServiceClient::json(201, $created);
        $this->answers[] = RecordingServiceClient::json(200, ['label' => 'Essay 2'] + $created);
        $this->answers[] = new ServiceResponse(204, [], '');
        $ags = $this->ags();
        $endpoint = $this->endpoint([Scope::LINE_ITEM]);

        $item = $ags->createLineItem($this->lti->platform, $endpoint, new LineItem(20.0, 'Essay', Url::fromString('https://ignored.example.org/'), tag: 'essay'));
        self::assertSame(self::ITEM, $item->id?->toString());
        self::assertSame('{"scoreMaximum":20,"label":"Essay","tag":"essay"}', $this->serviceRequests()[0]->body);
        self::assertSame(['POST', self::ITEMS, MediaType::LINE_ITEM, MediaType::LINE_ITEM], [$this->serviceRequests()[0]->method, $this->serviceRequests()[0]->url->toString(), $this->serviceRequests()[0]->headers['Content-Type'], $this->serviceRequests()[0]->headers['Accept']]);

        self::assertSame('Essay 2', $ags->updateLineItem($this->lti->platform, $endpoint, $item)->label);
        self::assertSame(['PUT', self::ITEM], [$this->serviceRequests()[1]->method, $this->serviceRequests()[1]->url->toString()]);
        self::assertSame('{"id":"'.self::ITEM.'","scoreMaximum":20,"label":"Essay"}', $this->serviceRequests()[1]->body);

        $ags->deleteLineItem($this->lti->platform, $endpoint, Url::fromString(self::ITEM));
        self::assertSame(['DELETE', self::ITEM, null], [$this->serviceRequests()[2]->method, $this->serviceRequests()[2]->url->toString(), $this->serviceRequests()[2]->body]);

        $this->expectExceptionMessageIsOrContains('A line item is changed by its address.');
        $ags->updateLineItem($this->lti->platform, $endpoint, new LineItem(1.0, 'x'));
    }

    public function testResultsAreRead(): void
    {
        $this->answers[] = RecordingServiceClient::json(200, [['id' => 'https://lms.example.org/r/1', 'scoreOf' => self::ITEM, 'userId' => 'user-7', 'resultScore' => 0.85, 'resultMaximum' => 1, 'comment' => 'ok']]);
        $results = $this->ags()->results($this->lti->platform, $this->endpoint([Scope::RESULT_READ_ONLY]), Url::fromString(self::ITEM), 'user-7');

        self::assertSame(['id' => 'https://lms.example.org/r/1', 'scoreOf' => self::ITEM, 'userId' => 'user-7', 'resultScore' => 0.85, 'resultMaximum' => 1.0, 'comment' => 'ok'], $results[0]->toArray());
        self::assertSame('https://lms.example.org/c/1/lineitems/9/results?tenant=a&user_id=user-7', $this->serviceRequests()[0]->url->toString());
        self::assertSame(MediaType::RESULT_CONTAINER, $this->serviceRequests()[0]->headers['Accept']);

        $this->answers[] = RecordingServiceClient::json(200, []);
        $this->ags()->results($this->lti->platform, $this->endpoint([Scope::RESULT_READ_ONLY]), Url::fromString(self::ITEM));
        self::assertSame('https://lms.example.org/c/1/lineitems/9/results?tenant=a', $this->serviceRequests()[1]->url->toString());
    }

    public function testACallNeedsTheScopeOfTheLaunch(): void
    {
        $ags = $this->ags();
        $cases = [
            'score' => [fn () => $ags->publishScore($this->lti->platform, $this->endpoint([Scope::LINE_ITEM]), $this->score()), Scope::SCORE],
            'create' => [fn (): LineItem => $ags->createLineItem($this->lti->platform, $this->endpoint([Scope::SCORE]), new LineItem(1.0, 'x')), Scope::LINE_ITEM],
            'update' => [fn (): LineItem => $ags->updateLineItem($this->lti->platform, $this->endpoint([Scope::SCORE]), new LineItem(1.0, 'x')), Scope::LINE_ITEM],
            'delete' => [fn () => $ags->deleteLineItem($this->lti->platform, $this->endpoint([Scope::SCORE]), Url::fromString(self::ITEM)), Scope::LINE_ITEM],
            'results' => [fn (): array => $ags->results($this->lti->platform, $this->endpoint([Scope::SCORE]), Url::fromString(self::ITEM)), Scope::RESULT_READ_ONLY],
            'read' => [fn (): LineItemPage => $ags->lineItems($this->lti->platform, $this->endpoint([Scope::SCORE])), Scope::LINE_ITEM_READ_ONLY],
        ];

        foreach ($cases as $name => [$call, $scope]) {
            try {
                $call();
                self::fail($name);
            } catch (InvalidRequest $invalidRequest) {
                self::assertSame('The launch does not grant the scope "'.$scope.'".', $invalidRequest->getMessage(), $name);
            }
        }

        self::assertSame([], $this->client->requests);
    }

    public function testTheAddressesComeFromTheLaunch(): void
    {
        $endpoint = new AgsEndpoint(Scope::ALL);

        try {
            $this->ags()->publishScore($this->lti->platform, $endpoint, $this->score());
            self::fail('A score was sent nowhere.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertSame('The launch names no line item to score, and none is given.', $invalidRequest->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The launch names no line items of the context.');
        $this->ags()->lineItems($this->lti->platform, $endpoint);
    }

    public function testABadAnswerFails(): void
    {
        $cases = [
            'status' => [new ServiceResponse(403, [], ''), ServiceFailed::class, 'The service at "'.self::ITEMS.'" answered 403.'],
            'not json' => [new ServiceResponse(200, [], '<html>'), ServiceFailed::class, 'The service answered something that is not JSON.'],
            'scalar' => [new ServiceResponse(200, [], '3'), ServiceFailed::class, 'The service answered JSON that is neither an object nor a list.'],
            'object' => [RecordingServiceClient::json(200, ['a' => 1]), InvalidData::class, 'The line items of a context are a list.'],
            'item' => [RecordingServiceClient::json(200, [['label' => 'x']]), InvalidData::class, 'A line item has its "scoreMaximum".'],
            'not item' => [RecordingServiceClient::json(200, [3]), InvalidData::class, 'A line item has its "scoreMaximum".'],
        ];

        foreach ($cases as $name => [$answer, $class, $message]) {
            $this->answers[] = $answer;

            try {
                $this->ags()->lineItems($this->lti->platform, $this->endpoint());
                self::fail($name);
            } catch (ServiceFailed|InvalidData $exception) {
                self::assertInstanceOf($class, $exception, $name);
                self::assertSame($message, $exception->getMessage(), $name);
            }
        }

        $this->answers[] = RecordingServiceClient::json(200, ['a' => 1]);
        $this->expectExceptionMessageIsOrContains('The results of a line item are a list.');
        $this->ags()->results($this->lti->platform, $this->endpoint([Scope::RESULT_READ_ONLY]), Url::fromString(self::ITEM));
    }

    public function testTheModelsCheckTheirValues(): void
    {
        $at = new DateTimeImmutable('2026-11-14');
        $cases = [
            static fn (): LineItem => new LineItem(0.0, 'x'),
            static fn (): LineItem => new LineItem(\INF, 'x'),
            static fn (): LineItem => new LineItem(1.0, ' '),
            static fn (): LineItem => new LineItem(1.0, str_repeat('x', 4097)),
            static fn (): Score => new Score('', ActivityProgress::Started, GradingProgress::Pending, $at),
            static fn (): Score => new Score(str_repeat('u', 256), ActivityProgress::Started, GradingProgress::Pending, $at),
            static fn (): Score => new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, 1.0),
            static fn (): Score => new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, -1.0, 10.0),
            static fn (): Score => new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, \NAN, 10.0),
            static fn (): Score => new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, null, 0.0),
            static fn (): Score => new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, null, \INF),
            static fn (): Score => new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, comment: str_repeat('c', 4097)),
            static fn (): Score => Score::fromArray(['userId' => 'u', 'activityProgress' => 'Done', 'gradingProgress' => 'FullyGraded', 'timestamp' => '2026-11-14T00:00:00Z']),
            static fn (): Score => Score::fromArray(['userId' => 'u', 'activityProgress' => 'Completed', 'gradingProgress' => 'Graded', 'timestamp' => '2026-11-14T00:00:00Z']),
            static fn (): Score => Score::fromArray(['userId' => 'u', 'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded']),
            static fn (): Score => Score::fromArray(['userId' => 'u', 'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded', 'timestamp' => '2026-11-14']),
            static fn (): LineItem => LineItem::fromArray(['scoreMaximum' => '10', 'label' => 'x']),
            static fn (): LineItem => LineItem::fromArray(['scoreMaximum' => 10, 'label' => 'x', 'gradesReleased' => 'yes']),
        ];

        foreach ($cases as $index => $case) {
            try {
                $case();
                self::fail('Case '.$index.' was accepted.');
            } catch (InvalidData) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(0.0, new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at, 0.0, 1.0)->scoreGiven);
        self::assertSame(['userId' => 'u', 'timestamp' => '2026-11-14T00:00:00.000+00:00', 'activityProgress' => 'Started', 'gradingProgress' => 'Pending'], new Score('u', ActivityProgress::Started, GradingProgress::Pending, $at)->toArray());
        $read = Score::fromArray(['userId' => 'u', 'activityProgress' => 'Completed', 'gradingProgress' => 'FullyGraded', 'timestamp' => '2026-11-14T10:00:00.5-05:00', 'scoreGiven' => 1, 'scoreMaximum' => 2, 'comment' => 'c']);
        self::assertSame(['u', 1.0, 2.0, 'c', '2026-11-14T10:00:00.500-05:00'], [$read->userId, $read->scoreGiven, $read->scoreMaximum, $read->comment, $read->toArray()['timestamp']]);
        self::assertSame(['id' => self::ITEM, 'scoreMaximum' => 1.0, 'label' => 'x'], new LineItem(1.0, 'x')->withId(Url::fromString(self::ITEM))->toArray());
        self::assertSame(['userId' => 'u'], new Result('u')->toArray());
    }

    public function testEachCallAsksForTheScopeItNeeds(): void
    {
        $item = ['id' => self::ITEM, 'scoreMaximum' => 10, 'label' => 'Quiz'];
        $this->answers = [RecordingServiceClient::json(200, $item), RecordingServiceClient::json(200, $item), new ServiceResponse(204, [], ''), RecordingServiceClient::json(200, [])];
        $endpoint = $this->endpoint(Scope::ALL);
        $created = $this->ags()->createLineItem($this->lti->platform, $endpoint, new LineItem(10.0, 'Quiz'));
        $this->ags()->updateLineItem($this->lti->platform, $endpoint, $created);
        $this->ags()->deleteLineItem($this->lti->platform, $endpoint, Url::fromString(self::ITEM));
        $this->ags()->results($this->lti->platform, $endpoint, Url::fromString(self::ITEM));

        self::assertSame(
            ['Bearer token:'.Scope::LINE_ITEM, 'Bearer token:'.Scope::LINE_ITEM, 'Bearer token:'.Scope::LINE_ITEM, 'Bearer token:'.Scope::RESULT_READ_ONLY],
            array_map(static fn (ServiceRequest $request): string => $request->headers['Authorization'], $this->serviceRequests()),
        );
    }

    public function testALineItemOnTheLocalNetworkIsRefusedByDefault(): void
    {
        $local = Url::fromString('https://lms.localhost/c/1/lineitems/9', true);
        $endpoint = new AgsEndpoint(Scope::ALL, null, $local);
        $this->answers = [new ServiceResponse(204, [], '')];

        $this->ags(true)->publishScore($this->lti->platform, $endpoint, $this->score());
        self::assertSame('https://lms.localhost/c/1/lineitems/9/scores', $this->serviceRequests()[0]->url->toString());

        $this->expectException(InvalidUrl::class);
        $this->ags()->publishScore($this->lti->platform, $endpoint, $this->score());
    }

    public function testTheFormsOfALineItemAndAResult(): void
    {
        $item = ['id' => 'https://lms.localhost/i', 'scoreMaximum' => 10, 'label' => 'Quiz'];
        self::assertSame('https://lms.localhost/i', LineItem::fromArray($item, true)->id?->toString());
        self::assertSame([], array_diff_key(new LineItem(10.0, 'Quiz')->toArray(), ['scoreMaximum' => 1, 'label' => 1]));
        self::assertSame(str_repeat('l', 4096), new LineItem(10.0, str_repeat('l', 4096))->label);
        self::assertSame(str_repeat('u', 255), new Score(str_repeat('u', 255), ActivityProgress::Started, GradingProgress::Pending, new DateTimeImmutable('2026-11-14'), comment: str_repeat('c', 4096))->userId);

        $result = ['id' => 'https://lms.localhost/r', 'userId' => 'u', 'resultScore' => 1, 'resultMaximum' => 2];
        self::assertSame('https://lms.localhost/r', Result::fromArray($result, true)->id?->toString());

        foreach ([static fn (): LineItem => LineItem::fromArray($item), static fn (): Result => Result::fromArray($result), static fn (): Result => Result::fromArray([])] as $read) {
            try {
                $read();
                self::fail('A document that is not valid was read.');
            } catch (InvalidData) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @param list<string> $scopes
     */
    private function endpoint(array $scopes = [Scope::LINE_ITEM_READ_ONLY, Scope::SCORE]): AgsEndpoint
    {
        return new AgsEndpoint($scopes, Url::fromString(self::ITEMS), Url::fromString(self::ITEM));
    }

    private function ags(bool $allowLocalhost = false): AgsClient
    {
        $call = new ServiceCall($this->client, new AccessTokenProvider($this->client, $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock));

        return $allowLocalhost ? new AgsClient($call, true) : new AgsClient($call);
    }

    /**
     * @return list<ServiceRequest>
     */
    private function serviceRequests(): array
    {
        return array_values(array_filter($this->client->requests, static fn (ServiceRequest $request): bool => 'https://lms.example.org/token' !== $request->url->toString()));
    }

    private function score(): Score
    {
        return new Score('user-7', ActivityProgress::Submitted, GradingProgress::Pending, new DateTimeImmutable('2026-11-14'));
    }
}
