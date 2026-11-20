# Upgrading

## From 0.1 to 0.2

0.2 adds the units of an organization. Nothing an application calls changes its meaning, but a few things change:

- **Two tables.** `phil_organization_unit` and `phil_organization_unit_member` join the six tables of 0.1. The recipe
  of 0.2 copies `migrations/Version20261120000000.php`, which creates them; without the recipe, a migration calls
  `OrganizationTables::upgrade()`, or `doctrine:migrations:diff` writes it with the ORM — see
  [Installation](installation.md#the-tables). `down()` drops the two tables.
- **A built-in permission.** `units.manage` joins `Permission::BUILT_IN`: owners and admins hold it, and with it every
  unit permission in every unit. An application that lists the permissions of `admin`, or keeps a copy of
  `BUILT_IN`, sees one more.
- **A store of the application's own.** A service named in `store` that implements `OrganizationStore` only keeps
  working, without units: the units, `UnitTree`, `UnitAccess` and their pages are left out. To keep units it implements
  `UnitStore` as well, on the same transactions, and its `deleteMembership()` deletes the member's unit memberships of
  that organization.
- **Constructors.** `Members`, `MemberErasure` and `OrganizationsPrivacy` take an optional `UnitStore` last, and
  `OrganizationVoter` an optional `UnitAccess`; an application that builds them by hand passes them to announce and
  export unit memberships, and to vote on units.
- **The routes of the pages.** The recipe of 0.2 imports `config/routes/units.php` under `/admin/organizations`. An
  application that draws its own screens deletes `config/routes/phil_organizations.yaml`; the pages also need the
  recipes `Badge`, `Button`, `Input`, `NativeSelect`, `Table` and `Tree` of the kit.
- **The CSRF token.** The bundle declares `phil_organizations_units` among the stateless token identifiers of
  FrameworkBundle.
