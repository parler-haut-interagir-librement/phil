<!-- phil-dev source-sha256=f0f056ac91d7a8f9353a822dcebd743c871d78e330a2f78d7a7ae513bf87edc0 -->
# Protocole

La page dans le cadre et l'élément qui la contient se parlent par `postMessage`, avec des messages de cette forme :

```json
{"protocol": "phil-embed", "version": 1, "type": "resize", "height": 640}
```

| Type | De | Contient |
|---|---|---|
| `ready` | le cadre | rien d'autre |
| `resize` | le cadre | `height` : un nombre entier de pixels, de 0 à 100 000 |
| `theme` | l'élément | `theme` : une lettre minuscule, puis jusqu'à 31 lettres minuscules, chiffres ou `-` |

Un message est un objet simple qui a exactement ces clés ; tout le reste — un autre protocole ou une autre version, un
type inconnu, une clé de plus ou de moins, une valeur hors des bornes — est ignoré.

## Les vérifications

| Côté | Envoie à | Lit |
|---|---|---|
| Le cadre | l'origine du jeton, jamais `*` | les messages dont la source est son parent et dont l'origine est celle du jeton ; un thème seulement parmi ceux de l'intégration |
| L'élément | l'origine de l'adresse du cadre | les messages dont la source est son propre cadre et dont l'origine est celle de l'adresse du cadre |

Le cadre dit qu'il est prêt dès qu'il démarre, puis sa hauteur chaque fois qu'elle change, mesurée sur son élément
`<html>` ; l'élément envoie le thème lorsque son attribut `theme` change.

## La référence

`Domain\ValueObject\EmbedMessage` est la référence du protocole : `EmbedMessage::fromJson()` lit un message en JSON de
1 024 octets au plus, `toArray()` en écrit un. Le JavaScript lit les messages selon les mêmes règles, dans
`assets/src/lib/message.js`, et les deux sont testés contre les mêmes jeux de données d'essai,
`tests/Fixtures/messages.json`. Comme JavaScript ne distingue pas `1` de `1.0`, la référence non plus.
