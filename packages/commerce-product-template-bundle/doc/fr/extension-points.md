<!-- phil-dev source-sha256=7e83d3f3475923125126dc340002d5809b4e4cad5d4050e01103622cc9b4a66a -->
# Points d'extension

Le module est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0.

| Pour | Faites |
|---|---|
| Enregistrer les gabarits, les motifs ou les groupes ailleurs | Implémentez `ProductTemplateRepository`, `ProductDesignRepository` ou `ProductGroupRepository` et faites pointer l'interface vers le service par un alias |
| Garder les images ailleurs que dans un répertoire | Implémentez `Application\Port\ImageStore` — un compartiment, une médiathèque — et faites pointer l'interface vers lui par un alias |
| Vendre les produits sous un autre type de référence | Réglez `sellable_type` |
| Nommer les produits dans une autre locale | Réglez `default_locale`, ou donnez des versions linguistiques au motif |
| Borner la taille d'un plan | Redéfinissez le service `VariantPlanner` avec son argument `$maxVariants` |
| Générer dans un *worker* | Appelez `CatalogGeneration` et `Mockups` depuis un *handler* de message de l'application, dont le message porte les codes du motif et du gabarit |
| Afficher un groupe | Lisez `ProductGroups::page()`, et choisissez l'UGS avec le contrôleur Stimulus `variant-selector` ([Choisir un produit](selector.md)) |
| Imprimer ce qui change selon le produit ou la ligne | Déclarez une disposition d'impression sur le motif ([Fichiers d'impression et ordres de production](production.md)) |
| Garder les fichiers d'impression et les ordres de production ailleurs | Implémentez `Application\Port\ProductionStore` — un compartiment privé — et faites pointer l'interface vers lui par un alias |
| Chercher les polices ailleurs | Implémentez `Application\Port\PrintFonts` et faites pointer l'interface vers lui par un alias |
| Produire dans un *worker* | Acheminez `ProducePrintFiles` vers un transport asynchrone, et envoyez-le après les tirages, ou réglez `production.on_purchase` |
| Envoyer l'ordre de production à l'imprimeur | Lisez `PrintProduction::orderPath()` dans le magasin une fois que `produce()` a rendu la main, dans un *handler* de l'application |

Les services publics sont `CatalogGeneration`, `ProductGroups`, `Mockups` (avec `images_directory`), `PrintProduction`
(avec `production.directory`) et les interfaces des dépôts et des magasins. `TemplateOptions` est privé : le noyau le
trouve par son étiquette. Aucun service ne garde d'état entre deux requêtes : un *worker* les sert donc tels quels.
