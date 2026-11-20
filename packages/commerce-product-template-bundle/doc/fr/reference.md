<!-- phil-dev source-sha256=88885e32186b89886396ae1d037c11c58170172f699943ec818d54b4605fd076 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle` | `final class` | Le bundle : la configuration, la correspondance, les services |
| `Phil\CommerceProductTemplateBundle\Application\CatalogGeneration` | `final readonly class` | Génère et retire les produits d'un groupe |
| `Phil\CommerceProductTemplateBundle\Application\ProductGroups` | `final readonly class` | Lit la page d'un groupe |
| `Phil\CommerceProductTemplateBundle\Application\GroupPage` | `final readonly class` | Le nom, les sélecteurs et les membres d'un groupe dans une locale |
| `Phil\CommerceProductTemplateBundle\Application\TemplateOptions` | `final readonly class` | Le `SellableOptions` du noyau pour les produits générés |
| `Phil\CommerceProductTemplateBundle\Application\Mockups` | `final readonly class` | Compose les maquettes d'un groupe |
| `Phil\CommerceProductTemplateBundle\Application\Port\ImageStore` | interface | Lit les images et écrit les maquettes par chemin |
| `Phil\CommerceProductTemplateBundle\Application\PrintProduction` | `final readonly class` | Compose les fichiers d'impression d'une commande et écrit son ordre de production |
| `Phil\CommerceProductTemplateBundle\Application\ProductionCache` | `final class` | Interne : ce qu'une exécution de production ne lit qu'une fois |
| `Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles` | `final readonly class` | Le message qui demande la production d'une commande, par son numéro |
| `Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore` | interface | Lit et écrit les fichiers d'impression et les ordres de production par chemin |
| `Phil\CommerceProductTemplateBundle\Application\Port\PrintFonts` | interface | Les polices dans lesquelles les textes sont imprimés, par nom |
| `Phil\CommerceProductTemplateBundle\Domain\Print\PrintElementKind` | enum | Image, texte, choix ou dés |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement` | `final readonly class` | Un élément de la disposition d'impression d'un motif |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceSlot` | `final readonly class` | Un dé d'un élément de dés : sa boîte, ses faces et ses images |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceCode` | `final readonly class` | Les résultats de dés, lus d'un tirage et écrits à raison d'un caractère par dé |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintFile` | `final readonly class` | Un fichier d'impression : face, zone, encre, taille, résolution, chemin |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionLine` | `final readonly class` | Les unités d'un produit imprimées de la même façon |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionOrder` | `final readonly class` | Ce que reçoit l'imprimeur, en JSON et en CSV |
| `Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate` | `final class` | Un gabarit enregistré, sa sorte, sa catégorie de taxe et ses options |
| `Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign` | `final class` | Un motif enregistré, ses options et sa disposition d'impression |
| `Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup` | `final class` | Le groupe d'un motif sur un gabarit |
| `Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember` | `final class` | Un produit d'un groupe, par UGS |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption` | `final readonly class` | Une option du noyau avec une condition |
| `Phil\CommerceProductTemplateBundle\Domain\ValueObject\GenerationReport` | `final readonly class` | Ce qu'une génération a fait, par UGS |
| `Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused` | `final class` | Ce que le module refuse |
| `Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository` | interface | Trouve et enregistre les gabarits |
| `Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository` | interface | Trouve et enregistre les motifs |
| `Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository` | interface | Trouve et enregistre les groupes et les membres |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductTemplateRepository` | `final readonly class` | Les gabarits sur Doctrine |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductDesignRepository` | `final readonly class` | Les motifs sur Doctrine |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductGroupRepository` | `final readonly class` | Les groupes et les membres sur Doctrine |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryImageStore` | `final readonly class` | Le magasin d'images dans un répertoire |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryProductionStore` | `final readonly class` | Le magasin de production dans un répertoire lisible par son propriétaire seulement |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts` | `final readonly class` | Les polices de `production.fonts` |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProducePrintFilesHandler` | `final readonly class` | Traite `ProducePrintFiles`, en réessayant ce qui peut réussir plus tard |
| `Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProduceOnPurchase` | `final readonly class` | Envoie `ProducePrintFiles` sur `OrderPurchased`, avec `production.on_purchase` |
| `@phil/commerce-product-template-bundle/variant-selector` | contrôleur Stimulus | Choisit un produit d'un groupe, les combinaisons qu'il ne vend pas étant désactivées |
