# Courses

With phil/courses-bundle enabled and `courses.enabled` on, the bundle registers `VirtualClassroomActivity`, the
activity type `virtual_classroom`, tagged `phil_courses.activity_type`, and `CoursesCompletion`, a listener of
`AttendanceUpdated`.

## The activity

```php
$courses->addActivity($module->id, 'Live session', 'virtual_classroom', [
    'provider' => 'main',   // a provider of the configuration; the default one when absent
    'record' => true,
    'duration' => 90,       // minutes, 1 to 1440
    'threshold' => 60,      // percents of the session, 1 to 100; courses.threshold when absent
]);
```

Every setting is optional and checked when the activity is saved. The learner does not mark it done:
`completesManually()` is false.

At the first launch, the room of the activity is scheduled, owned by `phil_courses:<activity>`, titled after the
activity — cut to 64 characters, `Live class` when shorter than 2 —; every launch then sends the learner, in a window
of its own, to the join link of that room. An ended room cannot be launched again: `ActivityUnavailable`. So is a
room on a provider no longer configured.

## The completion

When the attendance of an ended room of an activity changes, `CoursesCompletion` reads the report of the session and,
for each participant whose share reaches the activity's threshold and who is enrolled in its course — the participant's
identifier being the learner's —, records the activity as completed, at the end of the room. A participant who is not
a learner of the course is left out; a run again records nothing new. Teachers who join as moderators are counted
like anyone: they complete nothing unless enrolled.
