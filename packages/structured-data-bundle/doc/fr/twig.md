<!-- phil-dev source-sha256=b175ad5aed7a274c5b047fc0eb2a9cac49a88cbccbd37536a9c0480d92acee90 -->
# Twig

| Fonction | Donne |
|---|---|
| `structured_data(nonce = null)` | `<script type="application/ld+json">…</script>` avec chaque nœud de la page ; vide quand la page n'en a aucun ou montre une fiche retirée des moteurs de recherche, et aucun *nonce* n'est alors demandé |
| `structured_data_add(node, …)` | Rien : ajoute à la page des valeurs `Node`, ou des données schema.org sous forme de tableaux associatifs |
| `structured_data_about(record, …)` | Rien : nomme les fiches que montre la page, dont le titulaire a pu les retirer des moteurs de recherche |

```twig
{% do structured_data_add({'@type': 'Person', 'name': 'Ada'}) %}
{% do structured_data_add(menu_breadcrumb_schema('main')) %}

{{ structured_data() }}
```

Imprimez-le une fois, à la fin du corps du gabarit de base, après que le contenu a ajouté ses nœuds.

Un tableau associatif est lu comme le lit `Node::fromArray()` : son `@context` est retiré, et les tableaux associatifs
qui y sont imbriqués deviennent des nœuds, des références ou des chaînes en langue.

## L'élément de script

L'élément est écrit par `JsonLd::script()` de phil/structured-data : aucune valeur ne peut le fermer ni y ouvrir un
commentaire. C'est un bloc de données, qu'une politique de sécurité du contenu n'exécute pas : par défaut, il n'a aucun
*nonce*, et une page qui l'imprime garde son cache partagé. Le *nonce* relève du choix du site, dans cet ordre :

1. celui qui est donné à `structured_data()` ;
2. le premier que donne un `ScriptNonceProvider` enregistré par le site ;
3. l'attribut de requête que nomme `nonce.request_attribute`, ou le *nonce* de script de la politique de
   phil/browser-security-bundle avec `nonce.content_security_policy` ;
4. aucun.

Aucun *nonce* n'est pris par défaut, pour qu'une page garde son cache partagé.

Un *nonce* est un texte en base64 ; tout le reste arrête le rendu plutôt que d'être échappé.

## Une fiche retirée des moteurs de recherche

Une fiche qui implémente `HasIndexingConsent` de phil/indexing-consent dit si son titulaire laisse les moteurs de
recherche la trouver. Un gabarit qui en montre une la nomme, et la page n'imprime alors aucune donnée structurée à
moins que chaque fiche nommée permette `Reader::StructuredData` :

```twig
{% do structured_data_about(profile) %}
```

Voir [Le graphe d'une page](graph.md) pour le côté du contrôleur.

## Autres paquets

`contact_json_ld()` de phil/contact-bundle et `calendar_event_json_ld()` de phil/calendar-bundle ajoutent leur nœud à
la page et n'impriment rien quand ce bundle est installé, pour que la page imprime un seul élément. Produisez le rendu
de `structured_data()` après eux.
