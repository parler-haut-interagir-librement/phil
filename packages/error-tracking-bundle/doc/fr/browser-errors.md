<!-- phil-dev source-sha256=35f7a260bc91a2964d90d97225ecefbf0e752d5f91524137b0fae767e647e0ba -->
# Erreurs du navigateur

## Le point de terminaison

Avec `browser.enabled` et `config/routes_browser.php` importé, `POST /_errors/browser` reçoit les rapports du
contrôleur Stimulus. Il est sans état et répond par un corps vide dont le code dit ce qu'il en est de la réception :

| Code | Quand |
|---|---|
| `204` | le rapport a été lu et remis au traqueur, qui peut encore l'écarter par échantillonnage ou limitation |
| `415` | le type de média n'est ni `application/json` ni `text/plain` |
| `413` | le corps dépasse `browser.max_bytes` ; jamais plus d'un octet au-delà de la borne n'est lu |
| `403` | l'en-tête `Origin` est absent ou non permis, ou le jeton est mauvais |
| `429` | le client a envoyé plus que ne le permet `browser.rate_limit` |
| `400` | le corps n'est pas un rapport |

Les vérifications s'exécutent dans cet ordre, les moins coûteuses d'abord.

## L'origine et le jeton

L'en-tête `Origin` est la défense contre une page d'un autre site qui enverrait au nom d'un visiteur : il doit être
l'une des `browser.origins`, ou l'origine du point de terminaison lui-même quand aucune n'est configurée. Chaque origine
configurée est lue par `phil/url` et gardée comme l'envoie un navigateur, `https://example.com:8443` ; une origine avec
un chemin, une chaîne de requête, un fragment ou une espace fait échouer la construction. Un jeton,
quand `browser.token` est défini, est comparé à temps constant avec l'en-tête `X-Error-Tracking-Token`. Le jeton est
écrit dans la page : ce n'est donc pas un secret pour le visiteur. Il tient à l'écart les scripts qui ne chargent pas
la page.

## Le rapport

```json
{"type": "TypeError", "message": "x is undefined", "url": "https://example.com/cart", "stack": "TypeError: …\n    at …"}
```

- `url`, requis : une adresse `http` ou `https` que lit `phil/url`, de 2 048 octets au plus, sans espace ni caractère
  de contrôle ; `localhost` est accepté, une adresse IP, des identifiants de connexion ou le port 0 sont refusés. La page
  est gardée normalisée — hôte en minuscules et sous sa forme ASCII, port par défaut retiré, `.` et `..` résolus — et
  sans sa chaîne de requête ni son fragment ;
- `message`, requis : coupé à 2 048 caractères, ses caractères de contrôle changés en espaces ;
- `type` : un nom JavaScript, `Error` quand c'est autre chose ;
- `stack` : lue jusqu'à 16 Kio, ligne par ligne, dans les formats de Chromium (`at fn (url:line:column)`) et de
  Firefox et Safari (`fn@url:line:column`), jusqu'à `browser.max_frames` cadres ; les autres lignes sont ignorées. Le
  fichier d'un cadre est l'adresse de son script, sans chaîne de requête ni fragment, et il appartient à l'application
  quand `phil/url` la lit et qu'elle vient de l'origine de la page, comparée sur le schéma, l'hôte et le port
  normalisés.

L'événement est de source `browser`, avec la page comme adresse de la requête, le `User-Agent` comme seul en-tête, et
l'adresse du client tronquée. Le traqueur l'assainit comme n'importe quel autre. Le limiteur compte une empreinte à clé de l'adresse du client, jamais l'adresse, sous une clé dérivée du secret par
HKDF-SHA256 sous `phil_error_tracking.browser_throttle`. Quand
phil/rate-limit-bundle est enregistré, il passe par un limiteur de ce bundle nommé `phil_error_tracking.browser`, sur
la même fenêtre, que remplace la décoration du service `phil_error_tracking.browser_throttle.limit` ; sans ce bundle,
il compte directement sur `symfony/rate-limiter`. Voir [la configuration](configuration.md#ce-qui-fait-échouer-la-construction)
pour ce dont a besoin une limite de débit.

## Le contrôleur Stimulus

```twig
<body data-controller="phil--error-tracking-bundle--capture"
      data-phil--error-tracking-bundle--capture-endpoint-value="{{ path('phil_error_tracking_browser') }}"
      data-phil--error-tracking-bundle--capture-token-value="{{ error_tracking_token }}">
```

La valeur du jeton est `browser.token`, que l'application passe à son gabarit ; sans jeton, omettez l'attribut. Tant
qu'il est connecté, le contrôleur écoute les événements `error` et `unhandledrejection` de la fenêtre et envoie un
rapport pour chacun : au plus `limit` par page (10 par défaut, `data-…-limit-value`), et une seule fois le même
message. Il envoie l'origine et le chemin de la page, jamais sa chaîne de requête ni son fragment, par un `fetch`
maintenu en vie et sans identifiants de connexion. Il ne dépose aucun témoin et ne stocke rien. Une erreur d'un script
d'une autre origine, que le navigateur réduit à `Script error.`, n'est pas envoyée.

Avec AssetMapper, le bundle associe `assets/src` à `@phil/error-tracking-bundle`, le nom que Symfony UX donne aux
contrôleurs d'un paquet, pour que StimulusBundle trouve le contrôleur que déclare son `controllers.json`. Le contrôleur
est chargé d'emblée : son code se charge avec la page, pour qu'une erreur levée tôt soit captée elle aussi.

L'analyseur du point de terminaison est une cible de fuzzing : voir [Tests](testing.md).
