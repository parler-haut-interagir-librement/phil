<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Message;

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Message\Claim;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\DeepLinkingSettings;
use Phil\Lti\Message\DocumentTarget;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\LaunchPresentation;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\NrpsEndpoint;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Message\ToolPlatform;
use Phil\Lti\Service\Scope;
use Phil\Lti\Support\Fields;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaunchMessage::class)]
#[CoversClass(Launch::class)]
#[CoversClass(Person::class)]
#[CoversClass(Context::class)]
#[CoversClass(ResourceLink::class)]
#[CoversClass(ToolPlatform::class)]
#[CoversClass(LaunchPresentation::class)]
#[CoversClass(AgsEndpoint::class)]
#[CoversClass(NrpsEndpoint::class)]
#[CoversClass(DeepLinkingSettings::class)]
#[CoversClass(Fields::class)]
final class LaunchMessageTest extends TestCase
{
    public function testEveryClaimRoundTrips(): void
    {
        $message = self::full();
        $claims = $message->toClaims();
        $read = LaunchMessage::fromClaims($claims);

        self::assertEquals($message, $read);
        self::assertSame($claims, $read->toClaims());
        self::assertSame('LtiResourceLinkRequest', $claims[Claim::MESSAGE_TYPE]);
        self::assertSame('1.3.0', $claims[Claim::VERSION]);
        self::assertSame(['id' => 'link-1', 'title' => 'Week 1', 'description' => 'The first week'], $claims[Claim::RESOURCE_LINK]);
        self::assertSame(['id' => 'course-1', 'label' => 'CS101', 'title' => 'Intro', 'type' => [Context::COURSE_OFFERING]], $claims[Claim::CONTEXT]);
        self::assertSame(['document_target' => 'iframe', 'return_url' => 'https://lms.example.org/back', 'locale' => 'fr-CA', 'width' => 800, 'height' => 600], $claims[Claim::LAUNCH_PRESENTATION]);
        self::assertSame(['scope' => [Scope::SCORE], 'lineitems' => 'https://lms.example.org/c/1/lineitems', 'lineitem' => 'https://lms.example.org/c/1/lineitems/9'], $claims[Claim::ENDPOINT]);
        self::assertSame(['context_memberships_url' => 'https://lms.example.org/c/1/members', 'service_versions' => ['2.0']], $claims[Claim::NAMES_ROLE_SERVICE]);
        self::assertSame(['week' => '1'], $claims[Claim::CUSTOM]);
        self::assertSame(['user-9'], $claims[Claim::ROLE_SCOPE_MENTOR]);
        self::assertSame('user-7', $claims['sub']);
        self::assertSame('ada@example.org', $claims['email']);
    }

    public function testAMinimalMessageCarriesNoEmptyClaim(): void
    {
        $message = new LaunchMessage(MessageType::ResourceLink, 'd', Url::fromString('https://tool.example.com/'), new Person(null), Roles::of([]), new ResourceLink('l'));
        $claims = $message->toClaims();

        self::assertSame([Claim::MESSAGE_TYPE, Claim::VERSION, Claim::DEPLOYMENT_ID, Claim::TARGET_LINK_URI, Claim::ROLES, Claim::RESOURCE_LINK], array_keys($claims));
        self::assertSame([], $claims[Claim::ROLES]);
        self::assertSame(['id' => 'l'], $claims[Claim::RESOURCE_LINK]);
        self::assertEquals($message, LaunchMessage::fromClaims($claims));
        self::assertNull(LaunchMessage::fromClaims($claims)->person->subject);
    }

    public function testADeepLinkingRequestRoundTrips(): void
    {
        $settings = new DeepLinkingSettings(Url::fromString('https://lms.example.org/dl'), ['ltiResourceLink', 'link'], ['iframe'], 'image/*', true, true, true, 'Pick', 'Pick a page', 'opaque');
        $message = new LaunchMessage(MessageType::DeepLinking, 'd', Url::fromString('https://tool.example.com/dl'), new Person('u'), Roles::of([ContextRole::Instructor]), deepLinking: $settings);
        $claims = $message->toClaims();

        self::assertSame([
            'deep_link_return_url' => 'https://lms.example.org/dl',
            'accept_types' => ['ltiResourceLink', 'link'],
            'accept_presentation_document_targets' => ['iframe'],
            'accept_media_types' => 'image/*',
            'accept_multiple' => true,
            'accept_lineitem' => true,
            'auto_create' => true,
            'title' => 'Pick',
            'text' => 'Pick a page',
            'data' => 'opaque',
        ], $claims[Claim::DEEP_LINKING_SETTINGS]);
        self::assertEquals($message, LaunchMessage::fromClaims($claims));
        self::assertTrue($settings->accepts('link'));
        self::assertFalse($settings->accepts('file'));

        $minimal = new DeepLinkingSettings(Url::fromString('https://lms.example.org/dl'), ['link']);
        self::assertSame(['deep_link_return_url' => 'https://lms.example.org/dl', 'accept_types' => ['link'], 'accept_multiple' => false, 'accept_lineitem' => false, 'auto_create' => false], $minimal->toClaim());
    }

