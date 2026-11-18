<!-- phil-dev source-sha256=09c8288d8632040965ae2f737101d0af6b59b0b0716690125ffc0ac6ccf83284 -->
# Console

Avec `symfony/console`, le bundle enregistre quatre commandes. Elles affichent des identifiants, des *slugs*, des noms
et des rôles, rien d'autre d'un membre.

## `phil:organizations:list`

```sh
bin/console phil:organizations:list --status=active --limit=50 --after=acme
```

Les organisations par *slug*, une page à la fois : identifiant, *slug*, nom, statut et domaine vérifié. `--status` vaut
`active` ou `archived`, les deux quand il est omis ; `--limit` va de 1 à 1000, 100 par défaut ; `--after` est le *slug*
après lequel la page commence.

## `phil:organizations:show`

```sh
bin/console phil:organizations:show acme-guild
```

Une organisation par identifiant ou par *slug* : son nom, son statut, sa création et son domaine, le nombre de membres,
de propriétaires et d'équipes qu'elle compte, le nombre d'invitations encore utilisables, et ses propriétaires — avec un
avertissement quand elle a moins de propriétaires que `recommended_owners`. La commande échoue sur une organisation
inconnue.

## `phil:organizations:check-owners`

```sh
bin/console phil:organizations:check-owners --limit=100
```

Les organisations qui ont moins de propriétaires que recommandé, celles qui n'en ont aucun d'abord. La commande **échoue
quand l'une n'a aucun propriétaire** — son dernier propriétaire a été effacé —, afin qu'une exécution planifiée alerte
l'opérateur ; avoir moins de propriétaires que recommandé n'est qu'un avertissement.

## `phil:organizations:purge-invitations`

```sh
bin/console phil:organizations:purge-invitations --retention=30
```

Supprime les invitations expirées ou révoquées depuis plus longtemps que la durée de conservation, en jours,
`purge_retention` par défaut. Exécutez-la chaque jour depuis le planificateur ou cron.
