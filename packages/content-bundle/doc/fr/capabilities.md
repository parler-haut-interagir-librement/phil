<!-- phil-dev source-sha256=ebcbdb6a77a12915a8f8fb01d035d79af3022cafde544588ff7c249578ed2073 -->
# Capacités

Ce qu'une ligne contient au-delà de sa dimension est déclaré par les **capacités** qu'elle implémente. Chaque capacité
est une interface de `Phil\ContentBundle\Domain\Capability` associée à un trait, à un `CapabilityMapping` pour ses
colonnes dans `Infrastructure\Doctrine\Mapping`, et à un mappeur de données, un fusionneur et un normaliseur dans
`Application`. Une classe de ligne implémente celles dont elle a besoin ; les autres n'ajoutent aucune colonne à sa
table.

```php
class PageDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable, RoutableCapable
{
    use RoutableTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
```

**Les traits ne portent aucun mappage.** Les colonnes de chaque capacité se trouvent dans l'infrastructure, dans son
`CapabilityMapping`, et sont ajoutées à chaque classe mappée qui implémente l'interface quand Doctrine la charge
([storage.md](storage.md#lécouteur-de-mappage)). Le pipeline reconnaît une capacité par `instanceof`.

## Vue d'ensemble

| Capacité | Valeurs | Écrites sur | Colonnes |
|---|---|---|---|
| `TemplateCapable` | clé de gabarit, valeurs des champs | la clé sur la ligne non localisée, les valeurs sur la ligne localisée | `template_key` `VARCHAR(64)`, `template_data` JSON |
| `WorkflowCapable` | place dans le workflow, date de la dernière publication | le workflow, sur la ligne localisée ; la date sur les deux | `workflow_place` `VARCHAR(32)`, `workflow_published` `datetime_immutable` |
| `SeoCapable` | titre, description, mots-clés, URL canonique, `noindex`, `nofollow`, caché du plan du site | ligne localisée | `seo_title` `VARCHAR(255)`, `seo_description` text, `seo_keywords` JSON, `seo_canonical_url` `VARCHAR(2048)`, `seo_no_index`, `seo_no_follow`, `seo_hide_in_sitemap` booléens pouvant être nuls |
| `ExcerptCapable` | titre, description, référence d'image | titre et description localisés, image partagée | `excerpt_title` `VARCHAR(255)`, `excerpt_description` text, `excerpt_image` `VARCHAR(2048)` |
| `LinkCapable` | un `Link` : une autre racine de contenu, ou une `Url` | ligne localisée | `link_resource_key` `VARCHAR(64)`, `link_resource_id` `VARCHAR(64)`, `link_locale` (`phil_content_locale`, 12), `link_url` `VARCHAR(2048)` |
| `RoutableCapable` | un `Slug` | ligne localisée | `slug` `VARCHAR(255)` |
| `BodyCapable` | un `Body` : source, `MarkupFormat`, HTML | ligne localisée | `body_source` text, `body_format` `VARCHAR(16)`, `body_html` text |

Chaque colonne peut être nulle, sauf `template_data` et `seo_keywords`, qui contiennent un tableau JSON vide quand rien
n'est défini. « Écrites sur » indique où le mappeur de données du bundle écrit la valeur ; le fusionneur lit les deux
lignes, donc une application qui écrit directement une valeur sur l'autre ligne est quand même lue
([pipeline.md](pipeline.md)).

## Les valeurs sont refusées, jamais corrigées

Un mutateur de trait ou un objet-valeur refuse ce qui ne peut pas être enregistré par `InvalidContent` (une
`DomainError` de `phil/ddd-bundle`, et une `InvalidArgumentException`). Il ne rogne, ne tronque et ne réécrit jamais
rien : une ligne qui réécrirait sans bruit ce qu'on lui a donné enregistrerait quelque chose que son appelant n'a
jamais vu. Rogner, et lire un champ vidé comme `null`, est le travail des mappeurs de données sur les données
soumises.

`CapabilityText` contient les vérifications du texte libre :

| Vérification | Sert pour | Refuse |
|---|---|---|
| `text($value, $field)` | les descriptions | l'UTF-8 invalide ; une chaîne vide ou non rognée (une valeur absente vaut `null`) ; les caractères de contrôle autres que les sauts de ligne et les tabulations |
| `line($value, $field)` | les titres, les mots-clés | ce que refuse `text()` ; les sauts de ligne et les tabulations ; plus de `CapabilityText::LINE_LENGTH` (255) caractères |
| `reference($value, $field)` | l'image d'extrait | tout ce qui n'est pas de 1 à 2048 caractères ASCII imprimables sans espaces |

## TemplateCapable

```php
public function getTemplateKey(): ?string;
public function setTemplateKey(?string $templateKey): void;
/** @return array<string, mixed> */
public function getTemplateData(): array;
/** @param array<string, mixed> $templateData */
public function setTemplateData(array $templateData): void;
```

La clé de gabarit choisit le gabarit Twig ([rendering.md](rendering.md)) ; les valeurs des champs sont un document
libre dont le bundle ne produit jamais le rendu. **Un seul document JSON plutôt qu'une colonne par champ**, parce que
les champs appartiennent au gabarit, et qu'un gabarit change sans migration. Le trait ne valide aucune des deux
valeurs. La fusion est superficielle : un champ qui contient une liste ou un bloc imbriqué est une seule valeur,
remplacée en entier par la ligne localisée.

## WorkflowCapable

```php
public function getWorkflowPlace(): ?string;
public function setWorkflowPlace(?string $workflowPlace, array $context = []): void;
public function getWorkflowPublished(): ?DateTimeImmutable;
public function setWorkflowPublished(?DateTimeImmutable $workflowPublished): void;
```

L'accesseur et le mutateur de la place suivent le magasin de marquage par méthode de `symfony/workflow` (`property:
workflowPlace`, état unique). La ligne enregistre seulement où elle se trouve ; le workflow est `ContentWorkflow`
([publication.md](publication.md)). **Une classe de ligne doit être `WorkflowCapable` pour être publiée** : publier
une ligne qui ne l'est pas lève `TransitionRefused`.

`workflow_published` est un `datetime_immutable`, qui n'enregistre aucun fuseau horaire : le publieur l'écrit en UTC,
et une ligne de brouillon avec une date de publication appartient à une version figée.

## SeoCapable

Des accesseurs et des mutateurs pour `seoTitle` (une ligne), `seoDescription` (du texte), `seoKeywords` (une liste de
lignes), `seoCanonicalUrl` (une `Url`) et trois directives, `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap`.

- **Les mots-clés** sont gardés dans l'ordre, un mot-clé répété une seule fois ; un mot-clé qui contient une virgule
  est refusé, puisque les virgules séparent les mots-clés dans la balise `<meta>`.
- **Une directive a trois états.** `null` indique que la ligne ne la définit pas : la ligne fusionnée garde donc ce
  que disait la ligne partagée. `true` et `false` sont explicites : un `false` localisé lève donc un `true` partagé.
  Une directive que personne n'a définie se lit comme `false` une fois normalisée.

## ExcerptCapable

Des accesseurs et des mutateurs pour `excerptTitle` (une ligne), `excerptDescription` (du texte) et `excerptImage`
(une référence).

**L'image est une référence, pas un modèle de média** : une URL, un chemin public ou l'identifiant d'une médiathèque,
d'au plus 2048 caractères ASCII imprimables. Ce vers quoi elle pointe, et comment elle est redimensionnée et servie,
relève de l'application. `ExcerptDataMapper` l'écrit sur la ligne non localisée, puisqu'une image est d'habitude la
même dans chaque langue, et le titre et la description sur la ligne localisée. Le fusionneur copie chaque valeur
seulement quand la source la définit : un extrait localisé garde donc l'image partagée.

## LinkCapable

```php
public function getLink(): ?Link;
public function setLink(?Link $link): void;
```

Un `Link` pointe vers une autre racine de contenu ou vers une URL absolue, jamais les deux, jamais aucune :

```php
use Phil\LocaleDimensions\Locale;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\Url\Url;

$toArticle = Link::toContent('article', '0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70');          // follows the reader's locale
$toFrench = Link::toContent('article', '0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70', Locale::fromString('fr'));
$outside = Link::toUrl(Url::fromString('https://example.org/'));
$toArticle->isToContent();   // true
```

- **Une racine de contenu est nommée par une clé de ressource et un identifiant, tous deux propres à l'application**,
  avec les formes d'une `ContentReference` ; une locale facultative fixe la langue de la cible. Le lien est résolu au
  moment du rendu : il suit donc la cible quand son slug change.
- **La forme est vérifiée ; l'existence de la racine cible est une question pour l'application**, posée quand elle
  soumet le lien ou en produit le rendu.
- Quatre colonnes plutôt qu'un document, pour qu'une requête trouve chaque ligne qui pointe vers une racine par
  `(link_resource_key, link_resource_id)`. `setLink()` écrit les quatre ensemble, et le lien est fusionné en entier :
  une URL localisée n'emprunte jamais la locale d'un lien de contenu partagé.

Une URL — un lien vers un autre site, une URL canonique — est une `Url` de `phil/url` : une adresse `http` ou `https`
absolue, d'au plus 2048 caractères, sans identifiants de connexion, normalisée — schéma et hôte en minuscules, hôte
international sous sa forme ASCII, port par défaut retiré, chemin racine `/`. Deux graphies d'une même adresse sont
donc enregistrées comme une seule. Les mappeurs de données transforment son refus en `InvalidContent`. Un lien interne
à l'application est une racine de contenu, pas une URL.

## RoutableCapable

```php
public function getSlug(): ?Slug;
public function setSlug(?Slug $slug): void;
```

`Slug` est `Phil\Slug\Slug`, de `phil/slug`. `Slug::fromString()` accepte un segment d'URL : des lettres ASCII
minuscules et des chiffres en mots reliés par des traits d'union simples, d'au plus 255 caractères
(`my-first-article`) ; un `slug` soumis qui n'en est pas un donne une `InvalidContent`. Transformer un titre en slug —
la translittération, le choix des mots — relève de l'application, avec un générateur de slugs de `phil/slug` ou de
`phil/slug-bundle` si elle en veut un.

**Un slug est propre à chaque locale, et n'est pas unique dans la table des lignes.** Chaque version et les deux étapes
d'une locale portent le même slug : un index unique à cet endroit refuserait la copie que fait la publication.
L'unicité se trouve plutôt dans la table de routes : une modification est refusée par `SlugTaken` quand son slug est
l'adresse actuelle d'une autre racine existante du même genre dans la même locale ([routing.md](routing.md)).

## BodyCapable

```php
public function getBody(): ?Body;
public function setBody(?Body $body): void;
```

Le texte principal d'un article ou d'une page est un `BodyCapable` plutôt qu'un champ des données du gabarit. Les
valeurs du gabarit sont un document libre dont le bundle ne produit jamais le rendu ; le corps est le seul texte que
le bundle compile, et donc le seul endroit où il produit du HTML. En tant que capacité, il a des colonnes typées et un
seul chemin vers l'écran :

1. **À l'écriture**, `BodyDataMapper` compile la source — Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO ou le
   format d'un module complémentaire en passant par un document UJO, HTML tel qu'il est écrit, ou texte brut — par le
   port `MarkupCompiler`, purifie le résultat par le port `HtmlPurifier`, et enregistre la source, son format et le
   HTML purifié. **Un brouillon garde le format dans lequel son auteur écrit.** Une source UJO qui n'est pas un
   document valide est refusée par `InvalidContent`. Le HTML brut d'un rédacteur qui a le rôle
   [`raw_html_role`](configuration.md#raw_html_role) est rendu, comme il sera publié ; celui de tout autre rédacteur
   s'affiche comme du texte. La page n'est pas recompilée à chaque lecture.
2. **À la publication**, `ContentPublisher` enregistre sur la ligne en ligne le corps que le port `BodyConverter` tire
   de celui du brouillon : **la source convertie en UJO** — UJO (Universal JSON Output), le format de document par
   blocs de `phil/ujo` —, le format `ujo`, et le HTML compilé à partir de cet UJO, purifié au niveau `Level::All` ; le
   `time` de l'UJO est le moment de la publication. Chaque texte publié est dans un seul format, quel que soit celui de
   son brouillon, et un éditeur par blocs peut l'ouvrir ([Le cycle de vie d'un corps](#le-cycle-de-vie-dun-corps)).
3. **À la lecture**, `BodyNormalizer` purifie de nouveau le HTML enregistré avant de le remettre. Une ligne écrite en
   contournant le mappeur de données — par une migration, à la main — est purifiée quand même, et une politique plus
   stricte s'applique à chaque ligne le jour où elle est configurée. **Le `bodyHtml` de la ligne normalisée est le seul
   HTML de corps qu'un gabarit peut afficher sans échappement.**

   Cette purification se paie à chaque lecture, et rien ne la met en cache : environ 60 µs par paragraphe de HTML
   riche, une milliseconde pour un article typique, dix pour une longue lecture. Une page lit un seul corps et ne s'en
   aperçoit pas ; une liste, un flux ou un point de terminaison d'API qui normalise beaucoup de contenus la paie une
   fois par contenu. Une telle page normalise seulement ce qu'elle affiche — un extrait n'a pas besoin de corps — ou
   met sa propre réponse en cache. Un cache indexé sur la version de la ligne ne serait pas sûr, puisqu'une ligne
   écrite en contournant le mappeur de données garde sa version.

`Body::of(string $source, MarkupFormat|string $format, string $html)` garde la source telle qu'elle est écrite,
espaces de tête comprises (elles ont un sens en Markdown), et le format par son nom ; il refuse une source faite
seulement de blancs et une source ou un HTML qui n'est pas de l'UTF-8 valide. `MarkupFormat` est celui de
`phil/markup` : `Markdown` (`markdown`), `Html` (`html`), `BBCode` (`bbcode`), `MediaWiki` (`mediawiki`), `DokuWiki`
(`dokuwiki`), `Creole` (`creole`), `Text` (`text`) et `Ujo` (`ujo`) ; un format qu'un module complémentaire apporte au
compilateur est donné par son nom, de 16 caractères au plus, la longueur de la colonne. Une source dans un autre
langage de balisage est convertie avant d'être importée. La source, son format et son HTML voyagent ensemble par le
fusionneur : le HTML correspond donc toujours à la source dont il a été compilé.

Les adaptateurs par défaut sont ceux de `phil/markup`, qui les décrit :

- **`DefaultCompiler`** : Markdown, BBCode, MediaWiki, DokuWiki, Creole et les formats des modules complémentaires
  lus en UJO par l'analyseur propre à la bibliothèque, et UJO, dont le rendu est produit par `phil/ujo` ; le HTML brut
  des formats écrits est échappé, puisque le niveau n'est pas donné à `compile()`, et les liens dangereux sont
  écartés ; HTML tel qu'il est écrit ; texte brut par le compilateur propre à la bibliothèque.
- **`SanitizerPurifier::defaultPolicies()`** (`symfony/html-sanitizer`) : le corps est purifié au niveau
  `Level::All`, les éléments sûrs de l'assainisseur ; liens en `http`, `https`, `mailto` ou relatifs ; images en
  `http`, `https` ou relatives ; scripts, styles, cadres, formulaires et gestionnaires d'événements retirés ; **aucune
  limite à la longueur de l'entrée**.

- **`UjoBodyConverter`**, le `BodyConverter` du bundle : le lecteur du format du brouillon, tiré de `DefaultCompiler`,
  lit la source en un document UJO **au niveau `Level::Forum`** — le niveau auquel lit `compile()`, pour que le texte
  publié dise ce que montrait le brouillon : le HTML brut écrit en Markdown est du texte, jamais un bloc `raw`, et un
  bloc `raw` écrit en UJO devient un paragraphe qui montre son HTML comme du texte. Le `time` du document est fixé au
  moment de la publication, en millisecondes depuis l'époque Unix ; l'encodeur de `phil/ujo` l'écrit en JSON, et ce
  JSON est compilé et purifié. HTML et texte brut, que le brouillon compile tels qu'ils sont écrits, sont convertis en
  blocs par leurs lecteurs ; ce pour quoi UJO n'a pas de bloc est écarté.
- **Le HTML brut, pour un rôle de confiance seulement.** Avec [`raw_html_role`](configuration.md#raw_html_role)
  défini, un publieur à qui le vérificateur d'autorisation de Symfony accorde ce rôle voit sa source lue au niveau
  `Level::All` : le HTML brut des formats écrits et les blocs `raw` d'UJO sont publiés comme blocs `raw`, leur HTML
  gardé comme le permet le profil `raw` de `phil/ujo` — les éléments de la structure d'une page, aucun script, style,
  classe ou événement — et purifié de nouveau au niveau `Level::All`. **Le brouillon d'un rédacteur qui a ce rôle le
  montre de la même façon** : `BodyDataMapper` compile sa source pour `Level::All`, par le `compileFor()` d'un
  `LevelAwareCompiler`, pour que l'aperçu de son brouillon produise le rendu du HTML brut comme le fera sa
  publication ; le brouillon de tout autre rédacteur le montre comme du texte. Sans la clé, la valeur par défaut,
  personne n'écrit de HTML brut ; un enregistrement ou une publication depuis la console, qui n'a aucun utilisateur
  connecté, n'en écrit jamais.

L'application remplace n'importe lequel d'entre eux en faisant de `MarkupCompiler`, `HtmlPurifier` ou `BodyConverter`
un alias vers son propre service ([extension-points.md](extension-points.md#les-ports-de-balisage)). Le convertisseur
lit par `DefaultCompiler` même quand `MarkupCompiler` est un alias vers autre chose. Un champ de gabarit qui contient
du texte riche est à compiler et à purifier par l'application, avec les mêmes services.

### Le cycle de vie d'un corps

| Ligne | `body_source` | `body_format` | `body_html` |
|---|---|---|---|
| Brouillon, tel qu'écrit | le texte de l'auteur | le format de l'auteur : `markdown`, `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `html`, `text`, `ujo` ou un format d'un module complémentaire | compilé à partir du texte, purifié |
| En ligne, une fois publiée | le JSON UJO en lequel le texte se convertit, son `time` étant le moment de la publication | `ujo` | compilé à partir de l'UJO, purifié |

- **La publication ne touche jamais la ligne de brouillon.** Le brouillon est figé avec sa version et garde son texte ;
  la modification suivante crée une version dans le format où elle est écrite, et la ligne en ligne garde l'UJO de la
  version servie aux lecteurs.
- **La conversion s'exécute avant tout changement.** Un corps illisible — de l'UJO écrit en contournant le mappeur de
  données qui n'est pas un document valide — fait lever `InvalidContent` à `publish()`, et la locale reste où elle
  était.
- **Retirer un brouillon remet la ligne de brouillon figée de la version publiée** : le brouillon contient de nouveau
  le texte que son auteur a publié, dans son propre format. C'est seulement quand cette ligne n'existe plus —
  historique élagué, ou locale publiée avant que la conversion existe — que la ligne en ligne est remise, son corps
  étant l'UJO publié, au format `ujo`.
- **Les colonnes ne changent pas.** L'UJO est enregistré dans `body_source`, une colonne de texte sans longueur, et
  `ujo` tient dans le `VARCHAR(16)` de `body_format`. Une ligne en ligne publiée avant que la conversion existe garde
  son format jusqu'à sa prochaine publication ; rien n'est à migrer.

## Ajouter votre propre capacité

Une capacité propre à l'application est une interface et un trait, une sous-classe de `CapabilityMapping` pour ses
colonnes, et un mappeur de données, un fusionneur et un normaliseur. Les quatre sont étiquetés par configuration
automatique et s'exécutent à côté de ceux du bundle. **Une capacité sans fusionneur n'est ni lue par l'agrégateur, ni
copiée par la publication ou la création de version.** La recette complète se trouve dans
[extension-points.md](extension-points.md#une-capacité-propre-à-lapplication).
