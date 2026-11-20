<!-- phil-dev source-sha256=ee0ea0157b3e60c0cb9caf3afa15f25888ca585fe876864c5ed529e5a8722531 -->
# Mise à niveau

## De la 0.1 à la 0.2

La 0.2 ajoute les unités d'une organisation. Rien de ce qu'une application appelle ne change de sens, mais quelques
points changent :

- **Deux tables.** `phil_organization_unit` et `phil_organization_unit_member` s'ajoutent aux six tables de la 0.1. La
  recette de la 0.2 copie `migrations/Version20261120000000.php`, qui les crée ; sans la recette, une migration appelle
  `OrganizationTables::upgrade()`, ou `doctrine:migrations:diff` l'écrit avec l'ORM — voir
  [Installation](installation.md#les-tables). `down()` supprime les deux tables.
- **Une permission intégrée.** `units.manage` rejoint `Permission::BUILT_IN` : les propriétaires et les administrateurs
  la détiennent, et avec elle chaque permission d'unité dans chaque unité. Une application qui liste les permissions
  d'`admin`, ou qui garde une copie de `BUILT_IN`, en voit une de plus.
- **Un magasin propre à l'application.** Un service nommé dans `store` qui n'implémente que `OrganizationStore` continue
  de fonctionner, sans unités : les unités, `UnitTree`, `UnitAccess` et leurs pages sont laissés de côté. Pour conserver
  des unités, il implémente aussi `UnitStore`, sur les mêmes transactions, et son `deleteMembership()` supprime les
  adhésions du membre aux unités de cette organisation.
- **Les constructeurs.** `Members`, `MemberErasure` et `OrganizationsPrivacy` prennent un `UnitStore` facultatif en
  dernier, et `OrganizationVoter` un `UnitAccess` facultatif ; une application qui les construit à la main les leur
  passe pour annoncer et exporter les adhésions aux unités, et pour voter sur les unités.
- **Les routes des pages.** La recette de la 0.2 importe `config/routes/units.php` sous `/admin/organizations`. Une
  application qui dessine ses propres écrans supprime `config/routes/phil_organizations.yaml` ; les pages exigent aussi
  les recettes `Badge`, `Button`, `Input`, `NativeSelect`, `Table` et `Tree` du kit.
- **Le jeton CSRF.** Le bundle déclare `phil_organizations_units` parmi les identifiants de jetons sans état de
  FrameworkBundle.
