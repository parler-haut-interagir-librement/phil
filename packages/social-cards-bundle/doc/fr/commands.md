<!-- phil-dev source-sha256=6321b5dd181a538f8e964037dac6a49b0df4694d0b6a9fafe716e30c2963f8ac -->
# Commandes

## `phil:social-cards:check`

```bash
bin/console phil:social-cards:check /blog/a-post
bin/console phil:social-cards:check https://example.org/
```

Un chemin est rendu par l'application elle-même, comme une requête à l'adresse du routeur —
`framework.router.default_uri` — et à son chemin de base ; une adresse est récupérée par le client protégé, comme
pour un aperçu de lien. La commande liste les balises `og:`, `article:`, `twitter:` et `fediverse:`, puis chaque
constat de `TagCheck` comme une erreur ou un avertissement.

| Code de sortie | Quand |
|---|---|
| 0 | Aucune erreur ; des avertissements peuvent rester |
| 1 | Une erreur, ou une page illisible : pas une réponse `200` en HTML, sur un réseau privé, trop lente |