    public function testTheLaunchExposesItsMessage(): void
    {
        $launch = new Launch(Lti::ISSUER, Lti::CLIENT, self::full(), ['sub' => 'user-7']);

        self::assertSame(MessageType::ResourceLink, $launch->type());
        self::assertSame('deployment-1', $launch->deploymentId());
        self::assertSame('user-7', $launch->subject());
        self::assertTrue($launch->roles()->isLearner());
        self::assertFalse($launch->isDeepLinking());
        self::assertSame(hash('sha256', Lti::ISSUER."\nclient-42\nuser-7"), $launch->userKey());

        $anonymous = new Launch(Lti::ISSUER, Lti::CLIENT, new LaunchMessage(MessageType::ResourceLink, 'd', Url::fromString('https://tool.example.com/'), new Person(null), Roles::of([]), new ResourceLink('l')), []);
        self::assertNull($anonymous->userKey());
        self::assertNull($anonymous->subject());
    }

    public function testTheEndpointClaimsAnswerWhatTheyAllow(): void
    {
        $ags = new AgsEndpoint([Scope::SCORE]);

        self::assertTrue($ags->allows(Scope::SCORE));
        self::assertFalse($ags->allows(Scope::LINE_ITEM));
        self::assertSame(['scope' => [Scope::SCORE]], $ags->toClaim());
    }

    public function testAnUnknownDocumentTargetIsDropped(): void
    {
        $claims = self::full()->toClaims();
        $claims[Claim::LAUNCH_PRESENTATION] = ['document_target' => 'popup'];

        self::assertNull(LaunchMessage::fromClaims($claims)->presentation?->documentTarget);
        self::assertSame([], new LaunchPresentation()->toClaim());
    }

    public function testCustomValuesAreReadAsText(): void
    {
        $claims = self::full()->toClaims();
        $claims[Claim::CUSTOM] = ['n' => 3, 'f' => 1.5, 'yes' => true, 'no' => false, 's' => 'x'];

        self::assertSame(['n' => '3', 'f' => '1.5', 'yes' => 'true', 'no' => 'false', 's' => 'x'], LaunchMessage::fromClaims($claims)->custom);
    }

    public function testAMessageInvariantIsChecked(): void
    {
        $url = Url::fromString('https://tool.example.com/');
        $cases = [
            'response' => [static fn (): LaunchMessage => new LaunchMessage(MessageType::DeepLinkingResponse, 'd', $url, new Person('u'), Roles::of([]), new ResourceLink('l')), 'A launch is a resource link or a deep linking request.'],
            'no link' => [static fn (): LaunchMessage => new LaunchMessage(MessageType::ResourceLink, 'd', $url, new Person('u'), Roles::of([])), 'A resource link launch names its resource link.'],
            'no settings' => [static fn (): LaunchMessage => new LaunchMessage(MessageType::DeepLinking, 'd', $url, new Person('u'), Roles::of([])), 'A deep linking launch carries its deep linking settings.'],
            'no deployment' => [static fn (): LaunchMessage => new LaunchMessage(MessageType::ResourceLink, '', $url, new Person('u'), Roles::of([]), new ResourceLink('l')), 'The deployment is named by 1 to 255 bytes.'],
            'long deployment' => [static fn (): LaunchMessage => new LaunchMessage(MessageType::ResourceLink, str_repeat('d', 256), $url, new Person('u'), Roles::of([]), new ResourceLink('l')), 'The deployment is named by 1 to 255 bytes.'],
        ];

        foreach ($cases as $name => [$build, $message]) {
            try {
                $build();
                self::fail($name);
            } catch (InvalidToken $invalidToken) {
                self::assertSame($message, $invalidToken->getMessage(), $name);
            }
        }

        self::assertSame(255, \strlen(new LaunchMessage(MessageType::ResourceLink, str_repeat('d', 255), $url, new Person('u'), Roles::of([]), new ResourceLink('l'))->deploymentId));
    }

