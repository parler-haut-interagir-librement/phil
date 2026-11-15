<!-- phil-dev source-sha256=df83488b15f50ac570f5a24128a2bd9b7972853acf5ad260813633c2abfa073e -->
# Points d'extension

## Ports

| Port | Valeur par défaut | Une application le remplace pour |
|---|---|---|
| `Application\Contract\ViewingConsent` | `NoViewingConsent` : personne n'a accepté | Répondre avec son gestionnaire de consentement : `tracking.consent` |
| `Application\Contract\ViewingStore` | `InMemoryViewingStore` | Conserver les cartes de chaleur : `tracking.store: cache`, ou son propre stockage |

Les deux ne gardent aucun état entre deux requêtes : le stockage en mémoire est vidé par `kernel.reset`.

## L'événement

`Application\Event\VideoProgressed` est distribué pour chaque visionnement compté, et porte son `ViewingProgress` : la
part regardée, les parties. C'est ainsi qu'un autre bundle — un cours — apprend qu'une vidéo a été regardée.

## Gabarits et contrôleur

`@PhilVideo/components/Player.html.twig` se remplace comme tout gabarit d'un bundle. La façade, les pistes et la
transcription sont des recettes du kit qu'une application peut restyler.

## Stabilité

La configuration, les ports, l'événement et les propriétés du composant sont l'API publique, en 0.1 : ils peuvent
changer dans une version mineure jusqu'à la 1.0.
