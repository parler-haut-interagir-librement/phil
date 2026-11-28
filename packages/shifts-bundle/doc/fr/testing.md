<!-- phil-dev source-sha256=ed198b65315883068671aa4337b23243fb62e6631f950f9a144f7b7501feeb4e -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Exécute |
|---|---|
| `tests/Unit` | La configuration et les services enregistrés, les dépôts sur SQLite en mémoire, `Rosters`, `SwapBoard`, le *handler* de Messenger, le formulaire, les composants, la commande, le voter, la garde CSRF et les demandes de vie privée. |
| `tests/Functional` | Le bundle dans une petite application — FrameworkBundle avec Forms et Messenger, DoctrineBundle avec l'ORM sur SQLite, SecurityBundle, Twig et ses composants — et ses pages envoyées comme les envoie un navigateur sans JavaScript. |

`tests/Fixtures/Stage` construit les services sur SQLite en mémoire avec une horloge que les tests déplacent et un
répartiteur qui garde les événements ; `RacingRosterRepository` et `RacingSwapRepository` perdent leurs premières
écritures, comme si une autre requête écrivait entre chaque lecture et chaque écriture. Dans le monodépôt, Infection
maintient le bundle à 100 % des mutants couverts.

L'application fonctionnelle garde son cache et son fichier SQLite sous le répertoire temporaire du système, un par copie
de travail.

## Tester les quarts d'une application

`Rosters` et `SwapBoard` sont des services du conteneur ; un test déplace l'horloge et lit le magasin :

```php
$rosters = self::getContainer()->get(Rosters::class);
$rosters->plan($shift);
$rosters->signUp($shift->id, 'ada', []);

self::assertSame(PlaceStatus::Confirmed, $rosters->find($shift->id)?->statusOf('ada'));
```
