<!-- phil-dev source-sha256=182ac7906af7ffe656b28cd6ae874cc37e512a8084846415caa0d90e173e74b2 -->
# phil/audit-viewer

**Le journal d'audit de `phil/audit-bundle`, lu dans une interface d'administration EasyAdmin.**

phil/audit-viewer ajoute les pages du journal d'audit à chaque tableau de bord EasyAdmin d'une application Symfony : le
journal avec ses filtres, une ligne avec sa charge utile et ses empreintes, l'historique d'un enregistrement, l'état de
la chaîne d'empreintes regroupé par site, la vérification d'un segment et un export CSV. Chaque page CRUD d'une entité
qui implémente `Auditable` reçoit une action « Historique » qui mène à l'historique de cet enregistrement. Les pages
appellent les services de `phil/audit-bundle` — `AuditLog`, `ChainStatus`, `AuditTrail` — et ne changent rien dans le
journal : l'export est la seule chose qu'elles écrivent, une ligne qui consigne qui a exporté quoi, écrite avant la
première ligne du fichier.

Il existe parce qu'une piste d'audit que personne ne lit prouve peu de chose : les personnes qui en répondent doivent
trouver qui a fait quoi, voir l'historique d'une commande ou d'une page, vérifier que la chaîne tient et remettre les
lignes à quelqu'un d'autre, sans console. Le bundle garde cette lecture à part de la piste elle-même, qui reste un
service sans écrans.

Il s'adresse aux applications qui reposent sur `phil/audit-bundle` et ont une interface d'administration EasyAdmin,
pour un site ou pour plusieurs. Chaque page demande son propre attribut, `PHIL_AUDIT_VIEW`, `PHIL_AUDIT_HISTORY` ou
`PHIL_AUDIT_EXPORT`, accordé par défaut à un rôle dédié, de sorte que l'historique d'un enregistrement peut être ouvert
aux personnes qui le gèrent sans leur ouvrir tout le journal. Il ne modifie ni n'annule une ligne, n'ancre pas la piste
et ne décide pas qui peut la lire au-delà de ces rôles.

Le bundle est un bundle Symfony sur EasyAdmin 5, `phil/audit-bundle`, le composant Security et Twig. Ses pages sont des
pages EasyAdmin dans la mise en page du tableau de bord, sans rien en dehors de celle-ci.

## État

| Partie | État |
|---|---|
| `AuditViewerController` : le journal, une ligne, un historique, la chaîne, un segment, l'export | Implémenté et testé |
| `AuditFilters`, `AuditCsv` | Implémenté et testé |
| `AuditHistoryActions` : l'action « Historique » des entités auditées | Implémenté et testé |
| `AuditViewerVoter`, `AuditViewerAdmin` | Implémenté et testé |
| `AuditAccessPolicy` imposée à chaque page et à chaque liste : lignes du journal, segments de la chaîne, lignes de l'export | Implémenté et testé |
| `PhilAuditViewerBundle` | Configuration `role`, `export_role`, `history_role`, `access_policy`, `history_action`, `export_segment`, `export_limit`, `page_size`, `timezone` |

## Documentation

- [Installation](doc/fr/installation.md) : les prérequis, l'enregistrement du bundle, et ce que `phil/audit-bundle` doit
  avoir de configuré.
- [Configuration](doc/fr/configuration.md) : les neuf clés sous `phil_audit_viewer` et les services câblés.
- [Pages](doc/fr/pages.md) : chaque page, sa route sous un tableau de bord, ses filtres et ce qu'elle montre, la chaîne
  par site.
- [L'action Historique](doc/fr/history-action.md) : l'action sur les pages CRUD des entités auditées, qui la voit, et
  comment la désactiver ou la remplacer.
- [Accès et export](doc/fr/access-and-export.md) : les trois attributs et leurs rôles, l'export CSV, la façon dont il
  est consigné et ce contre quoi il protège.
- [Points d'extension](doc/fr/extension-points.md) : la politique d'accès qui restreint les rôles, vos propres *voters*, le lecteur de charge utile, les gabarits et
  les traductions.
- [Tests](doc/fr/testing.md) : comment le bundle est testé, sa cible de fuzzing, et comment exécuter ses tests.
- [Référence de l'API](doc/fr/reference.md) : chaque type, avec son rôle.

## Démarrage rapide

```bash
composer require phil/audit-viewer:^0.1@dev
```

```php
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerAdmin;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function configureMenuItems(): iterable
    {
        yield from AuditViewerAdmin::menuItems('admin');   // admin_audit_log, admin_audit_chain
    }
}
```

```yaml
# config/packages/phil_audit_viewer.yaml
phil_audit_viewer:
    role: ROLE_AUDIT_VIEWER
    export_role: ROLE_AUDIT_EXPORT
    history_role: ROLE_ADMIN
```

Le journal se trouve alors à `/admin/audit`, pour les titulaires de `ROLE_AUDIT_VIEWER`, et la page de détail d'une
commande dont l'entité implémente `Auditable` leur montre un bouton « Historique », ainsi qu'aux titulaires de
`ROLE_ADMIN`.

## Prérequis

PHP 8.5 ou plus récent, `phil/audit-bundle` 0.1 sur une connexion Doctrine, EasyAdmin 5.10.4, Symfony 8.2 avec
SecurityBundle, la protection CSRF, le routeur et le traducteur, et Twig 3.21.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
