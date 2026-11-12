<!-- phil-dev source-sha256=891a0e1e8d29d33a220d666b2970065f8917c24c813759e9802308eb82925f58 -->
# phil/accessibility-check

**Repérez ce qui écarte des lecteurs avant la publication d'un contenu : textes de remplacement manquants, plans de
titres brisés, liens vides, texte trop pâle.**

phil/accessibility-check vérifie un contenu au moment de sa publication. La bibliothèque lit le HTML — une page entière
ou le fragment produit par un éditeur — avec l'analyseur HTML de PHP. Elle lit les documents UJO (Universal JSON Output)
bloc par bloc : l'auteur sait ainsi quel bloc et quel champ corriger. Chaque règle est une classe dotée d'un
identifiant, du critère de succès WCAG 2.2 qu'elle teste, d'une sévérité et d'un message en anglais, en français, en
espagnol, en allemand, en italien et en portugais. Les règles couvrent les textes de remplacement des images, l'ordre
des titres et le titre de premier niveau, le nom des liens et des boutons, les textes de lien qui ne disent rien, le
contraste des couleurs données par le contenu, les cellules d'en-tête des tableaux, la langue des pages et des passages,
le titre des cadres, les sous-titres des vidéos, les identifiants donnés deux fois et le titre des dessins. Le rapport
indique, règle par règle, si la vérification a réussi. Il énumère ce qui a été constaté, avec son emplacement, et se
sérialise en JSON.

Elle s'adresse à toute application PHP qui publie ce que des gens écrivent : un CMS, un blogue, un wiki, un outil de
mise en page de livres ou de matériel de jeu. Une vérification ne remplace pas un test avec des personnes et des
technologies d'assistance. Elle arrête à chaque fois les erreurs qu'une machine peut voir, avant que les lecteurs ne les
rencontrent.

C'est une simple bibliothèque PHP au-dessus des extensions `dom`, `libxml` et `mbstring` et de
`symfony/translation`. Avec les
paquets auxquels elle s'adapte, elle fournit aussi aux applications Symfony une contrainte de validateur, une garde de
`symfony/workflow` qui retient une publication et une commande de console.

La conception est sobre : `AccessibilityChecker` exécute les règles retenues par une `Policy` sur un `HtmlTarget` ou un
`UjoTarget`, puis retourne un `Report` composé de `RuleResult` et de `Finding`. Une règle implémente `HtmlRule`,
`UjoRule` ou les deux, et produit des `Violation`. `symfony/translation` rédige leurs messages, par
`Translation\Messages`.

## État

| Partie | État |
|---|---|
| HTML lu avec `Dom\HTMLDocument`, limites de taille, de profondeur et d'éléments, UTF-8 seulement | Implémenté et testé |
| Documents UJO lus bloc par bloc, blocs imbriqués compris, champs contenant du HTML analysés à part | Implémenté et testé |
| 15 règles, avec leur critère de succès WCAG 2.2 et leur sévérité | Implémenté et testé |
| Contraste WCAG calculé à partir des couleurs du contenu et des jetons de l'apparence | Implémenté et testé |
| Messages en anglais, en français, en espagnol, en allemand, en italien et en portugais | Implémenté et testé |
| Rapport avec le résultat de chaque règle, les emplacements et le JSON | Implémenté et testé |
| Contrainte de validateur Symfony `AccessibleContent` | Implémenté et testé |
| Garde de workflow `PublicationGuard` | Implémenté et testé |
| Commande de console `phil:accessibility:check` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et services d'une application Symfony. |
| [Vérification](doc/fr/checking.md) | Vérifier du HTML et de l'UJO, le rapport, les constats et leurs emplacements, le JSON. |
| [Règles](doc/fr/rules.md) | Le catalogue des règles : ce que chacune vérifie, son critère, sa sévérité et ses messages. |
| [Politique](doc/fr/policy.md) | Limites, titres, contraste et couleurs, langues, textes de lien génériques, règles désactivées. |
| [Messages](doc/fr/messages.md) | Le traducteur, ses six langues, les clés et leurs paramètres substituables. |
| [Validateur](doc/fr/validator.md) | La contrainte `AccessibleContent`. |
| [Garde de publication](doc/fr/publication-guard.md) | Retenir une publication de `symfony/workflow`. |
| [Console](doc/fr/console.md) | La commande `phil:accessibility:check`. |
| [Points d'extension](doc/fr/extension-points.md) | Écrire une règle, ajouter des messages, les champs bruts d'UJO, le contenu d'un sujet. |
| [Exceptions](doc/fr/exceptions.md) | `AccessibilityCheckException` et ses trois exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le seuil de mutation, le *fuzzing* et le banc d'essai, et comment tester avec elle. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/accessibility-check
```

```php
use Phil\AccessibilityCheck\AccessibilityChecker;

$report = new AccessibilityChecker()->checkHtml(<<<'HTML'
    <h2>Opening hours</h2>
    <h4>Summer</h4>
    <img src="/uploads/IMG_2041.jpg">
    <p>Book a table <a href="/booking">here</a>.</p>
    <p style="color: #aaa">Closed on holidays.</p>
    HTML);

foreach ($report->findings as $finding) {
    printf("[%s] %s (WCAG %s) %s: %s\n", $finding->severity->value, $finding->rule, $finding->criterion->value,
        $finding->location->describe(), $report->message($finding));
}
// [error] image-alt (WCAG 1.1.1) line 3, img: The image /uploads/IMG_2041.jpg has no text alternative: …
// [warning] heading-order (WCAG 1.3.1) line 2, h4: This h4 heading follows an h2: a level is skipped.
// [warning] link-text (WCAG 2.4.4) line 4, p:nth-of-type(1) > a: The text of the link to /booking, "here", …
// [error] color-contrast (WCAG 1.4.3) line 5, p:nth-of-type(2): The text "Closed on holidays." has a contrast of 2.32:1 …

$report->passes();                 // false: an error was found
echo json_encode($report);         // the outcome of each rule and every finding
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `dom`, `libxml` et `mbstring` ; `phil/ujo` pour vérifier des documents UJO ;
`symfony/validator`, `symfony/workflow` et `symfony/console` pour la contrainte, la garde et la commande.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
