<?php

defined('MOODLE_INTERNAL') || die();

$subquery = require __DIR__ . '/separate_groups_subquery.php';

return <<<SQL
    FROM {talkpoint_talkpoint} tt
    INNER JOIN {talkpoint} t ON tt.instanceid = t.id
    INNER JOIN {user} u ON u.id = tt.userid AND u.deleted = 0
    LEFT JOIN ({$subquery}) g ON g.userid = tt.userid AND g.courseid = t.course
    WHERE tt.instanceid = :instanceid
        AND (tt.userid = :userid1 OR NOT EXISTS (
            SELECT tvc.*
            FROM {talkpoint_video_conversion} tvc
            WHERE tvc.talkpointid = tt.id
        ))
        AND (tt.userid = :userid3 OR g.userid IS NOT NULL)
SQL;
