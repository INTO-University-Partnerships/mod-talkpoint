<?php

defined('MOODLE_INTERNAL') || die();

return <<<SQL
    FROM {talkpoint_talkpoint} tt
    INNER JOIN {user} u ON u.id = tt.userid AND u.deleted = 0
    WHERE tt.instanceid = :instanceid
        AND (tt.userid = :userid OR NOT EXISTS (
            SELECT tvc.*
            FROM {talkpoint_video_conversion} tvc
            WHERE tvc.talkpointid = tt.id
        ))
SQL;
