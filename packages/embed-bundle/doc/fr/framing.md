<!-- phil-dev source-sha256=960442795bb05f872c463b0b650f1dd02d749f37e155ac5cf1958b4f8b05c734 -->
# Encadrement

Qui peut placer une page dans un cadre, c'est la directive `frame-ancestors` de la réponse, dans sa politique de sécurité
du contenu ; l'ancien en-tête `X-Frame-Options` dit seulement `DENY` ou `SAMEORIGIN`, et un navigateur qui voit les deux
obéit à `frame-ancestors`.

| La requête | `frame-ancestors` | `X-Frame-Options` |
|---|---|---|
| Un jeton valide | l'origine du jeton | retiré |
| Un autre jeton | `'none'` | conservé |
| Aucun jeton | celle du site | celui du site |

## Avec phil/browser-security-bundle

Lorsque `phil/browser-security-bundle` est activé, l'encadrement passe par son `BrowserSecurityContext` : une
surcharge des protections de la réponse remplace les sources de `frame-ancestors` dans la politique du site, et retire
`X-Frame-Options` lorsque l'origine est autorisée. Le reste de la politique demeure celui du site. La route du script se
laisse charger par d'autres sites de la même façon, avec `Cross-Origin-Resource-Policy: cross-origin`.

## Sans lui

`HeaderFraming` garde la décision sur la requête et écrit un en-tête `Content-Security-Policy` qui lui est propre, avec
`frame-ancestors` seulement, à côté de tout autre que l'application envoie : un navigateur applique chaque politique qu'il
reçoit, de sorte qu'une politique de l'application qui dit `frame-ancestors 'self'` interdit encore le cadre. Sans
politique propre, ou avec `phil/browser-security-bundle`, il n'y a rien à concilier.

`framing: header` garde l'en-tête du bundle même lorsque `phil/browser-security-bundle` est activé ; `framing:
browser_security` l'exige.
