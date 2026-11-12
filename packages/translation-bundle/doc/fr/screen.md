<!-- phil-dev source-sha256=92c2fd08e742b9498b3610362c70fd83dcf75a4028df7afb3026ad15d9a47f86 -->
# L'écran de traduction

## Routes

| Route | Méthode et chemin | Fait |
|---|---|---|
| `phil_translation_page` | `GET /{document}/{language}` | La page d'un document dans une langue cible |
| `phil_translation_save` | `POST /{document}/{language}/segment` | Une action sur un segment, puis le retour à ce segment avec une `303` |
| `phil_translation_comment` | `POST /{document}/{language}/comment` | `action=add` commente un segment, `action=remove` supprime un commentaire, puis retour avec une `303` |
| `phil_translation_machine` | `POST /{document}/{language}/machine` | Des traductions automatiques proposées aux segments non traduits, puis retour avec leur nombre |
| `phil_translation_export` | `GET /{document}/{language}/xliff` | Le document en pièce jointe XLIFF 2.1 |
| `phil_translation_import` | `POST /{document}/{language}/xliff` | Un fichier XLIFF fusionné, et la page avec un rapport |

Une référence de document compte de 1 à 255 lettres, chiffres, `_`, `.`, `:` ou `-` ; une langue, de 2 à 35 lettres,
chiffres ou `-`. Le même fichier importe les [pages de glossaire](glossary-pages.md).

## Les services de l'application

```php
use Phil\TranslationBundle\Application\SourceDocument;
use Phil\TranslationBundle\Application\SourceDocuments;
use Phil\TranslationMemory\LanguageTag;

final readonly class ArticleSources implements SourceDocuments
{
    public function __construct(private ArticleRepository $articles) {}

    public function find(string $document): ?SourceDocument
    {
        $article = str_starts_with($document, 'article:') ? $this->articles->find(substr($document, 8)) : null;

        return null === $article ? null : new SourceDocument($article->content(), LanguageTag::of($article->language()), $article->title());
    }
}
```

`TranslationPolicy` répond à trois questions pour l'utilisateur courant : `canTranslate()` (voir la page, écrire et
proposer des cibles, les confirmer et les vider, écarter des contrôles, commenter, demander des traductions
automatiques, exporter et importer), `canReview()` (marquer des cibles comme révisées et supprimer des commentaires) et
`canEditGlossary()` (modifier le glossaire d'une paire de langues).

| Réponse | Quand |
|---|---|
| 404 | Aucun `sources` ou aucune `policy` configuré ; un document que l'application ne trouve pas ; une langue qui n'est pas une étiquette ; un segment que le document n'a pas ; un commentaire qu'il n'a pas ; la traduction automatique alors qu'elle est désactivée |
| 403 | La langue cible est celle de la source ; l'utilisateur ne peut pas traduire ; une révision ou la suppression d'un commentaire par un utilisateur qui ne peut pas réviser ; un formulaire venu d'un autre site |
| 400 | Une action que l'écran ne connaît pas |
| 422 | Un changement que le segment ne peut pas accepter ; un contrôle qui n'est pas le nom d'un indicateur, ou des indicateurs trop longs ; un commentaire vide ou de plus de 4000 caractères ; une importation sans fichier, d'un fichier de plus de 10 Mio, illisible, ou d'autres langues ; la page dit pourquoi |
| 502 | Le traducteur automatique a échoué, ou n'a pas répondu une cible par source ; la page le dit, sans ce que le service a répondu |

## La page

La page prévisualise la préparation du document, sans rien enregistrer, puis affiche son avancement, le bouton
de traduction automatique quand un traducteur est configuré, le lien d'exportation et le formulaire d'importation, et
une ligne par segment : son état et sa clé, la marque `machine translation` sur une cible proposée par la machine, sa
source telle qu'écrite (les balises affichées en texte), ce qui a changé dans une source périmée, les termes et les
notes du glossaire, la cible qu'une variante régionale hérite, un formulaire avec la cible, les problèmes de glossaire,
les contrôles de qualité avec un bouton qui écarte chacun, les indicateurs du segment, les correspondances de la
mémoire pour tout segment pas encore utilisable, chacune avec un bouton qui la propose, et les commentaires avec un
formulaire pour en ajouter un.

| Bouton | Action | Affiché |
|---|---|---|
| Enregistrer | `save` : traduit | toujours |
| Proposer | `suggest` | toujours |
| Réviser | `review` | quand l'utilisateur peut réviser et que le segment a une cible qui n'est pas périmée |
| Toujours juste | `confirm` | sur un segment périmé |
| Vider | `clear` | quand le segment a une cible |
| Écarter « contrôle » | `dismiss` : l'indicateur `ignore-<check>` | sur chaque problème d'un contrôle de qualité |

Les formulaires envoient du HTML simple et fonctionnent sans JavaScript. Un formulaire venu d'un autre site est refusé
par `SameOriginGuard`, qui exige deux choses :

- les en-têtes standard de la requête nomment ce site : `Sec-Fetch-Site: same-origin`, ou sinon un `Origin` qui est
  celui de l'écran ;
- le formulaire passe la vérification CSRF sans état de Symfony pour le jeton `phil_translation`. Chaque formulaire
  porte le champ `_csrf_token`, rendu avec le nom du témoin du jeton (`csrf-token` par défaut, le
  `csrf_protection.cookie_name` de FrameworkBundle). Quand la mise en page charge Stimulus, le contrôleur `csrf` de
  l'écran (`phil--translation-bundle--csrf`, associé pour AssetMapper) le remplace, à l'envoi d'un formulaire, par un
  nouveau jeton aléatoire et le place dans un témoin de ce nom (`__Host-` d'abord et `secure` en HTTPS, même site
  seulement). Le champ et le témoin portent ainsi le même jeton. Sans JavaScript, le champ garde le nom et l'origine
  décide seule. `SameOriginCsrfTokenManager` refuse un jeton qui n'est ni l'un ni l'autre, ainsi qu'un témoin d'un
  autre jeton quand les en-têtes du navigateur ne nomment pas ce site. FrameworkBundle efface le témoin dans la
  réponse.

Le bundle ajoute le jeton au `csrf_protection.stateless_token_ids` de FrameworkBundle, ce qui active
`framework.csrf_protection` : laissez-le actif.
Avec `csrf_protection: false`, le conteneur s'arrête avec une `LogicException` qui le dit.

## La mise en page

L'écran étend le gabarit nommé par `layout`, qui définit les blocs `title` et `content`. Celui du bundle,
`@PhilTranslation/layout.html.twig`, est une page nue avec quelques styles et aucun script. Faites pointer `layout`
vers le gabarit de base de l'application, avec son application Stimulus, pour afficher l'écran dans son
administration avec le contrôleur `csrf`. Le balisage de la page se trouve dans `<main class="phil-translation">`, avec
les classes `progress`, `variant`, `done`, `error`, `report`, `state state-<state>`, `machine`, `change`, `inherited`,
`terms`, `issues`, `checks`, `flags`, `suggestions`, `comments` et `comment`.
