<!-- phil-dev source-sha256=bfe5edb413901ce214dac8dcf13d24c2fa1dbcdf05d84d57e2aa608e7323dfcf -->
# Modération

Chaque avis est publié dès qu'il est écrit, les négatifs comme les positifs. Aucun n'est écarté d'une liste, et aucun
ordre n'en cache. Un avis n'est retiré que sous l'un des critères publiés, et son auteur conteste le retrait une fois,
avec ses preuves.

## Les critères publiés

`removal_criteria` liste les critères, dans l'ordre où la page des règles les affiche. Les valeurs par défaut :

| Code | Quand un avis l'enfreint |
|---|---|
| `spam` | Publicité, liens ou texte qui ne donnent pas d'avis sur le sujet. |
| `off-topic` | Un avis sur autre chose : le transporteur, un autre produit, un différend ailleurs. |
| `hate-or-harassment` | Insultes, menaces, ou attaques contre une personne ou un groupe. |
| `personal-information` | L'adresse, le numéro de téléphone ou d'autres renseignements personnels de quelqu'un. |
| `conflict-of-interest` | Un avis du marchand, de l'organisateur, de leur personnel ou d'un concurrent. |
| `not-a-participant` | Un avis sur un sujet que l'auteur n'a pas acheté ou auquel il n'a pas participé, là où seuls les participants donnent un avis. |
| `illegal` | Un contenu que la loi interdit. |

Chaque page d'avis les affiche sous « How reviews are moderated », avec leurs explications, traduites dans le domaine
`phil_reviews` sous `criterion.<code>` et `criterion.<code>.help`. Un site qui publie d'autres critères liste ses
propres codes et les traduit.

```php
$reviews->withdraw($review->id, 'off-topic');   // ReviewRefused for a code that is not published
```

Un avis retiré quitte les listes et les notes, garde son critère et le moment de son retrait, et son auteur le voit
toujours, avec le critère, sur la page du sujet. `ReviewWithdrawn` est émis.

## Les signalements par phil/moderation-bundle

Avec `phil/moderation-bundle` enregistré, les avis sont une cible de sa file unique, du type `review` : un membre y
signale un avis, avec un motif de son catalogue, et la file affiche l'avis avec son sujet, son extrait et son auteur.

| Décision du modérateur | Ce qui arrive à l'avis |
|---|---|
| `Hide`, `Remove` | Retenu hors de vue, puis retiré sous le critère que nomme le motif public de la décision. |
| `Restore`, `Approve` | Publié de nouveau. |
| `Dismiss`, `Warn` | Rien. |

Le motif public nomme un critère par son code, seul ou suivi de `:` ou d'une espace et d'une explication :
`off-topic: about the courier`. Une décision dont le motif public ne nomme aucun critère publié publie de nouveau
l'avis, et le refus est journalisé au niveau d'avertissement avec l'avis et la décision : aucun avis n'est retiré hors
des critères, pas même par la modération. Un avis caché par des membres de confiance, avant une décision, reste retenu
— hors de vue, sans critère — jusqu'à ce que le modérateur décide.

## Les contestations

```php
$reviews->contest($review->id, $author, 'My review is about the game itself: the rules are in the box.');
$reviews->settleContest($review->id, upheld: true);  // the review is published again
$reviews->settleContest($review->id, upheld: false); // the withdrawal stands
```

L'auteur d'un avis retiré sous un critère le conteste une fois par retrait, depuis la page du sujet, avec ses preuves,
5 000 caractères au plus. `ContestFiled` est émis pour que l'application le porte devant celui qui tranche ; son issue
est conservée, et la page en informe l'auteur.

## Protection contre le pourriel et limites de débit

Avec `phil/spam-protection-bundle` enregistré et `spam_protection` activée, le formulaire d'avis porte son pot de miel,
son jeton signé à usage unique et sa preuve de travail, et le titre et le texte passent par ses règles de contenu et sa
limite de débit. Une soumission qu'il refuse reçoit « This submission could not be accepted », jamais la raison.

Avec `phil/rate-limit-bundle`, les règles de `rate_limit` sont données aux formulaires : `write` au formulaire d'avis,
`vote` aux votes, aux réponses et aux contestations. Un formulaire refusé par un limiteur reçoit la réponse
`429 Too Many Requests` de ce bundle.

```yaml
phil_rate_limit:
    limiters:
        reviews: { policy: sliding_window, limit: 5, interval: '1 hour', key: user_or_ip }
        votes: { policy: token_bucket, limit: 30, rate: { interval: '1 minute', amount: 10 } }

phil_reviews:
    rate_limit:
        write: [reviews]
        vote: [{ limiter: votes, key: user }]
```
