<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure\SecretScanning;

use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Domain\Event\TokenLeaked;
use Phil\ApiKeysBundle\Domain\Event\TokenRevoked;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubPublicKeys;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubSecretScanningController;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\LeakReport;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\GitHubKeys;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(GitHubSecretScanningController::class)]
final class GitHubSecretScanningControllerTest extends TestCase
{
    private GitHubKeys $keys;

    private RecordingDispatcher $events;

    private ApiTokens $tokens;

    private int $fetches = 0;

    protected function setUp(): void
    {
        $this->keys = new GitHubKeys();
        $this->events = new RecordingDispatcher();
        $this->tokens = Tokens::service(events: $this->events);
    }

    public function testEachTokenOfThisApplicationIsRevokedAndLabelled(): void
    {
        $issued = $this->tokens->create('alice', 'leaked', []);
        $kept = $this->tokens->create('alice', 'kept', []);
        $this->events->clear();
        $body = json_encode([
            ['token' => $issued->plainText, 'type' => 'test_api_token', 'url' => 'https://github.com/o/r/blob/x/f', 'source' => 'content'],
            ['token' => 'test_notours', 'type' => 'test_api_token', 'url' => 'https://github.com/o/r/blob/x/g', 'source' => 'content'],
        ], \JSON_THROW_ON_ERROR);

        $response = $this->post($body, $this->keys->identifier, $this->keys->sign($body));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame([
            ['token_hash' => hash('sha256', $issued->plainText), 'token_type' => 'test_api_token', 'label' => 'true_positive'],
            ['token_hash' => hash('sha256', 'test_notours'), 'token_type' => 'test_api_token', 'label' => 'false_positive'],
        ], json_decode((string) $response->getContent(), true));
        self::assertStringNotContainsString($issued->plainText, (string) $response->getContent());
        self::assertTrue($issued->token->isRevoked());
        self::assertFalse($kept->token->isRevoked());
        self::assertEquals([new TokenRevoked($issued->token->id()), new TokenLeaked($issued->token->id())], $this->events->events);
    }

    public function testAnEmptyReportIsAnsweredWithAnEmptyList(): void
    {
        $response = $this->post('[]', $this->keys->identifier, $this->keys->sign('[]'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('[]', $response->getContent());
    }

    public function testNothingIsReadWithoutAValidSignature(): void
    {
        $issued = $this->tokens->create('alice', 'leaked', []);
        $body = json_encode([['token' => $issued->plainText, 'type' => 't']], \JSON_THROW_ON_ERROR);
        $other = new GitHubKeys();

        foreach ([
            'no identifier' => ['', $this->keys->sign($body)],
            'an identifier with a space' => ['a b', $this->keys->sign($body)],
            'an identifier with a space after' => ['ab ', $this->keys->sign($body)],
            'an identifier with a space before' => [' ab', $this->keys->sign($body)],
            'an identifier ending with a newline' => ["ab\n", $this->keys->sign($body)],
            'an identifier too long' => [str_repeat('i', 257), $this->keys->sign($body)],
            'no signature' => [$this->keys->identifier, ''],
            'a signature too long' => [$this->keys->identifier, str_repeat('A', GitHubSecretScanningController::MAX_SIGNATURE_LENGTH + 1)],
            'no identifier and no signature' => ['', ''],
        ] as $case => [$identifier, $signature]) {
            $response = $this->post($body, $identifier, $signature);
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $case);
            self::assertSame('', $response->getContent(), $case);
            self::assertSame(0, $this->fetches, $case.': no key was looked for');
        }

        foreach ([
            'a signature not in base64' => [$this->keys->identifier, '!!!'],
            'a signature of another body' => [$this->keys->identifier, $this->keys->sign($body.' ')],
            'a signature by another key' => [$this->keys->identifier, $other->sign($body)],
            'a signature that is no signature' => [$this->keys->identifier, base64_encode('nope')],
            'an unknown key' => ['unknown', $this->keys->sign($body)],
        ] as $case => [$identifier, $signature]) {
            $response = $this->post($body, $identifier, $signature);
            self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $case);
            self::assertSame('', $response->getContent(), $case);
        }

        self::assertFalse($issued->token->isRevoked());
    }

    public function testHeadersThatAreMissingAreRefused(): void
    {
        $request = Request::create('/', 'POST', content: '[]');
        $controller = new GitHubSecretScanningController($this->tokens, new GitHubPublicKeys(new MockHttpClient(), new ArrayAdapter(), new MockClock()));

        self::assertSame(Response::HTTP_FORBIDDEN, $controller($request)->getStatusCode());

        $request->headers->set(GitHubSecretScanningController::IDENTIFIER_HEADER, 'id');

        self::assertSame(Response::HTTP_FORBIDDEN, $controller($request)->getStatusCode());
    }

    public function testTheLongestIdentifierAndSignatureAreRead(): void
    {
        $response = $this->post('[]', str_repeat('i', 256), str_repeat('A', GitHubSecretScanningController::MAX_SIGNATURE_LENGTH));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame(1, $this->fetches, 'the key was looked for');
    }

    public function testATooLongBodyIsRefusedBeforeAnyKeyIsFetched(): void
    {
        $body = '['.str_repeat(' ', LeakReport::MAX_BYTES - 1).']';

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $this->post($body, $this->keys->identifier, $this->keys->sign($body))->getStatusCode());
        self::assertSame(0, $this->fetches);

        $longest = '['.str_repeat(' ', LeakReport::MAX_BYTES - 2).']';
        self::assertSame(Response::HTTP_OK, $this->post($longest, $this->keys->identifier, $this->keys->sign($longest))->getStatusCode());
    }

    public function testASignedBodyThatIsNotAReportIsABadRequest(): void
    {
        $response = $this->post('{"token":"x"}', $this->keys->identifier, $this->keys->sign('{"token":"x"}'));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    private function post(string $body, string $identifier, string $signature): Response
    {
        $http = new MockHttpClient(function (): MockResponse {
            ++$this->fetches;

            return new MockResponse($this->keys->document());
        });
        $controller = new GitHubSecretScanningController($this->tokens, new GitHubPublicKeys($http, new ArrayAdapter(), new MockClock()));
        $request = Request::create('/', 'POST', content: $body);
        $request->headers->set(GitHubSecretScanningController::IDENTIFIER_HEADER, $identifier);
        $request->headers->set(GitHubSecretScanningController::SIGNATURE_HEADER, $signature);

        return $controller($request);
    }
}
