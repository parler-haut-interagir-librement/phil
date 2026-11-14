<!-- phil-dev source-sha256=2a1895b87d0aa7b22b6fe8f90afc38cbe9fa49aeae9d0684650a4b967e223d32 -->
# Sécurité de ligne

Le filtre Doctrine restreint chaque requête que construit l'ORM. Il ne voit ni le SQL natif, ni une requête lancée
avec le filtre désactivé par erreur, ni un rapport écrit directement sur les tables. Sur PostgreSQL, le bundle peut
répéter la règle dans la base elle-même, comme seconde barrière : la **sécurité de ligne** (*row-level security*),
facultative et désactivée par défaut.

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    row_level_security:
        enabled: true
        connection: default        # the Doctrine connection whose sessions carry the tenant
        entity_manager: default    # the entity manager whose tenant-scoped entities get a policy
        setting: phil.tenant_id    # the session variable
        policy: phil_tenant_isolation
```

## La variable de session

Quand le contexte du locataire entre dans un locataire, `TenantSession`, l'une de ses activations, pose la variable
sur chaque connexion ouverte de la connexion Doctrine configurée :

```sql
SELECT set_config('phil.tenant_id', 'retail', false)
```

- **Chaque connexion, à son ouverture.** Un intergiciel DBAL, `TenantSessionMiddleware`, remet chaque nouvelle
  connexion à la session, qui y pose le locataire actif. Une connexion qui se rouvre dans un processus de longue
  durée reprend le locataire.
- **Échec en mode fermé.** Une connexion s'ouvre sans la variable, et quitter le locataire la vide : aucune
  politique ne correspond. `TenantContext::leave()` s'exécute à chaque réinitialisation du noyau.
- **Liée, jamais interpolée.** La référence du locataire est un paramètre de `set_config()` : une référence qui
  ressemble à du SQL n'est qu'un locataire inconnu.
- **Pour la session.** La variable survit aux transactions : un regroupeur de connexions doit remettre la même
  connexion au même client. Connectez-vous directement à PostgreSQL, ou par un regroupeur en mode session, jamais en
  mode transaction. Entrez dans le locataire hors d'une transaction : une transaction annulée reprend le
  `set_config()` qu'elle contient.

La variable est formée de deux identifiants en minuscules joints par un point, comme PostgreSQL l'exige d'une
variable personnalisée ; la politique est un identifiant. Un nom hors de ces règles arrête la compilation du
conteneur.

## Les politiques

```bash
bin/console phil:tenancy:rls:policies            # the statements, for the up() of a migration
bin/console phil:tenancy:rls:policies --disable  # their removal, for its down()
```

Pour chaque entité par locataire — la racine de sa hiérarchie, comme la lit le filtre —, la commande affiche :

```sql
ALTER TABLE invoice ENABLE ROW LEVEL SECURITY;
ALTER TABLE invoice FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS phil_tenant_isolation ON invoice;
CREATE POLICY phil_tenant_isolation ON invoice USING (tenant_ref::text = current_setting('phil.tenant_id', true)) WITH CHECK (tenant_ref::text = current_setting('phil.tenant_id', true));
```

- `FORCE` soumet aussi le propriétaire de la table, pour que le rôle même de l'application passe par la politique.
- `USING` filtre les lignes lues, modifiées et supprimées ; `WITH CHECK` refuse une ligne écrite pour un autre
  locataire.
- `current_setting(…, true)` vaut null quand la variable n'a jamais été posée, et la comparaison ne correspond alors
  à rien.
- La colonne est comparée comme texte, comme le filtre la compare au texte de la référence du locataire : une colonne
  `uuid` porte sa forme en minuscules, un entier ses chiffres.

La commande n'exécute rien : collez les instructions dans une migration, chacune par `$this->addSql()`. Relancez-la
après avoir ajouté une entité par locataire.

## Le rôle

PostgreSQL n'applique pas la sécurité de ligne à un superutilisateur, ni à un rôle doté de `BYPASSRLS`.
L'application se connecte avec un rôle qui n'a ni l'un ni l'autre. Le travail qui couvre légitimement plusieurs
locataires — les migrations, les données de départ, un rapport sur tous les locataires — passe par une autre
connexion, avec un rôle doté de `BYPASSRLS`, là où une personne qui relit le code peut le voir.

## La vérification

```bash
bin/console phil:tenancy:rls:check
```

Sur la base elle-même, la commande vérifie que :

- la connexion est PostgreSQL ;
- son rôle n'est ni superutilisateur ni `BYPASSRLS` ;
- chaque table par locataire existe, a sa sécurité de ligne activée et forcée, et porte la politique, dont `USING` et
  `WITH CHECK` lisent la variable de session.

Chaque problème s'affiche sur sa ligne, et la commande échoue tant qu'il en reste un : lancez-la après les migrations
d'un déploiement.

## Ce qu'elle ne couvre pas

- Les autres bases : MySQL et MariaDB n'ont pas de sécurité de ligne, SQLite non plus.
- Les tables que l'ORM ne mappe pas, et les entités sans `#[TenantScoped]`.
- La seconde barrière ne remplace pas le filtre : le filtre écrit toujours le locataire dans chaque requête, ce qui
  garde en usage les index de la colonne du locataire.
