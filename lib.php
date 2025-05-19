<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Library of interface functions and constants for mod_customquiz.
 *
 * @package     mod_customquiz
 * @copyright   2025 Ingrid Vladimisky
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function customquiz_supports($feature) {
    switch ($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}

function customquiz_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();

    if (is_array($data->allowedcategories)) {
        $data->allowedcategories = implode(',', $data->allowedcategories);
    }

    return $DB->insert_record('customquiz', $data);
}

function customquiz_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    if (is_array($data->allowedcategories)) {
        $data->allowedcategories = implode(',', $data->allowedcategories);
    }

    return $DB->update_record('customquiz', $data);
}

function customquiz_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('customquiz', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('customquiz', ['id' => $id]);
    $DB->delete_records('customquiz_attempts', ['customquizid' => $id]); // Clean up user attempts
    return true;
}

function customquiz_scale_used($moduleinstanceid, $scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('customquiz', ['id' => $moduleinstanceid, 'grade' => -$scaleid]);
}

function customquiz_scale_used_anywhere($scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('customquiz', ['grade' => -$scaleid]);
}

function customquiz_grade_item_update($moduleinstance, $grades = null, $reset = false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = [
        'itemname' => clean_param($moduleinstance->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 10,
        'grademin'  => 0,
        'aggregationcoef' => 1.0,
        'iteminfo' => 'Solo se mostrará la nota más alta del estudiante',
        'aggregation' => GRADE_AGGREGATE_MAX
    ];

    if ($reset) {
        $item['reset'] = true;
    }

    return grade_update('mod/customquiz', $moduleinstance->course, 'mod', 'customquiz',
        $moduleinstance->id, 0, $grades, $item);
}

function customquiz_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/customquiz', $moduleinstance->course, 'mod', 'customquiz',
                        $moduleinstance->id, 0, null, ['deleted' => 1]);
}

function customquiz_update_grades($moduleinstance, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $grades = [];

    if ($userid) {
        $attempt = $DB->get_record_sql("
            SELECT *
            FROM {customquiz_attempts}
            WHERE userid = ? AND customquizid = ?
            ORDER BY score DESC
            LIMIT 1
        ", [$userid, $moduleinstance->id]);

        if ($attempt) {
            $grades[$attempt->userid] = (object)[
                'userid' => $attempt->userid,
                'rawgrade' => $attempt->score
            ];
        }

    } else {
        $attempts = $DB->get_records_sql("
            SELECT userid, MAX(score) AS score
            FROM {customquiz_attempts}
            WHERE customquizid = ?
            GROUP BY userid
        ", [$moduleinstance->id]);

        foreach ($attempts as $a) {
            $grades[$a->userid] = (object)[
                'userid' => $a->userid,
                'rawgrade' => $a->score
            ];
        }
    }

    return grade_update('mod/customquiz', $moduleinstance->course, 'mod', 'customquiz',
        $moduleinstance->id, 0, $grades);
}
