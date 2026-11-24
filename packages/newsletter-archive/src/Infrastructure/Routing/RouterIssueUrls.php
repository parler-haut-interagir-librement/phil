<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\Routing;

use Override;
use Phil\NewsletterArchive\Application\Contract\IssueUrls;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use SensitiveParameter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses of the archive's routes, absolute, from the router: on the command line, from the request context
 * the application configures (`framework.router.default_uri`).
 */
final readonly class RouterIssueUrls implements IssueUrls
{
    public const string INDEX = 'phil_newsletter_archive';

    public const string ISSUE = 'phil_newsletter_archive_issue';

    public const string LINK = 'phil_newsletter_archive_link';

    public function __construct(
        private UrlGeneratorInterface $router,
    ) {
    }

    #[Override]
    public function issue(ArchivedIssue $issue): string
    {
        return $this->router->generate(self::ISSUE, ['slug' => $issue->slug], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    #[Override]
    public function link(#[SensitiveParameter] string $token): string
    {
        return $this->router->generate(self::LINK, ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
