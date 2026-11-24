<!-- phil-dev source-sha256=9767217b7459069c174208c47b45c1e72ac30334e36389437945301a585f62d2 -->
# Configuration

La configuration du bundle se trouve sous `phil_reviews` :

```yaml
# config/packages/phil_reviews.yaml
phil_reviews:
    connection: default
    current_reviewer: ~             # Symfony Security's user identifier
    participations: App\Reviews\OrderParticipations
    responders: ~                   # Symfony Security and responder_attribute
    responder_attribute: ROLE_REVIEWS_RESPONDER
    reviewers: App\Reviews\MemberNames
    subjects: App\Reviews\CatalogueSubjects
    layout: 'base.html.twig'
    kit_subdirectory: ~             # the subdirectory of ux_toolkit
    page_size: 10
    erasure: anonymise              # or remove
    removal_criteria: [spam, off-topic, hate-or-harassment, personal-information, conflict-of-interest, not-a-participant, illegal]
    spam_protection: true
    rate_limit:
        write: [reviews]
        vote: [{ limiter: votes, key: user }]
    types:
        game:
            scale: 5
            criteria: [rules, replayability]
            superlatives: [great-for-beginners, gorgeous, quick-to-teach]
            max_superlatives: 3
            superlatives_shown: 3
            participants_only: false
            window: 30
            prior: { count: 5, mean: ~, minimum: 3 }
            body_length: 5000
        event:
            participants_only: true
            window: 14
```

## Options

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `connection` | `default` | La connexion DBAL des tables. |
| `current_reviewer` | `null` | Le service `CurrentReviewer` qui nomme le membre connecté ; l'identifiant d'utilisateur de Symfony Security quand elle vaut `null`. Un identifiant contenant `@` est refusé : donnez votre propre service quand les utilisateurs se connectent avec leur adresse courriel. |
| `participations` | `null` | Le service `Participations` du commerce ou de la billetterie ; aucun quand elle vaut `null`, de sorte qu'aucun avis n'est marqué vérifié et qu'un type réservé à ses participants ne reçoit aucun avis. |
| `responders` | `null` | Le service `ResponderPolicy` qui dit qui répond aux avis d'un sujet ; Symfony Security quand elle vaut `null`. |
| `responder_attribute` | `ROLE_REVIEWS_RESPONDER` | L'attribut que Symfony Security accorde à ceux qui répondent, le `SubjectRef` donné aux voters. |
| `reviewers` | `null` | Le service `ReviewerDirectory` qui nomme les auteurs ; leurs identifiants quand elle vaut `null`. |
| `subjects` | `null` | Le service `SubjectDirectory` qui nomme les sujets et donne leurs adresses ; leur type et leur identifiant quand elle vaut `null`. |
| `layout` | `@PhilReviews/layout.html.twig` | Le gabarit que les pages étendent, avec les blocs `title` et `main`. |
| `kit_subdirectory` | `null` | L'endroit où se trouvent les recettes du kit sous le répertoire des composants ; le sous-répertoire de `ux_toolkit` quand elle vaut `null`. |
| `page_size` | `10` | Le nombre d'avis qu'une page affiche, de 1 à 100. |
| `erasure` | `anonymise` | Ce que l'effacement d'une personne fait à ses avis : `anonymise` ou `remove` ([Vie privée](privacy.md)). |
| `removal_criteria` | sept codes | Les critères publiés sous lesquels un avis est retiré, au moins un ([Modération](moderation.md)). |
| `spam_protection` | `true` | Soumet le formulaire d'avis à `phil/spam-protection-bundle` quand il est enregistré. |
| `rate_limit.write` | `[]` | Les règles de `phil/rate-limit-bundle` du formulaire d'avis. |
| `rate_limit.vote` | `[]` | Les règles des votes, des réponses et des contestations. |
| `types` | `{}` | Les types de sujet, par nom. Un avis d'un type non déclaré est refusé. |

Une règle de `rate_limit` est le nom d'un limiteur, ou une table de `limiter`, `amount` et `key`, comme
`phil/rate-limit-bundle` lit la valeur par défaut de route `_rate_limit`.

## Un type de sujet

Un type se nomme par une lettre minuscule, puis jusqu'à 63 lettres minuscules, chiffres, `.`, `-` et `_` : `product`,
`game`, `place`, `board.game`.

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `scale` | `5` | Le nombre d'étoiles, de 2 à 10. |
| `criteria` | `[]` | Les codes des critères qu'un avis peut noter, dans l'ordre du formulaire, 20 au plus. |
| `superlatives` | `[]` | Les codes des étiquettes positives qu'un avis peut donner, 64 au plus. |
| `max_superlatives` | `3` | Le nombre de superlatifs qu'un avis donne, de 0 à 3. |
| `superlatives_shown` | `3` | Le nombre de superlatifs les plus fréquents que le sommaire affiche. |
| `participants_only` | `false` | Si seuls les participants que `Participations` connaît donnent un avis sur un sujet du type. |
| `window` | `30` | Le nombre de jours après leur participation pendant lesquels un participant donne son avis ; `null` pour aucune limite. Lue seulement avec `participants_only`. |
| `prior.count` | `5` | Le nombre d'avis fictifs que la moyenne pondérée ajoute, de 0 à 10 000. |
| `prior.mean` | `null` | Leur valeur ; le milieu de l'échelle quand elle vaut `null` — 3 sur 5. |
| `prior.minimum` | `3` | Le nombre d'avis avant qu'une moyenne soit affichée et que les données structurées soient données. |
| `body_length` | `5000` | Le nombre de caractères que contient le texte d'un avis, de 1 à 20 000. |

Un code — d'un critère, d'un superlatif ou d'un critère de retrait — est une lettre minuscule, puis jusqu'à 63 lettres
minuscules, chiffres, `-` et `_`. Son libellé est traduit dans le domaine `phil_reviews`, sous `criteria.<code>`,
`superlatives.<code>` et `criterion.<code>`.

Changer l'a priori d'un type change les moyennes pondérées déjà conservées : recalculez-les avec
`php bin/console phil:reviews:recompute`.
