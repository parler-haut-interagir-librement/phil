<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Service;

use ArrayObject;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\NrpsEndpoint;
use Phil\Lti\Message\Roles;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Nrps\MediaType;
use Phil\Lti\Service\Nrps\Member;
use Phil\Lti\Service\Nrps\MembershipPage;
use Phil\Lti\Service\Nrps\MemberStatus;
use Phil\Lti\Service\Nrps\NrpsClient;
use Phil\Lti\Service\Scope;
use Phil\Lti\Service\ServiceCall;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tests\Fixture\RecordingServiceClient;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(NrpsClient::class)]
#[CoversClass(Member::class)]
#[CoversClass(MembershipPage::class)]
final class NrpsClientTest extends TestCase
{
    private const string MEMBERS = 'https://lms.example.org/c/1/members';

    private Lti $lti;

    /** @var list<ServiceResponse> */
    private array $answers = [];

    private RecordingServiceClient $client;

    protected function setUp(): void
    {
        $this->lti = new Lti();
        $this->client = new RecordingServiceClient(function (ServiceRequest $request): ServiceResponse {
            if ('https://lms.example.org/token' === $request->url->toString()) {
                return RecordingServiceClient::json(200, ['access_token' => 'token-1', 'token_type' => 'Bearer', 'expires_in' => 3600]);
            }

            return array_shift($this->answers) ?? new ServiceResponse(500, [], '');
        });
    }

    public function testTheMembersAreReadPageAfterPage(): void
    {
        $this->answers[] = $this->page([$this->member('u1'), $this->member('u2')], ['<https://lms.example.org/c/1/members?p=2>; rel="next", </c/1/members/changes>; rel="differences"']);
        $this->answers[] = $this->page([$this->member('u3', 'Inactive')]);

        $members = iterator_to_array($this->nrps()->members($this->lti->platform, $this->endpoint(), ContextRole::Learner->value, 2), false);

        self::assertSame(['u1', 'u2', 'u3'], array_map(static fn (Member $member): string => $member->userId, $members));
        self::assertSame(MemberStatus::Inactive, $members[2]->status);
        $requests = $this->serviceRequests();
        self::assertSame(self::MEMBERS.'?role='.rawurlencode(ContextRole::Learner->value).'&limit=2', $requests[0]->url->toString());
        self::assertSame(['Authorization' => 'Bearer token-1', 'Accept' => MediaType::MEMBERSHIP_CONTAINER], $requests[0]->headers);
        self::assertSame('https://lms.example.org/c/1/members?p=2', $requests[1]->url->toString());
        parse_str((string) $this->client->requests[0]->body, $body);
        self::assertSame(Scope::MEMBERSHIP_READ_ONLY, $body['scope']);
    }

    public function testAPageCarriesItsContextAndLinks(): void
    {
        $this->answers[] = $this->page([$this->member('u1')], ['<https://lms.example.org/c/1/members?p=2>; rel="next", </c/1/members/changes>; rel="differences"']);
        $page = $this->nrps()->page($this->lti->platform, $this->endpoint());

        self::assertSame('course-1', $page->context->id);
        self::assertSame('CS101', $page->context->label);
        self::assertSame('https://lms.example.org/c/1/members?p=2', $page->next?->toString());
        self::assertSame('https://lms.example.org/c/1/members/changes', $page->differences?->toString());
        self::assertSame(self::MEMBERS, $this->serviceRequests()[0]->url->toString());
        $member = $page->members[0];
        self::assertSame(['user_id' => 'u1', 'status' => 'Active', 'roles' => [ContextRole::Learner->value], 'name' => 'N u1', 'given_name' => 'G', 'family_name' => 'F', 'email' => 'u1@example.org', 'picture' => 'https://lms.example.org/p.png', 'lis_person_sourcedid' => 's'], $member->toArray());
    }

    public function testTheReadStopsAtTheMostPages(): void
    {
        for ($page = 0; $page < NrpsClient::MAX_PAGES; ++$page) {
            $this->answers[] = $this->page([$this->member('u'.$page)], ['<https://lms.example.org/c/1/members?p='.($page + 1).'>; rel="next"']);
        }

        $read = new ArrayObject();

        try {
            foreach ($this->nrps()->members($this->lti->platform, $this->endpoint()) as $member) {
                $read->append($member->userId);
            }

            self::fail('The read did not stop.');
        } catch (ServiceFailed $serviceFailed) {
            self::assertSame('The members of the context span more than 100 pages.', $serviceFailed->getMessage());
        }

        self::assertCount(NrpsClient::MAX_PAGES, $read);
        self::assertCount(NrpsClient::MAX_PAGES, $this->serviceRequests());
    }

