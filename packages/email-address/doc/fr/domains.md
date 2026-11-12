<!-- phil-dev source-sha256=f594ec53462c012b4df1fd853c2cf982ec120cf763981c40a38321dd94031974 -->
# Domaines

## Domaines de premier niveau

`TopLevelDomains` contient les domaines de premier niveau que délègue la zone racine — `com`, `ca`, `xn--p1ai` — : un
domaine sous tout autre ne reçoit aucun courriel, et `ada@example.con` ou `ada@shop.local` est donc refusée avant
d'interroger le DNS.

| Méthode | Donne |
|---|---|
| `bundled()` | La liste de l'IANA importée dans le `data/` de la bibliothèque |
| `of(iterable $labels)` | La liste propre à l'application, en ASCII : des lettres, ou une forme `xn--` qu'accepte IDNA, en toute casse |
| `contains(EmailAddress\|string)` | Si la dernière étiquette du domaine de l'adresse, ou d'un domaine ASCII, est dans la liste |
| `isEmpty()`, `count()` | Si la liste ne contient aucun domaine, et combien |

Une étiquette qui ne peut pas être un domaine de premier niveau lève `Exception\InvalidDomainList`. Une liste vide ne
vérifie rien : `EmailValidator` saute son étape. Les noms que réservent la RFC 2606 et la RFC 6761 — `example`,
`test`, `invalid`, `localhost` — ne sont pas délégués : un test qui écrit des adresses sous eux donne sa propre liste
par `of()`, ou désactive l'étape.

## Domaines refusés et seuls permis

`DomainRules::of(iterable $denied = [], iterable $allowedOnly = [])` contient les domaines qu'une application refuse,
et les seuls domaines qu'elle accepte quand elle en nomme : une liste interne réservée au domaine de l'organisation,
une inscription du personnel. `withDenied()` et `withAllowedOnly()` y ajoutent. Un domaine vaut pour ses
sous-domaines : refuser `example.com` refuse `mail.example.com`. Un domaine refusé est refusé même s'il est aussi
permis.

`check(EmailAddress): ?Problem` donne `Problem::DeniedDomain`, `Problem::DomainNotAllowed` ou `null`. Les domaines
peuvent être écrits dans n'importe quelle écriture et n'importe quelle casse ; un domaine qui n'est pas un nom de
domaine lève `Exception\InvalidDomainList`.

## Domaines jetables

`DisposableDomains` contient les domaines des boîtes de courriel jetables, qui reçoivent le courriel quelques minutes
puis disparaissent, et leurs sous-domaines.

| Méthode | Donne |
|---|---|
| `bundled()` | La liste importée dans le `data/` de la bibliothèque |
| `of(iterable $domains)` | La liste propre à l'application |
| `merge(DisposableDomains)` | Les deux listes |
| `contains(EmailAddress\|string)` | Si le domaine de l'adresse, ou un domaine ASCII, ou l'un de ses parents, figure dans la liste |
| `count()` | Le nombre de domaines de la liste |

`bundled()` lit un fichier PHP qui contient un tableau trié : OPcache le garde en mémoire partagée, et la recherche
parcourt les étiquettes du domaine, avec au plus une recherche par empreinte par étiquette.

## Les listes fournies et leur importation

Chaque liste est un fichier PHP de `data/` ; `data/VERSION` contient une ligne par liste — son fichier, sa source et
la version importée — et chaque importation réécrit sa ligne et garde les autres. Chaque fichier est écrit à côté de
sa place puis déplacé, pour qu'aucun lecteur ne voie un demi-fichier. Les données sont importées, jamais modifiées à
la main, et importées de nouveau à chaque version de la bibliothèque.

### Domaines jetables

`data/disposable-domains.php` est généré à partir du `disposable_email_blocklist.conf` de
<https://github.com/disposable-email-domains/disposable-email-domains>, dont les contributeurs l'ont versé au domaine
public sous CC0 1.0 Universal : il ne porte aucune condition, et son avis est gardé dans `data/LICENSE` et `NOTICE`.

Pour importer une version :

```sh
php bin/import-disposable-domains.php path/to/disposable_email_blocklist.conf 2026-11-08
```

`Data\DisposableDomainsImporter` lit chaque ligne (un domaine par ligne, `#` commençant un commentaire) et vérifie
chacune comme nom de domaine avant que rien ne soit écrit : une liste qui a une mauvaise ligne ne change rien et nomme
la ligne. Les domaines sont écrits en ASCII, sans doublons et triés, pour que deux importations d'une même version
donnent le même fichier. La version, le commit ou la date donnés, ne contient pas d'espace. Vérifiez à chaque
importation que le `LICENSE.txt` de la source dit toujours CC0. Un domaine que l'application veut ajouter ou retirer
passe par `DisposableDomains::of()` et `merge()`, ou par `DomainRules`.

### Domaines de premier niveau

`data/top-level-domains.php` est généré à partir de <https://data.iana.org/TLD/tlds-alpha-by-domain.txt>, la liste de
la zone racine que publie l'Internet Assigned Numbers Authority ; `NOTICE` et `data/LICENSE` la nomment.

Pour l'importer :

```sh
php bin/import-top-level-domains.php path/to/tlds-alpha-by-domain.txt
```

`Data\TopLevelDomainsImporter` prend la version de la première ligne de la liste, `# Version 2026111000, Last Updated …`,
et refuse une liste qui ne l'a pas. Il vérifie chaque autre ligne comme une étiquette de premier niveau — des lettres,
ou une étiquette A dont la forme Unicode est acceptée par IDNA — avant que rien ne soit écrit, et les écrit en
minuscules, sans doublons et triées. Un domaine que l'application veut accepter hors de la liste, un domaine interne,
passe par `TopLevelDomains::of()`.
