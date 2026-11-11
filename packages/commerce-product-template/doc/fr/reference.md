<!-- phil-dev source-sha256=207cb1e77ddf14ac8ab9a8e917dc1a0fc8dd840605c26967c1ee986498463325 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\CommerceProductTemplate\Template` | `final readonly class` | Un article vierge : axes, faces, prix, exclusions, patron d'UGS, fournisseur |
| `Phil\CommerceProductTemplate\Axis` | `final readonly class` | Ce selon quoi les produits varient, et ses valeurs dans l'ordre |
| `Phil\CommerceProductTemplate\AxisValue` | `final readonly class` | Une valeur : code, libellé, pastille, nom du fournisseur, photos, canaux |
| `Phil\CommerceProductTemplate\Label` | `final readonly class` | Un texte par locale |
| `Phil\CommerceProductTemplate\Swatch` | `final readonly class` | Une couleur ou la photo d'un échantillon |
| `Phil\CommerceProductTemplate\Face` | `final readonly class` | Une face et ses zones d'impression |
| `Phil\CommerceProductTemplate\PrintZone` | `final readonly class` | Une zone en millimètres, sa résolution et son cadre sur les photos |
| `Phil\CommerceProductTemplate\PhotoBox` | `final readonly class` | Le rectangle d'une zone sur les photos de sa face, en pixels |
| `Phil\CommerceProductTemplate\Pricing` | `final readonly class` | Un prix de base, des prix par valeur d'un axe, des suppléments |
| `Phil\CommerceProductTemplate\Exclusion` | `final readonly class` | Des combinaisons qu'un gabarit ne fabrique pas |
| `Phil\CommerceProductTemplate\Combination` | `final readonly class` | Une valeur de chaque axe |
| `Phil\CommerceProductTemplate\SkuPattern` | `final readonly class` | Des littéraux et des marqueurs, rendus par produit |
| `Phil\CommerceProductTemplate\Design` | `final readonly class` | Ce qui est imprimé : code, nom, langues, illustrations, gabarits, valeurs permises |
| `Phil\CommerceProductTemplate\LanguageVersion` | `final readonly class` | Le code d'UGS et la locale d'une langue |
| `Phil\CommerceProductTemplate\Artwork` | `final readonly class` | Une image dans une zone, pour une langue et une condition |
| `Phil\CommerceProductTemplate\VariantPlanner` | `final readonly class` | Planifie les produits d'un visuel sur un gabarit |
| `Phil\CommerceProductTemplate\Variant` | `final readonly class` | Un produit planifié |
| `Phil\CommerceProductTemplate\PlacedArtwork` | `final readonly class` | Une illustration avec la face et la zone où elle s'imprime |
| `Phil\CommerceProductTemplate\TemplateArray` | `final class` | Un gabarit sous forme de tableau versionné, et retour |
| `Phil\CommerceProductTemplate\DesignArray` | `final class` | Un visuel sous forme de tableau versionné, et retour |
| `Phil\CommerceProductTemplate\Exception\ProductTemplateException` | interface | Chaque exception de la bibliothèque |
| `Phil\CommerceProductTemplate\Exception\InvalidDefinition` | `final class` | Une définition refusée |
| `Phil\CommerceProductTemplate\Exception\PlanRefused` | `final class` | Un plan refusé |

`Internal\Check` et `Internal\Reader` sont internes : ils peuvent changer dans n'importe quelle version.
