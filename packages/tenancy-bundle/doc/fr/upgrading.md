<!-- phil-dev source-sha256=afbd7c243b7b0c2cb6d18995b892c5c59485ef48b03c23f65605d9db4f8dd245 -->
# Mise à niveau

## De la 0.1 à la 0.2

La 0.2 ferme deux chemins entre locataires dans une base partagée : la table d'identité de Doctrine, qui répondait à
`find()`, à `getReference()` et aux associations paresseuses avant le filtre, et un `flush` qui écrivait l'entité d'un
autre locataire. Rien ne change dans la configuration, mais le code qui change de locataire au sein d'une même unité de
travail se comporte autrement.

### Faire `flush()` avant de changer de locataire

Un changement de locataire — `TenantContext::enter()`, `leave()` ou `within()`, ou la requête, le message ou la
commande qui entre dans son locataire — détache les entités liées à un locataire de chaque gestionnaire d'entités dont
le filtre est actif. La modification d'une entité chargée n'est planifiée qu'au `flush` : si le locataire change
avant, l'entité est détachée et sa modification est perdue, sans erreur.

```php
// 0.1: the change was written by the flush that came later, under whatever tenant was active.
$order->ship();
$context->within($wholesale, $work);
$entityManager->flush();

// 0.2: flush the tenant's work before leaving it.
$order->ship();
$entityManager->flush();
$context->within($wholesale, $work);
```

Une entité planifiée pour insertion (`persist()` d'une nouvelle entité) ou pour suppression (`remove()`) n'est pas
détachée : son écriture attend le prochain `flush` fait pendant que son propre locataire est actif.

### Relire ses entités après `within()`

Une instance chargée avant `within()` n'est plus gérée une fois le travail revenu au locataire d'avant. Relisez-la par
son dépôt ou par `find()` : une modification de l'ancienne instance n'est pas suivie.

```php
$order = $entityManager->find(Order::class, 7);
$context->within($wholesale, $work);
$order = $entityManager->find(Order::class, 7);   // managed again, loaded under the tenant before
```

Il en va de même dans le travail : une entité du locataire extérieur n'est pas trouvée depuis le locataire intérieur,
et une entité chargée pendant le travail est détachée quand il se termine.

### `CrossTenantWrite`

`TenantWriteGuard` refuse un `flush` qui insérerait, modifierait ou supprimerait une entité liée à un autre locataire
que celui réglé sur le filtre, et lève `Phil\TenancyBundle\Infrastructure\Exception\CrossTenantWrite` ; rien n'est
écrit. L'exception révèle du code qui règle la référence du locataire d'une nouvelle entité ailleurs que depuis le
locataire résolu, ou qui écrit une insertion en attente après un changement de locataire.

- Réglez le locataire d'une nouvelle entité depuis le locataire actif, `TenantContext::require()` ou
  `#[CurrentTenant]`.
- Écrivez les modifications de chaque locataire pendant qu'il est actif, comme plus haut.
- Le travail qui écrit exprès entre locataires — une migration, une importation, une commande d'administration —
  tourne sans locataire, ou avec le filtre désactivé ou suspendu (voir
  [Choix de conception](doctrine-filter.md#choix-de-conception)) ; la garde le laisse écrire.

Une unité de travail sans locataire, comme une fixture ou une commande lancée sans `--tenant`, écrit comme avant.

### Activations de l'application

`TenantContext::enter()` désactive maintenant chaque activation quand l'une d'elles échoue, puis relance l'échec :
l'unité de travail reste sans locataire plutôt qu'à moitié dans le nouveau. Une activation propre à l'application doit
accepter `deactivate()` après un `activate()` qui a échoué, ou qui n'a jamais été appelé.
