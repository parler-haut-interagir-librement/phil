<!-- phil-dev source-sha256=7e5b18ead1f120cb60eef46e9f191e8b7df256c424e45f6b0a25a79a14d160e7 -->
# Installation

```sh
composer require phil/seo-check
```

Le paquet demande PHP 8.5 ou une version plus récente, avec les extensions `dom`, `libxml`, `mbstring` et `uri`, et
requiert phil/publishing, phil/url et symfony/translation 8.2. Deux bibliothèques ajoutent chacune une règle quand
elles sont installées, et une troisième donne ses statuts à la règle broken-links :

```sh
composer require phil/structured-data   # the rule structured-data: the JSON-LD of the page, validated
composer require phil/social-cards      # the rule share-tags: the Open Graph and X tags of the page
composer require phil/link-checker      # LinkCheckerStatuses: the statuses of the links, from its checker
```

## Vérifier une page

Une page est vérifiée à partir de ses rendus : la page à son adresse canonique d'abord, puis, s'il y en a, les autres
chemins qui y mènent et ses rendus pour chaque vitrine ou canal.

```php
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\SeoChecker;
use Phil\Url\Url;

$canonical = Url::fromString('https://example.org/fr/');

$page = CheckedPage::of($canonical, 'fr', true, [
    new RenderedPage($canonical, $html, 200, ['Content-Language' => 'fr']),
    new RenderedPage(Url::fromString('https://example.org/'), $homeHtml),  // the same page, by another path
]);

$report = new SeoChecker()->check($page, 'fr');

foreach ($report->findings() as $finding) {
    echo $finding->severity->value, ' ', $finding->rule, ': ', $report->message($finding), "\n";
}
```

`new SeoChecker()` exécute les règles par défaut sans les ports : ni réciprocité de `hreflang`, ni liens morts. Donnez-lui
les règles à exécuter pour les ajouter :

```php
use Phil\SeoCheck\Bridge\SocialCards\ShareTagsRule;
use Phil\SeoCheck\Bridge\StructuredData\StructuredDataRule;
use Phil\SeoCheck\Rule\DefaultRules;

$checker = new SeoChecker([
    ...DefaultRules::create($renderedPages, $linkStatuses),
    new StructuredDataRule(),
    new ShareTagsRule(),
]);
```

## Vérifier chaque page à sa publication

phil/publishing envoie `PagePublished` quand une page passe en ligne : son adresse canonique, sa langue, son indicateur
« indexable » et l'identifiant de ce qui la possède. `PageCheckFactory` construit la page à vérifier à partir de cet
événement et des `RenderedPages` de l'application, qui produit le rendu de la page sans requête sur le réseau.

Un écouteur, dans une application ou dans un bundle, garde le rapport là où la personne qui a publié la page le lit :

```php
use Phil\Publishing\PagePublished;
use Phil\SeoCheck\PageCheckFactory;
use Phil\SeoCheck\Render\JsonRenderer;
use Phil\SeoCheck\SeoChecker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class CheckPublishedPage
{
    public function __construct(
        private PageCheckFactory $pages,
        private SeoChecker $checker,
        private SeoReports $reports,   // the application's own storage
    ) {
    }

    public function __invoke(PagePublished $event): void
    {
        $page = $this->pages->fromEvent($event);

        if (null === $page) {
            return;   // the application renders none of it
        }

        $report = $this->checker->check($page, $event->locale);   // or the language of the administration
        $this->reports->save($event->ownerId, new JsonRenderer()->render($report));
    }
}
```

Les services, dans une application Symfony :

```yaml
# config/services.yaml
services:
    Phil\SeoCheck\Contract\RenderedPages: '@App\Seo\KernelRenderedPages'
    Phil\SeoCheck\PageCheckFactory: ~
    Phil\SeoCheck\SeoChecker:
        arguments:
            $rules: !tagged_iterator phil_seo_check.rule

    _instanceof:
        Phil\SeoCheck\Contract\SeoRule:
            tags: ['phil_seo_check.rule']
```

Chaque règle est alors un service de l'application, portant l'étiquette `phil_seo_check.rule` et enregistré dans
l'ordre du rapport, celui que donne `DefaultRules::create()`. La vérification n'arrête jamais la publication et ne lève
jamais d'exception pour une page qu'elle juge fautive : une règle qui échoue, un port qui ne peut pas répondre, donne un
avertissement dans le rapport, et les autres règles s'exécutent quand même.

La vérification lit chaque rendu une fois, à un coût borné par la taille de la page ; pour de longues pages, ou de
nombreux rendus, exécutez-la plutôt depuis un *handler* de Messenger, le message ne portant que l'identifiant de la
page, et le *handler* reconstruisant à partir de lui les champs de `PagePublished`.

## Messages

Les messages sont des clés du domaine de traduction `phil_seo_check`, écrits en anglais et en français par
`Translation\Messages::translator()`, l'anglais étant la langue de repli. Une application qui a son propre traducteur le
donne à `SeoChecker`, après y avoir chargé `Messages::resources()`, et y ajoute ses propres langues.
