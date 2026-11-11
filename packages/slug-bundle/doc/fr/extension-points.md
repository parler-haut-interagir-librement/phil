<!-- phil-dev source-sha256=15c5fab8fbda347dc8d550f284e7702e9c4ad7a104523a7b1aa1ff4799655f8e -->
# Points d'extension

Ce qu'une application configure, décore ou utilise. Les types sont ceux de `phil/slug`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `sluggers` | Un générateur par nom, chacun avec sa `locale`, sa `max_length` et ses `leading_words` — voyez [Configuration](configuration.md) |
| `default` | Quel générateur nommé est `Phil\Slug\Slugger` |
| `history` | L'historique des anciens slugs, sa connexion et son horloge — voyez [Historique des anciens slugs](history.md) |

## Services

| Service | Comment une application l'utilise |
|---|---|
| `phil_slug.slugger.<name>` | Décoré par l'application, avec `#[AsDecorator]` ou `decorates:` : le décorateur prend la place du générateur dans `Sluggers`, par les alias et dans Twig — voyez [Services](services.md#décorer-un-générateur) |
| `Phil\Slug\Slugger` | Autoconnecté : le générateur par défaut |
| `Phil\Slug\Slugger $<name>Slugger` | Autoconnecté par nom d'argument : le générateur de ce nom |
| `Phil\Slug\Sluggers` | Injecté partout où le générateur est choisi à l'exécution ; public |
| `Phil\SlugBundle\Application\Contract\SlugHistory` | Autoconnecté quand l'historique est activé : enregistré par le code qui change un slug, lu par un fournisseur de redirections ou un contrôleur |
| `phil_slug.history` | L'historique Doctrine ; une application le remplace en faisant pointer l'alias `SlugHistory` vers sa propre implémentation |

Un bundle qui prend un générateur par identifiant de service reçoit `phil_slug.slugger.<name>`.

## SlugHistory

Une implémentation autre que celle de Doctrine garde les invariants du contrat :

- une portée et un sujet sont vérifiés avec `SlugHistoryKeys` avant que rien ne soit écrit ou lu ;
- enregistrer un changement fait cesser le nouveau slug d'être un ancien slug de la portée, quel que soit le sujet qui
  l'avait, fait pointer chaque ancien slug du sujet vers le nouveau slug, et écrit l'ancien slug comme ancien slug du
  sujet, d'un seul coup ;
- enregistrer un slug comme son propre ancien slug ne change rien ;
- `formerSlugs()` ne donne jamais de chaîne : chaque valeur est un slug actuel ;
- elle ne garde rien entre deux appels, ou porte l'étiquette `kernel.reset`.

## Twig

Le filtre `phil_slug` prend le nom d'un générateur ; il utilise les mêmes services, décorateurs compris.
