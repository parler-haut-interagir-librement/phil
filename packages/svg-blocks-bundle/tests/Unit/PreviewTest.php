<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Unit;

use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Renderer\MathRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewController;
use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[CoversClass(PreviewController::class)]
#[CoversClass(PreviewRequest::class)]
final class PreviewTest extends TestCase
{
    public function testDrawsAPreview(): void
    {
        $response = $this->controller()($this->request('{"type":"math","data":{"latex":"x"}}'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('<math', $this->answer($response)['markup'] ?? '');
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testAnswersEachRefusal(): void
    {
        $controller = $this->controller(10_000);

        foreach ([
            ['{"type":"math","data":{"latex":"\\\\nope"}}', 422, '"\\nope" is not supported'],
            ['{"type":"paragraph","data":{}}', 404, 'Blocks of type "paragraph" are not drawn.'],
            ['{"type":"'.str_repeat('é', 70).'","data":{}}', 404, 'type "'.str_repeat('é', 64).'" are'],
            ['{"type":"math"}', 400, 'The body is'],
            ['{"type":1,"data":{}}', 400, 'The body is'],
            ['[1]', 400, 'The body is'],
            ['not json', 400, 'The body is'],
            ['{"type":"math","data":{"latex":"'.str_repeat('x', 10_000).'"}}', 413, 'larger than 10000 bytes'],
        ] as [$body, $status, $error]) {
            $response = $controller($this->request($body));
            self::assertSame($status, $response->getStatusCode(), $body);
            self::assertStringContainsString($error, $this->answer($response)['error'] ?? '');
        }

        $exact = '{"type":"math","data":{"latex":"x"}}';
        self::assertSame(200, $this->controller(\strlen($exact))($this->request($exact))->getStatusCode());
        self::assertSame(413, $this->controller(\strlen($exact) - 1)($this->request($exact))->getStatusCode());
    }

    public function testReadsBodiesUpToTheDepthLimit(): void
    {
        $body = static fn (int $levels): string => '{"type":"math","data":{"latex":"x","deep":'.str_repeat('[', $levels - 2).str_repeat(']', $levels - 2).'}}';

        self::assertSame(200, $this->controller()(self::request($body(63)))->getStatusCode());
        self::assertSame(400, $this->controller()(self::request($body(64)))->getStatusCode());
    }

    public function testRefusesARequestFromAnotherSite(): void
    {
        $controller = $this->controller();

        foreach ([
            $this->request('{}', ['HTTP_X_PHIL_SVG_BLOCKS' => '0']),
            $this->request('{}', ['HTTP_SEC_FETCH_SITE' => 'cross-site']),
            $this->request('{}', ['HTTP_CSRF_COOKIE' => 'wrong']),
            $this->request('{}', [], false),
        ] as $request) {
            $response = $controller($request);
            self::assertSame(403, $response->getStatusCode());
            self::assertStringContainsString('only drawn for the pages of this site', $this->answer($response)['error'] ?? '');
        }

        self::assertSame(200, $controller($this->request('{"type":"math","data":{"latex":"x"}}', ['HTTP_SEC_FETCH_SITE' => 'same-origin']))->getStatusCode());
    }

    public function testNamesTheCookieOfTheToken(): void
    {
        self::assertSame('csrf-cookie', new PreviewRequest($this->tokens())->cookieName());
        self::assertSame('phil_svg_blocks', PreviewRequest::TOKEN_ID);
        self::assertSame('X-Phil-Svg-Blocks', PreviewRequest::HEADER);
        self::assertSame(65_536, PreviewController::MAX_BYTES);
    }

    /**
     * @return array<string, string>
     */
    private function answer(Response $response): array
    {
        /** @var array<string, string> */
        return json_decode((string) $response->getContent(), true, 4, \JSON_THROW_ON_ERROR);
    }

    private function controller(int $maxBytes = PreviewController::MAX_BYTES): PreviewController
    {
        return new PreviewController(new BlockMarkup(new Signer(str_repeat('s', 32)), ['math' => new MathRenderer()]), new PreviewRequest($this->tokens()), $maxBytes);
    }

    /**
     * @param array<string, string> $server
     */
    private function request(string $body, array $server = [], bool $token = true): Request
    {
        return Request::create('/preview', 'POST', server: ['HTTP_X_PHIL_SVG_BLOCKS' => '1', ...$token ? ['HTTP_CSRF_COOKIE' => 'good'] : [], ...$server], content: $body);
    }

    private function tokens(): CsrfTokenManagerInterface
    {
        return new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'csrf-cookie');
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return 'phil_svg_blocks' === $token->getId() && 'good' === $token->getValue();
            }
        };
    }
}
