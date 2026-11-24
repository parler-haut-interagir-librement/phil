<!-- phil-dev source-sha256=a35089c836e5bd7d24516df5d7a6f282a0d90724e648904888105b896ee64fcf -->
# Pages et gabarits

## Les routes

`config/routes.php` déclare les deux pages d'un répondant sans compte, servies par `AnswerController` :

| Route | Chemin | Ce qu'elle fait |
|---|---|---|
| `phil_scheduling_poll_join` | `/{poll}/join`, GET et POST | La page du sondage : ses dates, et un formulaire qui demande un nom et, facultativement, une adresse ; soumis, il mène le répondant à son propre lien. |
| `phil_scheduling_poll_answer` | `/answer?token=…`, GET et POST | La page du répondant : le tableau des dates et de leurs réponses ; soumis, il les enregistre et revient avec `saved=1`. |

Les deux répondent avec `Referrer-Policy: no-referrer`, afin qu'un lien sortant de la page ne porte jamais le jeton,
`Cache-Control: private, no-store` et `X-Robots-Tag: noindex`. Un lien qui n'ouvre rien répond 404 avec une seule phrase
pour toutes les raisons ; les réponses refusées répondent 422 avec le message. Quand l'application a
`symfony/security-csrf`, les deux formulaires portent un jeton CSRF. Une page pour l'organisateur — créer un sondage,
inviter, clôturer — revient à l'application, qui appelle `SchedulingPolls` et dessine
`@PhilSchedulingPoll/results.html.twig`.

## Les gabarits

| Gabarit | Variables | Blocs |
|---|---|---|
| `@PhilSchedulingPoll/answer.html.twig` | `sheet`, `token`, `action`, `csrf_token`, `errors`, `saved` | `chosen`, `celebration`, `answers`, `results` |
| `@PhilSchedulingPoll/join.html.twig` | `poll`, `slots`, `open`, `action`, `csrf_token`, `errors`, `values` | `fields` |
| `@PhilSchedulingPoll/results.html.twig` | `results`, `celebrate` | `chosen`, `celebration`, `table` |

La page de réponse est un formulaire autour d'un tableau : une ligne par date, son en-tête étant la date dans un
élément `time`, puis trois cellules, chacune un bouton radio — oui, au besoin, non — nommé `answers[<slot>]`. Les
boutons d'une ligne forment un seul groupe : Tab l'atteint, les flèches s'y déplacent, Espace choisit. Le nom
accessible de chaque bouton est la date de sa ligne et le mot de sa colonne, par `aria-labelledby`. Sans JavaScript, le
formulaire est soumis et la page revient. Le tableau se trouve dans le conteneur défilant du kit, et le tableau de
toutes les réponses dans une région que le clavier atteint (`tabindex="0"`), de sorte qu'un écran de 375 pixels montre
chaque date. Chaque réponse de ce tableau est écrite par un signe pour l'œil et par des mots pour un lecteur d'écran.

Les dates sont écrites par `format_datetime` de `twig/intl-extra`, dans la langue de la page ; les mots viennent du
domaine de traduction `phil_scheduling_poll`, en anglais et en français. Une application les remplace dans son propre
`translations/phil_scheduling_poll.<locale>.php`, et un gabarit sous `templates/bundles/PhilSchedulingPollBundle/`.

Avec Twig, trois fonctions :

| Fonction | Donne |
|---|---|
| `phil_scheduling_poll_results(poll, organizer, timezone)` | Le `ResultsView` de l'organisateur ; l'organisateur comme référence, texte ou objet. |
| `phil_scheduling_poll_timezones()` | Les fuseaux horaires de la base de données IANA, pour la liste de sélection de la page de réponse. |
| `phil_scheduling_poll_kit(recipe)` | Le nom d'une recette du kit telle que l'application l'a installée, `ui:Table:Row`. |

```twig
{{ include('@PhilSchedulingPoll/results.html.twig', {results: phil_scheduling_poll_results(poll.id, app.user.reference)}) }}
```
## Les contrôleurs Stimulus

`assets/package.json` déclare deux contrôleurs pour Symfony UX :

| Contrôleur | Ce qu'il ajoute |
|---|---|
| `phil--scheduling-poll-bundle--timezone` | À la première visite, choisit le fuseau horaire du navigateur dans la liste de sélection, quand elle l'offre ; réécrit chaque date, avec les mots propres du navigateur, dans le fuseau choisi, avant que quoi que ce soit soit soumis. |
| `phil--scheduling-poll-bundle--chosen` | Une fois la date choisie, répartit l'événement DOM `scheduling-poll:chosen` sur la fenêtre, son détail étant le sondage et le début de la date, une fois par sondage dans un onglet du navigateur. |

Sans eux, la page fonctionne de la même façon : les dates sont écrites dans le fuseau donné à la page, et le fuseau
soumis est utilisé dès lors.

## La célébration

La date choisie est une victoire : le bloc `celebration`, vide, est dessiné à côté pour la Celebration du kit, qui se
joue sur l'événement du contrôleur `chosen`. Elle dit son message dans une région vivante polie, et ne dessine ses
confettis que là où le mouvement n'est pas réduit :

```twig
{% embed '@PhilSchedulingPoll/answer.html.twig' %}
    {% block celebration %}
        <twig:Celebration message="{{ 'poll.date_chosen'|trans }}" data-action="scheduling-poll:chosen@window->celebration#play" />
    {% endblock %}
{% endembed %}
```
Côté serveur, l'événement `DateChosen` est réparti une seule fois, par la clôture gagnante.
