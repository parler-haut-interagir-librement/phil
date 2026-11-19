<!-- phil-dev source-sha256=98ba1f75e285937d5267c1715af7ad3ae8bff38ed9640dc129f0710b9b931709 -->
# Le calendrier

Quand `phil/calendar-bundle` est installé et que `calendar` est activé, les réservations confirmées sont des éléments
de ses vues, par la source d'éléments `ReservationItems`. Chaque élément a pour titre et pour étiquette le nom de sa
ressource, pour que les colonnes d'étiquettes de la vue du jour placent les ressources côte à côte ; c'est une plage
horaire, jamais une journée entière, et sa source est `reservation`. Les retenues et les réservations annulées sont
écartées, et le titulaire n'est jamais affiché.

Chaque période affiche au plus `batch` réservations. Désactivez la source avec `calendar: false`.
