<!-- phil-dev source-sha256=12392a02c039e37f01ac849ccd67609a17f01daa3f92b2a6067c00d15ce310b4 -->
# Diagrammes

## Le service

`docker/mermaid` contient le service Mermaid : Node.js 22, Chromium de Debian, `@mermaid-js/mermaid-cli` et un petit
serveur HTTP, `server.mjs`, avec les parties qui n'ont pas besoin du navigateur dans `lib.mjs`, exécuté sous
l'utilisateur non privilégié 1000.

| Requête | Réponse |
|---|---|
| `POST /render`, `application/json`, `{"code": text}` | `200 image/svg+xml`, ou `422 {"error": text}` quand Mermaid refuse le diagramme |
| `GET /health` | `200 ok` |

Un seul navigateur est démarré à la première utilisation, puis réutilisé. Mermaid tourne à son niveau de sécurité
`strict`, avec des libellés dessinés en texte SVG, jamais en HTML dans un `foreignObject`. Chaque SVG reçoit un
identifiant tiré de son code : deux diagrammes d'une même page ne partagent donc jamais les identifiants de leurs
styles et de leurs marqueurs. Son environnement :

| Variable | Valeur par défaut |
|---|---|
| `PORT` | 3000 |
| `CONCURRENCY` | 2 diagrammes dessinés à la fois |
| `MAX_QUEUE` | 16 diagrammes en attente, puis `503` |
| `RENDER_TIMEOUT_MS` | 8000, puis `503` et le navigateur fermé |
| `MAX_BODY_BYTES` | 65536, puis `413` |
| `MAX_CODE_LENGTH` | 20000 caractères, puis `400` |
| `MAX_SVG_BYTES` | 2097152 octets de SVG, puis `422` |

Un corps qui n'est pas `application/json` reçoit `415`.

Ce que répond le service n'est jamais conservé tel quel : `phil/svg-blocks` le réécrit d'abord avec
`phil/svg-sanitizer`, dont la politique pour les diagrammes garde leur `<style>`, dont les règles sont réécrites par
`phil/css-sanitizer`.

## Exécuter le service

```sh
docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
docker compose -f vendor/phil/svg-blocks-bundle/docker/mermaid/compose.yaml up -d
```

`compose.yaml` le lance sous l'utilisateur 1000, sur un système de fichiers en lecture seule avec un `/tmp` de 128 Mo en
mémoire, sans capacités ni nouveaux privilèges, sous le profil seccomp par défaut de Docker, dans 768 Mo de mémoire et
sans espace d'échange, un processeur, 256 processus et 1024 fichiers ouverts, sur un réseau interne. Ajoutez le service
de l'application au réseau `diagrams` et réglez `mermaid.url` à `http://mermaid:3000` ; ne publiez jamais le port.

La construction de l'image télécharge, et seulement alors :

| Quoi | D'où | Taille, environ |
|---|---|---|
| L'image de base `node:22.11.0-bookworm-slim` | Le registre public de Docker, `registry-1.docker.io/library/node` | 75 Mo compressés |
| `chromium`, `chromium-common` et leurs bibliothèques | Debian bookworm, `deb.debian.org` | 150 à 250 Mo de paquets |
| `fonts-dejavu-core`, `fonts-noto-core` | Debian bookworm, `deb.debian.org` | 40 Mo |
| `@mermaid-js/mermaid-cli` 11.4.2, `puppeteer` 23.11.1 et leurs dépendances, dont Mermaid | npm, `registry.npmjs.org` | 60 Mo décompressés |

Puppeteer ne télécharge aucun navigateur : il pilote celui de Debian. Les tailles dépendent des paquets du jour et sont
mesurées à la première construction.

## Examen de sécurité

