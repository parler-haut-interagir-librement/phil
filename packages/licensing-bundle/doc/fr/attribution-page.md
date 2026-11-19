<!-- phil-dev source-sha256=23d3accff3cb09292640718b76ddf523a5605a4beffa37a1c7ec04a5c7cb494b -->
# La page d'attribution

`AttributionPage::of($declaration, $catalog)` rassemble ce que montre la page : la licence, la mention, les
déclarations, les ressources (les sources de texte d'abord, puis les images, les cartes, les polices et les autres,
chaque genre dans l'ordre de la déclaration), chaque avis de droit d'auteur une seule fois, celui de l'œuvre en premier,
et chaque licence que nomme la page une seule fois.

## HTML

`AttributionRenderer::html($subject, $locale)` produit le rendu du gabarit, une `<section class="phil-licensing">` sans
document autour, pour une page du site ou un livre mis en page à partir de HTML. Le sujet est un `SubjectReference`, un
`Licensable` ou une déclaration ; un sujet sans déclaration donne une chaîne vide. Chaque texte est échappé, avec ses
sauts de ligne conservés.

| Section | Affichée quand |
|---|---|
| Licence, avec son adresse et sa référence | toujours ; un code inconnu le signale |
| Contenu offert sous la licence, Reserved Material, Product Identity | la déclaration le contient |
| Attributions, par genre | il y a des ressources |
| Avis de droit d'auteur | l'œuvre ou une ressource en a un |
| Texte de la licence | la déclaration le contient |

## Twig

| Fonction | Retourne |
|---|---|
| `phil_licensing_attribution(subject, locale = null)` | Le fragment HTML. |
| `phil_licensing_page(subject)` | Le `AttributionPage`, ou `null`, pour un gabarit de l'application. |
| `phil_licensing_license_name(license, locale = null)` | Le nom d'une licence ou d'un code, traduit. |
| `phil_licensing_violation(violation, locale = null)` | Le message d'une violation. |

```twig
<article>{{ phil_licensing_attribution(book, app.request.locale) }}</article>
```

Les textes sont les messages `page.*`, `kind.*` et `resource.*` du domaine `phil_licensing`.

## UJO

Avec `phil/ujo`, `AttributionDocument::of($page, $locale)` donne la même page sous forme de document UJO (Universal JSON
Output) composé de blocs `header`, `paragraph` et `list`, identifiés `phil-licensing-1`, `phil-licensing-2` et ainsi de
suite : un livre mis en page à partir de blocs l'ajoute à la fin. Chaque texte est échappé ; les adresses sont des
liens.

## JSON-LD

Avec `phil/structured-data`, `LicenseStructuredData::properties($page)` donne `license` (l'adresse de la licence de
l'œuvre), `copyrightNotice` et `isBasedOn`, chaque ressource étant un `CreativeWork`, un `ImageObject` ou un `Map` avec
ses `name`, `author`, `url`, `license` et `copyrightNotice`, à fusionner dans le nœud de l'œuvre de l'application.
`node($page, 'Book', $id)` donne un nœud distinct.
