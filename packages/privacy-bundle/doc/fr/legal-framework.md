<!-- phil-dev source-sha256=69cb6e8a44d22cf6edba80f13bd14eaf269178d049d2717c833526790ddb0f4e -->
# Cadre juridique

Cette page nomme les règles autour desquelles le bundle est construit, et dit ce que sa configuration par défaut fait
pour chacune. Ce n'est pas un avis juridique : la personne responsable de la protection des renseignements personnels
décide de ce que doit un site, et les textes eux-mêmes l'emportent sur ce résumé.

## Ce qui s'applique aujourd'hui

### Témoins et traceurs semblables : la directive ePrivacy et la CNIL

L'article 5(3) de la directive ePrivacy (2002/58/CE) soumet au consentement préalable d'une personne le stockage
d'information sur son appareil, ou sa lecture, sauf si c'est strictement nécessaire au service qu'elle a demandé. La
CNIL, l'autorité française, l'applique par ses lignes directrices et sa recommandation sur les témoins et les autres
traceurs : le consentement avant tout traceur facultatif, un refus aussi simple que l'acceptation, un choix qui peut
être retiré en tout temps, et sa preuve.

| La règle | Ce que fait le bundle par défaut |
|---|---|
| Rien de facultatif avant le consentement | Chaque catégorie facultative est refusée tant que le visiteur ne l'accorde pas ([consent.md](consent.md)). |
| Refuser aussi simplement qu'accepter | La bannière montre « Refuse all » et « Accept all » côte à côte, le même élément et le même style. |
| Retrait en tout temps | La page des choix est toujours accessible, et fonctionne sans JavaScript. |
| Preuve du consentement | Chaque choix laisse une preuve datée de la version du texte, qui ne nomme personne. |
| Redemander après un temps | Un choix est gardé six mois (`consent.lifetime: P6M`), puis le visiteur est de nouveau sollicité. |
| Contenu tiers | Un cadre d'un autre site attend derrière un aperçu servi par le site ([third-party-frames.md](third-party-frames.md)). |
| Une liste de chaque traceur | L'inventaire des témoins, la bannière et la politique des témoins qui en sont tirées, et un test qui échoue sur un témoin non déclaré ([cookie-inventory.md](cookie-inventory.md)). |

Le bundle respecte aussi le Global Privacy Control : un navigateur qui envoie `Sec-GPC: 1` voit refuser chaque catégorie
facultative sans être sollicité.

Un pixel dans un courriel lit aussi quelque chose sur l'appareil du destinataire. Le bundle ne suit aucun courriel, et
son consentement par membre ([email-tracking.md](email-tracking.md)) est inactif tant que l'application ne l'active pas,
chaque fin étant alors demandée séparément.

### Québec : la loi 25

La *Loi sur la protection des renseignements personnels dans le secteur privé* (chapitre P-39.1), telle que modifiée par la
loi 25, s'applique aux entreprises qui détiennent des renseignements personnels sur des personnes au Québec ; ses
règlements, comme le *Règlement sur les incidents de confidentialité* (chapitre A-2.1, r. 3.1), s'appliquent aux mêmes
entreprises.

| La règle | Ce que fait le bundle par défaut |
|---|---|
| Le plus haut niveau de confidentialité par défaut | Témoins facultatifs refusés, suivi des courriels inactif, rien de public sans le choix de la personne. |
| Une technologie qui identifie, localise ou profile une personne est inactive tant qu'elle ne l'active pas | Aucun traceur facultatif ne s'exécute avant le consentement. |
| Accès, rectification et portabilité | Un membre télécharge ses données depuis ses préférences, en archive ZIP avec un fichier JSON par module ([preferences.md](preferences.md)). |
| Détruire les renseignements une fois leur fin accomplie | Des règles de conservation, purgées par une tâche planifiée ([retention.md](retention.md)). |
| Un registre de chaque incident de confidentialité, et des avis quand il existe un risque de préjudice sérieux (articles 3.5 à 3.8 de la loi) | Le registre et ses commandes, qui nomment les avis encore dus ([incidents.md](incidents.md)). |
| Les huit paragraphes de chaque ligne de ce registre, tenus à jour et gardés au moins cinq ans après la découverte (*Règlement sur les incidents de confidentialité*, chapitre A-2.1, r. 3.1, articles 7 et 8) | Un champ pour chaque paragraphe, les paragraphes encore manquants nommés après chaque inscription, les corrections gardées avec leurs valeurs précédentes, une règle de conservation de moins de cinq ans refusée à la construction et une purge qui ne descend jamais sous cinq ans ([incidents.md](incidents.md#ce-que-demande-le-québec)). |
| Savoir ce qui est détenu et où | Le registre des traitements, tiré du calendrier de conservation et des champs déclarés ([export-and-erasure.md](export-and-erasure.md#le-registre-des-traitements)). |

### L'Union européenne : le RGPD

| La règle | Ce que fait le bundle par défaut |
|---|---|
| Un consentement libre, spécifique, éclairé et révocable (articles 4 et 7) | Un choix par catégorie ou par fin, refusé par défaut, retiré par le même formulaire. |
| Droit d'accès et à la portabilité (articles 15 et 20) | L'export du membre, actif par défaut ; la demande d'une personne sans compte, une fois activée ([requests-without-an-account.md](requests-without-an-account.md)). |
| Droit à l'effacement (article 17) | La suppression du compte, active par défaut, avec un délai pendant lequel on peut l'annuler ([account-deletion.md](account-deletion.md)). |
| Registre des activités de traitement (article 30) | `phil:privacy:register`, et son mode `--strict` pour l'intégration continue. |
| Limitation de la conservation (article 5(1)(e)) | Des règles de conservation et leurs purges. |
| Violations de données personnelles (articles 33 et 34) | Le registre des incidents ; l'avis à l'autorité est dû dans les 72 heures suivant la prise de connaissance d'une violation qui présente un risque. |
| Protection des données dès la conception et par défaut (article 25) | Le masquage des journaux avant l'écriture d'une ligne, des copies anonymisées pour le développement ([log-redaction.md](log-redaction.md), [anonymization.md](anonymization.md)). |

Désactiver un droit demande la phrase complète de [preferences.md](preferences.md#désactiver-un-droit) et avertit à
chaque construction et avant chaque commande : la loi de la plupart des endroits exige les deux.

## Ce qui est proposé : l'Omnibus numérique

En novembre 2025, la Commission européenne a proposé un *Omnibus numérique*, un ensemble de modifications du RGPD et
des règles ePrivacy sur les traceurs. C'est une proposition, non une loi : son texte peut changer avant son adoption, et
rien dans le bundle n'en dépend. Parmi ses propositions : déplacer dans le RGPD les règles sur la lecture et le stockage
d'information sur un appareil, exempter de consentement quelques fins à faible risque comme la mesure d'audience
agrégée, refuser en un clic, ne pas redemander pendant un temps après un refus, et respecter un choix exprimé une seule
fois par le navigateur, de façon lisible par machine. Les valeurs par défaut du bundle — refus par défaut, refus en un
clic, Global Privacy Control — vont déjà dans ce sens.

## Ce que la configuration laisse au site

- Les catégories de témoins et leurs textes, et la version de chaque texte.
- Les durées de conservation, les fins, les destinataires et les sous-traitants du registre.
- Si une personne sans compte peut demander par courriel, et si les courriels sont suivis.
- Si un risque est sérieux, et les avis eux-mêmes.
