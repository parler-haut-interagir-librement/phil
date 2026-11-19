<!-- phil-dev source-sha256=ed5c8deef2ecf97fd05633e25ac283e50ad8052b6155c28d2e77710d413ab5cd -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Implémentés par l'application

| Port | Ce qu'il fournit |
|---|---|
| `Contract\TransitionReader` | Les transitions prévues ailleurs entre deux moments, montrées à côté des éléments ; `NoTransitions` par défaut |

## Configurés par l'application

| Type | Ce qu'il règle |
|---|---|
| `Review\ReviewRules` | L'espacement minimal entre les publications et la plus longue période permise sans aucune |
| `ICalendar\ICalendarWriter` | Le domaine des identifiants d'événement, et le nom de chaque flux |
| `View\CalendarView::week()` | Le premier jour de la semaine |
| `Filter` | Les auteurs, sections et états qu'une vue montre |

## Sur quoi on peut compter

- Le calendrier, ses éléments et ses lots sont immuables : chaque changement donne une nouvelle valeur, et un lot est
  appliqué en entier ou pas du tout.
- Les moments sont gardés en UTC ; les vues les découpent dans le fuseau horaire qu'on leur donne.
- Un auteur est un identifiant stable, jamais une adresse courriel.
- `CalendarView`, `Review` et `ICalendarWriter` ne gardent aucun état entre les appels : une instance sert chaque
  requête et chaque worker.
- Les classes du paquet sont finales ; `Moment` est propre au paquet.
