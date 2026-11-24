<!-- phil-dev source-sha256=b26bed83bd63b133962b8c20b4c669b79b70683b448a939f93d8108453a5db58 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter une vérification | Implémentez `Contract\SeoRule`, et donnez-la à `SeoChecker` avec les autres règles |
| Donner à la vérification le HTML d'une page | Implémentez `Contract\RenderedPages` |
| Signaler les liens morts | Implémentez `Contract\LinkStatuses` à partir de ce que le dernier vérificateur de liens a vu |
| Dire si une page est indexable dans chaque canal | Implémentez `Contract\ChannelIndexing` |
| Écrire le rapport dans un autre format | Implémentez `Contract\ReportRenderer` |
| Écrire les messages dans une autre langue | Ajoutez les clés du domaine `phil_seo_check` à un traducteur |
| Changer une borne | Passez-la au constructeur de la règle : longueurs, textes de lien génériques, robots d'exploration, limites |

## Écrire une règle

Une règle lit la page et renvoie ce qu'elle trouve. Elle ne modifie jamais la page, ne garde rien d'une page à la
suivante — une instance sert chaque requête d'un *worker* — et ne lève jamais d'exception pour une page qu'elle juge
fautive : `SeoChecker` signale une exception par l'avertissement `check.rule-failed`.

```php
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

final readonly class ViewportRule implements SeoRule
{
    public function name(): string
    {
        return 'viewport';
    }

    public function check(CheckedPage $page): iterable
    {
        if ([] === $page->primary()->markup->meta('viewport')) {
            yield new Finding('viewport', Severity::Warning, new Message('viewport.missing'));
        }
    }
}
```

`CheckedPage` donne la page telle qu'elle a été publiée — `canonicalUrl`, `locale`, `indexable`,
`isIndexableIn($channel)` — et ses rendus : `primary()` est la page à sa propre adresse, `renderings` les donne tous. Un
`Rendering` contient le `RenderedPage` — adresse, HTML, statut, en-têtes, canal — et le `PageMarkup` lu à partir de lui
une seule fois : titres, `<meta>` par nom, langue du document, titres de premier niveau, liens canoniques et
alternatifs, liens et images. Une règle qui a besoin d'autre chose dans le HTML analyse elle-même `$rendering->page->html`,
à un coût borné.

Le nom est en kebab case et le même pour chaque constat. Le message est une clé et les valeurs de ses paramètres
substituables ; ajoutez la clé au traducteur, sinon le rapport écrit la clé telle quelle :

```php
use Phil\SeoCheck\SeoChecker;
use Phil\SeoCheck\Rule\DefaultRules;
use Phil\SeoCheck\Translation\Messages;

$translator = Messages::translator();
$translator->addResource('array', ['viewport.missing' => 'The page has no viewport.'], 'en', Messages::DOMAIN);

$checker = new SeoChecker([...DefaultRules::create(), new ViewportRule()], $translator);
```

Un constat qui porte sur un élément de la page — un lien, une image, un chemin — le nomme comme son `subject`, cité par
un extrait.

## Les ports

### `RenderedPages`

Là où la vérification obtient le HTML d'une page, tel que l'application le produit, sans requête sur le réseau : une
sous-requête au noyau, un rendu du gabarit de la page, un cache de pages.

- `renderingsOf(PagePublished $event)` : la page à son adresse canonique d'abord, puis les autres chemins qui y mènent
  — `/` et `/fr/` pour une page d'accueil — et son rendu pour chaque canal, chacun un `RenderedPage` avec son canal.
  Aucun quand la page ne peut pas être produite : `PageCheckFactory::fromEvent()` renvoie alors null.
- `renderingAt(Url $url)` : une autre page de l'application, comme la règle `hreflang` lit les traductions d'une page ;
  null quand l'adresse n'est pas celle d'une page de l'application.

Un `RenderedPage` garde les en-têtes que les moteurs de recherche lisent — `X-Robots-Tag`, `Content-Language`,
`Content-Type` pour le jeu de caractères — ; donnez-lui ceux de la réponse.

### `LinkStatuses`

Ce que l'application sait des liens d'une page : le code de statut de chaque réponse, tel que le dernier vérificateur de
liens l'a vu, par l'adresse qu'écrit `Url::toString()`. La règle `broken-links` interroge une fois par page, avec les
liens de la page sans leurs fragments, et ne signale rien pour un lien omis. Un adaptateur pour un vérificateur de liens
lit ses derniers résultats ; il ne fait jamais de requête sur les liens pendant la vérification de la page.

`Bridge\LinkChecker\LinkCheckerStatuses` est cet adaptateur pour phil/link-checker. Donnez-lui le `LinkChecker` de
l'application, construit avec un cache, pour que les liens vérifiés récemment soient lus dans le cache plutôt que
demandés de nouveau :

```php
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\SeoCheck\Bridge\LinkChecker\LinkCheckerStatuses;
use Phil\SeoCheck\Rule\DefaultRules;

$statuses = new LinkCheckerStatuses(new LinkChecker(new HttpLinkProbe(), cache: $cache));
$rules = DefaultRules::create($renderedPages, $statuses);
```

Ce que le vérificateur a trouvé devient le code de statut que la règle lit :

| Le vérificateur a trouvé | Le code de statut |
|---|---|
| Vivant, mort ou à revoir, avec une réponse | Le code de la réponse ; pour à revoir, seulement un `5xx` — `401`, `403` et `429` sont omis, car le vérificateur ne les compte jamais comme morts |
| Mort, son hôte n'existe pas | `404` |
| Une redirection, toutes les étapes permanentes | `301` |
| Une redirection avec une étape temporaire | `302` |
| Refusé, illisible, ou à revoir sans réponse, comme un délai dépassé | Omis |

Les liens parviennent au vérificateur comme les liens d'un seul contenu à `LinkCheckerStatuses::PAGE`, une adresse du
domaine réservé `.invalid` qu'aucun lien ne nomme ; le vérificateur ne demande jamais cette page. La requête d'un lien
absent du cache a lieu pendant la vérification de la page : pour une longue page, vérifiez-la dans un gestionnaire
Messenger dont le message ne porte que l'identifiant de la page.

### `ChannelIndexing`

Si une page est indexable dans chaque vitrine ou canal où l'application la sert, demandé une fois par canal par
`PageCheckFactory` ; null quand le canal suit ce que la page a été publiée comme.

### `ReportRenderer`

Écrit un rapport dans un format. `TextRenderer`, `JsonRenderer` et `HtmlRenderer` sont fournis ; celui du HTML porte les
classes `isError`, `isWarning` et `isInfo`, sans style ni script, pour la feuille de style propre à l'administration.

## Messages

`Translation\Messages` contient les messages en anglais et en français ; `Messages::resources()` les donne par locale,
pour qu'une application les charge dans son propre traducteur, sous le domaine `phil_seo_check`. Les paramètres
substituables s'écrivent `{name}`.
