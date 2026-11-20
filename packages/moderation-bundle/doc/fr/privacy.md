<!-- phil-dev source-sha256=1363db5d4139c94502b94b844c693c90308e927bbddd60f762ad0183bc42d68e -->
# Renseignements personnels

## Identifiants stables

Un membre est un `MemberId` : l'identifiant stable que l'application lui donne, jusqu'à 190 caractères visibles. **Un
identifiant qui ressemble à une adresse de courriel est refusé**, et l'identifiant d'utilisateur de Symfony Security
n'est jamais lu ([Installation](installation.md#les-membres)).

## L'export et l'effacement

Avec `phil/privacy-bundle`, `ModerationPrivacy` répond aux deux contrats pour le module `moderation`.

L'export contient, pour le membre que la demande concerne :

| Clé | Quoi |
|---|---|
| `reports` | Les signalements qu'il a faits : cible, raison, commentaire, moment, s'il est fermé |
| `decisions_about_my_content` | Les décisions sur son contenu : action, rôle, raison publique, moment |
| `decisions_taken` | Les décisions qu'il a prises comme modérateur |
| `statements_addressed_to_me` | Les exposés des motifs qui lui sont adressés : cible, action, raison, motifs, disposition, faits, si un signalement les a provoqués, si des moyens automatisés ont détecté le contenu, moment |
| `notes_about_me` | Les notes de la modération à son sujet, sans leur auteur |
| `sandbox_left_at` | Quand son premier message a été approuvé |

Il ne nomme jamais un autre membre : aucun modérateur, aucun signaleur, aucun auteur de note ; la note privée d'une décision reste hors de
l'export.

L'effacement supprime les notes sur le membre, les exposés des motifs qui lui sont adressés et l'enregistrement du bac à
sable, et retire le membre des signalements qu'il a faits, des décisions — comme auteur et comme modérateur, le rôle
demeurant — et des notes qu'il a écrites. Il peut être rejoué. Les incidents qu'un membre a déclenchés ou levés sont
du ressort de `phil/incident-mode-bundle`.
