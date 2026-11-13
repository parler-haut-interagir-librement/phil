<!-- phil-dev source-sha256=bd5bbe503b94b6e763eb4931a0276a381090640142c46ceae6b5034b8f3c6964 -->
# Signature

Un document est du JSON qu'envoie un client ; le dessin qu'il conserve est du balisage écrit dans les pages des
lecteurs sans être assaini de nouveau. Rien de ce qu'envoie un client ne doit devenir ce balisage : `Signer` fait la
différence.

```php
new Signer(#[SensitiveParameter] string $secret)    // at least 32 bytes
```

`sign($type, $source, $markup)` retourne `v1.` suivi du HMAC-SHA256 du type, de la source et du balisage, séparés par
des octets NUL, sous le secret, en Base64 compatible URL sans remplissage. `trusts(Block $block, $markup, $key)`
compare, en temps constant, la clé avec celle du type et de la source actuelle du bloc : une clé forgée, un balisage
modifié dans la base de données ou une source modifiée depuis le dessin échouent tous. Le préfixe nomme la
construction, pour qu'une construction future puisse lire les clés déjà conservées.

## Rendu

`HtmlRenderer::withStoredMarkup()` de `phil/ujo` prend la vérification sous forme de fermeture :

```php
$renderer = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...));
```

Un bloc dont le dessin est digne de confiance est écrit sous forme de `<div>` autour de son MathML, ou de `<figure>`
autour de son SVG avec sa légende ; tout autre bloc revient à son rendu propre. Les moteurs EPUB et d'impression qui
produisent leur rendu par le même `HtmlRenderer` obtiennent aussi le dessin.

## Le secret

Le secret est celui de l'application — dans Symfony, le secret du noyau par défaut. Le changer rend chaque dessin
indigne de confiance : les lecteurs voient le rendu propre des blocs jusqu'à ce que chaque document soit enregistré de
nouveau, ou qu'une passe sur les documents les redessine.
