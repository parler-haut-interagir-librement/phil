<!-- phil-dev source-sha256=92f9bb67e6e185a1f9892cef18d4e4942638e76a6258108bcd21ffb09c836e6b -->
# Portée

La caisse pour le noyau du commerce.

| Partie | Contenu | État |
|---|---|---|
| Caisses | Code, nom, devise, incrément de l'argent comptant, emplacement, canal, principale ou mobile | Implémenté et testé ([register.md](register.md), [devices.md](devices.md)) |
| Sessions | Le fonds de caisse, les ventes et remboursements en argent comptant, les entrées de fonds, les sorties de fonds, les dépôts, le comptage, l'excédent ou le manque | Implémenté et testé |
| Rapport de fin de journée | Le tiroir, les paiements par méthode, les pourboires, les ventes | Implémenté et testé |
| Rapport des écarts de caisse | Les sessions fermées d'une période, excédents et manques, un seuil qui signale | Implémenté et testé ([counter.md](counter.md)) |
| Argent comptant | L'arrondi à l'incrément dans le tiroir, la monnaie rendue | Implémenté et testé |
| Méthodes enregistrées | Un terminal qui n'est pas connecté, un chèque, avec l'autorisation | Implémenté et testé |
| Terminaux connectés | Pilotés par le serveur par un adaptateur par processeur, terminaux à NIP à la caisse principale, l'adaptateur factice | Implémenté et testé ([terminals.md](terminals.md)) ; les adaptateurs des vrais processeurs sont ceux de l'application |
| Ventes en attente, pourboires | La mise en attente d'un panier, un pourboire en montant ou en part ; la vente d'un vendeur terminée à la caisse principale | Implémenté et testé |
| Mises de côté | Acompte, versements, achèvement, annulation, échéances | Implémenté et testé ([layaways.md](layaways.md)) |
| Préréglages de vente | Un canal et un groupe de clients posés sur ceux du noyau, le client identifié | Implémenté et testé ([counter.md](counter.md)) |
| Afficheur client | Ce que montre l'écran tourné vers le client, rien sur la personne | Implémenté et testé ; l'écran est celui de l'application |
| Tirages au comptoir | Le formulaire que fournit le paquet d'un tireur, saisi une fois la commande payée | Implémenté et testé |
| Billets | Un article vendable du noyau, vendu comme tout autre | Implémenté et testé |
| Ventes hors ligne | La file d'attente IndexedDB de la caisse principale, l'importation, le recalcul des prix, les écarts | Implémenté et testé ([offline.md](offline.md)) |
| Changement de caissier par NIP | Un NIP par utilisateur de l'application, haché, tentatives limitées ; désactivé par défaut | Implémenté et testé ([devices.md](devices.md)) |
| Périphériques | Tiroir-caisse, imprimante de reçus, lecteur de codes | Pas commencé : l'écran de l'application |
| Permissions du personnel | Qui peut ouvrir, rembourser, sortir des fonds | Relève de l'application, par la sécurité de Symfony |

## Les ports du noyau qu'il implémente

- `PaymentGateway` : `RegisterGateway`, pour `cash` et les méthodes enregistrées ; `TerminalGateway`, pour les terminaux
  connectés.

## Ce qu'il a ajouté au noyau

- `AdjustmentType::Tip`, un ajustement sur aucune ligne, donc non taxé.
