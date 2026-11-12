<!-- phil-dev source-sha256=4fa1557323dfa1fdce65446911c5707278e1cd49c94c4e5f6ea5056778bca126 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, avec l'extension `mbstring`.
- `phil/accessibility-check`, pour les critères de succès de WCAG 2.2, leurs niveaux et les rapports que produit un
  audit.
- `phil/url`, qui lit comme le fait un navigateur les adresses `https://` vers lesquelles pointe une déclaration.

## Installer

```sh
composer require phil/accessibility-statement
```

La bibliothèque ne contient aucun service et ne lit aucun fichier. Une application construit ses déclarations, les relit
depuis son propre stockage avec `StatementReader` et génère la version suivante avec `StatementGenerator`. Dans une
application Symfony, `phil/accessibility-statement-bundle` branche autour d'elle une page, un stockage et une commande.
