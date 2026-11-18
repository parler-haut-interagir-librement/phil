<!-- phil-dev source-sha256=9db010ccfff51c5f7e8a8b2ef8804dba5979926422c0911a09352c0cf581c97a -->
# Vue du présentateur

La vue du présentateur montre au conférencier chaque diapositive avec ses notes, la diapositive suivante et une
minuterie. Elle contient les notes : elle n'est donc servie qu'au conférencier, par les routes du présentateur du
bundle, qui demandent un rôle, ou à une adresse propre à l'application, derrière son propre contrôle d'accès. La page de
l'auditoire est toujours une route propre à l'application.

## Les routes du présentateur

Le bundle fournit un fichier de routes facultatif, `config/routes.php`, avec deux routes :

| Route | Chemin | Ce qu'elle sert |
|---|---|---|
| `phil_slides_presenter` | `/{deck}` | La vue du présentateur d'une présentation, dans la mise en page `phil_slides.layout` |
| `phil_slides_presenter_notes_pdf` | `/{deck}/notes.pdf` | Le PDF de la présentation avec ses notes, en téléchargement ; demande `phil/paged-media-bundle` |

Rien n'est servi tant que l'application n'a pas importé le fichier, sous le préfixe de son choix et derrière son pare-feu :

```yaml
# config/routes/phil_slides.yaml
phil_slides_presenter:
    resource: '@PhilSlidesBundle/config/routes.php'
    prefix: /presenter
```

Les présentations viennent d'un service de l'application qui implémente `Phil\Slides\Application\Contract\PresentedDecks` :
il trouve une présentation par l'identifiant de l'adresse et rend une `Presentation` — la présentation, l'adresse de sa
page d'auditoire et la durée de la conférence en minutes —, ou `null`.

```php
use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\Presentation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class TalkDecks implements PresentedDecks
{
    public function __construct(private TalkRepository $talks, private DeckReader $reader, private UrlGeneratorInterface $urls)
    {
    }

    public function find(string $id): ?Presentation
    {
        $talk = $this->talks->findOneBySlug($id);

        return null === $talk ? null : new Presentation(
            $this->reader->fromMarkup($talk->source)->withId($id),
            $this->urls->generate('talk_show', ['slug' => $id]),
            $talk->minutes,
        );
    }
}
```

```yaml
# config/services.yaml
services:
    Phil\Slides\Application\Contract\PresentedDecks: '@App\Slides\TalkDecks'
```

Tant que l'application ne nomme pas le sien, `NoPresentedDecks` du bundle n'en trouve aucune et les deux routes
répondent 404. Un identifiant compte de 1 à 128 lettres, chiffres, `_`, `-`, `.` ou `:`, comme `Deck` l'accepte ; toute
autre adresse ne correspond à rien. Utilisez le même identifiant pour la page de l'auditoire, afin que les deux fenêtres
ouvrent le même canal.

## Sécurité

Les deux routes demandent au vérificateur d'autorisation de Symfony Security le rôle `phil_slides.presenter_role`,
`ROLE_SLIDES_PRESENTER` par défaut, avant toute autre chose :

| Qui demande | Réponse |
|---|---|
| Un utilisateur qui détient le rôle | La page, ou le PDF |
| Un utilisateur connecté sans le rôle | 403, que la présentation existe ou non |
| Une personne non connectée | Ce que répond le point d'entrée du pare-feu : une page de connexion, ou 401 |
| N'importe qui, sans `symfony/security-bundle` | 403 : sans vérificateur d'autorisation, les routes sont refusées à tous |
| Un utilisateur qui détient le rôle, pour une présentation que personne n'a | 404 |

Le rôle est vérifié avant que la présentation soit cherchée : un visiteur n'apprend donc rien des présentations qui
existent. Les deux réponses sont envoyées avec `Cache-Control: no-store, private` et `X-Robots-Tag: noindex, nofollow` ;
le PDF aussi avec `X-Content-Type-Options: nosniff` et un `Content-Disposition` à `attachment`, son nom étant
l'identifiant de la présentation suivi de `-notes.pdf`. Donnez le rôle aux conférenciers par la hiérarchie des rôles, ou
nommez-en un autre :

