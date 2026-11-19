<!-- phil-dev source-sha256=53d8e35d88e0c4c3e6369445663dee801aea6ffad23a2491d6ac848eae9f9ca1 -->
# Lots de publication

Un `PublicationBatch` regroupe des éléments publiés ensemble : un lancement en deux langues, les parties d'une série,
une page et les billets qui l'annoncent. Il a un identifiant, un titre, ses éléments dans leur ordre — au moins un,
chacun une seule fois —, et un moment en UTC, ou aucun quand il est appliqué à la main.

```php
$batch = new PublicationBatch('launch', 'Launch', ['launch-fr', 'launch-en'], new DateTimeImmutable('2026-12-01 14:00Z'));
$manual = new PublicationBatch('press', 'Press kit', ['kit', 'release']);
```

## Une entrée, un moment

Un élément d'un lot prend le moment du lot : les vues montrent le lot comme une seule entrée qui porte ses éléments, la
revue le compte comme une seule publication, et l'export iCalendar l'écrit comme un seul événement. Un élément d'un lot
manuel n'a aucun moment tant que le lot n'est pas appliqué. Un élément planifié sans moment donné prend celui de son
lot.

## Appliqué en entier, ou pas du tout

`applyBatch($id, $now)` publie chaque élément du lot à `$now`. Quand l'un d'eux ne peut pas être publié — c'est encore
un brouillon, ou il a été retiré —, aucun ne l'est : `BatchFailed` nomme le lot et l'élément, porte la transition
refusée, et le calendrier reste tel qu'il était. Un élément d'un lot n'est jamais publié seul : `transition()` le
refuse.

```php
try {
    $calendar = $calendar->applyBatch('launch', $now);
} catch (BatchFailed $failed) {
    $failed->item;          // 'launch-en'
    $failed->getPrevious(); // the InvalidTransition it refused
}
```

`dueBatches($now)` liste les lots dont le moment est venu et qui contiennent encore un élément à publier, dans l'ordre
de leurs moments ; `manualBatches()` liste ceux qui sont appliqués à la main et qui en contiennent encore.

## Avec un planificateur

Le calendrier décide ce qu'un lot publie et refuse un lot qui ne peut pas être publié en entier ; publier les contenus
eux-mêmes est le travail de l'application. Le contrat qu'elle exécute avec son planificateur est le suivant :

1. Au moment du lot, ou quand un éditeur applique un lot manuel, appelez `applyBatch()` sur le calendrier stocké.
2. Dans une seule transaction de base de données, appliquez la publication de chaque contenu du lot et stockez les
   éléments que contient le nouveau calendrier.
3. Si l'un d'eux échoue, annulez la transaction : aucun contenu n'est publié, et le calendrier stocké reste inchangé.

Comme le calendrier est immuable, l'étape 1 n'a aucun effet tant que l'étape 2 n'a pas stocké son résultat : un échec
à n'importe quelle étape ne laisse rien d'appliqué en partie.
