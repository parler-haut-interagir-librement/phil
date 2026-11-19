<!-- phil-dev source-sha256=59f734d2db2e227298a854b1b3b9f3e21604eb4a981c825f0ea154efdfaccb90 -->
# Installation

```sh
composer require phil/assignment
```

Le paquet exige PHP 8.5 ou une version plus récente, et rien d'autre : aucune extension au-delà de celles que PHP a
toujours, aucun framework. Les assigneurs ne gardent aucun état entre les appels ; une application Symfony peut donc
les déclarer comme services partagés :

```yaml
# config/services.yaml
services:
    Phil\Assignment\RankedRounds: ~
    Phil\Assignment\MinCostAssigner: ~
    Phil\Assignment\ProblemReader: ~
```

Pour changer un réglage — le mécanisme, l'ordre des rondes, le coût de rang, le budget de travail, les limites —,
déclarez le service avec ses arguments, ou construisez l'objet là où il sert.
