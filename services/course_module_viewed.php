<?php

defined('MOODLE_INTERNAL') || die();

$app['course_module_viewed'] = $app->protect(function (stdClass $cm, stdClass $instance, stdClass $course, stdClass $context) {
    $event = \mod_talkpoint\event\course_module_viewed::create(array(
        'objectid' => $cm->id,
        'context' => $context,
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('talkpoint', $instance);
    $event->add_record_snapshot('course', $course);
    $event->trigger();
});
