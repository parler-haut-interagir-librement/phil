<!-- phil-dev source-sha256=e67308431feefd54982367425b59f3f1a9981d8e0f0e58b18c8fb39fcc13bfac -->
# Cours

Avec phil/courses-bundle activé et `courses.enabled` à vrai, le bundle enregistre `VirtualClassroomActivity`, le type
d'activité `virtual_classroom`, portant l'étiquette `phil_courses.activity_type`, et `CoursesCompletion`, un écouteur
d'`AttendanceUpdated`.

## L'activité

```php
$courses->addActivity($module->id, 'Live session', 'virtual_classroom', [
    'provider' => 'main',   // a provider of the configuration; the default one when absent
    'record' => true,
    'duration' => 90,       // minutes, 1 to 1440
    'threshold' => 60,      // percents of the session, 1 to 100; courses.threshold when absent
]);
```

Chaque réglage est facultatif et vérifié quand l'activité est enregistrée. L'apprenant ne la marque pas comme faite :
`completesManually()` est faux.

Au premier lancement, la salle de l'activité est planifiée, possédée par `phil_courses:<activity>`, intitulée d'après
l'activité — coupée à 64 caractères, `Live class` quand elle fait moins de 2 —; chaque lancement envoie ensuite
l'apprenant, dans une fenêtre à part, au lien d'accès de cette salle. Une salle terminée ne peut plus être lancée :
`ActivityUnavailable`. Il en va de même d'une salle sur un fournisseur qui n'est plus configuré.

## L'achèvement

Quand l'assiduité d'une salle terminée d'une activité change, `CoursesCompletion` lit le rapport de la séance et, pour
chaque participant dont la part atteint le seuil de l'activité et qui est inscrit à son cours — l'identifiant du
participant étant celui de l'apprenant —, enregistre l'activité comme complétée, à la fin de la salle. Un participant
qui n'est pas un apprenant du cours est écarté ; une nouvelle exécution n'enregistre rien de nouveau. Les enseignants
qui arrivent comme modérateurs sont comptés comme n'importe qui : ils ne complètent rien sans être inscrits.
