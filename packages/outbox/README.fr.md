<!-- phil-dev source-sha256=edaa1ee4f3c7f4c1229d89028c593bf24f1cc49f416ff725f05aa545b4093957 -->
# phil/outbox

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/outbox/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/outbox)

**Ne perdez plus un événement après une écriture en base : l'événement est enregistré dans la même transaction que la
donnée, puis livré à ses écouteurs, avec de nouveaux essais jusqu'à ce qu'ils l'acceptent.**

Une bibliothèque qui écrit son état puis répartit un événement perd l'événement quand l'écouteur, le répartiteur ou le
processus échoue entre les deux : rien n'informe de nouveau les écouteurs. Avec phil/outbox, la bibliothèque enregistre
l'événement dans la même transaction que son écriture, et un relais informe ensuite les écouteurs, jusqu'à ce qu'ils
l'acceptent.

phil/outbox est une bibliothèque PHP simple, bâtie sur les interfaces PSR-14 et PSR-20, sans dépendance à Symfony
Messenger. [phil/outbox-bundle](https://github.com/parler-haut-interagir-librement/outbox-bundle) conserve les
enregistrements dans une table de la connexion Doctrine DBAL de l'application et exécute le relais dans Symfony.

## État

| Partie | État |
|---|---|
| `Outbox::record()` sur un entrepôt, la liste d'autorisation des codecs, la charge utile bornée d'identifiants | Implémentée et testée |
| Le relais : baux, ordre par clé, recul, enregistrements morts, enregistrements écartés, délai de grâce des types inconnus, livraison au moins une fois | Implémenté et testé |
| L'entretien : enregistrements morts inspectés, essayés de nouveau ou écartés, rétention des enregistrements livrés | Implémenté et testé |
| L'entrepôt en mémoire, et les tests de contrat que tout entrepôt réussit | Implémentés et testés |
| La cible de fuzzing du lecteur de charge utile, le banc d'essai d'un lot de relais | Implémentés |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Enregistrement](doc/fr/recording.md) | `Outbox::record()` : types d'événement, charges utiles, clés d'ordre et d'idempotence, la transaction. |
| [Codecs](doc/fr/codecs.md) | Comment un enregistrement redevient un événement, et le `RecordedEvent` générique. |
| [Relais](doc/fr/relaying.md) | Le relais : baux, ordre par clé, nouvelles tentatives, enregistrements morts, et pourquoi les écouteurs sont idempotents. |
| [Entretien](doc/fr/housekeeping.md) | Les enregistrements morts, la rétention des enregistrements livrés, les comptes. |
| [Entrepôts](doc/fr/stores.md) | Le contrat d'un entrepôt, l'entrepôt en mémoire, et les tests de contrat d'un adaptateur. |
| [Guide d'adoption](doc/fr/adoption.md) | Faire passer à l'outbox une bibliothèque qui enregistre puis répartit, avec un exemple complet, et le tenir à l'écart d'un effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, sa cible de fuzzing et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/outbox
```

```php
use Phil\Outbox\Codecs;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\StoreOutbox;

$store = new InMemoryOutboxStore();
$codecs = new Codecs([new RecordedEventCodec(['quiz.completed'])]);
$outbox = new StoreOutbox($store, $codecs);

// In the transaction of the write it describes:
$outbox->record('quiz.completed', ['quiz' => 'q-42', 'member' => 7], orderingKey: 'quiz:q-42');

// Later, in a worker:
$report = new Relay($store, $codecs, $dispatcher)->relay();
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `mbstring` et `sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
