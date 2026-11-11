<!-- phil-dev source-sha256=9587cb4ca883d0c38ee674a6967c56999e6f4d2b5227cfc587225ee33e3a0335 -->
# Patrons d'UGS

Un gabarit écrit l'UGS (unité de gestion de stock) de chacun de ses produits à partir d'un patron : des littéraux et des
marqueurs entre accolades.

| Marqueur | Remplacé par |
|---|---|
| `{design}` | Le code du visuel, obligatoire : deux visuels ne partagent jamais une UGS |
| `{lang}` | Le code de la version linguistique du produit ; rien pour un visuel en une langue |
| `{template}` | Le code du gabarit, pour un visuel fabriqué sur deux gabarits qui partagent leurs codes |
| `{<axis>}` | Le code de la valeur du produit sur cet axe |

```php
SkuPattern::parse('{design}{lang}{version}{size}{colour}')->render([...]);   // NAQ001FOMBK
SkuPattern::parse('TS-{design}-{size}-{colour}');                            // TS-NAQ001-M-BK
```

Un patron contient de 1 à 128 caractères : lettres, chiffres, `.`, `_`, `-` et marqueurs d'au plus 32 lettres
minuscules, chiffres ou `_`, chacun une seule fois. Un patron fait d'autre chose est refusé avec `InvalidDefinition`.

## Codes collés et vérification des collisions

Des codes collés se lisent sans séparateurs seulement si deux combinaisons ne se collent jamais en un même texte : `M` +
`BK` et `MB` + `K` le feraient. Les codes d'une boutique sont choisis pour que cela n'arrive jamais ; le planificateur
vérifie tout de même. Deux produits d'un même plan avec la même UGS font refuser tout le plan avec
`PlanRefused::collision()`, qui nomme l'UGS et les deux produits. Un patron qui omet un axe fait refuser son plan de la
même façon, dès que cet axe a deux valeurs.

L'UGS est aussi vérifiée comme un catalogue l'accepte : de 1 à 64 lettres, chiffres, `.`, `_` ou `-`, commençant par une
lettre ou un chiffre.

Un patron renvoie à des codes, pas à des positions : un suffixe de version avant la taille et la couleur —
`{design}{lang}{version}{size}{colour}` — est l'ordre que donne le patron, rien de plus.
