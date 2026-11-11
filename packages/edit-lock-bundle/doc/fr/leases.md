<!-- phil-dev source-sha256=a874a150b1fac5c25b70f7e7d30b2b55f3ff8aa5837a33835fdcb3f57e5bb953 -->
# Baux

## Clés et détenteurs

Un bail porte sur une `LeaseKey` : une ressource, nommée par l'application en 1 à 255 octets sans caractère de contrôle,
et au besoin une partie, l'identifiant d'un bloc de son document — de 1 à 64 lettres, chiffres, `_` ou `-`, tels que les
écrit UJO (Universal JSON Output).

```php
LeaseKey::of('article:42');          // the whole article
LeaseKey::of('article:42', 'aB3x');  // one block of it
```

Un bail sur la ressource entière exclut les baux des autres détenteurs sur chaque partie, et un bail sur une partie
exclut le bail d'un autre détenteur sur la même partie ou sur la ressource entière (`LeaseKey::overlaps()`). Le
détenteur de l'ensemble peut écrire chaque partie (`covers()`).

Un `Holder` est un identifiant — celui d'un utilisateur — et le nom que voient les autres rédacteurs. Deux détenteurs
qui ont le même identifiant sont le même rédacteur : les deux onglets d'une même personne partagent leurs baux.

## La vie d'un bail

```php
$lease = $locks->acquire(LeaseKey::of('article:42', 'aB3x'), $holder); // LeaseHeld if someone else holds it
$locks->heartbeat($lease->key(), $holder);                              // LeaseLost if it was lost
$locks->release($lease->key(), $holder);                                // false if it was not held
$locks->leave('article:42', $holder);                                   // every lease of the holder on the resource
```

| Étape | Ce qui se passe |
|---|---|
| Prise | La politique doit laisser le détenteur modifier la ressource. Libre, ou expirée, la clé est accordée pour `seconds` ; déjà détenue par le détenteur, elle est renouvelée |
| Pulsation | Le bail du détenteur est conservé pour `seconds` de plus à partir de maintenant ; un bail expiré que personne n'a pris est conservé lui aussi |
| Expiration | Un bail que personne ne renouvelle cesse de compter à `expires_at` : le prochain détenteur qui le demande l'obtient, et la présence le laisse de côté |
| Reprise | Un détenteur que la politique autorise retire les baux d'autrui sur la clé et sur ce qui la chevauche, et prend la clé |
| Libération, départ | Le détenteur rend la clé, ou toutes les clés de la ressource |
| Purge | `purge()` supprime les lignes expirées ; appelez-la depuis une tâche planifiée, même si un bail expiré est de toute façon remplacé |

Chaque étape qui modifie un bail communique aux autres rédacteurs les baux actifs sur la ressource. Les heures sont en
UTC, à la seconde près ; elles sont comparées avec `<` et `>`, jamais avec `==`.

## La politique

```php
use Phil\EditLockBundle\Application\EditPolicy;

final readonly class WritersPolicy implements EditPolicy
{
    public function mayEdit(Holder $holder, string $resource): bool
    {
        // the holder may write this resource: they are one of its authors, a moderator…
    }

    public function mayTakeOver(Holder $holder, Lease $lease): bool
    {
        // the holder may take this lease from its holder: they own the resource, they administer the site…
    }
}
```

`mayEdit()` protège la prise, la reprise et la lecture de la présence par le point de terminaison. Elle est
interrogée de nouveau à chaque pulsation, qui libère le bail d'un détenteur qui n'en a plus le droit, et à chaque bloc
enregistré ou retiré : un rédacteur dont le droit est retiré pendant que sa page est ouverte perd le bail à la pulsation
suivante et n'écrit plus rien. `mayTakeOver()` est interrogée pour chaque bail qu'une reprise retirerait. Un refus
donne `EditRefused`.

## Concurrence

Deux requêtes peuvent demander la même clé au même moment. Chaque écriture du dépôt est une comparaison et échange : une
insertion échoue lorsque la clé est prise, un remplacement lorsque la version conservée n'est plus celle qui a été lue.
`EditLocks` relit et réessaie, trois fois, puis répond `LeaseHeld` sans bail. Avec Doctrine, la course est tranchée par
la clé primaire de la table et par un `UPDATE … WHERE version = ?` ; une insertion perdue est interceptée sans fermer le
gestionnaire d'entités.

L'exclusion entre la ressource entière et ses parties est vérifiée en lisant les baux de la ressource, puis en
écrivant ; deux détenteurs qui demandent au même instant, l'un l'ensemble et l'autre une partie, ne doivent pas obtenir
tous deux le leur. Chaque revendication — prise, reprise, pulsation — passe donc par `LeaseRepository::exclusively()`,
pendant qu'aucune autre revendication sur la même ressource ne s'exécute. Avec Doctrine, la revendication est une
transaction dont la première instruction met à jour la ligne de la ressource dans `phil_edit_lease_resource`, en
l'insérant la première fois : la base de données retient à cette instruction toute autre revendication sur la ressource
jusqu'à ce que la première soit validée, et la seconde lit alors le bail que la première a écrit. Les revendications sur
d'autres ressources n'attendent pas, sauf avec SQLite, qui ne laisse entrer qu'un rédacteur à la fois, et pour les
premières revendications de MySQL sur des ressources qui n'ont jamais fait l'objet d'un bail, que ses verrous
d'intervalle peuvent ordonner. Un interblocage, ou une attente interrompue par `innodb_lock_wait_timeout`, le
`lock_timeout` de PostgreSQL ou le délai d'occupation de SQLite, annule la revendication ; `EditLocks` réessaie, comme
après une course perdue. Lorsque la propre transaction de l'application est ouverte sur la connexion, la revendication
s'exécute dans un point de sauvegarde, et la ligne reste verrouillée jusqu'à ce que l'application valide.

## Présence

`presence('article:42')` donne les baux actifs de la ressource et de ses parties, l'ensemble d'abord, puis par partie :
`Lease::holder()`, `key()->part`, `expiresAt()`, `secondsLeftAt($now)`.
