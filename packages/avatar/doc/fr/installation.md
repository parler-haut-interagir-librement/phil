<!-- phil-dev source-sha256=bd890fe6f7efc323c2478dc0056309f8d550ec50775e4d8687cec523e4c44a0e -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `mbstring` | Initiales prises et mises en majuscule par caractère, étiquettes comptées en caractères. |

```sh
composer require phil/avatar
```

## Le service

`AvatarGenerator` prend les styles de l'application, s'il y en a, à côté des trois du paquet. Il ne garde aucun état
entre les appels : une seule instance sert toutes les requêtes. Dans une application Symfony :

```yaml
# config/services.yaml
services:
    Phil\Avatar\AvatarGenerator:
        arguments: [!tagged_iterator app.avatar_style]
```

Sans styles à elle, le générateur n'a besoin d'aucune configuration : `new AvatarGenerator()`.
