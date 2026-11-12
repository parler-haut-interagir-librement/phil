<!-- phil-dev source-sha256=59a5ebef9a092f988f5a59762b3869007baa917d60a6996af19d8af7ebb7ef98 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilSocialCardsBundle`, `SocialCardsServices`, `RequiredServicesPass` | Les valeurs par défaut, chaque option, les services, leurs arguments et leurs étiquettes, ce que la configuration refuse, les services facultatifs retirés sans les leurs |
| `SharePage`, `CardTemplates`, `CardRequest`, `SocialCards` | Les couches de valeurs, les gabarits lus une fois, les demandes gardées et relues, les cartes mises en file une fois et dessinées une fois, un gabarit disparu |
| `ShareMetadataFactory`, `SocialMetaExtension`, `MediaShareImages` | Les quatre couches, l'adresse et la locale de la requête, une page sans titre ou sans adresse, la carte d'une page et son gabarit, un média comme image |
| `SocialCardController`, la file d'attente et le handler | Les en-têtes de cache, l'`ETag` et son `304`, les étiquettes de cache, un `404`, le message et son traitement |
| `GuardedPageFetcher`, `LinkPreviews`, `DirectoryCardRequestStore` | L'en-tête lu par phil/safe-http, les bornes, les redirections, les hôtes privés refusés au départ et à une redirection, les réponses qui ne sont pas des pages, le premier lien d'un texte, le cache, les fichiers |
| `CheckSocialTagsCommand` | Un chemin rendu à l'adresse et au port du routeur, les avertissements, les erreurs, les pages non lues, une adresse récupérée |
| Une application complète | FrameworkBundle, Messenger, Twig et ses composants : les balises d'une page, sa carte dessinée par le handler et servie, `304` et `404`, les aperçus, un aperçu gardé, le point de terminaison des liens collés et sa limite de débit, la commande |
| `link_preview_controller.js` | Le premier lien d'un texte, la carte construite à partir de nœuds de texte, le champ, un aperçu retiré et gardé retiré, les réponses sans aperçu, les erreurs, une réponse tardive abandonnée, le délai |

```sh
vendor/bin/phpunit packages/social-cards-bundle/tests
```

Le contrôleur s'exécute sous l'exécuteur de tests de Node, Stimulus étant remplacé par un *stub* :

```sh
cd packages/social-cards-bundle && node --import ./tests/js/register.mjs --test tests/js
```
