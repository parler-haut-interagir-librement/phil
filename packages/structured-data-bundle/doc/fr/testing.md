<!-- phil-dev source-sha256=af3df1b075a8cc9f94d672becd154cf6a1c5556f52638c679fba5bfb4d304a23 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PageStructuredData` | Les fournisseurs interrogés une fois, les écouteurs, les nœuds ajoutés avant et après l'impression, un graphe par requête principale, les fragments, la réinitialisation, l'adresse et la langue configurées ou tirées de la requête, la portée des fiches nommées et de l'attribut de requête |
| `DefaultNodes` | Chaque clé du site et de son propriétaire, ce qui est désactivé, une page sans adresse |
| `MenuBreadcrumbProvider` | Le fil d'Ariane, son lien depuis la page, une page hors du menu |
| `PageShareMetadata` | Chaque nœud lu, l'ordre des propriétés, les images omises, une page qui n'a rien à dire, les fournisseurs interrogés une fois |
| `StructuredDataExtension`, `RequestAttributeNonce`, `ContentSecurityPolicyNonce` | Les nœuds et les données ajoutés, l'élément imprimé une fois, l'ordre des *nonces*, rien d'imprimé ni de *nonce* demandé pour une fiche retirée, le *nonce* qu'envoie la politique |
| `CheckStructuredDataCommand` | Les fichiers et les adresses, les avertissements et `--strict`, le JSON, les bornes d'une récupération, les échecs |
| `PhilStructuredDataBundle` | Les services de chaque configuration, chaque valeur refusée, les nœuds de la configuration construits une fois |
| L'application fonctionnelle | Une page dont la configuration, un fournisseur, un menu, un contrôleur et un gabarit donnent un seul graphe ; deux pages dans un même *worker* ; une page partagée à partir de son graphe, ce que définit la requête l'emportant sur lui ; une page imprimée en fin de corps ; une page qui montre une fiche retirée, puis la page suivante du *worker* |

```sh
vendor/bin/phpunit packages/structured-data-bundle/tests
```
