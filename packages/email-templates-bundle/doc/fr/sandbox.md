<!-- phil-dev source-sha256=3a8e4b4fa66fac03360d9e8ca00098ad17b0e89bbe5aa019db4aed4b755f3dd6 -->
# Le bac à sable et les composants

## Ce qu'un gabarit peut écrire

Chaque source d'un gabarit — objet, pré-en-tête, corps, partie en texte brut — produit son rendu dans un environnement
Twig distinct, isolé par le `Sandbox` de Twig en mode strict : ce que la liste d'éléments autorisés ne nomme pas est
refusé, à l'enregistrement qui le vérifie et à chaque rendu. Le Twig de l'application, ses variables globales, ses
extensions et ses gabarits sont hors de portée.

| Sorte | Autorisé |
|---|---|
| Balises | `if`, `for`, `set`, `apply` |
| Filtres | `abs`, `capitalize`, `date`, `default`, `e`, `escape`, `first`, `format`, `join`, `last`, `length`, `lower`, `nl2br`, `number_format`, `replace`, `reverse`, `round`, `slice`, `split`, `striptags`, `title`, `trim`, `upper`, `url_encode`, `panel`, `footer` ; avec `twig/intl-extra`, `format_date`, `format_datetime`, `format_time`, `format_number`, `format_currency` |
| Fonctions | `button`, `table`, `image`, `segment`, `max`, `min` |
| Tests | `defined`, `empty`, `even`, `odd`, `null`, `none`, `same as`, `divisible by`, `iterable`, `sequence`, `mapping`, `true` |
| Méthodes | `format()` et `getTimestamp()` d'une date |
| Propriétés | aucune |

Refusés, entre autres : `include`, `extends`, `embed`, `import`, `macro`, `block`, `source`, `attribute`, `constant`,
`range` et `..`, `raw`, `map`, `filter` et `reduce`. L'application autorise les filtres et les fonctions de ses propres
extensions Twig en les étiquetant `phil_email_templates.sandbox_extension` et en listant leurs noms dans
`sandbox.filters` et `sandbox.functions`.

Un corps est échappé comme du HTML ; un objet, un pré-en-tête et une partie en texte brut produisent un rendu en texte,
puis l'objet et le pré-en-tête sont ramenés sur une seule ligne. Les variables sont strictes : lire un nom que le
contexte n'a pas est une erreur, et un enregistrement signale chaque variable que les sources lisent et que le schéma ne
déclare pas. Un gabarit lit le rendu lui-même par `email.key`, `email.site`, `email.locale` et `email.segments`.

Les dates : le filtre `date` convertit vers le fuseau horaire de PHP, sauf si on lui donne `false` —
`{{ starts_at|date('Y-m-d H:i', false) }}` conserve le décalage de la valeur. Les filtres Intl prennent la locale en
argument : `{{ total|format_currency('CAD', locale=email.locale) }}`.

## Les limites

| Limite | Valeur |
|---|---|
| Boucles imbriquées | 2 niveaux — une liste contient au plus 1000 valeurs, si bien qu'un gabarit en parcourt au plus un million |
| Sortie d'une source | `max_html_bytes`, 1 Mio par défaut |
| Le document entier | `max_html_bytes` |
| Une source | 2000 caractères pour l'objet et le pré-en-tête, 256 Kio pour le corps et le texte brut |

## Les composants

Les composants dessinent avec des tables et des styles en ligne, que les clients de courriel affichent de la même façon,
celui d'Outlook compris ; chacun porte la classe que le mode sombre de la mise en page redéfinit. Dans l'objet, le
pré-en-tête et la partie en texte brut, ils écrivent du texte à la place.

| Composant | Dessine | En texte |
|---|---|---|
| `button(label, url, {align})` | un bouton dessiné par une cellule de table ; `url` est une adresse absolue `http`, `https` ou `mailto` ; `align` vaut `left`, `center` ou `right` | `label: address` |
| `table(rows, {header, caption})` | une table de données : les cellules d'en-tête, puis chaque ligne de cellules, chaque cellule échappée | les lignes, cellules séparées par `\|` |
| `image(src, alt, {width})` | une image : `cid:<name>` des `embeds` du gabarit, ou une adresse `https` ; `width` de 1 à 1200 | `[alt]` |
| `{% apply panel %}…{% endapply %}` | un panneau sur la couleur de panneau | le contenu |
| `{% apply footer %}…{% endapply %}` | le petit pied de message, atténué | le contenu |
| `segment(name)` | si le destinataire appartient à un segment que le gabarit déclare | |

```twig
<h1>Here is your ticket, {{ recipient.name }}</h1>
{{ table([['Ticket', ticket.number], ['When', event.starts_at|date('l, F j, Y \\a\\t g:i A', false)]]) }}
{{ button('Show my ticket', ticket_url, {align: 'center'}) }}
{% apply footer %}<p style="margin:0;">You receive this email because a ticket was issued in your name.</p>{% endapply %}
```

## Markdown

Un corps en Markdown est d'abord rendu par Twig, ses variables échappées comme du HTML, puis lu par `phil/markup` —
CommonMark et les extensions de GitHub — et purifié à son niveau `all` : titres, emphase, liens, listes, citations,
tables et images, rien de ce qu'un assainisseur juge dangereux. Le bundle emploie `phil/markup` plutôt que le
`markdown_to_html` de Twig, qui exige une bibliothèque Markdown à part, et pour que la purification soit celle que
chaque paquet PHIL applique.

Pendant la lecture du Markdown, chaque composant est mis de côté derrière un substitut fait de lettres et de chiffres,
que le Markdown laisse intact, puis remis en place tel quel une fois le Markdown devenu du HTML : un bouton, une table,
un panneau ou un pied de message devient un bloc à part ; une image reste dans sa ligne. Le contenu d'un panneau ou d'un
pied de message est lui aussi du Markdown.
