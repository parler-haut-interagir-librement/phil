<!-- phil-dev source-sha256=3d70fc4ea85b013dde0f5c5fd6c535de4b5d1973ecd20e381097aff1da242ca4 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## Écrire une règle

Une règle implémente `Contract\Rule` — `id()`, `criterion()`, `severity()` — et `Contract\HtmlRule`, `Contract\UjoRule`
ou les deux. Elle produit une `Violation` pour chaque problème : un `Location`, un `Message` et, lorsque ce cas diffère
de la règle, sa propre `Severity` ou son propre `SuccessCriterion`. Elle ne conserve aucun état entre deux contenus.

```php
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Violation;

final class NoMarquee implements HtmlRule
{
    public function id(): string
    {
        return 'no-marquee';
    }

    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::PauseStopHide;
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function checkHtml(HtmlTarget $target): iterable
    {
        foreach ($target->elements('marquee', 'blink') as $element) {
            yield new Violation($target->locate($element), new Message('no-marquee.found', ['element' => $element->localName]));
        }
    }
}
```

`HtmlTarget` donne ce qu'une règle demande au contenu : `elements(...$names)`, `identifiers()`, `locate()`,
`languageOf()`, `isHidden()`, `style()`, `hasRole()`, `accessibleName()`, `labelOf()`, `textOf()`, `ownText()`,
`svgTitle()`, ainsi que `policy`, `language` et `isFullDocument`. `UjoTarget` donne `blocks(...$types)` — chacun étant
un `BlockVisit` avec son bloc, son pointeur, sa langue et `locate()` —, `fragments(?BlockVisit)` — chaque champ HTML
sous forme de `HtmlTarget` situé dans son bloc —, ainsi que `field()` et `text()` pour lire les données.

`Rule\FragmentRule` est la base d'une règle qui lit les deux : elle implémente `checkUjo()` en appelant `checkBlock()`
pour les données de chaque bloc, puis `checkHtml()` pour chacun de ses champs HTML.

Les règles sont données au vérificateur dans leur ordre d'exécution —
`new AccessibilityChecker($policy, [...DefaultRules::all(), new NoMarquee()()])`, ou un itérateur étiqueté
([Installation](installation.md#dans-une-application-symfony)). Deux règles de même identifiant lèvent `InvalidPolicy`.
Donnez les messages de la règle au traducteur ([Messages](messages.md)).

## La politique

`Policy` contient les limites, les titres, le contraste, les couleurs, les langues, les textes de lien génériques et les
règles désactivées ou dotées d'une autre sévérité : voir [Politique](policy.md).

## Le traducteur

Le vérificateur prend un `TranslatorInterface` de `symfony/translation-contracts` : `Translation\Messages::translator()`
par défaut, auquel une application ajoute les messages d'une locale ou d'une règle, ou le traducteur de l'application
avec `Messages::resources()` chargé dans le domaine `phil_accessibility_check` : voir [Messages](messages.md).

## Les champs bruts d'UJO

`Ujo\PlainFields` nomme, par type de bloc, les champs de données qui ne contiennent jamais de HTML — `code` d'un bloc
`code`, `latex` et `mathml` de `math`, chaque champ de `chart` (`*`)… Le vérificateur ne les analyse pas, même s'ils
contiennent un `<`. Un site qui enregistre ses propres types de blocs donne au vérificateur ses champs bruts, qui
remplacent les valeurs par défaut de chaque type nommé :

```php
use Phil\AccessibilityCheck\Ujo\PlainFields;

$checker = new AccessibilityChecker(plainFields: new PlainFields(['recipe' => ['ingredients'], 'chart' => ['*']]));
```

## Le contenu d'un sujet

`Contract\SubjectContent` indique à la `PublicationGuard` le HTML ou le document UJO du sujet d'un workflow : voir
[Garde de publication](publication-guard.md).

## Le validateur et la commande

`Validator\AccessibleContentValidator` et `Console\CheckCommand` reçoivent l'`AccessibilityChecker` avec lequel ils
vérifient : enregistrez-les comme services avec le vérificateur de l'application.

## Le rapport

`Report`, `RuleResult`, `Finding`, `Location`, `Message`, `Severity`, `SuccessCriterion` et `ConformanceLevel` sont des
valeurs que lit une application. Les identifiants des règles, les clés des messages, les valeurs de `Severity` et les
numéros de `SuccessCriterion` sont stables : un rapport peut ainsi être conservé, puis relu grâce à eux.
