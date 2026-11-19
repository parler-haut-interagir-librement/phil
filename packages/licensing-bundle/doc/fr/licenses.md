<!-- phil-dev source-sha256=0b30f4aeb0e4041bafb78aa6b98c15bcabfe8da1045c07bedf8f7a0edddd5ff6 -->
# Licences

`LicenseCatalog` contient les licences intégrées, puis celles de l'application. Chaque `License` a un code, un nom, une
adresse ou une référence où trouver son texte, un identifiant SPDX s'il en a un, et ses exigences.

## Les licences intégrées

| Code | Licence | Exigences |
|---|---|---|
| `orc` | ORC License | `attribution`, `license_notice`, `share_alike`, `reserved_material` |
| `cc-by-4.0` | Creative Commons Attribution 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes` |
| `cc-by-sa-4.0` | Creative Commons Attribution-ShareAlike 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `share_alike`, `indicate_changes` |
| `cc-by-nc-4.0` | Creative Commons Attribution-NonCommercial 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes`, `non_commercial` |
| `cc-by-nc-sa-4.0` | Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `share_alike`, `indicate_changes`, `non_commercial` |
| `cc-by-nd-4.0` | Creative Commons Attribution-NoDerivatives 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes`, `no_derivatives` |
| `cc-by-nc-nd-4.0` | Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International | `attribution`, `license_notice`, `copyright_notice`, `indicate_changes`, `non_commercial`, `no_derivatives` |
| `cc0-1.0` | CC0 1.0 Universal | aucune |
| `ogl-1.0a` | Open Game License 1.0a | `full_text`, `copyright_notice`, `share_alike`, `product_identity`, `designated_content` |
| `custom` | Licence personnalisée | `license_notice` |

Les exigences résument les textes officiels ; c'est la licence, à son adresse, qui fait foi.

- **ORC License**, à `https://paizo.com/orclicense`, enregistrée à la Library of Congress sous le numéro TX 9-307-067 :
  l'ORC Notice, l'attribution du Licensed Material utilisé, l'identification de bonne foi du Reserved Material et le
  Licensed Material adapté offert sous la licence. Son texte n'a pas à être reproduit. L'Expressly Designated Licensed
  Material est facultatif : le contenu désigné de la déclaration le contient.
- **Creative Commons Attribution 4.0** : le crédit des créateurs comme ils le demandent, les mentions qu'ils
  fournissent, une référence à la licence et une indication des modifications ; un lien vers la licence suffit.
  **ShareAlike** ajoute que les adaptations sont sous la même licence ; **NonCommercial** tient le matériel à l'écart de
  tout usage commercial ; **NoDerivatives** empêche de partager du matériel adapté.
- **CC0 1.0** verse l'œuvre au domaine public et ne demande rien.
- **Open Game License 1.0a** : une copie de son texte avec chaque copie de l'Open Game Content, les avis de droit
  d'auteur de l'Open Game Content utilisé avec ceux de l'œuvre (sa section 15), la désignation de la Product Identity et
  l'identification claire de l'Open Game Content.
- **custom** est les conditions propres à l'œuvre : la déclaration les contient comme texte de licence.

## Les exigences et les vérifications

| Exigence | Sur la licence de l'œuvre | Sur la licence d'une ressource |
|---|---|---|
| `attribution` | — | l'attribution nomme un auteur ou porte une mention |
| `license_notice` | un texte de licence quand la licence n'a ni adresse ni référence | — |
| `full_text` | un texte de licence | — |
| `copyright_notice` | l'avis de l'œuvre | l'avis de la ressource |
| `share_alike` | — | l'œuvre est sous la même licence |
| `indicate_changes` | — | l'attribution dit ce qui a changé, ou que rien n'a changé |
| `reserved_material` | le Reserved Material | — |
| `product_identity` | la Product Identity | — |
| `designated_content` | le contenu désigné | — |
| `non_commercial` | — | — (l'éditeur tient le matériel à l'écart de tout usage commercial) |
| `no_derivatives` | — | — (l'éditeur ne partage aucune adaptation du matériel) |

L'[assistant de licence](license-finder.md) choisit parmi ces licences selon ce qu'un auteur permet.

## Les noms

`LicenseNames` donne le nom d'une licence dans la langue du lecteur : `license.<code>` du domaine de traduction
`phil_licensing` quand il est traduit, sinon le nom du catalogue. Les licences intégrées sont traduites en anglais et en
français ; une application traduit les siennes.
