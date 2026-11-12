<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use ArrayObject;
use LogicException;
use Phil\SocialCardsBundle\Application\FetchedPage;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Phil\SocialCardsBundle\Infrastructure\Console\CheckSocialTagsCommand;
use Phil\SocialCardsBundle\Tests\Support\FakeFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(CheckSocialTagsCommand::class)]
final class CheckSocialTagsCommandTest extends TestCase
{
    private const string GOOD = '<head><meta property="og:title" content="Title"><meta property="og:type" content="website"><meta property="og:url" content="https://example.com/post"><meta property="og:image" content="https://example.com/card.png"><meta property="og:image:alt" content="Alt"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta property="og:description" content="D"><meta name="twitter:card" content="summary_large_image"><meta name="description" content="Not listed"><meta name="blog:title" content="Not listed either"></head>';

    public function testAPageOfTheApplicationIsRendered(): void
    {
        /** @var ArrayObject<int, Request> $requests */
        $requests = new ArrayObject();
        $command = new CheckSocialTagsCommand($this->kernel($requests, new Response(self::GOOD)), new RequestContext('/app', 'GET', 'example.com', 'https', 80, 8443), new LinkPreviews(new FakeFetcher()));

        [$code, $output] = $this->check($command, '/post');

        self::assertSame(Command::SUCCESS, $code, $output);
        self::assertSame(['https://example.com:8443/app/post'], array_map(static fn (Request $request): string => $request->getUri(), $requests->getArrayCopy()));
        self::assertStringContainsString('https://example.com:8443/app/post', $output);
        self::assertStringContainsString('og:image:alt', $output);
        self::assertStringContainsString('summary_large_image', $output);
        self::assertStringNotContainsString('Not listed', $output);
        self::assertStringContainsString(' Tag Content ', $output);
        self::assertStringContainsString('The tags are complete.', $output);
    }

    public function testThePortsOfTheContext(): void
    {
        foreach ([['https', 443, 'https://example.com/p '], ['http', 80, 'http://example.com/p '], ['http', 81, 'http://example.com:81/p '], ['https', 444, 'https://example.com:444/p ']] as [$scheme, $port, $title]) {
            $context = new RequestContext('', 'GET', 'example.com', $scheme, 'http' === $scheme ? $port : 80, 'https' === $scheme ? $port : 443);
            $output = $this->check(new CheckSocialTagsCommand($this->kernel(new ArrayObject(), new Response(self::GOOD)), $context, new LinkPreviews(new FakeFetcher())), '/p')[1];
            self::assertStringContainsString($title, $output);
        }

        /** @var ArrayObject<int, Request> $requests */
        $requests = new ArrayObject();
        $this->check(new CheckSocialTagsCommand($this->kernel($requests, new Response(self::GOOD)), new RequestContext('', 'GET', 'example.com', 'https', 80, 443), new LinkPreviews(new FakeFetcher())), '/a');
        $this->check(new CheckSocialTagsCommand($this->kernel($requests, new Response(self::GOOD)), new RequestContext('', 'GET', 'example.com', 'http', 8080, 443), new LinkPreviews(new FakeFetcher())), '/b');
        $this->check(new CheckSocialTagsCommand($this->kernel($requests, new Response(self::GOOD)), new RequestContext('', 'GET', 'example.com', 'http', 80, 443), new LinkPreviews(new FakeFetcher())), '/c');

        self::assertSame(['https://example.com/a', 'http://example.com:8080/b', 'http://example.com/c'], array_map(static fn (Request $request): string => $request->getUri(), $requests->getArrayCopy()));
    }

    public function testAPageWithWarningsAndOneWithErrors(): void
    {
        $context = new RequestContext('', 'GET', 'example.com', 'https');
        $warned = str_replace('<meta property="og:image:alt" content="Alt">', '', self::GOOD);
        [$code, $output] = $this->check(new CheckSocialTagsCommand($this->kernel(new ArrayObject(), new Response($warned)), $context, new LinkPreviews(new FakeFetcher())), '/post');
        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('[WARNING] The image has no og:image:alt', $output);
        self::assertStringNotContainsString('[ERROR]', $output);
        self::assertStringContainsString('The tags are usable.', $output);

        [$code, $output] = $this->check(new CheckSocialTagsCommand($this->kernel(new ArrayObject(), new Response('<head><title>T</title></head>')), $context, new LinkPreviews(new FakeFetcher())), '/bare');
        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('[ERROR] The page has no og:title', $output);
        self::assertStringContainsString('[WARNING] The page has no og:description', $output);
        self::assertStringNotContainsString('The tags are', $output);
    }

    public function testAPageThatIsNotRead(): void
    {
        $context = new RequestContext('', 'GET', 'example.com', 'https');

        foreach ([new Response('Not found', Response::HTTP_NOT_FOUND), new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json'])] as $response) {
            [$code, $output] = $this->check(new CheckSocialTagsCommand($this->kernel(new ArrayObject(), $response), $context, new LinkPreviews(new FakeFetcher())), '/missing');
            self::assertSame(Command::FAILURE, $code);
            self::assertStringContainsString('cannot be read', $output);
        }
    }

    public function testAPageOfAnotherSiteIsFetched(): void
    {
        $fetcher = new FakeFetcher(['https://example.org/' => new FetchedPage(self::GOOD, 'https://example.org/')]);
        $command = new CheckSocialTagsCommand($this->kernel(new ArrayObject(), new Response('never')), new RequestContext(), new LinkPreviews($fetcher));

        self::assertSame(Command::SUCCESS, $this->check($command, 'https://example.org/')[0]);
        self::assertSame(Command::FAILURE, $this->check($command, '//example.org/')[0], 'A path that starts with two slashes is an address without its scheme.');
        self::assertSame(['https://example.org/'], $fetcher->fetched);
    }

    /**
     * @return array{int, string}
     */
    private function check(CheckSocialTagsCommand $command, string $page): array
    {
        $output = new BufferedOutput();
        $code = $command(new SymfonyStyle(new ArrayInput([]), $output), $page);

        return [$code, (string) preg_replace('/\s+/', ' ', $output->fetch())];
    }

    /**
     * @param ArrayObject<int, Request> $requests
     */
    private function kernel(ArrayObject $requests, Response $response): HttpKernelInterface
    {
        return new readonly class($requests, $response) implements HttpKernelInterface {
            /**
             * @param ArrayObject<int, Request> $requests
             */
            public function __construct(
                private ArrayObject $requests,
                private Response $response,
            ) {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                if ($catch || self::MAIN_REQUEST !== $type) {
                    throw new LogicException('The command renders a main request and lets its exceptions through.');
                }

                $this->requests[] = $request;

                return $this->response;
            }
        };
    }
}
