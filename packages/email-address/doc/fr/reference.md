<!-- phil-dev source-sha256=1622503a38a374168b91a0a93e5a4e67d29d420557888bb0a41f332738ca25bb -->
# Référence

| Type | Rôle |
|---|---|
| `EmailAddress` | Une adresse à laquelle un serveur de courriel peut livrer : partie avant le @, domaine en Unicode et en ASCII |
| `SyntaxPolicy` | Les formes de la RFC 5321 et de la RFC 6531 qu'accepte une application |
| `SyntaxError` | La raison pour laquelle un texte n'est pas une adresse, avec `describe()` |
| `EmailValidator` | Les étapes dans l'ordre : syntaxe, domaine de premier niveau, règles de domaine, domaines jetables, DNS, et une suggestion |
| `Validation` | Ce que le validateur a trouvé d'une adresse |
| `Problem` | Pourquoi une adresse est refusée |
| `TopLevelDomains` | Les domaines de premier niveau que délègue la zone racine, fournis ou propres à l'application |
| `DomainRules` | Domaines refusés et seuls permis, avec leurs sous-domaines |
| `DisposableDomains` | Les domaines des boîtes de courriel jetables, fournis ou propres à l'application |
| `TypoSuggester` | L'adresse voulue quand le domaine est à une faute de frappe d'un domaine bien connu |
| `MailDomain` | Ce que dit le DNS d'un domaine comme destination du courriel |
| `Contract\MailDomainResolver` | Le port vers le DNS |
| `Dns\DnsGetRecordResolver` | Le résolveur par `dns_get_record()` |
| `Dns\StaticResolver` | Le résolveur d'après une table |
| `Data\DisposableDomainsImporter` | L'importation d'une liste de domaines jetables dans `data/` |
| `Data\TopLevelDomainsImporter` | L'importation de la liste de l'IANA des domaines de premier niveau dans `data/` |
| `Validator\DeliverableEmailAddress` | La contrainte du Validator de Symfony, avec ses options, ses messages et ses codes |
| `Validator\DeliverableEmailAddressValidator` | Le validateur de la contrainte, qui exécute `EmailValidator` |
| `Exception\EmailAddressException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidEmailAddress` | Un texte qui n'est pas une adresse, avec sa `SyntaxError` |
| `Exception\InvalidDomainList` | Une liste qui contient une entrée qui n'est pas un domaine, une version qu'on ne peut pas inscrire, ou un fichier où l'on ne peut pas écrire |
| `Internal\*` | Les vérifications des noms de domaine et des étiquettes de premier niveau, l'ensemble des domaines, la distance et le répertoire des données : hors de l'API publique |
