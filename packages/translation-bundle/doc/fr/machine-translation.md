<!-- phil-dev source-sha256=2a05e7d45eda7496879b64b54e380bd6771ffaeb39c9cb2e7e980e9ca20c2ceb -->
# Traduction automatique

La traduction automatique propose des cibles pour les segments non traduits d'un document. Elle est désactivée par
défaut, et ses cibles ne sont jamais plus que des propositions : chacune est `suggested`, signalée à l'écran comme
produite par une machine, jamais apprise par la mémoire avant qu'une personne la traduise ou la révise, et elle ne
crédite personne.

## Le port

Un service de traduction automatique s'atteint par un adaptateur qui porte son nom et qui implémente
`Phil\TranslationBundle\Application\Contract\MachineTranslator` :

| Méthode | Contrat |
|---|---|
| `name(): string` | Le nom du service, affiché dans les erreurs |
| `translate(list<string> $sources, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): list<string>` | Une cible par source, en HTML en ligne, dans le même ordre |

Le bundle ne fournit ni adaptateur ni SDK : l'application écrit l'adaptateur du service qu'elle a choisi, sur le client
HTTP de Symfony, et le nomme dans la configuration.

```yaml
phil_translation:
    machine_translation:
        enabled: true
        translator: App\Translation\SomeServiceTranslator
```

`enabled: true` sans `translator` arrête le conteneur avec un message ; un `translator` sans `enabled: true` reste
désactivé.

## Traduire

```php
$count = $translations->machineTranslate('article:42', LanguageTag::of('fr'));
```

Les segments non traduits du document préparé sont envoyés par lots de 50 (`Translations::MACHINE_BATCH`). Chaque cible
qui contient des mots, en UTF-8 valide, est proposée (`suggested`) et la ligne est signalée comme produite par une
machine (`SegmentRecord::isMachine()`) ; une cible sans mots est omise. La méthode donne le nombre de segments à qui
l'on a proposé une cible.

`MachineTranslationFailed` est lancée quand la traduction automatique est désactivée, quand le traducteur lance une
exception (conservée comme exception précédente) ou quand il ne répond pas une cible par source ; rien n'est alors
écrit. `NotFound` est lancée pour un document qui n'est pas préparé dans la langue. `canMachineTranslate()` indique si
un traducteur est configuré.

## À l'écran

Quand un traducteur est configuré, la page montre un bouton qui propose des traductions automatiques pour les segments
non traduits (`POST /{document}/{language}/machine`), puis indique combien ont été proposées. Chaque cible venue de la
machine et que personne n'a réécrite porte la marque `machine translation`. Une défaillance du service répond `502` avec
un message qui lui est propre, le même quoi que le service ait répondu : ce que dit l'exception d'un adaptateur (une
adresse, un quota, une clé) reste hors de la page, et reste dans la `MachineTranslationFailed` qu'elle chaîne, pour les
journaux de l'application. Sans traducteur, la route répond `404`.
