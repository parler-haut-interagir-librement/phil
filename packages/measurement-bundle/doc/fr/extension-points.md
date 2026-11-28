<!-- phil-dev source-sha256=884286793142f135bd45e340c1932bf6abde0579022e54d361a0078d31745de4 -->
# Points d'extension

Le bundle est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter des unités ou des familles | Ajoutez-les sous `units` dans la configuration. |
| Choisir l'unité d'un canal | Ajoutez-la sous `channels`. |
| Remplacer le registre, les préférences ou le formateur | Décorez le service `Phil\Measurement\UnitRegistry`, `Phil\Measurement\Cldr\UnitPreferences` ou `Phil\Measurement\Cldr\UnitFormatter` ; le type de formulaire, les validateurs, les filtres et le type de colonne les reçoivent. |
| Changer un message | Donnez l'option de message de la contrainte, ou traduisez son texte dans votre domaine `validators`. |
| Stocker une mesure dans deux colonnes | Mappez un `decimal` et un code d'unité : voir [Doctrine](doctrine.md#deux-colonnes-pour-trier-ou-additionner-en-sql). |
| Se passer du type de colonne | Réglez `doctrine_type: false`. |
