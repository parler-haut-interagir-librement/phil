<!-- phil-dev source-sha256=7a5f03789b2521d5ae88e8e414f89c9d300738d09a3d414951bb4b1439ff09ea -->
# Accessibilité

Un EPUB est accessible quand son contenu peut être lu par tous (par un lecteur d'écran, un afficheur braille, en gros
caractères ou à voix haute) et quand ses métadonnées le disent, pour qu'un lecteur puisse le savoir avant d'acheter ou
d'emprunter. La bibliothèque prend soin de la structure ; l'application déclare ce qu'elle sait de son contenu.

## Les métadonnées

`Metadata::$accessibility` est une valeur `Accessibility`, écrite comme propriétés schema.org dans le document de
paquet :

| Argument | Écrit comme | Exemple |
|---|---|---|
| `accessModes` | un `schema:accessMode` chacun | `AccessMode::Textual`, `AccessMode::Visual`, `AccessMode::ChartOnVisual` |
| `sufficientModes` | un `schema:accessModeSufficient` par ensemble, les modes joints par des virgules | `[[AccessMode::Textual]]` quand chaque image a un texte de remplacement |
| `features` | un `schema:accessibilityFeature` chacune | `AccessibilityFeature::AlternativeText`, `StructuralNavigation`, `ReadingOrder` |
| `hazards` | un `schema:accessibilityHazard` chacun | `AccessibilityHazard::None`, ou `NoFlashingHazard` et ses semblables |
| `summary` | `schema:accessibilitySummary` | Une phrase qu'un lecteur peut comprendre |
| `conformsTo` | `dcterms:conformsTo` | `EPUB Accessibility 1.1 - WCAG 2.2 Level AA` |
| `certifiedBy` | `a11y:certifiedBy` | Qui l'a vérifié |

`AccessibilityHazard::None` et `Unknown`, ainsi qu'`AccessibilityFeature::None`, sont déclarés seuls.

## Ce qu'ajoute le rédacteur

Le rédacteur ajoute ce qu'il sait par lui-même, après ce qu'a déclaré l'application et sans le répéter :

- le mode d'accès `textual`, toujours ; `visual` quand le livre contient une image ou une vidéo ; `auditory` quand il
  contient un fichier audio ou vidéo ;
- la fonctionnalité `tableOfContents`, puisque chaque livre a son document de navigation ;
- la fonctionnalité `pageNavigation` quand le livre a une liste de pages ;
- la fonctionnalité `synchronizedAudioText` quand un chapitre a une superposition de médias ;

sauf si l'application a déclaré la fonctionnalité `none`. Il n'ajoute aucun danger, aucun résumé et aucune
conformité : seule l'application les connaît.

## Ce qui rend le contenu accessible

La bibliothèque écrit ce qu'on lui donne. Le contenu est accessible quand le HTML l'est :

- des titres dans l'ordre, qui font aussi la table des matières ;
- un texte de remplacement sur chaque image ; la bibliothèque garde `alt`, et une image qu'elle ne peut pas inclure
  laisse son texte de remplacement dans le texte ;
- la langue du livre, et celle de chaque chapitre écrit dans une autre langue ;
- des repères sur les chapitres qui ont un rôle (`LandmarkType::TitlePage`, `Index`, `Glossary`…) ;
- des marqueurs de saut de page quand le livre suit une édition imprimée, pour qu'une classe puisse dire « allez à la
  page 42 » ; voir [Navigation](navigation.md).

Le document de navigation est marqué `role="doc-toc"`, l'image de couverture `role="doc-cover"`, et les repères et la
liste de pages sont cachés de l'ordre de lecture.
