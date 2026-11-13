<!-- phil-dev source-sha256=41ccfee600c123c6a4c113e433bf2b31e3237a702693e297fb850b57e6eb7da3 -->
# Dessiner les blocs

`BlockMarkup` dessine les blocs d'un document et conserve chaque dessin avec son bloc :

```json
{"id": "f1", "type": "math", "data": {"latex": "x^2", "rendered": {"markup": "<math …>…</math>", "key": "v1.…"}}}
```

```php
new BlockMarkup(Signer $signer, array $renderers, array $deferred = ['mermaid'])->render(Document $document, bool $withDeferred = false): Rendered
```

Chaque type de `$renderers` est dessiné par son `MarkupRenderer` ; les blocs des autres types sont laissés tels quels,
et les blocs imbriqués dans des colonnes, des bascules ou des conteneurs sont dessinés comme ceux du premier niveau.
Pour chaque bloc d'un type dessiné :

- un dessin dont la clé correspond à la source actuelle du bloc est conservé tel quel : enregistrer de nouveau un
  document inchangé ne dessine rien et retourne le même `Document` ;
- tout autre dessin — envoyé par un client, ou produit pour une ancienne source — est retiré, et le bloc est redessiné ;
- un bloc d'un type de `$deferred` n'est dessiné que si `$withDeferred` vaut vrai : son identifiant est listé dans
  `pending` ;
- un bloc dont la source ne peut pas être dessinée ne garde aucun dessin, et son identifiant et la raison sont listés
  dans `failures`. Les lecteurs voient alors le rendu propre du bloc — son code, sa formule en texte.

`Rendered` contient le `document`, indique s'il a `changed`, et donne les blocs `pending` et les `failures`.

## Sources

| Type | Dessiné à partir de | Par |
|---|---|---|
| `math` | `latex` | `MathRenderer`, `phil/latex-mathml`, en MathML de bloc |
| `mermaid` | `code` | `MermaidRenderer`, le service Mermaid, assaini |
| `chart` | toute la configuration sauf `rendered` | `ChartRenderer`, `phil/svg-chart` |
| `qrCode` | `data` | `QrCodeRenderer`, `phil/qr-code` |

Une légende ne fait pas partie de la source : `phil/ujo` l'écrit à côté du dessin. Un dessin compte au plus
`StoredMarkup::MAX_MARKUP` caractères (1 Mio) ; un dessin plus grand est un échec.

## Aperçus

`preview(Block $block)` dessine un bloc et retourne son balisage sans le conserver, pour un auteur en train d'écrire ;
il lève `RenderingFailed` pour un type qui n'est pas dessiné ou une source qui ne peut pas l'être. `draws($type)`
indique si un type est dessiné.

## Coût

Le dessin se fait à l'enregistrement, une fois par changement. La lecture vérifie un HMAC par dessin : trois dessins
d'une page coûtent environ un dixième de milliseconde, mesuré par `tests/Benchmark/StoredMarkupBench.php`.
`BlockMarkup` ne garde aucun état entre deux appels et peut s'utiliser sans risque dans un *worker* qui sert de
nombreuses requêtes.
