<!-- phil-dev source-sha256=a791676aae0ce2d6ff92f3e31acf58d6647754650aef34600be62882a862d5ab -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/instant-messenger` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `MessengerType`, `KnownMessenger`, `Handle`, `Messengers` ; `InstantMessengerException` et `InvalidHandle`.
  `Messengers::detect()` lit l'entrée une fois, et un service n'analyse un lien que s'il en publie un et que l'entrée
  contient un point. `Handle::fromCanonical()` reprend un identifiant gardé dans sa forme canonique sans le relire, si
  bien qu'un numéro de téléphone qu'une importation ultérieure des plans de numérotation ne connaît pas se charge
  encore.
- Les numéros de téléphone lus par `phil/phone` : dans leur forme internationale, dans le plan de leur pays, sans poste
  ; montrés au format international.
- Les pages propres à Telegram, `t.me/joinchat/…`, `t.me/addstickers/…`, `t.me/proxy` et leurs semblables, ne sont pas
  des noms d'utilisateur.
- Les comptes officiels LINE : `@id`, et les liens `line.me/R/ti/p/@id`, gardés avec leur `@` et liés comme
  `https://line.me/R/ti/p/@id` ; les liens `line.me/R/ti/p/~id` sont lus. Un code d'ami LINE, `line.me/ti/p/AbCd…`,
  n'est pas un identifiant LINE.
- XMPP : une adresse avec `?`, `#` ou un caractère de contrôle dans son utilisateur est refusée, et `link()` encode en
  pourcentage l'utilisateur comme l'écrit la RFC 5122, si bien qu'un lien `xmpp:` ouvre une conversation avec l'adresse
  montrée, jamais une autre.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
