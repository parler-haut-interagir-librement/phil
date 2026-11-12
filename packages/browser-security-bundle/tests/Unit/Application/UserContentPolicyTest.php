<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(UserContentPolicy::class)]
final class UserContentPolicyTest extends TestCase
{
    public function testAnImageIsShownWithEveryStrictHeader(): void
    {
        $response = new Response('png', Response::HTTP_OK, [
            'Content-Type' => 'Image/PNG; charset=binary',
            'Content-Security-Policy-Report-Only' => "default-src 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);

        new UserContentPolicy()->apply($response);

        self::assertSame([
            'content-type' => ['Image/PNG; charset=binary'],
            'x-frame-options' => ['DENY'],
            'content-security-policy' => ["default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox"],
            'x-content-type-options' => ['nosniff'],
            'content-disposition' => ['inline'],
            'cross-origin-resource-policy' => ['same-origin'],
            'referrer-policy' => ['no-referrer'],
            'x-permitted-cross-domain-policies' => ['none'],
        ], array_diff_key($response->headers->all(), ['cache-control' => true, 'date' => true]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function activeTypes(): iterable
    {
        yield 'html' => ['text/html; charset=utf-8'];
        yield 'svg' => ['image/svg+xml'];
        yield 'xhtml' => ['application/xhtml+xml'];
        yield 'pdf' => ['application/pdf'];
        yield 'unknown' => ['application/x-anything'];
    }

    #[DataProvider('activeTypes')]
    public function testEveryOtherTypeIsAnAttachment(string $type): void
    {
        $response = new Response('', Response::HTTP_OK, ['Content-Type' => $type]);

        new UserContentPolicy()->apply($response);

        self::assertSame('attachment', $response->headers->get('Content-Disposition'));
        self::assertSame($type, $response->headers->get('Content-Type'));
    }

    public function testAResponseWithoutTypeIsNeverGuessed(): void
    {
        $response = new Response('<script>alert(1)</script>');
        $response->headers->remove('Content-Type');

        new UserContentPolicy()->apply($response);

        self::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        self::assertSame('attachment', $response->headers->get('Content-Disposition'));
    }

    public function testTheNameOfTheResponseIsKeptAndAnAttachmentStaysOne(): void
    {
        $inline = new Response('', Response::HTTP_OK, ['Content-Type' => 'text/html', 'Content-Disposition' => 'inline; filename="page.html"']);
        $attachment = new Response('', Response::HTTP_OK, ['Content-Type' => 'image/png', 'Content-Disposition' => ' Attachment ;filename="cat.png"']);
        $policy = new UserContentPolicy();

        $policy->apply($inline);
        $policy->apply($attachment);

        self::assertSame('attachment; filename="page.html"', $inline->headers->get('Content-Disposition'));
        self::assertSame('attachment;filename="cat.png"', $attachment->headers->get('Content-Disposition'));
    }

    public function testTheTypeAndTheDispositionAreReadAroundTheirSpaces(): void
    {
        $response = new Response('', Response::HTTP_OK, ['Content-Type' => ' image/png ; charset=binary', 'Content-Disposition' => ' inline; filename="cat.png" ']);

        new UserContentPolicy()->apply($response);

        self::assertSame('inline; filename="cat.png"', $response->headers->get('Content-Disposition'));
        self::assertTrue(new UserContentPolicy(inlineTypes: ['image/png'])->isInline('IMAGE/PNG'));
        self::assertFalse(new UserContentPolicy(inlineTypes: ['image/png'])->isInline('image/gif'));
    }

    public function testAGivenNameIsMadeSafe(): void
    {
        $policy = new UserContentPolicy();
        $cases = [
            'cat.png' => 'inline; filename=cat.png',
            '../../etc/passwd' => 'inline; filename=.._.._etc_passwd',
            "café\r\n\"x\".png" => "inline; filename=caf___x_.png; filename*=utf-8''caf%C3%A9_x_.png",
            '100%.png' => "inline; filename=100_.png; filename*=utf-8''100%25.png",
            ' / ' => 'inline; filename=_',
            "\x00" => 'inline; filename=_',
            '   ' => 'inline; filename=file',
        ];

        foreach ($cases as $name => $expected) {
            $response = new Response('', Response::HTTP_OK, ['Content-Type' => 'image/png']);
            $policy->apply($response, $name);
            self::assertSame($expected, $response->headers->get('Content-Disposition'), $name);
        }
    }

    public function testTheConfigurationIsFollowed(): void
    {
        $policy = new UserContentPolicy(['default-src' => ["'none'"], 'frame-ancestors' => ["'self'"]], ['application/pdf'], 'same-site');
        $response = new Response('', Response::HTTP_OK, ['Content-Type' => 'application/pdf', 'X-Frame-Options' => 'SAMEORIGIN']);

        $policy->apply($response);

        self::assertSame("default-src 'none'; frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        self::assertSame("default-src 'none'; frame-ancestors 'self'", $policy->header());
        self::assertSame('inline', $response->headers->get('Content-Disposition'));
        self::assertSame('same-site', $response->headers->get('Cross-Origin-Resource-Policy'));
        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'), 'Only a policy framed nowhere denies every frame.');
        self::assertTrue($policy->isInline('Application/PDF'));
        self::assertFalse($policy->isInline('image/png'));
    }

    public function testTheDefaultsAreStrict(): void
    {
        $policy = new UserContentPolicy();

        foreach (UserContentPolicy::DEFAULT_INLINE_TYPES as $type) {
            self::assertTrue($policy->isInline($type));
            self::assertNotContains($type, UserContentPolicy::ACTIVE_TYPES);
        }

        self::assertFalse($policy->isInline('image/svg+xml'));
        self::assertSame('_user_content', UserContentPolicy::ROUTE_DEFAULT);
        self::assertSame(['same-origin', 'same-site', 'cross-origin'], UserContentPolicy::RESOURCE_POLICIES);
    }

    /**
     * @return iterable<string, array{array<array-key, list<string>|bool>, list<string>, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no directive' => [[], [], 'same-origin', 'The policy of user content declares at least one directive.'];
        yield 'unknown directive' => [['nope' => true], [], 'same-origin', '"nope" is not a directive'];
        yield 'resource policy' => [UserContentPolicy::DEFAULT_DIRECTIVES, [], 'anyone', '"anyone" is not a value of Cross-Origin-Resource-Policy: same-origin, same-site or cross-origin.'];
        yield 'uppercase type' => [UserContentPolicy::DEFAULT_DIRECTIVES, ['Image/PNG'], 'same-origin', '"Image/PNG" is not a media type in lowercase, such as image/png.'];
        yield 'wildcard type' => [UserContentPolicy::DEFAULT_DIRECTIVES, ['image/*'], 'same-origin', '"image/*" is not a media type'];
        yield 'parameter' => [UserContentPolicy::DEFAULT_DIRECTIVES, ['text/plain; charset=utf-8'], 'same-origin', 'is not a media type'];
        yield 'active type' => [UserContentPolicy::DEFAULT_DIRECTIVES, ['image/png', 'image/svg+xml'], 'same-origin', '"image/svg+xml" can run as a page or a script: it is always served as an attachment.'];
    }

    /**
     * @param array<array-key, list<string>|bool> $directives
     * @param list<string>                        $types
     */
    #[DataProvider('invalid')]
    public function testAnInvalidPolicyIsRefused(array $directives, array $types, string $resource, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        new UserContentPolicy($directives, $types, $resource);
    }
}
