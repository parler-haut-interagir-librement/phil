<!-- phil-dev source-sha256=9644d0dcf8abf69e641c03056fd519ede7edb751220338cd2baec76f329f85cb -->
# phil/commerce-product-template-bundle

**Chaque SKU d'un motif sur un gabarit, généré dans le catalogue, réuni en une seule page, avec ses maquettes, ses
options, et les fichiers d'impression et l'ordre de production de chaque commande.**

phil/commerce-product-template-bundle est un module de `phil/commerce-catalog-bundle`. Il enregistre des gabarits de
produits et des motifs, tels que les décrit `phil/commerce-product-template`, et **génère** dans le catalogue les
produits d'un motif sur un gabarit : un produit par SKU (unité de gestion de stock, *UGS*), nommé, tarifé, classé,
décrit par ses attributs et vendu par ses canaux. Générer de nouveau met à jour ce qui a changé, rétablit ce qui est
revenu et retire ce que le plan n'a plus ; rien n'est supprimé. Les produits d'un motif sur un gabarit forment un
**groupe** : une page avec un sélecteur par axe, chaque produit restant une UGS du catalogue. Leurs **maquettes** sont
composées par `phil/print-compositor`, les illustrations posées sur les photos de l'article vierge, un WebP par couleur
et par face. Les **options** que déclare un gabarit ou un motif — un texte, un choix, une encre, un tirage — sont
données au noyau du commerce par son port `SellableOptions` : une ligne de commande configurée avec elles est donc
vérifiée et facturée. Une fois une commande payée et ses tirages faits, ses **fichiers d'impression** sont composés —
l'illustration, le texte que l'acheteur a saisi dans une police et une zone imposées, les objets d'un coffre à butin
choisis par ses dés, les objets fixes d'une version, un fichier par encre — et son **ordre de production** est écrit
pour l'imprimeur, tout de suite ou dans un *worker* par un message de Messenger qui ne porte que le numéro de la
commande. Un contrôleur Stimulus choisit un produit d'un groupe, les combinaisons que le groupe ne vend pas étant
désactivées.

Il s'adresse à une boutique qui imprime ses motifs sur des articles vierges en plusieurs tailles et couleurs : les
t-shirts sont l'exemple que suit la documentation, puisqu'ils sont la raison d'être du module, mais un gabarit ne sait
rien des t-shirts.

C'est un bundle Symfony qui refuse de se charger sans `phil/commerce-bundle` et `phil/commerce-catalog-bundle`.

## État

| Partie | État |
|---|---|
| Les gabarits et les motifs enregistrés, avec leurs options | Implémenté et testé |
| La génération dans le catalogue : créer, mettre à jour, rétablir, retirer, ne jamais supprimer | Implémenté et testé |
| Les groupes : les membres par UGS, la page d'un groupe, l'UGS des valeurs choisies | Implémenté et testé |
| Les maquettes avec `phil/print-compositor`, nommées par leur contenu, dans un magasin d'images | Implémenté et testé |
| Les options des gabarits et des motifs données au noyau | Implémenté et testé |
| Le stockage sur Doctrine | Implémenté et testé |
| Les fichiers d'impression et les ordres de production : dispositions d'impression, dés, encres, JSON et CSV, un magasin privé | Implémenté et testé |
| La production dans un *worker* par Messenger, idempotente, à l'achat si configuré | Implémenté et testé |
| Le contrôleur Stimulus qui choisit un produit d'un groupe, servi par AssetMapper | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du module. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, sa valeur par défaut et ce qu'elle change. |
| [Génération](doc/fr/generation.md) | Enregistrer un gabarit et un motif, générer, ce qui change, ce qui est refusé. |
| [Groupes](doc/fr/groups.md) | Les membres, la page d'un groupe, trouver une UGS. |
| [Maquettes](doc/fr/mockups.md) | Le magasin d'images, ce qui est composé, les noms, les nouvelles exécutions. |
| [Options](doc/fr/options.md) | Déclarer des options sur les gabarits et les motifs, les conditions, ce que reçoit le noyau. |
| [Fichiers d'impression et ordres de production](doc/fr/production.md) | Les dispositions d'impression, les dés, les fichiers d'impression, l'ordre de production, Messenger, le magasin de production. |
| [Choisir un produit](doc/fr/selector.md) | Le contrôleur Stimulus de la page d'un groupe. |
| [Stockage](doc/fr/storage.md) | Les tables, les colonnes et le JSON. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les services et ce qu'une application remplace. |
| [Tests](doc/fr/testing.md) | Les tests du module, et le test d'une application qui génère. |
| [Référence](doc/fr/reference.md) | Chaque type public du module. |

## Démarrage rapide

```sh
composer require phil/commerce-bundle phil/commerce-catalog-bundle phil/commerce-product-template-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle::class => ['all' => true],
    Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle::class => ['all' => true],
];
```

```php
$templates->add(ProductTemplate::create($shirt, ProductKind::Physical, 'goods'));
$designs->add(ProductDesign::create($gnome));
$entityManager->flush();

$report = $generation->generate('NAQ001', 'unisex');   // NAQ001FOMBK, NAQ001FOMPK, ...
$entityManager->flush();

// Once an order is paid and its draws are made, in a worker:
$bus->dispatch(new ProducePrintFiles($order->number));
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-sodium` ; `phil/commerce-bundle`, `phil/commerce-catalog-bundle`,
`phil/commerce-product-template`, `phil/ddd-bundle`, `phil/money` et `phil/print-compositor` 0.1 ; Doctrine ORM 3.7 ;
`symfony/config` et `symfony/dependency-injection` 8.2 ou plus récent. `symfony/messenger` 8.2 pour produire dans un
*worker* ; AssetMapper et `symfony/stimulus-bundle` pour le contrôleur.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
