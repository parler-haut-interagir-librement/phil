<!-- phil-dev source-sha256=672facac9e936d2fb1f036d9d1903cdae013aa7ae3bd8f200540ad51104e8cbd -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Conserver les gabarits ailleurs que dans les contenus | Implémentez `Application\Contract\TemplateStore` — brouillons, publication, versions, catalogue, chacun exactement dans la locale donnée — et nommez son service dans `store` ; `StoreTestCase`, dans les tests du bundle, contient ce qu'un magasin promet |
| Garder ou suivre une publication | Écoutez les événements du flux de travail `phil_content` de `phil/content-bundle`, dont les sujets incluent les lignes de `EmailTemplateDimension` |
| Fournir des gabarits par défaut | Ajoutez des fichiers `<key>.<locale>.yaml` à un répertoire de `defaults_paths`, ou remplacez `Application\Contract\DefaultTemplates` |
| Nommer le site courant | Implémentez `Application\Contract\SiteScope` et faites pointer l'interface vers cette implémentation |
| Changer la mise en page de la maison | Remplacez `@PhilEmailTemplates/email/layout.html.twig`, ou nommez un autre gabarit dans `layout` ; ou implémentez `Application\Contract\EmailLayout` |
| Mettre les styles en ligne autrement | Implémentez `Application\Contract\StyleInliner` et faites pointer l'interface vers cette implémentation |
| Lire les images ailleurs | Implémentez `Application\Contract\EmbedLoader` et faites pointer l'interface vers cette implémentation |
| Produire le rendu du Markdown autrement | Implémentez `Application\Contract\MarkdownRenderer` et faites pointer l'interface vers cette implémentation |
| Donner un filtre ou une fonction au bac à sable | Étiquetez une extension Twig `phil_email_templates.sandbox_extension` et listez ses noms dans `sandbox.filters` ou `sandbox.functions` |
| Prévisualiser avec d'autres données, ou avec un paquet d'aperçu dédié | Implémentez `Application\Contract\PreviewRenderer` et nommez son service dans `preview` |
| Envoyer depuis un message | Implémentez `Application\Contract\DeliveryResolver` et nommez son service dans `messenger.delivery_resolver` |
| Réagir à un changement | Écoutez `Domain\Event\TemplateSaved`, `TemplatePublished`, `TemplateDraftDiscarded` et `TemplateReset` |
| Remplacer le moteur | Implémentez `Application\Contract\TemplateEngine` et faites pointer l'interface vers cette implémentation — le bac à sable devient alors votre responsabilité |
