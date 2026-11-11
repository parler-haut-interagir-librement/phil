<!-- phil-dev source-sha256=3aa6665a199439ca5f40d83c16aa640ccc9aa09840ddec701e55550b7720059c -->
# La friction

**Un verdict n'est pas un refus.** Un verdict est calculé chez le client : il peut donc être falsifié, et un faux
positif doit tout de même passer. Le résultat le plus fort d'un verdict est donc **une étape de friction de plus**.

État : `Friction` et `FrictionPolicy` sont implémentés et testés.

## `Friction`

Une énumération pure avec exactement deux cas :

| Cas | `adds()` | Sens |
|---|---|---|
| `Friction::None` | `false` | La requête se poursuit comme d'habitude |
| `Friction::Step` | `true` | La requête reçoit une étape de friction de plus |

**Il n'y a délibérément aucun cas `Refuse`**, et les tests vérifient que `Friction::cases()` vaut exactement
`[None, Step]`.

L'application donne son sens à `Step` sur chaque surface, par exemple :

- une étape de recul de plus sur un limiteur de débit d'inscription ;
- un défi d'authentification plus fort imposé, ou une étape de plus sur un limiteur de tentatives de paiement ;
- un accès que la mesure d'audience ne compte pas.

**L'étape n'enregistre jamais pourquoi elle a été ajoutée.** Elle se fond dans l'état de ce qu'elle ralentit (le
compteur d'un limiteur, l'exigence d'un défi), et cet état ne garde pas le verdict.

## `FrictionPolicy`

Traduit un verdict en friction. La correspondance est fixe par conception, à un choix près :

| Verdict | `robotOnly()` | `robotOrUnknown()` |
|---|---|---|
| `Human` | `None` | `None` |
| `Robot` | `Step` | `Step` |
| `Unknown` | `None` | `Step` |

```php
$policy = FrictionPolicy::robotOrUnknown();
$policy->unknownAddsFriction;              // true
$policy->frictionFor(Verdict::Unknown);    // Friction::Step

FrictionPolicy::robotOnly()->frictionFor(Verdict::Unknown); // Friction::None
```

Le constructeur est privé : les deux constructeurs nommés sont les seules politiques.

### Choisir, surface par surface

| Politique | Coût | À utiliser quand |
|---|---|---|
| `robotOnly()` | Un client peut éviter la détection en n'envoyant rien | La friction coûte cher à une personne (un paiement), ou le code du navigateur n'est pas encore déployé sur la surface |
| `robotOrUnknown()` | Ralentit les clients qui n'ont jamais exécuté le script de détection, et aussi les personnes dont le navigateur bloque les scripts | L'abus coûte peu et la friction est légère (un recul à l'inscription) |

Sur une surface où le contrôleur `verdict` n'est pas placé, chaque verdict est `Unknown` : `robotOrUnknown()` ajoute
alors une étape à chaque requête de cette surface.

## Ce que la politique ne décide pas

**Un navigateur qui résiste à la prise d'empreinte n'est pas un robot.** Des signaux aléatoires ou vidés, tels que les
produisent les navigateurs axés sur la vie privée, ne doivent pas déclencher un détecteur. C'est une propriété des
détecteurs du navigateur, pas de cette politique : quand un verdict atteint le serveur, il est déjà `robot` ou `human`
([browser-signals.md](browser-signals.md)).

Les robots d'indexation déclarés (les bons robots qui s'annoncent dans leur agent utilisateur) relèvent du serveur, qui
en décide à partir de l'agent utilisateur avec une liste de robots comme `jaybizzle/crawler-detect`, et non de ce
bundle.
