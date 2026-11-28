<!-- phil-dev source-sha256=7ac9f50a300bfa87afa3942c7ec0317e975365ab30675a46a7e5a7337f3d5973 -->
# phil/svg-blocks-bundle

**Formules, diagrammes, graphiques et codes QR dans les documents à blocs de Symfony, dessinés une fois sur le serveur
et affichés partout.**

phil/svg-blocks-bundle intègre `phil/svg-blocks` à Symfony. Chaque document UJO (Universal JSON Output) que reçoit un
champ de formulaire (le `UjoType` de `phil/ujo-bundle`, ou le champ de l'éditeur par blocs) voit ses blocs `math`,
`mermaid`, `chart` et `qrCode` dessinés après sa vérification. Chaque dessin est conservé, signé, avec son bloc. Le
`HtmlRenderer` de `phil/ujo-bundle` est décoré pour écrire ces dessins : les pages, les fichiers EPUB et les PDF
affichent ainsi du MathML et du SVG sans script. Un point de terminaison d'aperçu et son contrôleur Stimulus montrent
le dessin aux auteurs pendant qu'ils tapent.

Les diagrammes sont dessinés par un service Mermaid dans un conteneur, fourni dans `docker/mermaid` : Node.js,
Chromium et Mermaid derrière un petit serveur HTTP, comme Gotenberg tourne à côté d'une application, et confiné comme
l'exige son examen de sécurité écrit. Par défaut, ils sont dessinés en arrière-plan. L'enregistrement les liste comme en attente, et un message Messenger qui ne porte que le
sujet du document demande à un *handler* de les dessiner par le port `MarkupDocuments` de l'application.

C'est un bundle Symfony construit sur `phil/svg-blocks`. Chaque service est la classe même de la bibliothèque, déclarée
sous son nom de classe.

## État

| Partie | État |
|---|---|
| Configuration : secret, niveau des codes QR, taille des graphiques, limites des formules et budget de macros, service Mermaid, aperçu | Implémenté et testé |
| Formules : chaque construction de `phil/latex-mathml`, dont les macros, les couleurs, les liens, les numéros et la chimie | Implémenté et testé |
| `StoredMarkupTypeExtension` : les blocs de `UjoType` et du champ de l'éditeur par blocs dessinés à la soumission | Implémenté et testé |
| `HtmlRenderer` de `phil/ujo-bundle` décoré pour écrire les dessins de confiance | Implémenté et testé |
| Point de terminaison d'aperçu, CSRF sans état, et son contrôleur Stimulus, chargé à la demande et associé dans AssetMapperBundle | Implémenté et testé ; modules purs sous Node |
| Passe en arrière-plan : `RenderDeferredMarkup`, son *handler*, `MarkupScheduler`, le port `MarkupDocuments` | Implémenté et testé |
| Service Mermaid : examen de sécurité, Dockerfile et fichier compose confinés, serveur HTTP avec file d'attente, délais et limites de taille | Implémenté et testé sous Node ; image non construite |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la route, le contrôleur, le service. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services qu'elle déclare. |
| [Formulaires et rendu](doc/fr/forms-and-rendering.md) | Ce qu'un champ fait d'un document soumis, et comment les pages, l'EPUB et le PDF l'affichent. |
| [Aperçu](doc/fr/preview.md) | Le point de terminaison, ses vérifications et le contrôleur Stimulus. |
| [Diagrammes](doc/fr/diagrams.md) | Le service Mermaid, son image et la passe en arrière-plan. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, et le test d'une application qui utilise le bundle. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/svg-blocks-bundle
```

```yaml
# config/packages/phil_svg_blocks.yaml
phil_svg_blocks:
    mermaid:
        url: '%env(MERMAID_URL)%'
        documents: App\Content\ArticleDocuments
```

```php
$builder->add('body', UjoType::class);     // math, chart and qrCode blocks drawn when submitted
// after the flush:
$scheduler->schedule('article:'.$article->getId());   // diagrams drawn in the background
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, `phil/svg-blocks`. `phil/ujo-bundle` pour les champs UJO et le moteur de rendu ;
Symfony HttpClient pour le service Mermaid ; Symfony Messenger pour la passe en arrière-plan ; le composant CSRF de
Symfony pour l'aperçu ; AssetMapper et le bundle Stimulus pour le contrôleur d'aperçu. Docker, ou tout autre
environnement d'exécution de conteneurs, pour le service Mermaid.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
