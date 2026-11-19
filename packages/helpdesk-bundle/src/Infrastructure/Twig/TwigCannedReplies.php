<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Twig;

use Override;
use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Twig\Environment;

/**
 * The canned replies of the configuration, each a Twig template of the application rendered for the request — given
 * as `ticket`, with the `locale` asked for — and offered in the queues it names, or in every queue when it names none.
 * The templates are the application's own, written by its developers: what an agent picks is an identifier, never a
 * template.
 *
 * @phpstan-type Declaration array{label: string, template: string, queues: list<string>}
 */
final readonly class TwigCannedReplies implements CannedReplies
{
    /**
     * @param array<string, Declaration> $replies by identifier, in their order
     */
    public function __construct(
        private Environment $twig,
        private array $replies,
    ) {
    }

    #[Override]
    public function for(Ticket $ticket): array
    {
        $offered = [];

        foreach ($this->replies as $id => $reply) {
            if ($this->isOffered($reply, $ticket)) {
                $offered[] = new CannedReply($id, $reply['label']);
            }
        }

        return $offered;
    }

    #[Override]
    public function render(string $id, Ticket $ticket, ?string $locale = null): string
    {
        $reply = $this->replies[$id] ?? null;

        if (null === $reply || !$this->isOffered($reply, $ticket)) {
            throw CannedReplyNotFound::of($id);
        }

        return trim($this->twig->render($reply['template'], ['ticket' => $ticket, 'locale' => $locale]));
    }

    /**
     * @param Declaration $reply
     */
    private function isOffered(array $reply, Ticket $ticket): bool
    {
        return [] === $reply['queues'] || \in_array($ticket->queue()->value, $reply['queues'], true);
    }
}
