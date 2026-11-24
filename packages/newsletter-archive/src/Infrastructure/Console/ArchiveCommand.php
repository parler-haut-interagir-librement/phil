<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\Console;

use Phil\NewsletterArchive\Application\Archive;
use Phil\NewsletterArchive\Application\Contract\IssueUrls;
use Phil\NewsletterArchive\Domain\Exception\NewsletterArchiveException;
use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * What an editor does with the archive, from the command line: publish a sent campaign, unpublish it, share it by a
 * new link, revoke its link, or file it under other tags.
 *
 *     bin/console phil:newsletter-archive publish <campaign> --tag=events --tag=game-night
 *     bin/console phil:newsletter-archive share <campaign>
 */
#[AsCommand(name: self::NAME, description: 'Publishes, unpublishes, shares or retags a sent campaign in the newsletter archive.')]
final readonly class ArchiveCommand
{
    public const string NAME = 'phil:newsletter-archive';

    public const array ACTIONS = ['publish', 'unpublish', 'share', 'revoke', 'retag'];

    public function __construct(
        private Archive $archive,
        private IssueUrls $urls,
    ) {
    }

    /**
     * @param list<string> $tag
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('publish, unpublish, share, revoke or retag.')]
        string $action,
        #[Argument('The identifier of the campaign.')]
        string $campaign,
        #[Option('A tag to file the issue under, for publish and retag; repeat it for several.')]
        array $tag = [],
    ): int {
        try {
            switch ($action) {
                case 'publish':
                    $issue = $this->archive->publish($campaign, $tag);
                    $io->success(\sprintf('Published at %s', $this->urls->issue($issue)));

                    break;

                case 'unpublish':
                    $this->archive->unpublish($campaign);
                    $io->success('Unpublished; its link, if it had one, is revoked.');

                    break;

                case 'share':
                    $link = $this->urls->link($this->archive->share($campaign));
                    $io->success('Shared; a link shared before no longer works. The link is shown once:');
                    $io->writeln($link);

                    break;

                case 'revoke':
                    $this->archive->revokeLink($campaign);
                    $io->success('The link is revoked.');

                    break;

                case 'retag':
                    $this->archive->retag($campaign, $tag);
                    $io->success('Retagged.');

                    break;

                default:
                    $io->error(\sprintf('"%s" is not an action: %s.', $action, implode(', ', self::ACTIONS)));

                    return Command::INVALID;
            }
        } catch (NewsletterArchiveException|NewsletterException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
