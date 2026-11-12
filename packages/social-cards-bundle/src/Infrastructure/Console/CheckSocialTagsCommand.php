<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Console;

use Phil\SocialCards\Preview\PageTags;
use Phil\SocialCards\Preview\Severity;
use Phil\SocialCards\Preview\TagCheck;
use Phil\SocialCards\Preview\TagReader;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * `phil:social-cards:check /blog/a-post` renders a page of this application and checks its share tags;
 * `phil:social-cards:check https://example.org/` fetches a page of another site through the guarded fetcher. The tags
 * are listed, then what `TagCheck` finds; the command fails on an error, not on a warning.
 */
#[AsCommand(name: 'phil:social-cards:check', description: 'Checks the Open Graph, X and fediverse tags of a page.')]
final readonly class CheckSocialTagsCommand
{
    public function __construct(
        private HttpKernelInterface $kernel,
        private RequestContext $context,
        private LinkPreviews $previews,
        private TagReader $reader = new TagReader(),
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('A path of this application, as /blog/a-post, or the address of a page')] string $page): int
    {
        $tags = str_starts_with($page, '/') && !str_starts_with($page, '//') ? $this->render($page) : $this->previews->tags($page);

        if (!$tags instanceof PageTags) {
            $io->error(\sprintf('The page "%s" cannot be read: it is not HTML, it is on a private network, or it did not answer in time.', $page));

            return Command::FAILURE;
        }

        $rows = [];

        foreach ($tags->meta as $key => $contents) {
            if (1 === preg_match('/^(?:og|article|twitter|fediverse):/', $key)) {
                foreach ($contents as $content) {
                    $rows[] = [$key, $content];
                }
            }
        }

        $io->title($tags->url);
        $io->table(['Tag', 'Content'], $rows);

        $findings = TagCheck::check($tags);

        foreach ($findings as $finding) {
            Severity::Error === $finding->severity ? $io->error($finding->message) : $io->warning($finding->message);
        }

        if (TagCheck::hasErrors($findings)) {
            return Command::FAILURE;
        }

        $io->success([] === $findings ? 'The tags are complete.' : 'The tags are usable.');

        return Command::SUCCESS;
    }

    private function render(string $path): ?PageTags
    {
        $port = 'https' === $this->context->getScheme() ? $this->context->getHttpsPort() : $this->context->getHttpPort();
        $origin = $this->context->getScheme().'://'.$this->context->getHost().(\in_array($port, [80, 443], true) ? '' : ':'.$port);
        $url = $origin.$this->context->getBaseUrl().$path;
        $response = $this->kernel->handle(Request::create($url), HttpKernelInterface::MAIN_REQUEST, false);
        $type = $response->headers->get('Content-Type', 'text/html');

        if (Response::HTTP_OK !== $response->getStatusCode() || !str_starts_with($type, 'text/html')) {
            return null;
        }

        return $this->reader->read((string) $response->getContent(), $url);
    }
}
