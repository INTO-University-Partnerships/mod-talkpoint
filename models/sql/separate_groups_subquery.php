<?php

defined('MOODLE_INTERNAL') || die();

return <<<SQL
    SELECT g.courseid, gm1.userid
    FROM {groups} g
    INNER JOIN {groups_members} gm1 ON gm1.groupid = g.id
    INNER JOIN {groups_members} gm2 ON gm2.groupid = g.id AND gm2.userid = :userid2
    GROUP BY g.courseid, gm1.userid
SQL;
