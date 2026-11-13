<!-- phil-dev source-sha256=94c671ecaa49f56cee40ae6138168797debc31e4bac4e8ff88d51e2862d08349 -->
# L'interface de vote

Le bundle donne aux bundles qui reposent sur les sondages — un sondage de disponibilités, un questionnaire, une boîte à
idées, une assemblée — et à l'application une façon d'afficher un sondage : le formulaire de son bulletin et ses
résultats, sous forme de vues que dessine un gabarit. Les gabarits écrivent le balisage des recettes du kit, si bien
que `phil/ux-css` les met en forme comme il met en forme les recettes, que celles-ci soient installées ou non. La page
qui les affiche garde sa propre route, son propre contrôleur et ses propres mots autour d'eux.

## Les vues

`PollViews` les construit pour une personne qui regarde, une référence de `phil/resource`, ou `null` pour quelqu'un qui
n'est pas connecté.

`ballot($poll, $viewer)` donne un `BallotView` :

| Champ | Ce qu'il contient |
|---|---|
| `poll`, `question` | L'identifiant et la question. |
| `kind` | `single`, `multiple` ou `ranked`. |
| `choices` | Un `BallotChoice` par option, dans l'ordre déclaré : `id`, `label`, `chosen` et, pour un bulletin classé, `rank`. |
| `minChoices`, `maxChoices`, `abstention` | Les bornes d'un bulletin, et si un bulletin blanc est accepté. |
| `anonymous`, `seats` | Si les bulletins sont gardés à l'écart de leurs votants ; les sièges que pourvoit le dépouillement. |
| `open`, `voted` | Si le sondage est ouvert maintenant ; si la personne a déposé un bulletin. |
| `mayCast`, `mayChange` | Si la personne peut déposer un bulletin maintenant, ou modifier le sien. `isEditable()` est l'un ou l'autre. |

Le bulletin de la personne remplit `chosen` et `rank` quand le sondage n'est pas anonyme : un bulletin anonyme n'est
connu que de qui détient son reçu. `mayCast` consulte la politique ; `mayChange` n'est jamais vrai dans un sondage
anonyme, dont les bulletins se modifient par reçu.

`results($poll, $viewer)` donne un `ResultsView`, ou `null` tant que les résultats ne sont pas ceux que la personne a le
droit de voir, comme le dit [Résultats](results.md). Il contient la méthode, les bulletins et les abstentions comptés,
les sièges, `revealed` — le sondage est fermé — et une `ResultLine` par option, dans l'ordre du classement : `label`,
`place`, `score`, `display`, `max` et `winner`. `max` est le score le plus élevé, d'au moins 1, pour la longueur d'une
barre. `display` est le score tel qu'il s'écrit : un vote unique transférable compte en milliardièmes de vote, écrits
avec deux décimales, tronqués (`2.71`). `winners()` énumère les libellés des options qui ont gagné ou qui ont été élues.

## Dans un gabarit

Avec Twig, les fonctions `phil_poll_ballot(poll, viewer)` et `phil_poll_results(poll, viewer)` donnent les vues ; la
personne est une référence sous forme de texte (`'user:7'`), un objet, ou omise pour quelqu'un qui n'est pas connecté.
Les gabarits les dessinent :

```twig
{{ include('@PhilPoll/ballot.html.twig', {
    ballot: phil_poll_ballot(poll.id, member),
    action: path('idea_vote', {id: idea.id}),
    csrf_token: csrf_token('idea_vote'),
}) }}

{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll.id, member)}) }}
```

`@PhilPoll/ballot.html.twig` prend la vue, l'adresse où le formulaire poste et, quand la page en vérifie un, un jeton
CSRF posté sous `_token`. Il dessine un `fieldset` nommé par la question, une phrase d'aide, les choix et un bouton. Un
bulletin à choix unique est un groupe de boutons radio, avec un bulletin blanc quand le sondage en accepte un ; un
bulletin à choix multiple, des cases à cocher ; un bulletin classé, une liste de rangs par option. Le `fieldset` est
désactivé, sans bouton, quand la personne ne peut ni déposer ni modifier un bulletin ; une phrase en donne la raison.
L'intégrer remplace un bloc : `choices`, `status` ou `actions`.

