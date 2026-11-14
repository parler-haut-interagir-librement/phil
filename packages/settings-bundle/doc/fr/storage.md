<!-- phil-dev source-sha256=e5a05acdec856daa0626ec0cbab58337a2ce89f8b90596598d7af412117abdc3 -->
# Stockage

## La table

`phil_settings` contient une ligne par sujet et par clé, la valeur encodée en JSON :

| Colonne | Type | Contient |
|---|---|---|
| `scope` | string(8) | `user` ou `site` |
| `subject` | string(190) | L'identifiant de l'utilisateur, ou `site` |
| `setting_key` | string(128) | La clé du réglage |
| `value` | text | La valeur, en JSON |
| `updated_at` | datetime | Le moment de l'enregistrement |

La clé primaire est formée de la portée, du sujet et de la clé : les valeurs d'un sujet se lisent donc par le début de
la clé. Seules les valeurs qui diffèrent de leur valeur par défaut ont une ligne. Un enregistrement retire les lignes
des clés qu'il touche et insère les nouvelles valeurs dans une seule transaction. De deux enregistrements de la même
clé au même moment, le second échoue sur la clé primaire plutôt que de les mélanger.

`phil_settings_migrations` contient les versions des migrations appliquées :

| Colonne | Type | Contient |
|---|---|---|
| `version` | string(190) | La version de la migration, la clé primaire |
| `applied_at` | datetime | Le moment où elle a été appliquée |

Une migration écrit ses changements et la ligne de sa version dans une seule transaction : de deux exécutions de la même
migration en même temps, la seconde échoue sur la clé primaire et ne change rien. Elle lit chaque valeur d'une clé, d'un
sujet à l'autre, sans index propre : elle s'exécute à un déploiement, non sur une page.

`Infrastructure\Doctrine\SettingTables::configure()` ajoute les tables qui manquent à un schéma DBAL, et le bundle les
ajoute au schéma que l'ORM produit pour la connexion configurée.

## Une lecture par requête

`Settings` lit toutes les valeurs d'un sujet en une requête la première fois qu'on en demande une, puis les garde pour
la requête, chacune décodée une seule fois. Un enregistrement oublie les valeurs du sujet, qui sont relues à la demande suivante. Le conteneur
réinitialise le service entre deux requêtes d'un *worker* (FrankenPHP, RoadRunner, un consommateur de Messenger) par
`kernel.reset`. Aucune valeur ne survit donc à sa requête.

## Pour les tests

`store: memory` garde les valeurs, et les versions des migrations appliquées, en mémoire aussi longtemps que vit le
conteneur.