Chromium dessine les diagrammes, et le bac à sable de Chromium exige des fonctions du noyau (des espaces de noms
d'utilisateur, ou un assistant setuid) qu'un conteneur ne donne pas à un processus non privilégié. Le service le lance
donc avec `--no-sandbox`. Cet examen pèse ce que cela laisse, et ce qui remplace le bac à sable.

### Ce qu'un attaquant contrôle

Le code d'un diagramme : tout auteur d'un document, jusqu'à 20 000 caractères. Le service n'est joignable que depuis
l'application, sur le réseau interne ; il n'a aucune authentification propre, donc le réseau est la frontière de ceux
qui peuvent l'appeler.

### Les risques

1. **Un diagramme qui exécute un script.** Mermaid à `strict` encode le texte des libellés et refuse les rappels
   `click` ; les libellés sont du texte SVG, jamais du HTML. Une directive `%%{init: …}%%` ne peut pas changer les clés
   listées dans `secure` : le niveau de sécurité, les libellés HTML, les réglages des diagrammes de flux et de classes
   qui les portent, le CSS d'un thème, la configuration de DOMPurify, les limites. Un script qui s'exécuterait malgré
   tout le ferait dans une page d'un navigateur qui n'atteint rien (4, 5).
2. **Un diagramme qui épuise le service.** Le corps est refusé au-delà de 64 Ko avant d'être lu en entier, le code
   au-delà de 20 000 caractères, les `maxTextSize` et `maxEdges` de Mermaid arrêtent les graphes plus grands, deux
   diagrammes sont dessinés à la fois et seize attendent, les autres sont refusés. Un diagramme qui dépasse son délai
   ferme le navigateur, pour qu'aucune page ne continue à dessiner après sa réponse ; la requête suivante en démarre
   un nouveau. La mémoire, le processeur, les processus et les fichiers du conteneur sont plafonnés : un navigateur
   emballé ne peut donc pas affamer l'hôte.
3. **Un SVG qui porte un script jusqu'aux pages de l'application.** Le SVG est plafonné à 2 Mo, et le côté PHP le
   réécrit avec `phil/svg-sanitizer` : aucun script, aucun gestionnaire d'événement, aucun `foreignObject`, aucune
   référence externe, les styles par `phil/css-sanitizer`. Ce que répond le service n'est pas fiable.
4. **Un moteur de rendu compromis qui atteint le réseau.** Dans le navigateur, chaque requête va vers un mandataire sur
   un port fermé et aucun nom n'est résolu ; les extensions, le réseau en arrière-plan, les mises à jour de composants
   et la synchronisation sont désactivés. À l'extérieur, le conteneur est sur un réseau interne : aucune route n'en
   sort, quoi qu'il exécute.
5. **Un moteur de rendu compromis qui s'échappe dans le conteneur.** Sans le bac à sable de Chromium, un exploit du
   moteur de rendu s'exécute comme le processus du navigateur. Celui-ci s'exécute sous l'utilisateur 1000, qui ne
   possède rien dans l'image ; le système de fichiers racine est en lecture seule et seul `/tmp`, en mémoire, `nosuid`
   et `nodev`, est inscriptible ; toutes les capacités sont retirées et aucun nouveau privilège ne peut être acquis ; le
   profil seccomp par défaut de Docker filtre les appels système ; V8 tourne sans son JIT, ce qui supprime la classe
   d'exploits qui écrivent du code exécutable. Ce qui reste est la surface du noyau sous ce profil.
6. **La chaîne d'approvisionnement.** L'image de base, le Chromium de Debian, Mermaid et Puppeteer viennent de leurs
   registres à la construction ; npm n'exécute aucun script d'installation. Épinglez l'image de base par son condensat
   et construisez à partir d'un fichier de verrouillage là où l'image est construite pour la production ; reconstruisez
   à chaque mise à jour de sécurité de Chromium.
7. **L'information dans les erreurs.** Le refus de Mermaid est envoyé sans les cadres de pile qui nomment des fichiers
   du service, sur 500 caractères au plus. Le code d'un diagramme n'est jamais journalisé.

### Conclusions

Le conteneur prend le rôle du bac à sable : un utilisateur non privilégié, un système de fichiers en lecture seule,
aucune capacité, le profil seccomp par défaut, un réseau interne, et les sorties propres à Chromium fermées. Dans ces
conditions, l'option `--no-sandbox` est acceptable pour un service qui dessine des diagrammes écrits par les auteurs de
l'application. Ces conditions ne sont pas facultatives : n'exécutez jamais l'image avec `--privileged`, avec
`seccomp=unconfined`, avec un port publié, ni sur un réseau qui a une route vers l'extérieur. Une application qui dessine
des diagrammes de visiteurs anonymes exécute le service sur un hôte à part.

## La passe en arrière-plan

Avec `mermaid.async: true`, un enregistrement laisse les diagrammes en attente. Une fois le document stocké,
l'application envoie :

```php
$scheduler->schedule('article:42');   // MarkupScheduler
```

Le message, `RenderDeferredMarkup`, ne porte que le sujet, de 1 à 255 octets. Dirigez-le vers un transport qui lui est
propre, pour tenir un service lent à l'écart des requêtes :

```yaml
framework:
    messenger:
        routing:
            Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup: async
```

Son *handler* lit le document par le `MarkupDocuments` de l'application, dessine chaque bloc en attente, et
l'enregistre quand un dessin a été ajouté ou retiré. Un document disparu entre-temps est laissé tel quel. Un diagramme
que le service refuse ne garde aucun dessin et affiche son code.

```php
final class ArticleDocuments implements MarkupDocuments
{
    public function find(string $subject): ?Document { /* … */ }
    public function save(string $subject, Document $document): void { /* … */ }
}
```

Quand le texte a changé entre l'enregistrement et la passe, le dessin de l'ancien texte est retiré au prochain
enregistrement. Une application qui n'en veut aucun compare la version du document dans `save()`.
