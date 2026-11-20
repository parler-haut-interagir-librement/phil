<!-- phil-dev source-sha256=af2bb938a9c18ad0c079d8097b53dab93bedda56aa709e4a588754b94baaeb49 -->
# Console

## phil:usage-meter:purge

```sh
bin/console phil:usage-meter:purge
```

Oublie les compteurs, et leurs clés d'idempotence, qu'aucune fenêtre ne compte plus : les périodes terminées, les
compartiments sortis de leur fenêtre glissante. Elle affiche le nombre de lignes retirées :

```text
42 counter(s) and key(s) forgotten.
```

Rien ne casse sans elle, mais le stockage grossit d'une ligne par sujet, compteur et période, et d'une par
compartiment d'une fenêtre glissante. Une fois par jour le garde petit. Avec Symfony Scheduler :

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

RecurringMessage::cron('17 3 * * *', new RunCommandMessage('phil:usage-meter:purge'));
```

Ou depuis le cron du système :

```text
17 3 * * * /srv/app/bin/console phil:usage-meter:purge --no-interaction
```

La commande lit le moment dans l'horloge `clock` avec laquelle le bundle est configuré.
