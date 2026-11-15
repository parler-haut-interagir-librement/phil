<!-- phil-dev source-sha256=1341316cd6893c87cf372a532b80d9e5d4d161320af659299f923aea647f56f2 -->
# Progression du visionnement

## Ce qui est compté

Un visionnement est cent parties d'une vidéo, chacune regardée ou non. Le lecteur marque une partie quand la vidéo y
joue, et envoie les cent marques — `{"video": "talk-12", "parts": "0111…"}` — avec `navigator.sendBeacon()` quand la page
est cachée ou que la vidéo se termine. Rien ne dit qui a regardé.

Il ne les envoie que si `PlayerComponent::isTracking()` était vrai au moment où la page a été dessinée : une vidéo
hébergée, le suivi activé, un point de terminaison, et le consentement du spectateur, que donne le `ViewingConsent` de
l'application. Sinon le lecteur n'envoie rien, et une vidéo liée n'envoie jamais rien : son lecteur est celui de la
plateforme.

## Ce que fait le serveur

`ProgressController`, la route `POST /_phil/video/progress`, accepte un corps d'un kilooctet au plus venant d'une page du
site — `Sec-Fetch-Site: same-origin`, ou une `Origin` du site là où le navigateur n'envoie pas `Sec-Fetch-Site` — et le
lit comme un `ViewingProgress`. Il répond `204` que le visionnement soit compté ou non, de sorte que la réponse ne dit
rien du consentement du spectateur ; `400` pour un corps qui n'est pas un visionnement, `403` pour une autre origine,
`413` pour un corps trop gros ; jamais mis en cache.

`ViewingTracker::record()` ne compte un visionnement que si le suivi est activé et que le spectateur a accepté — le
consentement est de nouveau demandé sur le serveur — : il ajoute les parties à la carte de chaleur de la vidéo et
distribue `VideoProgressed`.

## La carte de chaleur

`ViewingStore::heatmap($videoId)` donne une `Heatmap` : le nombre de visionnements comptés et, pour chaque partie,
combien l'ont regardée. `percentages()` donne la part des visionnements qui ont regardé chaque partie. La carte de
chaleur est la seule chose conservée : aucun visionnement ne se distingue d'un autre.

| Stockage | Conserve |
|---|---|
| `memory` | Les cartes de chaleur d'une requête, vidées entre deux requêtes : tests et développement |
| `cache` | Un élément par vidéo dans un pool PSR-6, sans expiration ; deux visionnements enregistrés au même instant peuvent compter pour un |
| Un service | N'importe quel `ViewingStore` : un incrément atomique dans une base de données |

## La part regardée

`VideoProgressed` porte le `ViewingProgress` : `percent()` est la part de la vidéo regardée, `isComplete()` dit si chaque
partie l'a été. Un cours l'écoute pour marquer une leçon vue ; le spectateur, quand il en a besoin, est le jeton de
sécurité de la requête, que l'écouteur lit lui-même.

```php
#[AsEventListener]
final readonly class LessonSeen
{
    public function __invoke(VideoProgressed $event): void
    {
        if ($event->progress->percent() >= 90) {
            // mark the lesson of $event->progress->videoId seen for the signed-in member
        }
    }
}
```
