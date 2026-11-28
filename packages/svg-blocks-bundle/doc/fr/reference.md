<!-- phil-dev source-sha256=faae9d203f1d94074b0b9fb888dd4945144335600b52059b0fc1f260375cc775 -->
# Référence

Chaque classe et chaque module du bundle, avec son rôle.

| Classe ou module | Rôle |
|---|---|
| `PhilSvgBlocksBundle` | La configuration, le jeton sans état pour FrameworkBundle, le chemin du contrôleur pour AssetMapperBundle. |
| `Application\Contract\MarkupDocuments` | Les documents de l'application, pour la passe en arrière-plan. |
| `Application\Message\RenderDeferredMarkup` | Demande que les blocs en attente d'un sujet soient dessinés. |
| `Application\Handler\RenderDeferredMarkupHandler` | Les dessine et enregistre le document. |
| `Infrastructure\Messenger\MarkupScheduler` | Envoie le message. |
| `Infrastructure\Form\StoredMarkupTypeExtension` | Dessine les blocs des documents des champs UJO. |
| `Infrastructure\Form\StoredMarkupTransformer` | Le transformateur qu'elle ajoute. |
| `Infrastructure\Http\PreviewController` | Le point de terminaison de l'aperçu. |
| `Infrastructure\Http\PreviewRequest` | Si une requête d'aperçu vient de ce site. |
| `Infrastructure\DependencyInjection\SvgBlocksServices` | Les services de la configuration. |
| `Infrastructure\DependencyInjection\StoredMarkupPass` | Décore le moteur de rendu UJO, branche ou retire la passe en arrière-plan, vérifie le CSRF. |
| `assets/src/preview_controller.js` | Le contrôleur Stimulus de l'aperçu. |
| `assets/src/lib/preview.js` | Les données du bloc, la requête, la réponse, la pause. |
| `assets/src/lib/csrf.js` | Le jeton à double soumission. |
| `docker/mermaid` | Le service Mermaid : Dockerfile, serveur, ses parties sans navigateur dans `lib.mjs`, fichier compose. |
