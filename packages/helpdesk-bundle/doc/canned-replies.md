# Canned replies

## Templates of the configuration

A canned reply is a Twig template of the application, written for the request it answers:

```yaml
phil_helpdesk:
    canned_replies:
        thanks:
            label: Thanks for your patience
            template: helpdesk/thanks.txt.twig
        refund:
            label: Refund on its way
            template: helpdesk/refund.txt.twig
            queues: [billing]
```

```twig
{# templates/helpdesk/thanks.txt.twig #}
Thank you for your patience: your request about {{ ticket.queue.value }} is in good hands.
```

The template receives the request as `ticket` and the locale of the agent's page as `locale`; its text, trimmed, is
posted in the conversation as the agent's message. A reply is offered in the queues it names, or in every queue when it
names none; the desk lists those offered for the request shown. What an agent picks is an identifier, never a template:
the templates are the application's own, written by its developers.

## Another source

The port `CannedReplies` lists the replies offered for a request and writes one; `canned_reply_source` names a service
of the application that replaces the templates of the configuration — an adapter to the content templates the
application already keeps, edited by its staff:

```php
use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Model\Ticket;

final readonly class TemplateLibraryReplies implements CannedReplies
{
    public function __construct(private TemplateLibrary $library) {}

    public function for(Ticket $ticket): array
    {
        return array_map(
            static fn (LibraryTemplate $template): CannedReply => new CannedReply($template->id, $template->title),
            $this->library->forTag('helpdesk.'.$ticket->queue()->value),
        );
    }

    public function render(string $id, Ticket $ticket, ?string $locale = null): string
    {
        return $this->library->render($id, ['queue' => $ticket->queue()->value], $locale)
            ?? throw CannedReplyNotFound::of($id);
    }
}
```

A source that renders text written by its users renders it without running it as a template, or in Twig's sandbox.
