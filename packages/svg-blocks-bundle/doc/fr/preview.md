<!-- phil-dev source-sha256=dd41104ebc42e6195b7e9002239e2d45657b2b4caf1b123f3659da2f5f4df219 -->
# Aperçu

## Le point de terminaison

`POST /_svg-blocks/preview` (`phil_svg_blocks_preview`) dessine un bloc sans le conserver :

```json
{"type": "math", "data": {"latex": "x^2"}}
```

| Réponse | Quand |
|---|---|
| `200 {"markup": text}` | le dessin |
| `422 {"error": text}` | la source ne peut pas être dessinée : la raison, pour l'auteur |
| `404` | le type n'est pas dessiné |
| `400` | le corps n'est pas `{"type": text, "data": object}` |
| `413` | le corps dépasse `preview.max_bytes` |
| `403` | la requête ne vient pas d'une page de ce site |

Les réponses sont `no-store`. Une requête est de confiance quand elle porte l'en-tête `X-Phil-Svg-Blocks: 1`, que le
navigateur ne dit pas qu'elle vient d'un autre site, et qu'elle passe la vérification CSRF sans état de Symfony pour le
jeton `phil_svg_blocks` : un jeton dans un en-tête et dans un témoin, ou `Sec-Fetch-Site`, `Origin` ou `Referer` qui
nomme le site. La route reste derrière le pare-feu de l'application : la vérification empêche seulement un autre site
de l'appeler au nom d'un rédacteur. L'aperçu d'un diagramme appelle le service Mermaid à chaque pause de l'auteur. Le
service en dessine quelques-uns à la fois et refuse les autres.

## Le contrôleur

```twig
<div data-controller="phil--svg-blocks-bundle--preview"
     data-phil--svg-blocks-bundle--preview-url-value="{{ path('phil_svg_blocks_preview') }}"
     data-phil--svg-blocks-bundle--preview-type-value="math"
     data-phil--svg-blocks-bundle--preview-csrf-cookie-value="{{ csrf_token('phil_svg_blocks') }}">
    <textarea data-field="latex"
              data-phil--svg-blocks-bundle--preview-target="field"
              data-action="phil--svg-blocks-bundle--preview#update"></textarea>
    <div data-phil--svg-blocks-bundle--preview-target="output"></div>
    <p data-phil--svg-blocks-bundle--preview-target="error" role="alert" hidden></p>
</div>
```

| Valeur | Rôle |
|---|---|
| `url` | le point de terminaison |
| `type` | `math`, `mermaid`, `chart` ou `qrCode` |
| `csrfCookie` | le nom du témoin que donne `csrf_token('phil_svg_blocks')` |
| `delay` | les millisecondes de pause avant la demande, 400 par défaut |

Chaque cible `field` donne une propriété des données du bloc sous son `data-field` ou son `name`. Un champ avec
`data-kind="json"` contient un objet dont les propriétés sont fusionnées, par exemple toute la configuration d'un
graphique. Après chaque pause, le contrôleur demande le dessin, écarte les réponses aux requêtes plus anciennes, écrit
le balisage dans `output` et la raison dans `error`, règle `aria-busy` pendant l'attente, et répartit
`phil--svg-blocks-bundle--preview:rendered` avec le résultat. Le balisage est celui du point de terminaison, dessiné et
assaini sur le serveur.

Ses modules purs, `lib/preview.js` et `lib/csrf.js`, sont testés sous Node.
