<!-- phil-dev source-sha256=f5df8cd3f52a94f779b9fe80aecd963e1141728482cca420e339a9a24677454b -->
# Syntaxe

`EmailAddress::parse(string $input, SyntaxPolicy $policy = new SyntaxPolicy())` retourne une `EmailAddress` ou lève
`Exception\InvalidEmailAddress`, dont la `reason` est une `SyntaxError`. Le message de l'exception ne contient jamais
l'adresse : c'est un renseignement personnel, et les messages aboutissent dans les journaux.

## Ce qui est accepté

La boîte de courriel de la RFC 5321, ce à quoi un serveur de courriel peut livrer :

- les espaces, tabulations et sauts de ligne autour de l'adresse sont retirés ; rien d'autre n'est réparé ;
- avant le @, un dot-atom : des lettres, des chiffres et ``! # $ % & ' * + / = ? ^ _ ` { | } ~ -``, avec des points
  entre eux, jamais en premier, en dernier ou doublés ; 64 octets au plus ;
- après le @, un nom de domaine d'au moins deux étiquettes, chacune de 63 octets au plus en ASCII, le tout de 253 au
  plus, la dernière étiquette n'étant ni numérique ni d'un seul caractère ;
- l'adresse entière de 254 octets au plus, avec son domaine en ASCII.

Le domaine passe par IDNA (UTS #46, traitement non transitionnel, règles STD3, vérifications bidi et des liants), comme
le lisent les navigateurs : `ada@BÜCHER.example` est `ada@bücher.example`, envoyé comme `ada@xn--bcher-kva.example`.
Un domaine écrit en punycode est vérifié et affiché dans son écriture.

## La politique

`SyntaxPolicy` contient ce que la valeur par défaut refuse. Chaque méthode `with` retourne une nouvelle politique.

| Méthode | Permet | Pourquoi c'est refusé par défaut |
|---|---|---|
| `withInternationalLocalPart()` | Des caractères non ASCII avant le @ (RFC 6531), normalisés en NFC | L'expéditeur et chaque serveur en chemin doivent parler SMTPUTF8 |
| `withQuotedLocalPart()` | `"ada lovelace"@example.com`, avec `\"` et `\\` | Peu de serveurs et encore moins de gens les utilisent ; elles cachent des espaces et des @ |
| `withIpLiteral()` | `ada@[192.0.2.1]`, `ada@[IPv6:2001:db8::1]` | Aucune boîte de courriel publique n'y vit |

## Les formes d'une adresse

| Membre | Donne |
|---|---|
| `localPart` | La partie avant le @, telle qu'écrite : elle est sensible à la casse |
| `domain` | Le domaine en minuscules, dans son écriture |
| `asciiDomain` | Le domaine en ASCII, pour SMTP et le DNS |
| `toString()`, `(string)` | `localPart@domain`, pour les gens |
| `toAscii()` | `localPart@asciiDomain`, pour un transport de courriel |
| `requiresSmtpUtf8()` | Si la partie avant le @ n'est pas en ASCII |
| `isIpLiteral()` | Si le domaine est une adresse IP entre crochets |
| `equals()` | La même partie avant le @ et le même domaine |

Deux adresses qui ne diffèrent que par la casse de la partie avant le @ sont des adresses différentes : la RFC 5321
laisse le choix au serveur qui reçoit. Une application qui veut un seul compte par boîte de courriel, quelle que soit
la casse, compare une clé à elle.

## Raisons

| `SyntaxError` | Quand |
|---|---|
| `Empty`, `TooLong`, `MissingAt` | Rien, plus de 254 octets, aucun @ |
| `EmptyLocalPart`, `LocalPartTooLong` | Rien avant le @, plus de 64 octets |
| `MisplacedDot` | Un point en premier, en dernier ou doublé avant le @ |
| `InvalidLocalPart` | Un caractère qu'aucune adresse ne contient, de l'UTF-8 brisé, une partie entre guillemets mal formée |
| `InternationalLocalPart` | Un caractère non ASCII que la politique ne permet pas |
| `QuotedLocalPart` | Une partie entre guillemets que la politique ne permet pas |
| `EmptyDomain`, `InvalidDomain` | Rien après le @, un nom qu'IDNA refuse ou qui a une étiquette vide |
| `DomainTooLong`, `LabelTooLong` | Plus de 253 octets, une étiquette de plus de 63, en ASCII |
| `SingleLabelDomain`, `InvalidTopLevelDomain` | Aucun point ; une dernière étiquette numérique ou d'un seul caractère |
| `IpLiteral`, `InvalidIpLiteral` | Un littéral IP que la politique ne permet pas, ou qui est mal formé |

`describe()` donne chacune en quelques mots anglais pour un journal ; un formulaire traduit plutôt la valeur du cas.
