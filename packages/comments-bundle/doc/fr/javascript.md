<!-- phil-dev source-sha256=55a66f107da0580bf775b6a5e13cc300039fb0c8023f0deefccdf28ddbca9779 -->
# JavaScript

## Le contrôleur

```twig
<div data-controller="phil--comments-bundle--comments"
     data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}">
    {{ form_row(form.body) }}  {# the block editor of phil/block-editor-bundle #}
    <aside data-phil--comments-bundle--comments-target="panel"></aside>
</div>
```

`comments` vaut `ClientConfig::for()`, auquel la page peut ajouter :

| Clé | Valeur par défaut | Rôle |
|---|---|---|
| `locale` | le `lang` de la page | Les libellés `en` ou `fr` ; une autre langue prend l'anglais |
| `messages` | aucune | Les libellés propres à l'application, par clé |
| `refresh` | `0` | Les secondes entre deux chargements des fils ; `0` les charge une seule fois |

Le contrôleur trouve l'éditeur par blocs dans son élément. Il fonctionne de la même façon autour d'un éditeur en
lecture seule : les fils peuvent y être lus, ouverts et répondus, mais aucune suggestion n'y est acceptée. Sans
éditeur — un article publié, une page d'un site —, seule la page entière est commentée :

```twig
<section data-controller="phil--comments-bundle--comments"
         data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}"></section>
```

`ClientConfig::for($subject, $viewer, page: false)` omet le formulaire de la page, pour un écran de révision où seuls
des passages sont discutés ; les fils déjà sur la page sont quand même listés. Une page qui doit fonctionner sans aucun
script dessine plutôt les commentaires sur la page côté serveur : voyez [la page](page.md).

Le contrôleur est déclaré `"fetch": "lazy"` : StimulusBundle ne le charge que lorsqu'un élément de la page le nomme.

## Mentions

Avec `phil/mentions-bundle` activé et les routes de l'éditeur par blocs importées, `ClientConfig::for()` donne
`mentions`, l'adresse de la recherche de membres. Chaque zone de texte du panneau — le formulaire de rédaction, une
réponse, une modification — prend alors le contrôleur `mention` de ce bundle — le formulaire de la page aussi —, dont
l'autocomplétion `@` écrit l'identifiant choisi et dit combien de membres elle a trouvés ; ce bundle enregistre son
contrôleur et sa feuille de style. Sans l'adresse, les zones de texte restent simples.

Le champ reste du texte simple : le serveur retrouve les identifiants quand ce bundle enregistre le commentaire, à
chaque commentaire écrit, et seuls les membres qui peuvent voir le commentaire sont notifiés, comme en décide
`phil/mentions-bundle`.

## Ce qu'il fait

- **Sélection.** Quand le lecteur sélectionne des mots à l'intérieur d'un champ d'un bloc, une petite barre d'outils
  offre *Comment* et *Suggest*. Une sélection qui couvre deux champs, ou vide, n'offre rien.
- **Rédaction.** Le passage, une zone de texte et, pour une suggestion, le remplacement — d'abord le passage lui-même,
  vidé pour proposer un retrait. L'envoi ouvre le fil.
- **Panneau.** Les fils dans l'ordre de leurs passages dans le document, les fils orphelins — dont le passage n'est
  trouvé ni par le serveur ni dans l'éditeur — à la fin, sous *Orphaned: the quoted passage is no longer in the text* ;
  les fils résolus sur demande. Chacun montre son passage, son état, sa suggestion sous forme de suppression et
  d'insertion, ses commentaires avec leur auteur et leur date, un formulaire de réponse et les boutons que permet le
  `may` du serveur.
- **La page.** Sous les fils sur des passages, une section intitulée *On the page* liste les fils sur la page entière et
  se termine par un formulaire pour en écrire un : envoyé, le fil rejoint la liste et le formulaire se vide ; refusé, le
  texte reste dans le formulaire et l'état du panneau dit pourquoi.
- **Surlignage.** Les passages des fils ouverts sont surlignés dans l'éditeur par la CSS Custom Highlight API, qui
  colore le texte sans toucher au balisage de l'éditeur ; celui du fil actif l'est d'une couleur plus forte. Pendant
  que l'auteur tape, chaque passage est retrouvé dans l'éditeur avec les règles de [l'ancrage](anchoring.md).
- **Navigation.** Un clic sur un passage surligné met son fil en avant dans le panneau ; un clic sur le passage d'un
  fil dans le panneau fait défiler l'éditeur jusqu'à lui.
- **Acceptation.** Le passage est remplacé dans le bloc de l'éditeur, l'acceptation est enregistrée et le bloc est
  appliqué à l'éditeur par son événement `phil-block-editor:apply` ; voyez [les suggestions](suggestions.md).

## Modules

| Module | Rôle |
|---|---|
| `comments_controller.js` | Le contrôleur : la sélection, le panneau, le surlignage, les actions |
| `lib/passage.js` | Les caractères comptés comme le serveur les compte ; le texte du HTML en ligne ; un passage retrouvé et remplacé ; les champs de l'éditeur et leurs chemins ; les points DOM et les positions des caractères |
| `lib/api.js` | Les requêtes aux points de terminaison, avec l'en-tête du contrôleur et, pour un `POST`, un nouveau jeton CSRF |
| `lib/csrf.js` | Un nouveau jeton aléatoire pour chaque requête, son témoin et son en-tête |
| `lib/view.js` | Les éléments du panneau ; chaque texte qu'envoie le serveur est inséré comme du texte |
| `lib/i18n.js` | Les libellés en anglais et en français |

## Les rôles de WAI-ARIA 1.3

Le brouillon de WAI-ARIA 1.3 ajoute des rôles pour les annotations. Avec `aria_roles: true` dans la configuration du
bundle, `ClientConfig` donne `ariaRoles` au contrôleur, qui les écrit alors dans le panneau :

| Élément | Sans | Avec `ariaRoles` |
|---|---|---|
| Un commentaire | Son auteur, sa date et son texte dans l'élément de liste | Les mêmes, enveloppés dans un élément de rôle `comment` |
| Une suggestion | L'état, `<del>` et `<ins>` côte à côte | L'état, puis un élément de rôle `suggestion` qui ne possède que `<del>` et `<ins>` ; le mot pour un retrait est hors de lui |
| Un passage | La citation | La citation, avec `aria-details` qui nomme la liste des commentaires de son fil, `phil-comments-<thread>-comments` |

L'option reste désactivée jusqu'à ce que le brouillon devienne une recommandation et que les lecteurs d'écran annoncent
les rôles ; le balisage est autrement le même. Les surlignages de l'éditeur passent par la CSS Custom Highlight API, qui
n'ajoute aucun élément : un passage dans l'éditeur ne porte donc aucun attribut.

## La feuille de style

`comments.css` met en forme le panneau, la barre d'outils de sélection et le surlignage
(`::highlight(phil-comments)` et `::highlight(phil-comments-active)`). Elle s'appuie sur des propriétés personnalisées
qui prennent les jetons de PHIL UX quand sa feuille de style est chargée, et les couleurs de la page sinon.
