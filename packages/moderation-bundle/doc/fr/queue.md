<!-- phil-dev source-sha256=9634bedb19cc2c77def3ff7ebd292b28e1df073a65bb494e5c338841f726ae74 -->
# La file

## Cibles

Une cible est ce qui peut être signalé : un message de forum, un commentaire, une réaction, une annonce, un message, un
profil. Chacune est désignée par une `TargetReference` — un type tel que `forum.post`, et l'identifiant du contenu dans
ce type. Le paquet qui détient le contenu implémente `ModerationTarget` :

```php
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

final readonly class ListingTarget implements ModerationTarget
{
    public function type(): string
    {
        return 'listing';
    }

    public function describe(string $id): ?TargetView
    {
        $listing = $this->listings->find($id);

        return null === $listing ? null : new TargetView($listing->title, $listing->summary, '/listings/'.$id, $listing->owner);
    }

    public function apply(string $id, DecisionAction $action): void
    {
        match ($action) {
            DecisionAction::Approve, DecisionAction::Restore => $this->listings->publish($id),
            DecisionAction::Hide => $this->listings->hide($id),
            DecisionAction::Remove => $this->listings->remove($id),
            default => null,
        };
    }
}
```

L'autoconfiguration enregistre le service. Le paquet garde son contenu et sa visibilité ; le bundle ne lit jamais ses
tables. `apply()` doit être idempotente : une décision peut être rejouée.

## Signalements

`Reports::report($target, $reason, $comment)` dépose le signalement du membre connecté. La raison est l'un des codes
configurés, le commentaire compte jusqu'à 1 000 caractères. **Un membre signale une cible une seule fois tant qu'elle
est ouverte** : un second signalement rend le premier. Un signalement sur un type qu'aucun paquet n'a enregistré est
refusé avec `UnknownTarget` ; celui d'un visiteur, ou celui dont la raison est hors du catalogue, avec
`ModerationRefused`.

Chaque signalement garde sa source : `member`, `watched_words`, `sandbox` ou `trusted_hide` ([Crochets](hooks.md)).

## La file unique

`ModerationQueue::items($limit, $offset)` donne les cibles qui ont des signalements ouverts, le signalement le plus
ancien d'abord, avec leur nombre, leur premier et leur dernier moment, leurs raisons et leurs sources, chacune une fois.
Un modérateur voit les types que son rôle couvre ; un membre qui n'est pas modérateur ne voit rien. `entry($target)`
donne une cible telle qu'un modérateur l'examine : ce que son paquet en dit, ses signalements ouverts et son
historique.

## Rôles

La configuration énumère les rôles dans l'ordre ; le membre courant agit sous le premier dont l'attribut de sécurité lui
est accordé. Un rôle avec `types` décide sur ces types seulement, et ne voit qu'eux dans la file.

## Décisions

`Decisions::decide($target, $action, $publicReason, $note, $public, $grounds)` est la décision d'un modérateur :

| Action | Ce qu'on demande au paquet de la cible |
|---|---|
| `Dismiss` | Rien ; le contenu reste tel quel |
| `Approve` | Publier un contenu retenu |
| `Hide` | Cacher le contenu au public |
| `Restore` | Montrer de nouveau un contenu masqué |
| `Remove` | Retirer le contenu |
| `Warn` | Rien ; l'auteur est averti |

La décision garde le modérateur, le rôle, la raison que montre le journal public (jusqu'à 500 caractères), la note que
seule la modération lit (jusqu'à 2 000), et l'auteur que le paquet de la cible a désigné à ce moment. Elle ferme chaque
signalement ouvert sur la cible. Un membre qui n'est pas modérateur, ou dont le rôle ne couvre pas le type, est refusé
avec `ModerationRefused`. `on($target)` et `about($author)` donnent l'historique, le plus récent d'abord.

`Hide`, `Remove` et `Warn` restreignent l'auteur : **une telle décision sur un contenu qui a un auteur s'accompagne
d'un exposé des motifs**, et ses `Grounds` — une raison, les faits, une disposition — sont obligatoires et vérifiés
avant que l'action soit appliquée ([Exposés des motifs](statements-of-reasons.md)).

## Événements

| Événement | Quand |
|---|---|
| `ReportFiled` | Un signalement a été déposé, par un membre ou par le bundle |
| `DecisionTaken` | Un modérateur a décidé |
| `ContentHeld` | Un contenu attend la modération : un premier message, ou un texte que les mots surveillés ont retenu |
| `ContentHiddenByMember` | Un membre de confiance a masqué un contenu |
| `StatementIssued` | Un exposé des motifs a été émis à l'auteur d'un contenu restreint, après `DecisionTaken` |

Ils sont répartis dans le processus, avec le signalement, la décision ou l'exposé ; un écouteur qui envoie un message ne
porte que leurs identifiants.
