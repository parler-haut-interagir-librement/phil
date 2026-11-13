<!-- phil-dev source-sha256=11bdb43eb6fba8a2bc68aa3c6c408c43191d40d2762cfd34ecfb40d9a7ebb118 -->
# Lots

## Créer un lot

```php
$created = $codes->create('Album cards, autumn fair', 500, new DateTimeImmutable('2027-12-31 23:59:59'));
$created->batch->id;     // 32 hexadecimal digits, what the application unlocks from
$created->batch->name;   // "Album cards, autumn fair"
```

`create()` prend un nom de 1 à 120 caractères, sans espaces aux extrémités, une taille de 1 à 1 000 000 et une
expiration facultative postérieure à maintenant. Elle tire des codes distincts, calcule l'empreinte de chacun avec la
clé active et remet le lot et les empreintes au magasin en une seule transaction. Un lot de 100 000 codes prend environ
une demi-seconde ; voir [Tests](testing.md).

Le `Batch` garde son identifiant, son nom, sa taille, sa création, son expiration et sa révocation. Ce qu'un code
débloque — un fichier, un album, un billet — appartient à l'application : elle garde l'identifiant du lot à côté du
produit.

## Les codes, une seule fois

Le `CreatedBatch` contient les codes en clair pour un seul usage. Écrivez-les en CSV :

```php
use Phil\AccessCodes\RedemptionLinks;
use Phil\Url\Url;

$created->writeCsv(new SplFileObject('/secure/cards.csv', 'x'), new RedemptionLinks(Url::fromString('https://shop.example/redeem')));
```

```text
code,link
7KQ2-M9XD-H4TB-H,https://shop.example/redeem?code=7KQ2M9XDH4TBH
...
```

ou prenez-les sous forme de liste, pour un gabarit d'impression ou un publipostage :

```php
$codes = $created->takeCodes();               // ["7KQ2-M9XD-H4TB-H", ...]
$canonical = $created->takeCodes(false);      // ["7KQ2M9XDH4TBH", ...]
```

Le second appel de l'une ou l'autre méthode lève `CodesAlreadyTaken`, et `hasCodes()` dit si les codes sont encore là.
Rien d'autre ne peut les donner : le magasin ne conserve que leurs empreintes. Écrivez le CSV dans un fichier avant de
l'envoyer où que ce soit, pour qu'un téléversement échoué ne fasse pas perdre le lot ; un lot dont les codes ont été
perdus est révoqué, puis créé de nouveau.

L'objet refuse d'être sérialisé ou cloné et affiche `"500 codes, hidden"` à `var_dump()`, pour qu'un cache, une session,
une file d'attente ou un journal ne garde pas les codes par accident.

## Les liens préremplis

`RedemptionLinks` construit l'adresse de la page d'échange avec un code rempli, sur `phil/url` :

```php
$links = new RedemptionLinks(Url::fromString('https://shop.example/redeem?from=card'), 'code');
$links->for('7KQ2M9XDH4TBH');   // https://shop.example/redeem?from=card&code=7KQ2M9XDH4TBH
```

Le paramètre est composé de 1 à 32 lettres minuscules, chiffres et soulignés ; la page n'a pas de fragment. Ouvrir le
lien remplit le formulaire ; la personne confirme tout de même. Servez la page avec `Referrer-Policy: no-referrer`, pour
qu'un lien sortant de la page ne transmette pas le code à un autre site.

## Révoquer et compter

```php
$codes->revoke($batchId);   // the number of codes revoked: those not yet redeemed
$codes->counts($batchId);   // BatchCounts: total, redeemed, revoked, unused()
$codes->batch($batchId);    // Batch, with revokedAt
```

Un lot révoqué n'échange plus rien ; les codes déjà échangés restent échangés. Révoquer deux fois ne révoque rien la
seconde fois. Un lot inconnu lève `UnknownBatch`.
