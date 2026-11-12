<!-- phil-dev source-sha256=8cb2d0fd9f3991057b1f8ef4a46c9f29623821f7a8a5aec513b934c0e6716b0c -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Une application lui fournit quatre ports, dans
`Phil\LegalNotices\Contract`.

## `DocumentSource`

L'endroit où sont conservées les versions : la configuration, une base de données, des fichiers.

| Méthode | Contrat |
|---|---|
| `history(DocumentType $type): DocumentHistory` | Toutes les versions du document, brouillons compris ; un historique vide s'il n'en a aucune |

`LegalNotices` l'interroge à chaque appel et ne conserve rien : une source qui lit une base de données la relit, une
source qui lit la configuration construit ses historiques une seule fois.

## `BodyRenderer`

| Méthode | Contrat |
|---|---|
| `toHtml(string $format, string $body): string` | Du HTML sûr à afficher ; `InvalidLegalNotice` pour un format inconnu ou un corps illisible |

`PlainTextBody` produit le rendu du format `text` : échappé, un `<p>` par paragraphe, `<br>` pour les autres sauts de
ligne. phil/legal-notices-bundle produit le rendu de tous les formats de phil/markup. Un moteur de rendu laisse `${…}`
tel quel, pour les paramètres substituables.

## `RetentionDurations`

| Méthode | Contrat |
|---|---|
| `durations(): list<RetentionDuration>` | Les durées de conservation que liste la politique de confidentialité, dans son ordre |

## `ConsentCategories`

| Méthode | Contrat |
|---|---|
| `categories(): list<ConsentCategory>` | Les catégories de témoins que liste la politique de confidentialité, dans son ordre |

## L'événement

`Event\DocumentPublished` transporte le type et le numéro d'une version, des identifiants seulement. La bibliothèque ne
répartit rien ; phil/legal-notices-bundle le répartit pour les versions publiées dans un intervalle de temps.
