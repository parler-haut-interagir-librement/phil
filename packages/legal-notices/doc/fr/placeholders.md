<!-- phil-dev source-sha256=807674d67e1b1ec4ac58252b050fed6191d53bfaf7a187d21a53fe8058a5693e -->
# Paramètres substituables

Un corps nomme des valeurs entre `${` et `}`. `LegalNotices::render()` produit d'abord le rendu HTML du corps, puis
remplace chaque paramètre de son texte qui a une valeur par cette valeur, en tant que texte : échappée pour le HTML,
avec ses sauts de ligne conservés sous forme de `<br>`. Un paramètre sans valeur reste tel quel ; `LegalNotices::check()` le signale.

| Paramètre | Valeur |
|---|---|
| `${publisher_name}`, `${host_address}`… | Le champ de l'identité portant ce nom |
| `${version}` | Le numéro de la version |
| `${effective_date}` | Sa date d'entrée en vigueur, `Y-m-d` |
| `${retention.<id>}` | La durée d'une conservation, en toutes lettres dans la locale |
| `${consent.<key>}` | Le nom d'une catégorie de consentement dans la locale |

Un nom est une lettre minuscule, puis des lettres minuscules, des chiffres et `_`, 64 caractères au plus, éventuellement
suivi d'un `.` et d'un identifiant.

## Pourquoi `${…}`

Le marqueur doit parvenir au HTML tel qu'il est écrit, quel que soit le format de phil/markup du corps, puisque les
valeurs sont remplies une fois le rendu du corps produit. Chaque format réserve certains crochets :

| Marqueur | Lu par |
|---|---|
| `[[…]]` | MediaWiki, DokuWiki et Creole : un lien interne |
| `{{…}}` | MediaWiki : un modèle ; DokuWiki et Creole : une image |
| `{{{…}}}` | Creole : du texte préformaté |
| `<<…>>` | Creole : une macro |
| `%%…%%` | DokuWiki : du texte laissé sans mise en forme |
| `{…}` | Markdown : des attributs, ou du ruby avec une barre verticale |
| `[…]` | BBCode : une balise ; Markdown : le texte d'un lien |
| `%name%` | Les paramètres d'un conteneur Symfony, dans la configuration qui contient les corps |

Aucun format ne donne de sens à `${…}` : il traverse sans changement le texte brut, le Markdown, le HTML, le BBCode,
MediaWiki, DokuWiki, Creole et UJO (Universal JSON Output), si bien qu'un corps nomme des paramètres dans chacun d'eux.
Dans une chaîne PHP entre guillemets doubles, écrivez `\${name}`, ou employez des guillemets simples.

```text
This site is published by ${publisher_name}, ${publisher_address}.
We keep your account for ${retention.R-001}.
```

`Placeholders::names($text)` liste les noms que donne un texte, chacun une seule fois. `Placeholders::of()` construit
les valeurs d'une version dans une locale. `fill($html)` les remplace, chaque valeur une seule fois, de sorte qu'une
valeur n'est jamais relue comme un paramètre.

## Le texte seulement

`fill()` lit le HTML rendu avec l'analyseur HTML5 de l'extension DOM, comme le contenu d'un `<body>`, remplit les
paramètres de ses nœuds de texte seulement et réécrit le HTML avec le sérialiseur HTML. Un paramètre écrit ailleurs
reste tel quel :

- dans une balise : un `href`, un `src`, un `style` ou tout autre attribut, où une valeur comme `javascript:alert(1)`
  ferait un lien qui exécute un script, quel que soit son échappement ;
- dans un commentaire ou un `<template>` ;
- dans le texte d'un élément dont le sérialiseur écrit le texte tel quel : `<script>`, `<style>`, `<iframe>`,
  `<noembed>`, `<noframes>`, `<noscript>`, `<plaintext>` et `<xmp>`, où une valeur pourrait fermer l'élément.

`misplaced($html)` liste les noms qui ont une valeur mais restent ainsi tels quels, et `LegalNotices::check()` signale
chacun comme `misplaced_placeholder`. Un lien vers le site s'écrit dans le corps avec son adresse, jamais avec un
paramètre :

```html
<!-- Left as written, and reported -->
<a href="${site_name}">${site_name}</a>

<!-- Filled -->
<a href="https://example.com/">${site_name}</a>
```

Un HTML sans paramètre à remplir revient inchangé ; un HTML avec un paramètre à remplir revient tel que l'écrit le
sérialiseur, `&apos;` devenant `'` par exemple.