    public function testMalformedClaimsAreRefused(): void
    {
        $claims = self::full()->toClaims();
        $cases = [
            'no version' => [[Claim::VERSION => null], 'A message of LTI 1.3 carries the version "1.3.0".'],
            'old version' => [[Claim::VERSION => '1.1'], 'A message of LTI 1.3 carries the version "1.3.0".'],
            'unknown type' => [[Claim::MESSAGE_TYPE => 'LtiSubmissionReviewRequest'], 'The message type is not one of LTI 1.3.'],
            'no type' => [[Claim::MESSAGE_TYPE => null], 'The member "'.Claim::MESSAGE_TYPE.'" is required.'],
            'no roles' => [[Claim::ROLES => null], 'A launch carries the roles of the person, an empty list when it has none.'],
            'roles text' => [[Claim::ROLES => 'Learner'], 'A launch carries the roles of the person, an empty list when it has none.'],
            'roles object' => [[Claim::ROLES => ['a' => 'Learner']], 'The member "'.Claim::ROLES.'" is a list of 64 strings at most.'],
            'roles numbers' => [[Claim::ROLES => [1]], 'The member "'.Claim::ROLES.'" is a list of strings.'],
            'too many roles' => [[Claim::ROLES => array_fill(0, 65, 'Learner')], 'The member "'.Claim::ROLES.'" is a list of 64 strings at most.'],
            'no target' => [[Claim::TARGET_LINK_URI => null], 'The member "'.Claim::TARGET_LINK_URI.'" is required.'],
            'bad target' => [[Claim::TARGET_LINK_URI => 'javascript:alert(1)'], 'The member "'.Claim::TARGET_LINK_URI.'" is not a web address: '],
            'link list' => [[Claim::RESOURCE_LINK => ['l']], 'The member "'.Claim::RESOURCE_LINK.'" is an object.'],
            'link without id' => [[Claim::RESOURCE_LINK => ['title' => 'x']], 'The member "'.Claim::RESOURCE_LINK.'.id" is required.'],
            'long link id' => [[Claim::RESOURCE_LINK => ['id' => str_repeat('x', 256)]], 'The member "'.Claim::RESOURCE_LINK.'.id" is a UTF-8 string of 255 bytes at most.'],
            'subject number' => [['sub' => 7], 'The member "sub" is a UTF-8 string of 255 bytes at most.'],
            'bad utf-8' => [['name' => "\xff"], 'The member "name" is a UTF-8 string of 512 bytes at most.'],
            'custom list' => [[Claim::CUSTOM => ['a']], 'The member "'.Claim::CUSTOM.'" is an object of 100 members at most.'],
            'custom nested' => [[Claim::CUSTOM => ['a' => ['b']]], 'The member "'.Claim::CUSTOM.'" maps names to strings.'],
            'custom long' => [[Claim::CUSTOM => ['a' => str_repeat('x', 4097)]], 'The member "'.Claim::CUSTOM.'" maps names of 255 bytes to strings of 4096 bytes at most.'],
            'custom long name' => [[Claim::CUSTOM => [str_repeat('k', 256) => 'x']], 'The member "'.Claim::CUSTOM.'" maps names of 255 bytes to strings of 4096 bytes at most.'],
            'too many custom' => [[Claim::CUSTOM => array_combine(array_map(static fn (int $i): string => 'k'.$i, range(0, 100)), array_fill(0, 101, 'v'))], 'The member "'.Claim::CUSTOM.'" is an object of 100 members at most.'],
            'width text' => [[Claim::LAUNCH_PRESENTATION => ['width' => '800']], 'The member "'.Claim::LAUNCH_PRESENTATION.'.width" is an integer.'],
            'memberships missing' => [[Claim::NAMES_ROLE_SERVICE => ['service_versions' => ['2.0']]], 'The member "'.Claim::NAMES_ROLE_SERVICE.'.context_memberships_url" is required.'],
            'deep linking flag' => [[Claim::DEEP_LINKING_SETTINGS => ['deep_link_return_url' => 'https://lms.example.org/dl', 'accept_types' => [], 'accept_multiple' => 'yes']], 'The member "'.Claim::DEEP_LINKING_SETTINGS.'.accept_multiple" is a boolean.'],
        ];

        foreach ($cases as $name => [$changes, $message]) {
            try {
                LaunchMessage::fromClaims(array_filter($changes + $claims, static fn (mixed $value): bool => null !== $value));
                self::fail($name.' was read.');
            } catch (InvalidToken $invalidToken) {
                self::assertStringStartsWith($message, $invalidToken->getMessage(), $name);
            }
        }
    }

    public function testLocalhostIsReadOnlyWhenAllowed(): void
    {
        $claims = self::full()->toClaims();
        $claims[Claim::TARGET_LINK_URI] = 'https://tool.localhost/launch';

        self::assertSame('tool.localhost', LaunchMessage::fromClaims($claims, true)->targetLinkUri->host);
        $this->expectException(InvalidToken::class);
        LaunchMessage::fromClaims($claims);
    }

    public static function full(): LaunchMessage
    {
        return new LaunchMessage(
            MessageType::ResourceLink,
            'deployment-1',
            Url::fromString(Lti::LAUNCH),
            new Person('user-7', 'Ada Lovelace', 'Ada', 'Lovelace', 'ada@example.org', 'https://lms.example.org/ada.png', 'en'),
            Roles::of([ContextRole::Learner]),
            new ResourceLink('link-1', 'Week 1', 'The first week'),
            new Context('course-1', 'CS101', 'Intro', [Context::COURSE_OFFERING]),
            new ToolPlatform('guid-1', 'LMS', 'lms', '4.2', 'https://lms.example.org', 'admin@example.org', 'An LMS'),
            new LaunchPresentation(DocumentTarget::Iframe, Url::fromString('https://lms.example.org/back'), 'fr-CA', 800, 600),
            ['week' => '1'],
            new AgsEndpoint([Scope::SCORE], Url::fromString('https://lms.example.org/c/1/lineitems'), Url::fromString('https://lms.example.org/c/1/lineitems/9')),
            new NrpsEndpoint(Url::fromString('https://lms.example.org/c/1/members')),
            null,
            ['user-9'],
        );
    }
}
