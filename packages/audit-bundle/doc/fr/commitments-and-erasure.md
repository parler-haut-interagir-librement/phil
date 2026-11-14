<!-- phil-dev source-sha256=5b554fafde88146f33c47dcc578d853bd667fda62ee0e13bf65a6c673c5e0446 -->
# Engagements salés et effacement

**État : `Salt` et `Commitment` sont implémentés et testés.** L'opération d'effacement sur les lignes d'audit n'est pas
implémentée; cette page décrit le contrat que lui donnent les deux objets-valeurs.

## Pourquoi des engagements

Une piste d'audit doit révéler les falsifications, et les renseignements personnels doivent pouvoir être effacés.
Intégrer le texte en clair dans la chaîne rend les deux incompatibles : supprimer la valeur brise l'empreinte. À la
place, chaque champ personnel entre dans la chaîne seulement par un **engagement salé propre à la ligne et au champ**.
L'effacement supprime le texte en clair **et le sel** et garde l'engagement : l'empreinte de la ligne ne bouge pas et
chaque ancre reste vérifiable.

## La construction

```text
commitment = HMAC-SHA256(key = salt, message = value)      // raw 32 bytes
```

- **HMAC, et non `H(salt ‖ value)`.** Une concaténation passée dans une fonction de hachage est une construction
  maison, exposée à l'extension de longueur; HMAC est la construction standard que personne n'a à défendre.
- **Le sel est la clé du HMAC** : au moins 128 bits, tirés d'un générateur cryptographique, propres à une ligne et à un
  champ. C'est lui qui rend l'engagement irréversible une fois supprimé : un sel court ou prévisible transforme un
  engagement sur une adresse de courriel en attaque par dictionnaire.
- **Sortie binaire**, exactement 32 octets.

La construction est une porte à sens unique du même genre que la sérialisation canonique : elle se ferme à la première
ligne qui porte un engagement. Changer ensuite l'algorithme, les rôles de clé et de message ou l'encodage de la sortie
rend invérifiable chaque engagement existant.

## `Salt`

`Phil\AuditBundle\Domain\Commitment\Salt` : `final readonly`, `$bytes` public.

| Membre | Comportement |
|---|---|
| `MIN_BYTES = 16` | 128 bits |
| `static generate(): self` | `random_bytes(16)` : exactement le minimum, tiré du générateur cryptographique |
| `static fromBytes(string $bytes): self` | Relit un sel stocké; refuse moins de 16 octets avec `InvalidValue` (`A salt is at least 16 bytes; this one is 15.`). Les sels plus longs sont acceptés. |

## `Commitment`

`Phil\AuditBundle\Domain\Commitment\Commitment` : `final readonly`, `$bytes` public.

| Membre | Comportement |
|---|---|
| `static of(Salt $salt, string $value): self` | `hash_hmac('sha256', $value, $salt->bytes, true)` |
| `static fromBytes(string $bytes): self` | Relit un engagement stocké; refuse tout ce qui ne fait pas 32 octets avec `InvalidValue` (`A commitment is 32 bytes; this one is 31.`). Sans ce refus, une colonne tronquée ou une autre construction ferait échouer `matches()` sans dire pourquoi |
| `matches(Salt $salt, string $value): bool` | Recalcule et compare en temps constant (`hash_equals`) |
| `equals(Commitment $other): bool` | Comparaison en temps constant de deux engagements |

La comparaison en temps constant compte : une comparaison qui s'arrête au premier octet différent permet à un
attaquant de mesurer, octet par octet, à quel point une valeur devinée est proche.

La construction est fixée par un vecteur publié dans les tests : le cas de test 1 de la RFC 4231 (une clé de 20 octets
de `0x0b`, le message `Hi There`).

## Utilisation

Écrire une ligne :

```php
use Phil\AuditBundle\Domain\Commitment\Commitment;
use Phil\AuditBundle\Domain\Commitment\Salt;

$salt = Salt::generate();
$commitment = Commitment::of($salt, $email);

// Store, for this row and this field:
//   plaintext  -> $email                (erasable)
//   salt       -> $salt->bytes          (erasable, 16 bytes)
//   commitment -> $commitment->bytes    (kept forever, 32 bytes, part of the hashed fields)
```

Vérifier qu'un texte en clair stocké n'a pas été modifié :

```php
$stored = Commitment::fromBytes($row['email_commitment']);
$ok = $stored->matches(Salt::fromBytes($row['email_salt']), $row['email']);
```

Stockez les sels et les engagements dans des colonnes binaires (`bytea`, `BINARY`/`VARBINARY`), ou encodez-les
vous-même; les objets-valeurs contiennent des octets bruts.

## Contrat d'effacement

Pour effacer un champ personnel d'une ligne :

1. supprimez le texte en clair;
2. supprimez le sel;
3. gardez l'engagement intact;
4. affichez le champ comme `[erased]` à la lecture de la ligne.

Après l'étape 2, l'engagement ne peut plus être vérifié contre aucune valeur candidate : un dictionnaire ne peut donc
pas le défaire, et l'empreinte de la ligne, calculée sur l'engagement, reste la même. Un champ de texte libre écrit par
une personne (un motif, un commentaire) ne peut pas être séparé en parties personnelles et non personnelles : il est
caviardé en entier.

Quels champs sont personnels, et quand les effacer, relèvent des décisions de l'application.

## Erreurs

Les deux objets-valeurs lèvent `Phil\DddBundle\Domain\Exception\InvalidValue` (une `InvalidArgumentException` qui
implémente `Phil\DddBundle\Domain\Exception\DomainError`).
