<!-- phil-dev source-sha256=fabf3f42a379a063e5653a62136c2434504976daca1e9ef715eac73b077b1dd1 -->
# Installation

```sh
composer require phil/calendar-import
```

Le paquet exige PHP 8.5 ou une version plus récente, avec les extensions `intl` et `mbstring`. Il lit les flux par
`phil/safe-http`, qui accepte n'importe quel client HTTP de Symfony ; si aucun n'est fourni, il en crée un.