`@PhilPoll/results.html.twig` prend la vue. Il dessine la question, les bulletins comptés et une ligne par option avec
sa barre, son score et, pour les gagnantes, le mot qui le dit ; ou une phrase tant que les résultats sont cachés.
L'intégrer remplace `lines`, ou remplit `revealed`.

Le balisage est celui des recettes `Field`, `RadioGroup`, `Checkbox`, `NativeSelect`, `Button` et `Progress` : leurs
attributs `data-slot` et leurs classes `isX`. Les mots viennent du domaine de traduction `phil_poll`, en anglais et en
français ; une application les remplace dans son propre `translations/phil_poll.<locale>.php`, et un gabarit sous
`templates/bundles/PhilPollBundle/`.

## Lire ce que poste le formulaire

`BallotForm::choices($rules, $fields)` lit les champs que poste le formulaire pour en faire les choix d'un bulletin :
`choice` pour un bulletin à choix unique, `choices[]` pour un bulletin à choix multiple, `ranks[option]` pour un
bulletin classé, un rang vide laissant l'option de côté. Tout ce que le formulaire n'envoie pas — une liste là où un
texte est attendu, une option que le sondage n'a pas, un rang qui n'est pas un nombre de 1 à 999, deux options qui
partagent un rang — lève `InvalidBallot`. `Polls` vérifie de nouveau les choix selon les règles du sondage.

```php
use Phil\PollBundle\Application\BallotForm;

#[Route('/ideas/{id}/vote', methods: ['POST'])]
public function vote(string $id, Request $request): Response
{
    $poll = $this->polls->get($this->ideas->pollOf($id));
    $choices = BallotForm::choices($poll->rules(), $request->request->all());

    $this->polls->hasVoted($poll->id, $this->member())
        ? $this->polls->change($poll->id, $this->member(), $choices)
        : $this->polls->cast($poll->id, $this->member(), $choices);

    return $this->redirectToRoute('idea_show', ['id' => $id], Response::HTTP_SEE_OTHER);
}
```

La page qui reçoit le formulaire vérifie son jeton CSRF, comme tout formulaire de l'application.

## Célébrer les résultats révélés

Une fois un sondage fermé, ses résultats sont révélés. Deux crochets permettent à une page de les célébrer, par
exemple avec la `Celebration` du kit, sans que le bundle la connaisse.

**L'événement du DOM.** Les résultats portent le contrôleur Stimulus `phil--poll-bundle--results`, tiré de
l'`assets/package.json` du bundle. Quand les résultats qu'il dessine sont révélés, il diffuse `poll:revealed` sur la
fenêtre, son détail nommant le sondage et les libellés de ses gagnants, une seule fois par sondage dans un onglet du
navigateur. La recette du kit joue sur cet événement :

```twig
<twig:Celebration message="{{ 'idea.chosen'|trans }}" data-action="poll:revealed@window->celebration#play" />
```

**Le bloc Twig.** Le bloc `revealed` de `@PhilPoll/results.html.twig` n'est dessiné que pour des résultats révélés :

```twig
{% embed '@PhilPoll/results.html.twig' with {results: phil_poll_results(poll.id, member)} %}
    {% block revealed %}<twig:Celebration message="{{ 'poll.revealed'|trans }}" :autoplay="true" />{% endblock %}
{% endembed %}
```

La recette dit son message dans une région vivante polie et ne dessine rien là où le mouvement est réduit.

## Accessibilité

| Quoi | Comment |
|---|---|
| La question | La légende du `fieldset`, qui nomme ses choix |
| Les bornes d'un bulletin | Une phrase d'aide, que le `fieldset` nomme par `aria-describedby` |
| Chaque choix | Un `label` lié à son contrôle par `for` |
| Un bulletin classé | Une liste native par option, atteignable au clavier, lue avec son libellé |
| Pourquoi le formulaire ne peut pas être envoyé | Une phrase dans une région `status` ; les contrôles sont désactivés, non cachés |
| Un résultat | Chaque barre nommée par son option avec `aria-labelledby` ; le score écrit à côté |

Tout fonctionne sans JavaScript : le formulaire est un formulaire ordinaire, et les résultats une liste.

## Plus tard

Les formulaires d'une question sans pointage — une échelle, un nuage de mots, un indice de recommandation, un
remue-méninges — et le dépouillement par notes et par jugement majoritaire sont construits quand un site les demande ;
aucun ne fait encore partie du bundle.
