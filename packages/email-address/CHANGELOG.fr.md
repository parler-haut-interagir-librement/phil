<!-- phil-dev source-sha256=3d66b76cb5dd58c62243654314f67c28e77db61793e95993561300e638d22572 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/email-address` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `EmailAddress::parse()` : la boîte aux lettres de la RFC 5321 — des dot-atoms, 64 octets avant le @, 253 pour le
  domaine, 254 en tout comptés en ASCII —, le domaine passé par IDNA (UTS #46, non transitionnel, STD3, bidi et liants
  vérifiés) et gardé dans ses formes Unicode et ASCII, `toString()`, `toAscii()`, `requiresSmtpUtf8()`, `isIpLiteral()`
  et `equals()`.
- `SyntaxPolicy` : les parties locales non ASCII (RFC 6531, normalisées en NFC), les parties locales entre guillemets et
  les littéraux IP, chacun refusé par défaut et permis par sa méthode `with`.
- `SyntaxError`, la raison de chaque refus avec `describe()`, portée par `Exception\InvalidEmailAddress`.
- `DomainRules` : des domaines refusés et des domaines seuls permis, chacun valant pour ses sous-domaines, un domaine
  refusé l'étant même quand il est permis.
- `DisposableDomains` : `bundled()`, `of()`, `merge()`, `contains()` et `count()`, sous-domaines compris.
- `Data\DisposableDomainsImporter` et `bin/import-disposable-domains.php` : une liste de domaines jetables vérifiée,
  écrite en ASCII sans doublons et triée dans `data/disposable-domains.php`, sa source et sa version sur sa ligne de
  `data/VERSION`, chaque fichier écrit par le Filesystem de Symfony et mis en place une fois écrit ; la liste source est
  versée dans le domaine public sous CC0 1.0 (`data/LICENSE`, `NOTICE`).
- `TopLevelDomains` : `bundled()`, `of()`, `contains()`, `isEmpty()` et `count()`, des lettres ou des A-labels vérifiés
  par IDNA.
- `Data\TopLevelDomainsImporter` et `bin/import-top-level-domains.php` : la liste de l'IANA de la zone racine, sa
  version tirée de sa ligne `# Version`, vérifiée, écrite en minuscules sans doublons et triée dans
  `data/top-level-domains.php`, sa source et sa version sur sa ligne de `data/VERSION` (`data/LICENSE`, `NOTICE`).
- `Contract\MailDomainResolver` et `MailDomain` : des enregistrements MX, un MX nul (RFC 7505), des enregistrements A ou
  AAAA seulement, aucun, ou une recherche échouée ; `Dns\DnsGetRecordResolver`, qui interroge `dns_get_record()` avec un
  point final, et `Dns\StaticResolver`.
- `TypoSuggester` : l'adresse voulue quand le domaine est à une ou deux fautes — distance d'alignement optimal de
  chaînes, bornée, calculée sur la bande dans la limite après un filtre de Levenshtein natif — d'un domaine bien connu,
  ou finit par une faute de `com`, de `net` ou d'`org` qui n'est pas elle-même déléguée ; ses domaines et ses fautes
  remplaçables.
- `EmailValidator` et `Validation` : la syntaxe, le domaine de premier niveau, les règles de domaines, les domaines
  jetables, puis le DNS, chaque étape seulement quand les précédentes ont réussi, une liste vide de domaines de premier
  niveau ne vérifiant rien, une suggestion quel que soit le résultat, et un `Problem` pour chaque refus.
- `Validator\DeliverableEmailAddress` et `Validator\DeliverableEmailAddressValidator` : une contrainte du Validator de
  Symfony par-dessus `EmailValidator`, avec ses options de syntaxe, les domaines de premier niveau et les domaines
  jetables activés par défaut, les domaines refusés et seuls permis, le DNS sur demande, un message et un code par
  problème, et les paramètres `{{ value }}`, `{{ domain }}`, `{{ reason }}` et `{{ suggestion }}` ; construite sans
  conteneur, elle lit les listes fournies.
- `Exception\EmailAddressException`, implémentée par `InvalidEmailAddress` et `InvalidDomainList`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
