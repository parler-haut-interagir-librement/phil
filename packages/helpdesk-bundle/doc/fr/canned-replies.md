<!-- phil-dev source-sha256=1a55048bd0b2737edbd44efdf0e03392b48943403b1af7fb21a22c76e48828d8 -->
# Réponses types

## Gabarits de la configuration

Une réponse type est un gabarit Twig de l'application, écrit pour la demande à laquelle il répond :

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

Le gabarit reçoit la demande sous le nom `ticket` et la locale de la page de l'agent sous le nom `locale` ; son texte,
rogné, est publié dans la conversation comme le message de l'agent. Une réponse est proposée dans les files qu'elle
nomme, ou dans toutes les files quand elle n'en nomme aucune ; le bureau énumère celles proposées pour la demande
affichée. Ce que choisit un agent est un identifiant, jamais un gabarit : les gabarits sont ceux de l'application,
écrits par ses développeurs.

## Une autre source

Le port `CannedReplies` énumère les réponses proposées pour une demande et en écrit une ; `canned_reply_source` nomme un
service de l'application qui remplace les gabarits de la configuration — un adaptateur vers les gabarits de contenu que
l'application garde déjà, modifiés par son personnel :

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

Une source qui produit le rendu d'un texte écrit par ses utilisateurs le produit sans l'exécuter comme un gabarit, ou
dans le bac à sable de Twig.
