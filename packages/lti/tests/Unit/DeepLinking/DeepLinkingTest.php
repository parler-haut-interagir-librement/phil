<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\DeepLinking;

use DateTimeImmutable;
use Phil\Lti\Contract\ContentItem;
use Phil\Lti\DeepLinking\ContentItems;
use Phil\Lti\DeepLinking\FileItem;
use Phil\Lti\DeepLinking\HtmlItem;
use Phil\Lti\DeepLinking\Image;
use Phil\Lti\DeepLinking\ImageItem;
use Phil\Lti\DeepLinking\LineItemRequest;
use Phil\Lti\DeepLinking\LinkItem;
use Phil\Lti\DeepLinking\ResourceLinkItem;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Message\Claim;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\DeepLinkingSettings;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\DeepLinkingResult;
use Phil\Lti\Platform\DeepLinkingReturn;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tests\Fixture\RecordingNonces;
use Phil\Lti\Tool\DeepLinkingResponder;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(DeepLinkingResponder::class)]
#[CoversClass(DeepLinkingReturn::class)]
#[CoversClass(DeepLinkingResult::class)]
#[CoversClass(ContentItems::class)]
#[CoversClass(ResourceLinkItem::class)]
#[CoversClass(LinkItem::class)]
#[CoversClass(FileItem::class)]
#[CoversClass(HtmlItem::class)]
#[CoversClass(ImageItem::class)]
#[CoversClass(Image::class)]
#[CoversClass(LineItemRequest::class)]
final class DeepLinkingTest extends TestCase
{
    private Lti $lti;

    protected function setUp(): void
    {
        $this->lti = new Lti();
    }

