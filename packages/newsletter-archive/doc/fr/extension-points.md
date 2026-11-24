<!-- phil-dev source-sha256=ef952a041518570c3608eadcd71bf26f9bf66aad4ce95020231b9ed4121820cb -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Rendre la version Web autrement | Implémentez `Application\Contract\WebVersionRenderer` et faites de l'interface un alias de votre service. Ce qu'il retourne est conservé tel quel et écrit sans échappement : purifiez-le. |
| Garder les numéros ailleurs | Implémentez `Domain\Contract\ArchiveRepository` et faites de l'interface un alias de votre service. |
| Donner d'autres adresses aux numéros | Implémentez `Application\Contract\IssueUrls`, utilisée par la commande et par `IssueFeed`. |
| Fabriquer les slugs autrement | Nommez le service d'un `Phil\Slug\Slugger` dans `slugger`. |
| Servir plusieurs sites | Remplacez le service `CurrentSite` de `phil/newsletter-bundle` : l'archive y lit le site d'une requête. |
| Changer les pages | Remplacez les gabarits dans `templates/bundles/PhilNewsletterArchiveBundle/archive/`, ainsi que les textes du domaine `phil_newsletter_archive`. |
| Ajouter une image de partage | Remplissez le bloc `meta` de la mise en page, avec `phil/social-cards` par exemple. |
| Construire un fil des numéros | Lisez `IssueFeed::latest()`. |
