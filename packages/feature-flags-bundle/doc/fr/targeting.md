<!-- phil-dev source-sha256=b20f91f39c30962164bc84add54a1dce05682a9a0335152495f37bfb2724a278 -->
# Ciblage

Un indicateur actif avec des règles est activé pour un contexte lorsque **n'importe laquelle** de ses règles correspond.
Les règles sont essayées dans leur ordre de déclaration, et la première qui correspond donne la raison de l'évaluation.

## Attributs

Une règle lit un attribut de l'`EvaluationContext` (`TargetingAttribute`) :

| Attribut | Valeur typique |
|---|---|
| `tenant` | Le compte ou l'organisation pour lequel agit la requête. |
| `project` | Le projet à l'intérieur d'un locataire. |
| `subject` | La personne ou le client qui fait la requête. |

C'est la règle, et non le contexte, qui dit de quel attribut elle parle : un déploiement progressif par locataire garde
tous les membres d'un locataire du même côté de l'indicateur ; un déploiement progressif par sujet les sépare.

**Une règle qui ne peut pas lire son attribut ne correspond pas.** Un contexte sans locataire ne correspond jamais à une
règle de locataire : un indicateur reste donc fermé pour un contexte qu'il ne peut pas placer. Avec
`AnonymousContextResolver`, toutes les règles échouent.

## Appartenance : `MemberOf`

```yaml
rules:
    - { attribute: tenant, members: [tenant-a, tenant-b] }
```

Correspond lorsque la valeur de l'attribut est l'un des identifiants listés (comparaison de chaînes exacte, sensible à
la casse). L'ensemble exige au moins un membre et aucun membre vide : un ensemble vide équivaudrait à un indicateur
inactif. Raison en cas de correspondance : `TARGETING_MATCH`.

## Déploiement progressif par pourcentage : `PercentageRollout`

```yaml
rules:
    - { attribute: tenant, percentage: 20 }
```

Correspond à une part stable des valeurs de l'attribut. Le pourcentage est un nombre entier de 0 à 100 (`Percentage`) :
0 n'admet personne, 100 admet chaque contexte qui a l'attribut. Raison en cas de correspondance : `SPLIT`.

Une règle a soit `members`, soit `percentage`, jamais les deux.

### Le compartiment stable

Chaque valeur tombe dans un compartiment de 0 à 99 (`Bucket::of()`) :

```text
bucket = murmur3a("<flag>:<value>") mod 100
```

`murmur3a` est le MurmurHash3 de 32 bits que PHP fournit dans `ext/hash`, lu comme un entier non signé ; l'entrée a la
forme `group:identifier` du déploiement graduel des SDK d'Unleash. Une valeur fait partie du déploiement progressif
lorsque son compartiment est **strictement inférieur** au pourcentage (`Percentage::admits()`).

Ce qui en découle :

- **Stable.** La même valeur reçoit le même compartiment à chaque requête et sur chaque serveur : un déploiement
  progressif ne vacille donc pas.
- **Monotone.** Augmenter un pourcentage ne fait jamais qu'ajouter des valeurs : un locataire admis dans un déploiement
  à 20 % le reste à 50 %.
- **Salé par le nom de l'indicateur.** Deux indicateurs placent les mêmes locataires de façon indépendante : les
  locataires admis en premier dans un déploiement ne sont donc pas les premiers dans tous. Renommer un indicateur
  rebrasse son déploiement progressif.
- **Presque uniforme.** `2^32 mod 100 = 96` : les compartiments 0 à 95 sont donc plus probables que 96 à 99 d'environ
  une part sur 43 millions, bien en deçà de ce qu'un déploiement progressif peut observer.
- Une valeur vide est refusée (toutes les clés vides partageraient un même compartiment) ; le contexte refuse déjà les
  attributs vides.

### Une porte à sens unique

**Changer la fonction de compartiment rebrasse d'un coup tous les déploiements progressifs en cours.** Les tests
unitaires fixent des valeurs connues (`BucketTest`), par exemple :

| Entrée | Compartiment |
|---|---|
| `new-checkout:tenant-1` | 81 |
| `new-checkout:tenant-2` | 62 |
| `other-flag:tenant-1` | 49 |
| `new-checkout:42` | 73 |

Un changement qui déplace l'une d'elles est délibéré ou constitue un bogue, jamais un accident.

## Combiner des règles

```yaml
flags:
    new-checkout:
        active: true
        rules:
            - { attribute: tenant, members: [tenant-a] }      # the pilot tenant, always
            - { attribute: tenant, percentage: 20 }           # plus a fifth of the others
```

`tenant-a` est activé avec la raison `TARGETING_MATCH` ; un autre locataire est activé avec la raison `SPLIT` s'il tombe
dans les 20 %, sinon désactivé avec la raison `DEFAULT`. Un contexte sans locataire est désactivé (`DEFAULT`).

Pour désactiver le déploiement progressif pour tout le monde, désactivez l'indicateur (une bascule) ; les règles
restent, et la réactivation ramène les mêmes 20 % ([toggles-and-edits.md](toggles-and-edits.md)).

## Règles personnalisées

`Rule` est une interface (`matches(FlagName, EvaluationContext): bool` et `matchReason(): EvaluationReason`), et
`FlagDefinition` accepte n'importe quelle implémentation. Un fournisseur de l'application peut construire des
définitions avec ses propres règles. Elles ne peuvent toutefois pas être écrites dans la configuration ni dans le
magasin en base de données : `FlagDefinitionFormat` ne lit et n'écrit que `MemberOf` et `PercentageRollout`, et refuse
d'écrire toute autre règle ([extension-points.md](extension-points.md#rule)).
