<!-- phil-dev source-sha256=5e900ef194b68ede3ba60b53617a49b63455080a72d2a550a1a147d78033715a -->
# Résultats, conservation et vie privée

## Résultats

`Results::of($questionnaire, $subject)` compte les réponses stockées avec `phil/poll`, une réponse à la fois :

| Type | Résultat |
|---|---|
| `yes_no`, `single_choice`, `multiple_choice` | Le décompte de chaque option |
| `ranking` | Les points de chaque option selon ses rangs |
| `scale` | La répartition et la moyenne |
| `net_promoter` | L'indice de promotion net (Net Promoter Score) |
| `matrix` | Le décompte de chaque colonne, ligne par ligne |

Les réponses libres ne sont jamais comptées. Le `ResultsReport` contient le nombre de réponses, le minimum et le
résultat de chaque question comptée, sous forme de `QuestionResult`.

Rien n'est montré sous le `results.minimum` du questionnaire, 5 par défaut : tant que moins de réponses sont comptées,
le rapport est retenu et ne contient aucune question ; une question à laquelle moins de répondants ont répondu est
retenue à part ; une matrice est retenue tant que sa ligne la moins répondue est sous le minimum. Une réponse qui ne
convient plus à sa question, comme une option retirée depuis, est écartée.

`Results::public()` donne le même rapport à un répondant après sa réponse, et lance `SurveyRefused` à moins que le
`results.public` du questionnaire soit activé ; il est désactivé par défaut. La page de fin ne le montre que si la
réponse a été conservée.

## Conservation

Le `retention_days` d'un questionnaire fixe la durée de conservation de ses réponses ; sans lui, elles sont conservées
jusqu'à leur suppression. Le message `PurgeSurveyResponses`, qui ne porte rien, supprime par lots les réponses échues,
avec leurs addenda. Son *handler* est idempotent : exécuté deux fois, il ne supprime rien la seconde fois.

Avec Symfony Scheduler et Messenger, le calendrier `phil_survey` l'envoie chaque jour à `schedule.at`. Avec une fabrique
de verrous, un seul *worker* l'exécute, peu importe combien consomment le calendrier ; avec un groupe de cache, le
calendrier garde sa dernière exécution et rattrape une exécution manquée. Consommez-le avec :

```sh
bin/console messenger:consume scheduler_phil_survey
```

## Vie privée

Avec `phil/privacy-bundle`, le module `survey` participe à l'exportation et à l'effacement des données d'une personne,
par ses contrats publics :

- l'exportation donne les réponses identifiées de la personne, ses réponses, ses variables calculées, sa fin, ses
  addenda et son instant, ainsi que les questionnaires auxquels la personne a participé ;
- l'effacement supprime ces réponses, leurs addenda et les marques de participation de la personne.

Les réponses anonymes ne contiennent rien qui nomme une personne : ni l'une ni l'autre ne les trouve.
