<!-- phil-dev source-sha256=c44b45e24b85dc7dcfcf455eaed6cd49d7e9886639bda9c87071d39be881bbfc -->
# EasyAdmin

Avec `easycorp/easyadmin-bundle`, le bundle enregistre `MediaFieldConfigurator`, et `MediaField` montre une propriété
qui contient un média (une colonne `phil_media`) dans un contrôleur CRUD :

```php
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaField;

public function configureFields(string $pageName): iterable
{
    yield TextField::new('title');
    yield MediaField::new('cover')->setCollection('covers');
}
```

| Page | Ce qu'elle montre |
|---|---|
| Liste et détail | La vignette du média (le format `admin_thumbnail`), ou l'extension de son fichier, et son nom ; `None` quand la propriété est vide ou que le média n'existe plus |
| Création et modification | `MediaPickerType` : le fichier choisi, un bouton qui ouvre le sélecteur de l'explorateur dans une boîte de dialogue, et un bouton qui vide le champ |

`setCollection()` ouvre le sélecteur sur une collection, sauf si les options de formulaire du champ en nomment déjà
une. Le sélecteur est la liste de l'explorateur : importez ses routes, derrière le même pare-feu que le tableau de bord,
et chargez les contrôleurs Stimulus du bundle sur les pages du tableau de bord ; avec AssetMapper, ajoutez au tableau de
bord l'entrée d'importmap de l'amorce Stimulus de l'application par `configureAssets()`. Associez la propriété à la
contrainte `MediaFile` pour limiter ce qui peut être choisi.

## L'explorateur dans l'interface d'administration

L'explorateur de fichiers est la page d'administration de la médiathèque. `MediaAdmin::menuItem()` y lie le menu du
tableau de bord :

```php
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Content');
    yield MediaAdmin::menuItem();                                    // "Media", fa fa-photo-film
    yield MediaAdmin::menuItem('Covers', 'fa fa-image', 'covers');   // opened on a collection
}
```

Le libellé peut être un `TranslatableInterface`. L'explorateur garde ses propres routes, son accès, ses jetons CSRF sans
état et sa mise en page, et passe par `MediaLibrary` pour chaque téléversement, renommage, déplacement et suppression :
une suppression interroge donc d'abord les enregistrements qui montrent le média. Faites pointer
`phil_media.admin.layout` vers un gabarit de l'interface d'administration qui définit les blocs `title`,
`stylesheets`, `javascripts` et `content`, et importez les routes de l'explorateur derrière le pare-feu du tableau de
bord : voir [L'explorateur de fichiers](admin.md).

L'intégration est facultative : sans EasyAdmin, rien n'en est branché et rien n'en est chargé.
