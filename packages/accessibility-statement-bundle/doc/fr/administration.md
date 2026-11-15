<!-- phil-dev source-sha256=ce1a3883b8ba1d3eef06f8bf63441395de3c51ebd9f1fa8869d41361e7b40802 -->
# Administration

Avec `admin.enabled`, le bundle sert une page d'administration qui publie la version suivante de la déclaration.
Importez ses routes sous le pare-feu de l'administration de l'application :

```yaml
# config/packages/phil_accessibility_statement.yaml
phil_accessibility_statement:
    source: doctrine
    admin: { enabled: true, role: ROLE_ADMIN }

# config/routes/phil_accessibility_statement_admin.yaml
phil_accessibility_statement_admin:
    resource: '@PhilAccessibilityStatementBundle/config/admin_routes.php'
    prefix: /admin
```

| Route | Chemin | Méthode |
|---|---|---|
| `phil_accessibility_statement_admin` | `/accessibility-statement` | `GET`, `HEAD` |
| `phil_accessibility_statement_admin_publish` | `/accessibility-statement/publish` | `POST` |

## Qui l'ouvre

La page demande à Symfony Security si la personne a le rôle `admin.role`, `ROLE_ADMIN` par défaut : le pare-feu invite
un visiteur à se connecter, et une personne sans le rôle est refusée. Le formulaire porte un jeton CSRF de la
protection de Symfony, `phil_accessibility_statement_publish`. Activer la page sans `symfony/security-core` et
`symfony/security-csrf` fait échouer la construction ; un conteneur sans vérificateur d'autorisation ni gestionnaire de
jetons refuse la page plutôt que de l'ouvrir. Chaque réponse porte `Cache-Control: no-store`.

## Ce qu'elle montre

1. Les versions publiées, la plus récente d'abord, la version en vigueur étant marquée.
2. La version suivante, sous forme de document JSON dans une zone de texte : la version que donne le dernier audit quand
   `audit` est activé et qu'un rapport est là, sinon la version en vigueur avec le numéro suivant et la date du jour.
3. Un bouton qui publie le document.

Le document se lit comme la déclaration de la configuration, avec les clés de `StatementReader` de
`phil/accessibility-statement`. Il n'est publié que s'il se lit, pèse au plus 1 Mio et porte le numéro suivant : une
version n'est jamais modifiée une fois publiée. Un document refusé revient dans la zone de texte avec la raison, en
`422` ; une version publiée redirige vers la page avec `?published={n}`, qui l'indique.

Avec la source `config`, les versions s'écrivent dans la configuration : la page montre la version suivante à y copier
et aucun formulaire.

## Les gabarits

`admin.html.twig` étend la mise en page de l'option `layout` et dessine ses parties avec les recettes `Alert`, `Field`,
`Textarea`, `Button`, `Table` et `Badge` du kit ([La page](page.md#les-recettes-du-kit)). Il reçoit `latest`,
`versions`, `writable`, `draft`, `error`, `published`, `csrf_token` et `layout`, et se remplace comme les autres
gabarits du bundle.
