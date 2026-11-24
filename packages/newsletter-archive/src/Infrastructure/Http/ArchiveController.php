<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\Http;

use Phil\NewsletterArchive\Application\ArchiveReader;
use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\Slug\Slug;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The public pages of the archive: the list of the published issues, a page at a time, filtered by list and by tag;
 * an issue at its slug; and an issue by the link it is shared by, which no search engine indexes, no cache keeps and
 * no `Referer` carries away. What is not there, or no longer public, is a 404.
 */
final readonly class ArchiveController
{
    public const string INDEX_TEMPLATE = '@PhilNewsletterArchive/archive/index.html.twig';

    public const string ISSUE_TEMPLATE = '@PhilNewsletterArchive/archive/issue.html.twig';

    public function __construct(
        private ArchiveReader $reader,
        private CurrentSite $site,
        private Environment $twig,
        private int $pageSize = 20,
    ) {
    }

    public function index(Request $request): Response
    {
        $site = $this->site->site()->key;
        $list = $request->query->getString('list');
        $tag = $request->query->getString('tag');
        $number = $request->query->getString('page', '1');

        if (('' !== $list && 1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $list)) || 1 !== preg_match('/^[1-9]\d{0,3}$/D', $number)) {
            throw new NotFoundHttpException();
        }

        try {
            $filter = new ArchiveFilter($site, '' === $list ? null : $list, '' === $tag ? null : Tag::of($tag), (int) $number, $this->pageSize);
        } catch (InvalidArchiveValue) {
            throw new NotFoundHttpException();
        }

        $page = $this->reader->page($filter);

        if ($filter->page > $page->pages()) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render(self::INDEX_TEMPLATE, [
            'page' => $page,
            'lists' => $this->reader->lists($site),
            'tags' => $this->reader->tags($site),
            'list' => $filter->listKey,
            'tag' => $filter->tag?->value,
        ]));
    }

    public function issue(string $slug): Response
    {
        if (\strlen($slug) > Slug::MAX_LENGTH) {
            throw new NotFoundHttpException();
        }

        $issue = $this->reader->issue($this->site->site()->key, $slug) ?? throw new NotFoundHttpException();

        return new Response($this->twig->render(self::ISSUE_TEMPLATE, ['issue' => $issue, 'shared' => false]));
    }

    public function link(#[SensitiveParameter] string $token): Response
    {
        $issue = $this->reader->shared($token);

        if (!$issue instanceof ArchivedIssue || $issue->site !== $this->site->site()->key) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render(self::ISSUE_TEMPLATE, ['issue' => $issue, 'shared' => true]), Response::HTTP_OK, [
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
