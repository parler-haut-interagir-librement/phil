<!-- phil-dev source-sha256=c789c41b0d8e4661edf9be1cab5a62d0e63d96c12a1d726662a684f7f6e6c886 -->
# phil/licensing-bundle

**La licence sous laquelle une œuvre est publiée, ce que cette licence demande et la page qui la présente : déclarée,
vérifiée et affichée en HTML, en UJO (Universal JSON Output) ou en JSON-LD.**

phil/licensing-bundle conserve la licence d'une œuvre ou d'un produit numérique (un livre, un ensemble de règles, un
produit de boutique) et le crédit de chaque ressource que l'œuvre utilise : les images, les cartes, les polices, les
sources de texte comme un document de référence du système. Son catalogue connaît l'ORC License, les six licences
Creative Commons 4.0, CC0 1.0, l'Open Game License 1.0a et des conditions personnalisées, chacune avec ce qu'elle
exige : le crédit des créateurs, une mention qui nomme la licence, son texte intégral, les avis de droit d'auteur, le
partage à l'identique, le Reserved Material, la Product Identity, la désignation du contenu ouvert. Une application
ajoute ses propres licences.

Il s'adresse à toute application Symfony qui publie des œuvres construites sur du matériel sous licence et qui doit le
dire : attachez un `LicenseDeclaration` au sujet par son type et son identifiant, listez les attributions, et le bundle
génère la page de licence et d'attribution, vérifie la déclaration par rapport aux licences et refuse une exportation
sans licence ou à laquelle il manque ce qu'une licence exige — avec l'impression stricte, une impression qui ne nomme
aucune œuvre aussi. Un formulaire d'attribution demande les modifications qu'une licence veut voir indiquées, et un
assistant de licence, en étapes et sans JavaScript, choisit la licence selon ce que l'auteur permet ou rédige des
conditions propres.

Les obligations de chaque licence sont résumées à partir de son texte officiel, jamais citées ; le bundle n'est fourni
avec aucun texte de licence. Quand une licence demande que son texte accompagne l'œuvre, la déclaration le contient, tel
que l'application le fournit.

## État

| Partie | État |
|---|---|
| Le catalogue : ORC, CC BY, BY-SA, BY-NC, BY-NC-SA, BY-ND et BY-NC-ND 4.0, CC0 1.0, OGL 1.0a, conditions personnalisées, licences de l'application | Implémenté et testé |
| `LicenseDeclaration`, attaché à n'importe quel sujet, avec ses attributions, dans la table `phil_licensing_declaration` | Implémenté et testé |
| `LicenseCompliance`, les violations d'une déclaration | Implémenté et testé |
| `ExportGuard`, son événement `ExportChecking` et le voter `PHIL_LICENSING_EXPORT` | Implémenté et testé |
| La garde d'exportation avant chaque impression de `phil/paged-media-bundle` qui nomme son œuvre, les sujets sans licence | Implémenté et testé |
| L'impression stricte, désactivée par défaut : une impression qui ne nomme aucune œuvre refusée | Implémenté et testé |
| `AttributionType`, les modifications exigées par la licence, « No modifications » | Implémenté et testé |
| `LicenseFinder`, l'arbre de décision, et ses conditions propres | Implémenté et testé |
| `LicenseFinderType`, l'assistant de licence en étapes sur le flux de formulaire de Symfony | Implémenté et testé |
| Programmes de contenu communautaire : une licence offerte aux créateurs, le partage des revenus | Pas commencé ([Portée](doc/fr/scope.md)) |
| La page de licence et d'attribution sous forme de fragment HTML, en anglais et en français | Implémenté et testé |
| La page sous forme de document UJO (Universal JSON Output), avec `phil/ujo` | Implémenté et testé |
| La licence d'une œuvre en JSON-LD, avec `phil/structured-data` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la table, les paquets facultatifs. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, les licences de l'application, les services. |
| [Licences](doc/fr/licenses.md) | Le catalogue, ce qu'exige chaque licence et d'où cela vient. |
| [Déclarations](doc/fr/declarations.md) | La déclaration d'une licence, les attributions, les limites, Doctrine. |
| [La page d'attribution](doc/fr/attribution-page.md) | Le fragment HTML, les fonctions Twig, le document UJO, le JSON-LD. |
| [Conformité](doc/fr/compliance.md) | Les vérifications, les violations, la garde d'exportation, son événement et son voter. |
| [Impression](doc/fr/printing.md) | La garde des impressions, les sujets sans licence, l'impression stricte. |
| [Le formulaire d'attribution](doc/fr/attribution-form.md) | Le crédit d'une ressource sous forme de formulaire, les modifications et « No modifications ». |
| [L'assistant de licence](doc/fr/license-finder.md) | L'arbre de décision, les conditions propres, le formulaire en étapes, les gabarits. |
| [Portée](doc/fr/scope.md) | Ce que fait le bundle, et ce qui reste en dehors. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, remplace ou écoute. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/licensing-bundle
```

```php
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

$declaration = LicenseDeclaration::declare(SubjectReference::of('book', $book->getId()), $catalog->get(LicenseCatalog::ORC), $now);
$declaration->describe('© 2026 Example Press', 'The names, maps and art of the setting.', null, null, $now);
$declaration->attribute([
    Attribution::of(ResourceKind::Text, 'Example Reference Document', LicenseCatalog::ORC, 'Example Games'),
    Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, 'Jane Doe', 'https://example.com/cover', '© 2024 Jane Doe', 'Cropped'),
], $now);
$declarations->add($declaration);   // LicenseDeclarationRepository; the entity manager flushes it

$exportGuard->assertExportable(SubjectReference::of('book', $book->getId()));   // ExportBlocked otherwise
```

```twig
{{ phil_licensing_attribution(book) }}  {# a Licensable, a SubjectReference or a declaration #}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring`, `phil/url`, Symfony 8.2 ou plus récent, Doctrine ORM 3.7 et Twig 3.21 ;
DoctrineBundle pour la table, TwigBundle pour la page, `symfony/translation` pour l'anglais et le français, `phil/ujo`
pour le document UJO, `phil/structured-data` pour le JSON-LD, SecurityBundle pour le voter, `phil/paged-media-bundle`
pour la garde des impressions, `symfony/form` et `symfony/validator` pour les formulaires.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
