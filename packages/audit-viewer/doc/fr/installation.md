<!-- phil-dev source-sha256=9a77c97a2646aa3a86a91b1d299756f64e8dcf8be011fe43948ee56a86aecb23 -->
# Installation

## Prérequis

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le plancher déclaré. |
| `phil/audit-bundle` | `^0.1@dev` | Les services que les pages appellent : `AuditLog`, `ChainStatus`, et `AuditTrail` pour l'export. |
| `phil/ddd-bundle` | `^0.1@dev` | L'`InvalidValue` que lève un filtre qui ne se lit pas. |
| `easycorp/easyadmin-bundle` | `^5.8` | Les pages sont des routes d'administration de chaque tableau de bord, dans sa mise en page. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | Le *voter*, le vérificateur d'autorisation et le jeton de l'export. |
| `symfony/http-foundation`, `symfony/http-kernel` | `^8.2` | Les requêtes, les réponses et les erreurs du contrôleur. |
| `symfony/translation` | `^8.2` | Les libellés du menu, sous forme de messages traduisibles. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | La classe du bundle étend `AbstractBundle`. |
| `psr/clock` | `^1.0` | Le nom d'un fichier d'export. |
| `twig/twig` | `^3.21` | Les pages. |

## Installer

```bash
composer require phil/audit-viewer:^0.1@dev
```

Le paquet déclare `"minimum-stability": "dev"` et un alias de branche `dev-main` → `0.1.x-dev`.

## Enregistrer le bundle

Avec Symfony Flex, le bundle est enregistré automatiquement. Sinon, ajoutez-le après `phil/audit-bundle` et EasyAdmin
dans `config/bundles.php` :

```php
return [
    // ...
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    Phil\AuditBundle\PhilAuditBundle::class => ['all' => true],
    Phil\AuditViewer\PhilAuditViewerBundle::class => ['all' => true],
];
```

La mise en page d'EasyAdmin utilise les composants Twig et les extras de Twig : une application qui a EasyAdmin
enregistre `TwigComponentBundle` et `TwigExtraBundle`, comme le fait Flex.

## Ce que `phil/audit-bundle` doit avoir

- Une connexion Doctrine (`phil_audit.connection`) : les pages lisent la table de la piste par `DbalAuditLogReader`.
- Un sérialiseur (`phil_audit.serializer`) pour l'export : sans `AuditTrail`, l'export ne peut pas être consigné; il
  n'est donc pas offert et sa route répond `404`.
- Un `PayloadReader`, facultatif, dont l'application fait l'alias : la charge utile d'une ligne est alors montrée sous
  forme de champs plutôt que sous forme des octets stockés ([extension-points.md](extension-points.md)).
- Un `AuditActor` sur la classe de l'utilisateur, facultatif, pour qu'un export nomme qui l'a fait
  ([access-and-export.md](access-and-export.md)).

## Les routes

EasyAdmin génère les routes des pages pour chaque tableau de bord; rien n'est importé. Sous un tableau de bord dont la
route est `admin` à `/admin`, le journal est `admin_audit_log` à `/admin/audit` ([pages.md](pages.md)).
