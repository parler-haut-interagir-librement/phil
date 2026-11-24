<!-- phil-dev source-sha256=c7a1bc301992f02ae8a147952c998670c93798a694c809f7bb5d9b9b8bc96c81 -->
# Une page d'accueil

Le bundle ne tient aucun fil d'activité. Ce qu'un membre voit sur sa page d'accueil est composé par l'application à
partir de deux sources qu'elle a déjà : les sujets que le membre suit, et ses notifications.

## Les sujets qu'un membre suit

`Follows::following()` donne les suivis propres du membre une page à la fois, par type de sujet puis par identifiant,
tous niveaux compris :

```php
$subjects = [];
$after = null;

do {
    $page = $follows->following($member, $after, 200);

    foreach ($page as $follow) {
        if ($follow->level()->follows()) {
            $subjects[$follow->subject->type][] = $follow->subject->id;
        }

        $after = $follow->subject->key();
    }
} while (200 === \count($page));
```

L'application lit ensuite ses propres enregistrements de ces sujets — les derniers fils des forums, les prochains
événements des calendriers, les nouvelles annonces des catégories — avec ses propres requêtes et son propre ordre. Un
sujet que le membre ignore est laissé de côté ; l'enfant d'un parent suivi revient à l'application, qui connaît les
parents.

## Les notifications

Le centre de phil/notifications-bundle contient ce qui a atteint le membre : `follow.activity` et
`follow.activity_quiet`, une par sujet, avec le nombre d'activités que chacune représente. Ses composants Twig les
montrent sur la page d'accueil comme ailleurs.

## Voir un sujet

Quand le membre ouvre un sujet, l'application en informe le bundle, pour que la prochaine activité reparte de nouveau
sur tous les canaux :

```php
$follows->seen($member, new SubjectReference('thread', $thread->id));
```

## Ce qu'une page d'accueil ne fait jamais

Le bundle n'offre aucune suggestion tirée de qui suit qui, ne classe rien selon le nombre d'abonnés et ne bâtit aucun
profil des intérêts d'un membre : une page d'accueil composée ainsi montre ce que le membre a choisi de suivre, dans
l'ordre que l'application donne à ses propres enregistrements.
