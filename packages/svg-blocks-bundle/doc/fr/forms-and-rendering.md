<!-- phil-dev source-sha256=6151d4ea023c179db52276fb970625eec9eafe073859415b58e68c50f8965b67 -->
# Formulaires et rendu

## Champs

`StoredMarkupTypeExtension` étend le `UjoType` de `phil/ujo-bundle` et, quand il est installé, le `BlockEditorType` de
l'éditeur par blocs. Une fois que le champ a vérifié le document soumis, son transformateur donne le document à
`BlockMarkup` :

- les blocs `math`, `chart` et `qrCode` sont dessinés, et chaque dessin est conservé sous `rendered` avec sa clé ;
- un dessin envoyé par le client est retiré et refait : aucun balisage écrit par un client n'atteint une page ;
- un document inchangé est dessiné à partir de ce qu'il conserve, sans coût ;
- les blocs `mermaid` restent en attente, sauf si `mermaid.async` vaut false ;
- un bloc qui ne peut pas être dessiné ne garde aucun dessin. Le formulaire reste valide, et les lecteurs voient le
  rendu propre du bloc. L'aperçu montre la raison à son auteur.

Le modèle garde sa forme : du JSON canonique, ou un `Document` avec `document: true`.

```php
$builder->add('body', UjoType::class, ['svg_blocks' => false]);   // this field's documents left as they are
```

## Formules

Les blocs `math` sont convertis par `phil/latex-mathml`, dans les limites de `math` : toute construction qu'il lit
(macros définies dans la formule, couleurs, liens, `\boxed`, `\tag`, filets dans `array`, notation chimique dans
`\ce{…}`) atteint la page, et une formule qu'il refuse ne garde aucun dessin, son motif étant montré par l'aperçu.
Voyez son `doc/commands.md` pour les commandes lues et celles qui sont refusées.

## Rendu

Le service `HtmlRenderer` de `phil/ujo-bundle` est décoré par `withStoredMarkup()`. `ujo_html` dans Twig, `Ujo`, et
tout ce qui est construit sur le moteur de rendu (les chapitres de `phil/epub`, une page HTML envoyée à un moteur de
médias paginés) écrivent les dessins de confiance :

```html
<div class="ujo-math"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">…</math></div>
<figure class="ujo-mermaid"><svg …>…</svg><figcaption>The flow</figcaption></figure>
<figure class="ujo-chart"><svg …>…</svg></figure>
<figure class="ujo-qr-code"><svg …>…</svg></figure>
```

Un bloc sans dessin de confiance se rabat sur son propre rendu : la formule en texte, le code du diagramme dans
`<pre class="mermaid">`, la configuration du graphique dans `data-chart`, le texte du code QR.

## PDF

Un PDF produit à partir de ce HTML utilise les dessins tels qu'ils sont conservés. Les moteurs qui dessinent le SVG
(WeasyPrint, Chromium par Gotenberg) dessinent les diagrammes, les graphiques et les codes QR. Chromium dessine le
MathML ; WeasyPrint ne le dessine pas. Un PDF produit par WeasyPrint n'affiche donc les formules que par la solution
de repli de l'application.
