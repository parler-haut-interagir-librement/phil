<!-- phil-dev source-sha256=15dd21d340b47007831b5cfcc6b00fb758fb0f7477503501ea099bbb36184e58 -->
# phil/slides

**Des diapositives dans Symfony : un document UJO, ou une source Markdown, découpé en diapositives, avec des notes du
présentateur que l'auditoire ne voit jamais, une vue du présentateur dans une seconde fenêtre derrière une route protégée facultative, des fragments, le plein
écran et l'export PDF.**

phil/slides transforme un document UJO (Universal JSON Output) en présentation : une diapositive par section, ou par
bloc séparateur — le `---` de Markdown. Une source Markdown est lue par `phil/markup`, dont l'en-tête YAML donne à la
présentation ses directives globales : titre, langue, classes, pagination, en-tête, pied de page, forme et niveau de
titre qui coupe. Chaque diapositive est rendue par le service `Ujo` de `phil/ujo-bundle`, donc vérifiée et purifiée
comme tous les autres documents de l'application. Les notes du présentateur — un conteneur `::: notes`, ou la
modulation `note` de n'importe quel bloc, que `phil/markup` donne au bloc qui suit un commentaire HTML — sont retirées
de ce que voit l'auditoire : elles ne figurent ni dans la page de l'auditoire ni dans le PDF public. Un commentaire
fait de directives Marp, `<!-- _class: lead -->`, met plutôt en forme sa diapositive.

Deux gabarits dessinent la présentation. La page de l'auditoire se lit comme une simple suite de diapositives, sans
script ; son contrôleur Stimulus montre une diapositive à la fois, révèle les fragments un à un, répond aux flèches, à
Page précédente, Page suivante, Début, Fin et Échap, passe en plein écran, garde l'adresse sur la diapositive courante
et annonce chaque nouvelle diapositive dans une région dynamique. La vue du présentateur, ouverte dans une seconde
fenêtre, montre la diapositive courante avec ses notes, la suivante et une minuterie ; les deux fenêtres restent à la
même position grâce à un `BroadcastChannel`, sans serveur. Un fichier de routes facultatif sert la vue du présentateur
et le PDF avec les notes à un seul rôle configurable, `ROLE_SLIDES_PRESENTER` par défaut ; tous les autres sont refusés
avant même qu'une présentation soit cherchée. L'impression passe par `phil/paged-media` : une page par diapositive, les
notes omises sauf demande, et un PDF produit par le moteur d'impression de `phil/paged-media-bundle` lorsqu'il est
installé.

Il s'adresse aux applications Symfony qui présentent des conférences, des cours ou des rapports sous forme de
diapositives, à partir des documents qu'elles conservent déjà. Les contrôleurs sont servis par AssetMapper ; les boutons et
les liens sont la recette `Button` du kit PHIL UX ou du kit Shadcn, installée par le Toolkit de Symfony UX ;
l'apparence repose sur les jetons `--phil-*` de la feuille de style de PHIL UX, et les libellés sont en anglais et en
français.

## État

| Partie | État |
|---|---|
| `Slicer` : un document coupé à ses séparateurs ou à ses titres, notes retirées, directives globales lues | Implémenté et testé |
| `CommentDirectives` : notes et directives Marp tirées des commentaires HTML, que `phil/markup` conserve comme modulations `note` | Implémenté et testé |
| `DeckReader` : des présentations à partir d'un document, de son JSON, d'une lecture de `phil/markup` ou d'une source | Implémenté et testé |
| `SlideRenderer` : chaque diapositive et ses notes rendues par le service `Ujo` de `phil/ujo-bundle` | Implémenté et testé |
| Les gabarits de l'auditoire et du présentateur, les fonctions Twig, leurs boutons du kit PHIL UX ou Shadcn | Implémenté et testé |
| Les routes du présentateur : vue du présentateur et PDF avec les notes, pour le rôle `presenter_role` seulement | Implémenté et testé |
| Les contrôleurs Stimulus `deck` et `presenter`, et la feuille de style | Implémenté et testé |
| `DeckPrint` et `DeckPdf` : une page par diapositive, notes sur demande | Implémenté et testé |
| La configuration `phil_slides`, la recette Flex `recipe/0.1` | Implémenté et testé |
| Les libellés en anglais et en français | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la recette Flex, les contrôleurs et la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_slides`. |
| [Rédaction](doc/fr/authoring.md) | Où les diapositives sont coupées, les notes, les fragments, les directives d'une source. |
| [Montrer une présentation](doc/fr/showing.md) | La page de l'auditoire, le clavier, le plein écran, l'accessibilité, l'apparence. |
| [Vue du présentateur](doc/fr/presenter.md) | Les routes du présentateur et leur sécurité, la seconde fenêtre, les notes, la diapositive suivante, la minuterie, la synchronisation. |
| [Export PDF](doc/fr/pdf.md) | `DeckPrint` et `DeckPdf`, les pages, les notes. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure, remplace ou substitue. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/slides
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```twig
{# the audience, at a route of the application #}
{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(talk.source, 'markdown', talk.slug), presenter_url: path('phil_slides_presenter', {deck: talk.slug})}) }}
```

```yaml
# config/routes/phil_slides.yaml: the speaker's view and the PDF with notes, for ROLE_SLIDES_PRESENTER only
phil_slides_presenter:
    resource: '@PhilSlidesBundle/config/routes.php'
    prefix: /presenter
```

Les routes du présentateur trouvent chaque présentation par un service de l'application qui implémente
`PresentedDecks` ; voir [Vue du présentateur](doc/fr/presenter.md).

```markdown
---
title: A talk
paginate: true
---

# Welcome

::: notes
Thank the organisers.
:::

---

## The point

::: fragment
Revealed on the next key press.
:::
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` ; `phil/ujo` et `phil/ujo-bundle` 0.1, `phil/markup` 0.2,
`phil/paged-media` 0.1 ; `symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation`,
`symfony/http-kernel` et `symfony/routing` 8.2 ou plus récent ; `symfony/ux-twig-component` 3.7 ou plus récent ;
`twig/twig` 3.21 ou plus récent ; la recette `Button` de `phil/ux-kit` ou du kit Shadcn. TwigBundle, AssetMapper,
StimulusBundle, SecurityBundle, le traducteur et `phil/paged-media-bundle` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