    public function testABadPageIsRefused(): void
    {
        $cases = [
            'no context' => [RecordingServiceClient::json(200, ['members' => []]), 'The member "context" is required.'],
            'members object' => [RecordingServiceClient::json(200, ['context' => ['id' => 'c'], 'members' => ['a' => 1]]), 'The member "members" is a list of 1000 objects at most.'],
            'no user' => [RecordingServiceClient::json(200, ['context' => ['id' => 'c'], 'members' => [['roles' => []]]]), 'The member "user_id" is required.'],
            'status' => [RecordingServiceClient::json(200, ['context' => ['id' => 'c'], 'members' => [['user_id' => 'u', 'status' => 'Gone']]]), 'The status of a member is not one of NRPS.'],
        ];

        foreach ($cases as $name => [$answer, $message]) {
            $this->answers[] = $answer;

            try {
                $this->nrps()->page($this->lti->platform, $this->endpoint());
                self::fail($name);
            } catch (InvalidData $invalidData) {
                self::assertSame($message, $invalidData->getMessage(), $name);
            }
        }

        $this->expectException(InvalidData::class);
        new Member('', Roles::of([]));
    }

    public function testTheQueryOfTheAddressIsKeptAndTheOneOfAPageIsNot(): void
    {
        $tenant = new NrpsEndpoint(Url::fromString(self::MEMBERS.'?tenant=a'));
        $this->answers = [$this->page([]), $this->page([]), $this->page([])];

        $this->nrps()->page($this->lti->platform, $tenant);
        $this->nrps()->page($this->lti->platform, $tenant, 'Learner', 5);
        $this->nrps()->page($this->lti->platform, $tenant, 'Learner', 5, Url::fromString(self::MEMBERS.'?p=2'));

        self::assertSame([self::MEMBERS.'?tenant=a', self::MEMBERS.'?tenant=a&role=Learner&limit=5', self::MEMBERS.'?p=2'], array_map(static fn (ServiceRequest $request): string => $request->url->toString(), $this->serviceRequests()));
    }

    public function testAnAddressOnTheLocalNetworkIsRefusedByDefault(): void
    {
        $endpoint = new NrpsEndpoint(Url::fromString('https://lms.localhost/c/1/members', true));
        $this->answers = [$this->page([])];

        self::assertSame([], $this->nrps(true)->page($this->lti->platform, $endpoint, 'Learner')->members);

        $this->expectException(InvalidUrl::class);
        $this->nrps()->page($this->lti->platform, $endpoint, 'Learner');
    }

    private function nrps(bool $allowLocalhost = false): NrpsClient
    {
        $call = new ServiceCall($this->client, new AccessTokenProvider($this->client, $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock));

        return $allowLocalhost ? new NrpsClient($call, true) : new NrpsClient($call);
    }

    private function endpoint(): NrpsEndpoint
    {
        return new NrpsEndpoint(Url::fromString(self::MEMBERS));
    }

    /**
     * @param list<array<string, mixed>> $members
     * @param list<string>               $links
     */
    private function page(array $members, array $links = []): ServiceResponse
    {
        return RecordingServiceClient::json(200, ['id' => self::MEMBERS, 'context' => ['id' => 'course-1', 'label' => 'CS101', 'title' => 'Intro'], 'members' => $members], [] === $links ? [] : ['link' => $links]);
    }

    /**
     * @return array<string, mixed>
     */
    private function member(string $id, string $status = 'Active'): array
    {
        return ['user_id' => $id, 'status' => $status, 'roles' => ['Learner'], 'name' => 'N '.$id, 'given_name' => 'G', 'family_name' => 'F', 'email' => $id.'@example.org', 'picture' => 'https://lms.example.org/p.png', 'lis_person_sourcedid' => 's'];
    }

    /**
     * @return list<ServiceRequest>
     */
    private function serviceRequests(): array
    {
        return array_values(array_filter($this->client->requests, static fn (ServiceRequest $request): bool => 'https://lms.example.org/token' !== $request->url->toString()));
    }
}