    public function testTheToolAnswersAndThePlatformReadsTheItems(): void
    {
        $items = self::items();
        $post = $this->responder()->respond($this->launch(), $items, 'Done', 'logged', 'oops', 'error logged');
        $result = $this->platform()->read($post->fields['JWT']);

        self::assertSame('https://lms.example.org/dl', $post->action->toString());
        self::assertSame(Lti::CLIENT, $result->clientId);
        self::assertSame(Lti::DEPLOYMENT, $result->deploymentId);
        self::assertEquals($items, $result->items);
        self::assertSame(['opaque', 'Done', 'logged', 'oops', 'error logged'], [$result->data, $result->message, $result->log, $result->errorMessage, $result->errorLog]);

        $claims = Jwt::parse($post->fields['JWT'])->claims;
        self::assertSame($claims, $result->claims);
        self::assertIsString($claims['nonce']);
        self::assertSame(hash('sha256', \strlen(Lti::CLIENT).':'.Lti::CLIENT."\n".$claims['nonce']), $result->key);
        self::assertSame($result->key, DeepLinkingResult::key(Lti::CLIENT, $claims['nonce']));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $result->key);
        self::assertEquals($result, DeepLinkingResult::fromClaims($result->claims));
    }

    public function testTheClaimsOfAResponseAreReadAgainWithoutItsToken(): void
    {
        $claims = ['iss' => 'client', 'nonce' => 'n', Claim::DEPLOYMENT_ID => 'd', Claim::CONTENT_ITEMS => [['type' => 'link', 'url' => 'http://localhost/a']]];
        $result = DeepLinkingResult::fromClaims($claims, true);

        self::assertSame([DeepLinkingResult::key('client', 'n'), 'client', 'd', 1, null, null, null, null, null, $claims], [$result->key, $result->clientId, $result->deploymentId, \count($result->items), $result->data, $result->message, $result->log, $result->errorMessage, $result->errorLog, $result->claims]);
        self::assertNotSame(DeepLinkingResult::key("a\nb", 'c'), DeepLinkingResult::key('a', "b\nc"));

        foreach (['iss', 'nonce', Claim::DEPLOYMENT_ID] as $claim) {
            try {
                DeepLinkingResult::fromClaims(array_diff_key($claims, [$claim => true]), true);
                self::fail($claim);
            } catch (InvalidToken $invalidToken) {
                self::assertStringContainsString('"'.$claim.'"', $invalidToken->getMessage());
            }
        }

        try {
            DeepLinkingResult::fromClaims($claims);
            self::fail('A local address was read.');
        } catch (InvalidToken) {
            self::addToAssertionCount(1);
        }
    }

    public function testTheResponseClaims(): void
    {
        $post = $this->responder()->respond($this->launch(), []);
        $jwt = Jwt::parse($post->fields['JWT']);
        $now = $this->lti->clock->now()->getTimestamp();

        self::assertSame('tool-1', $jwt->keyId());
        self::assertSame([Lti::CLIENT, Lti::ISSUER, $now, $now + 300, 'LtiDeepLinkingResponse', '1.3.0', Lti::DEPLOYMENT, [], 'opaque'], [
            $jwt->claims['iss'], $jwt->claims['aud'], $jwt->claims['iat'], $jwt->claims['exp'],
            $jwt->claims[Claim::MESSAGE_TYPE], $jwt->claims[Claim::VERSION], $jwt->claims[Claim::DEPLOYMENT_ID], $jwt->claims[Claim::CONTENT_ITEMS], $jwt->claims[Claim::DATA],
        ]);
        self::assertIsString($jwt->claims['nonce']);
        self::assertSame(43, \strlen($jwt->claims['nonce']));
        self::assertArrayNotHasKey(Claim::MESSAGE, $jwt->claims);
        self::assertArrayNotHasKey(Claim::ERROR_LOG, $jwt->claims);
        self::assertSame([], $this->platform()->read($post->fields['JWT'])->items);
    }

    public function testTheItemsMustBeAccepted(): void
    {
        $responder = $this->responder();
        $link = new LinkItem(Url::fromString('https://tool.example.com/page'));

        try {
            $responder->respond($this->launch(multiple: false), [$link, $link]);
            self::fail('Two items were sent to a request that accepts one.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertSame('The deep linking request accepts one content item at most.', $invalidRequest->getMessage());
        }

        self::assertCount(1, $responder->respond($this->launch(multiple: false), [$link])->fields);

        try {
            $responder->respond($this->launch(types: ['ltiResourceLink']), [$link]);
            self::fail('A link was sent to a request that does not accept links.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertSame('The deep linking request does not accept items of the type "link".', $invalidRequest->getMessage());
        }

        $resourceLaunch = new Launch(Lti::ISSUER, Lti::CLIENT, Lti::message(), []);
        $this->expectExceptionMessageIsOrContains('Content items answer a deep linking request only.');
        $responder->respond($resourceLaunch, []);
    }

    public function testThePlatformChecksTheResponse(): void
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $base = ['iss' => Lti::CLIENT, 'aud' => Lti::ISSUER, 'iat' => $now, 'exp' => $now + 60, Claim::MESSAGE_TYPE => 'LtiDeepLinkingResponse', Claim::VERSION => '1.3.0', Claim::DEPLOYMENT_ID => Lti::DEPLOYMENT];
        $cases = [
            'no issuer' => [['iss' => null], InvalidToken::class, 'A deep linking response is issued by the client of the tool.'],
            'unknown client' => [['iss' => 'nope'], UnknownRegistration::class, 'No tool is registered under the client "nope".'],
            'other audience' => [['aud' => 'https://other.example.org'], InvalidToken::class, 'The deep linking response is not addressed to the platform.'],
            'other type' => [[Claim::MESSAGE_TYPE => 'LtiResourceLinkRequest'], InvalidToken::class, 'The token is not a deep linking response of LTI 1.3.'],
            'other version' => [[Claim::VERSION => '1.1'], InvalidToken::class, 'The token is not a deep linking response of LTI 1.3.'],
            'no nonce' => [['nonce' => null], InvalidToken::class, 'The member "nonce" is required.'],
            'unknown deployment' => [[Claim::DEPLOYMENT_ID => 'nope'], UnknownRegistration::class, 'The deployment "nope" is not registered.'],
            'items object' => [[Claim::CONTENT_ITEMS => ['a' => 1]], InvalidToken::class, 'The member "'.Claim::CONTENT_ITEMS.'" is a list of 100 objects at most.'],
        ];

        foreach ($cases as $name => [$changes, $class, $message]) {
            try {
                $this->platform()->read(JwtSigner::sign(array_filter($changes + $base + ['nonce' => $name], static fn (mixed $value): bool => null !== $value), Keys::tool()));
                self::fail($name);
            } catch (InvalidToken|UnknownRegistration $exception) {
                self::assertInstanceOf($class, $exception, $name);
                self::assertSame($message, $exception->getMessage(), $name);
            }
        }

        self::assertSame(Lti::DEPLOYMENT, $this->platform()->read(JwtSigner::sign(['aud' => [Lti::ISSUER, 'x'], 'nonce' => 'list'] + $base, Keys::tool()))->deploymentId);
        $this->expectExceptionMessageIsOrContains('The signature of the token does not verify.');
        $this->platform()->read(JwtSigner::sign(['nonce' => 'forged'] + $base, $this->signedAsTool()));
    }

    public function testTheMembersOfTheResponseHaveTheirLimits(): void
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $base = ['iss' => Lti::CLIENT, 'aud' => Lti::ISSUER, 'iat' => $now, 'exp' => $now + 60, Claim::MESSAGE_TYPE => 'LtiDeepLinkingResponse', Claim::VERSION => '1.3.0', Claim::DEPLOYMENT_ID => Lti::DEPLOYMENT];

        foreach ([[Claim::MESSAGE_TYPE, 64], [Claim::VERSION, 16], ['nonce', 255], [Claim::DEPLOYMENT_ID, 255]] as $index => [$claim, $max]) {
            foreach ([$max, $max + 1] as $size) {
                $thrown = null;

                try {
                    $this->platform()->read(JwtSigner::sign([$claim => str_repeat('x', $size)] + ['nonce' => $index.'-'.$size] + $base, Keys::tool()));
                } catch (InvalidToken|UnknownRegistration $exception) {
                    $thrown = $exception;
                }

                self::assertSame($size > $max, $thrown instanceof Throwable && str_contains($thrown->getMessage(), 'at most'), $claim.' of '.$size.' bytes');
            }
        }
    }

    public function testTheNonceOfAResponseIsKeptPerTool(): void
    {
        $nonces = new RecordingNonces($this->lti->nonces);
        $post = $this->responder()->respond($this->launch(), []);

        new DeepLinkingReturn(Lti::ISSUER, $this->lti->tools, $nonces, $this->lti->verifier)->read($post->fields['JWT']);

        self::assertCount(1, $nonces->used);
        self::assertSame(DeepLinkingReturn::NONCE_SCOPE, $nonces->used[0][0]);
        self::assertStringStartsWith(Lti::CLIENT."\n", $nonces->used[0][1]);
    }

    public function testItemsOnTheLocalNetworkAreRefusedByDefault(): void
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $token = JwtSigner::sign(['iss' => Lti::CLIENT, 'aud' => Lti::ISSUER, 'iat' => $now, 'exp' => $now + 60, Claim::MESSAGE_TYPE => 'LtiDeepLinkingResponse', Claim::VERSION => '1.3.0', Claim::DEPLOYMENT_ID => Lti::DEPLOYMENT, 'nonce' => 'local', Claim::CONTENT_ITEMS => [['type' => 'link', 'url' => 'https://tool.localhost/x']]], Keys::tool());

        self::assertCount(1, new DeepLinkingReturn(Lti::ISSUER, $this->lti->tools, $this->lti->nonces, $this->lti->verifier, true)->read($token)->items);

        $this->lti->nonces->reset();
        $this->expectException(InvalidToken::class);
        $this->platform()->read($token);
    }

    public function testOnlyADeepLinkingRequestIsAnsweredWhateverItCarries(): void
    {
        $person = new Person('u');
        $roles = Roles::of([ContextRole::Instructor]);
        $settings = new DeepLinkingSettings(Url::fromString('https://lms.example.org/dl'), ['link'], ['iframe']);

        $this->expectException(InvalidRequest::class);
        $this->responder()->respond(new Launch(Lti::ISSUER, Lti::CLIENT, new LaunchMessage(MessageType::ResourceLink, Lti::DEPLOYMENT, Url::fromString(Lti::LAUNCH), $person, $roles, new ResourceLink('l'), deepLinking: $settings), []), []);
    }

    public function testAResponseIsReadOnce(): void
    {
        $post = $this->responder()->respond($this->launch(), []);
        $this->platform()->read($post->fields['JWT']);

        $this->expectExceptionMessageIsOrContains('The nonce of the deep linking response was already used.');
        $this->platform()->read($post->fields['JWT']);
    }

    public function testUnknownItemTypesAreSkippedAndBadOnesRefused(): void
    {
        $claims = [Claim::CONTENT_ITEMS => [['type' => 'custom', 'x' => 1], ['type' => 'link', 'url' => 'https://tool.example.com/']]];

        self::assertCount(1, ContentItems::fromClaims($claims, Claim::CONTENT_ITEMS));

        $cases = [
            [['type' => 'link'], 'The member "'.Claim::CONTENT_ITEMS.'[0].url" is required.'],
            [['type' => 'html'], 'The member "'.Claim::CONTENT_ITEMS.'[0].html" is required.'],
            [['type' => 'ltiResourceLink', 'lineItem' => ['label' => 'x']], 'A line item has its "scoreMaximum".'],
            [['type' => 'ltiResourceLink', 'available' => ['startDateTime' => 'tomorrow']], 'The member "'.Claim::CONTENT_ITEMS.'[0].available.startDateTime" is a date and time of ISO 8601 with its offset.'],
            [['type' => 'file', 'url' => 'https://tool.example.com/f', 'expiresAt' => '2026-13-45T99:00:00Z'], 'The member "'.Claim::CONTENT_ITEMS.'[0].expiresAt" is not a valid date: '],
        ];

        foreach ($cases as [$item, $message]) {
            try {
                ContentItems::fromClaims([Claim::CONTENT_ITEMS => [$item]], Claim::CONTENT_ITEMS);
                self::fail($message);
            } catch (InvalidToken $invalidToken) {
                self::assertStringStartsWith($message, $invalidToken->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('is a list of objects.');
        ContentItems::fromClaims([Claim::CONTENT_ITEMS => ['link']], Claim::CONTENT_ITEMS);
    }

    public function testTheInvariantsOfTheItems(): void
    {
        $cases = [
            static fn (): LineItemRequest => new LineItemRequest(0.0),
            static fn (): ResourceLinkItem => new ResourceLinkItem(availableFrom: new DateTimeImmutable('2026-11-15'), availableUntil: new DateTimeImmutable('2026-11-14')),
            static fn (): ResourceLinkItem => new ResourceLinkItem(submissionFrom: new DateTimeImmutable('2026-11-15'), submissionUntil: new DateTimeImmutable('2026-11-14')),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('An invalid item was made.');
            } catch (InvalidData) {
                self::addToAssertionCount(1);
            }
        }

        $same = new DateTimeImmutable('2026-11-14');
        self::assertSame(['startDateTime' => '2026-11-14T00:00:00+00:00', 'endDateTime' => '2026-11-14T00:00:00+00:00'], new ResourceLinkItem(availableFrom: $same, availableUntil: $same, submissionFrom: $same, submissionUntil: $same)->toArray()['available']);
        self::assertSame(['type' => 'ltiResourceLink'], new ResourceLinkItem()->toArray());
        self::assertSame(['endDateTime' => '2026-11-14T00:00:00+00:00'], new ResourceLinkItem(submissionUntil: $same)->toArray()['submission']);
        self::assertSame(['scoreMaximum' => 0.5], new LineItemRequest(0.5)->toArray());
        self::assertSame(['url' => 'https://tool.example.com/i.png'], new Image(Url::fromString('https://tool.example.com/i.png'))->toArray());
    }

    /**
     * @return list<ContentItem>
     */
    public static function items(): array
    {
        $icon = new Image(Url::fromString('https://tool.example.com/icon.png'), 32, 32);

        return [
            new ResourceLinkItem(Url::fromString('https://tool.example.com/quiz/3'), 'Quiz 3', 'A quiz', ['quiz' => '3'], new LineItemRequest(10.0, 'Quiz 3', 'quiz-3', 'graded', true), $icon, $icon, new DateTimeImmutable('2026-11-15T08:00:00+00:00'), new DateTimeImmutable('2026-11-30T23:59:00+00:00'), new DateTimeImmutable('2026-11-15T08:00:00+00:00'), new DateTimeImmutable('2026-11-29T23:59:00+00:00')),
            new LinkItem(Url::fromString('https://tool.example.com/page'), 'Page', 'A page', $icon, $icon),
            new FileItem(Url::fromString('https://tool.example.com/file.pdf'), 'File', 'A file', 'application/pdf', new DateTimeImmutable('2026-11-15T08:00:00+00:00')),
            new HtmlItem('<p>Hello</p>', 'Html', 'Some HTML'),
            new ImageItem(Url::fromString('https://tool.example.com/i.png'), 'Image', 'An image', 640, 480),
        ];
    }

    private function signedAsTool(): RsaPrivateKey
    {
        return RsaPrivateKey::fromPem(Keys::pem('platform'), 'tool-1');
    }

    private function responder(): DeepLinkingResponder
    {
        return new DeepLinkingResponder($this->lti->toolKeys, $this->lti->clock);
    }

    private function platform(): DeepLinkingReturn
    {
        return new DeepLinkingReturn(Lti::ISSUER, $this->lti->tools, $this->lti->nonces, $this->lti->verifier);
    }

    /**
     * @param list<string> $types
     */
    private function launch(bool $multiple = true, array $types = ['ltiResourceLink', 'link', 'file', 'html', 'image']): Launch
    {
        $settings = new DeepLinkingSettings(Url::fromString('https://lms.example.org/dl'), $types, ['iframe'], null, $multiple, true, false, null, null, 'opaque');

        return new Launch(Lti::ISSUER, Lti::CLIENT, new LaunchMessage(MessageType::DeepLinking, Lti::DEPLOYMENT, Url::fromString(Lti::LAUNCH), new Person('u'), Roles::of([ContextRole::Instructor]), deepLinking: $settings), []);
    }
}
