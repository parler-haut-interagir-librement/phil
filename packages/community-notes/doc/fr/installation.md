<!-- phil-dev source-sha256=69c83b4f4785be6351985c7b51106a057481e3392aa02d6e5dc0a86fcb502b61 -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `mbstring` | Le texte d'une note est compté en caractères. |
| `phil/resource` | `^0.1` | Les sujets, les auteurs et les évaluateurs nommés par des références (`post:42`, `user:7`). |
| `symfony/uid` | `^8.2` | Les identifiants aléatoires des notes. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | L'heure d'une note et d'une évaluation ; les événements. |
| `symfony/security-core` | `^8.2`, suggéré | `AuthorizationQualification`, qui demande à Symfony Security qui peut rédiger et évaluer. |

```sh
composer require phil/community-notes
```

## Les services

`CommunityNotes` prend les stockages des notes et des évaluations, la qualification, une horloge, la règle — `new
CrossAgreementRule()` par défaut — et, facultativement, un répartiteur d'événements. Dans une application Symfony :

```yaml
# config/services.yaml
services:
    Phil\CommunityNotes\Contract\NoteRepository: '@App\CommunityNotes\DoctrineNoteRepository'
    Phil\CommunityNotes\Contract\RatingRepository: '@App\CommunityNotes\DoctrineRatingRepository'

    Phil\CommunityNotes\Security\AuthorizationQualification:
        arguments: ['@security.authorization_checker', 'ROLE_NOTE_WRITER', 'ROLE_NOTE_RATER']
    Phil\CommunityNotes\Contract\Qualification: '@Phil\CommunityNotes\Security\AuthorizationQualification'

    Phil\CommunityNotes\CrossAgreementRule:
        arguments: { $minRatings: 5, $pairs: 2 }

    Phil\CommunityNotes\CommunityNotes:
        arguments:
            $clock: '@clock'
            $events: '@event_dispatcher'

    Phil\CommunityNotes\Message\ReconsiderNoteHandler:
        tags: [{ name: messenger.message_handler }]
```

Les stockages de l'application implémentent `NoteRepository` et `RatingRepository` : voir
[Points d'extension](extension-points.md). Les stockages en mémoire, `InMemoryNoteRepository` et
`InMemoryRatingRepository`, servent aux tests et aux démonstrations.
