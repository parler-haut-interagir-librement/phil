<!-- phil-dev source-sha256=eae783ca451cf7c4716bc7ae6a0c3673b29014eaeb46d003c773e7d5984cab27 -->
# Le calendrier imprimé

Avec `phil/paged-media` installé, `PrintableCalendar` imprime le calendrier à raison d'un mois par page, avec le gabarit
de calendrier de la bibliothèque : chaque élément des vues (les événements dans la locale du lecteur, les dates de
l'almanach) sur chaque jour qu'il couvre, son heure d'abord quand il en a une, un élément annulé marqué `×`, le mois et
les jours de la semaine nommés par ICU dans la langue du lecteur, les semaines à partir du premier jour configuré.

```php
$pdf = $printable->document(2026, 11, count: 3, locale: 'fr_CA', tags: ['jeux'])->render();

return new Response($pdf, 200, ['Content-Type' => 'application/pdf']);
```

| Méthode | Donne |
|---|---|
| `months($year, $month, $count, $locale, $tags)` | Les `CalendarMonth` du gabarit, de 1 à 24 mois à partir d'un premier |
| `document(...)` | Le `Document`, sur lequel l'application peut encore dessiner avant d'en produire le rendu |
| `weekdays($locale)` | Les noms des sept jours, à partir du premier jour de la semaine |

La page est une page Letter à l'italienne, la valeur par défaut du gabarit ; la case d'un jour montre ce qui y tient,
coupé par une ellipse. Le bundle n'enregistre aucune route : le contrôleur de l'application choisit qui peut imprimer,
et quels mois.