```yaml
phil_slides:
    presenter_role: ROLE_SPEAKER
```

La page étend `phil_slides.layout`, `@PhilSlides/layout.html.twig` par défaut, une page nue avec les blocs `title` et
`main`. Nommez la mise en page propre à l'application, qui charge les contrôleurs et la feuille de style du bundle, avec
les deux mêmes blocs.

## Le gabarit

Une application qui sert elle-même la vue du présentateur inclut le gabarit, derrière son propre contrôle d'accès :

```twig
{{ include('@PhilSlides/presenter.html.twig', {deck: slides_markup(talk.source, 'markdown', 'talk-' ~ talk.id), audience_url: path('talk_show', {id: talk.id}), duration: 20}) }}
```

| Variable | Valeur par défaut | Rôle |
|---|---|---|
| `deck` | obligatoire | Le `Deck`, le même que celui de l'auditoire |
| `audience_url` | aucune | Un lien vers la page de l'auditoire, ouverte dans sa propre fenêtre |
| `notes_pdf_url` | aucune | Un lien qui télécharge le PDF de la présentation avec ses notes |
| `duration` | aucune | La durée de la conférence en minutes : la minuterie montre aussi le temps restant |
| `id` | `phil-slides-presenter` | L'`id` de l'élément, et le préfixe de ceux de ses titres |

Les boutons et les liens sont la recette `Button` du kit que l'application a installé (voir
[Installation](installation.md)).

## Sans script

Chaque diapositive est suivie de ses notes, ou de *No notes for this slide* : un texte à lire, ou à imprimer.

## Avec le contrôleur

`phil--slides--presenter` marque la vue `isPresenting` et montre la diapositive courante avec ses notes ; les
fragments à venir restent visibles pour le présentateur, estompés. À côté :

- la diapositive suivante, une copie sans identifiants, tous les fragments montrés, cachée aux technologies
  d'assistance ; *End of the deck* après la dernière ;
- la minuterie : le temps écoulé et, avec `duration`, le temps restant, négatif et marqué `isOvertime` une fois
  dépassé ; *Start* et *Pause* sur un même bouton à bascule, qui dit s'il est enfoncé, *Reset* ;
- *Previous*, *Next* et le compteur.

Les touches de la page de l'auditoire déplacent la présentation où que soit le focus ; la région dynamique annonce
chaque nouvelle diapositive.

## Deux fenêtres, aucun serveur

Chaque fenêtre d'une présentation ouvre un `BroadcastChannel` nommé `phil-slides:` suivi de l'identifiant de la
présentation. Une fenêtre qui s'ouvre salue les autres ; elles répondent par leur position — la diapositive et le nombre
de ses fragments montrés —, si bien qu'une fenêtre ouverte en retard rejoint la diapositive courante. Un déplacement
dans n'importe quelle fenêtre est envoyé à toutes, et une fenêtre qui en reçoit un le suit sans le renvoyer. Un nombre
quelconque de fenêtres d'auditoire suivent celle du présentateur.

Un `BroadcastChannel` relie les fenêtres d'un même navigateur sur une même origine : ouvrez la vue du présentateur et la
page de l'auditoire dans le même navigateur, depuis le même site. Les deux doivent rendre la même présentation sous le
même identifiant ; donnez-lui-en un stable — `slides_markup(source, 'markdown', 'talk-42')` ou `Deck::withId()` — quand
la présentation peut être modifiée entre les deux.

Le lien de chaque page ouvre l'autre dans une fenêtre qui lui est propre, une fenêtre contextuelle que le présentateur
déplace vers le second écran ; sans script, ou quand le navigateur la bloque, le lien ouvre un onglet.
