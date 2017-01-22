<?php

// This file is part of the originalcert module for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * originalcert module core interaction API
 *
 * @package    mod_originalcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add originalcert instance.
 *
 * @param stdClass $originalcert
 * @return int new originalcert instance id
 */
function originalcert_add_instance($originalcert) {
    global $DB;

    // Create the originalcert.
    $originalcert->timecreated = time();
    $originalcert->timemodified = $originalcert->timecreated;

    return $DB->insert_record('originalcert', $originalcert);
}

/**
 * Update originalcert instance.
 *
 * @param stdClass $originalcert
 * @return bool true
 */
function originalcert_update_instance($originalcert) {
    global $DB;

    // Update the originalcert.
    $originalcert->timemodified = time();
    $originalcert->id = $originalcert->instance;

    return $DB->update_record('originalcert', $originalcert);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool true if successful
 */
function originalcert_delete_instance($id) {
    global $DB;

    // Ensure the originalcert exists
    if (!$originalcert = $DB->get_record('originalcert', array('id' => $id))) {
        return false;
    }

    // Prepare file record object
    if (!$cm = get_coursemodule_from_instance('originalcert', $id)) {
        return false;
    }

    $result = true;
    $DB->delete_records('originalcert_issues', array('originalcertid' => $id));
    if (!$DB->delete_records('originalcert', array('id' => $id))) {
        $result = false;
    }

    // Delete any files associated with the originalcert
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    return $result;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified originalcert
 * and clean up any related data.
 *
 * Written by Jean-Michel Vedrine
 *
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function originalcert_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'originalcert');
    $status = array();

    if (!empty($data->reset_originalcert)) {
        $sql = "SELECT cert.id
                  FROM {originalcert} cert
                 WHERE cert.course = :courseid";
        $params = array('courseid' => $data->courseid);
        $originalcerts = $DB->get_records_sql($sql, $params);
        $fs = get_file_storage();
        if ($originalcerts) {
            foreach ($originalcerts as $certid => $unused) {
                if (!$cm = get_coursemodule_from_instance('originalcert', $certid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_originalcert', 'issue');
            }
        }

        $DB->delete_records_select('originalcert_issues', "originalcertid IN ($sql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('removecert', 'originalcert'), 'error' => false);
    }
    // Updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('originalcert', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);
    }

    return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the originalcert.
 *
 * Written by Jean-Michel Vedrine
 *
 * @param $mform form passed by reference
 */
function originalcert_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'originalcertheader', get_string('modulenameplural', 'originalcert'));
    $mform->addElement('advcheckbox', 'reset_originalcert', get_string('deletissuedoriginalcerts', 'originalcert'));
}

/**
 * Course reset form defaults.
 *
 * Written by Jean-Michel Vedrine
 *
 * @param stdClass $course
 * @return array
 */
function originalcert_reset_course_form_defaults($course) {
    return array('reset_originalcert' => 1);
}

/**
 * Returns information about received originalcert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $originalcert
 * @return stdClass the user outline object
 */
function originalcert_user_outline($course, $user, $mod, $originalcert) {
    global $DB;

    $result = new stdClass;
    if ($issue = $DB->get_record('originalcert_issues', array('originalcertid' => $originalcert->id, 'userid' => $user->id))) {
        $result->info = get_string('issued', 'originalcert');
        $result->time = $issue->timecreated;
    } else {
        $result->info = get_string('notissued', 'originalcert');
    }

    return $result;
}

/**
 * Returns information about received originalcert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $originalcert
 * @return string the user complete information
 */
function originalcert_user_complete($course, $user, $mod, $originalcert) {
    global $DB, $OUTPUT, $CFG;
    require_once($CFG->dirroot.'/mod/originalcert/locallib.php');

    if ($issue = $DB->get_record('originalcert_issues', array('originalcertid' => $originalcert->id, 'userid' => $user->id))) {
        echo $OUTPUT->box_start();
        echo get_string('issued', 'originalcert') . ": ";
        echo userdate($issue->timecreated);
        $cm = get_coursemodule_from_instance('originalcert', $originalcert->id, $course->id);
        originalcert_print_user_files($originalcert, $user->id, context_module::instance($cm->id)->id);
        echo '<br />';
        echo $OUTPUT->box_end();
    } else {
        print_string('notissuedyet', 'originalcert');
    }
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of originalcert.
 *
 * @param int $originalcertid
 * @return stdClass list of participants
 */
function originalcert_get_participants($originalcertid) {
    global $DB;

    $sql = "SELECT DISTINCT u.id, u.id
              FROM {user} u, {originalcert_issues} a
             WHERE a.originalcertid = :originalcertid
               AND u.id = a.userid";
    return  $DB->get_records_sql($sql, array('originalcertid' => $originalcertid));
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function originalcert_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Serves originalcert issues and other files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool|nothing false if file not found, does not return anything if found - just send the file
 */
function originalcert_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if (!$originalcert = $DB->get_record('originalcert', array('id' => $cm->instance))) {
        return false;
    }

    require_login($course, false, $cm);

    require_once($CFG->libdir.'/filelib.php');

    $certrecord = (int)array_shift($args);

    if (!$certrecord = $DB->get_record('originalcert_issues', array('id' => $certrecord))) {
        return false;
    }

    $canmanageoriginalcert = has_capability('mod/originalcert:manage', $context);
    if ($USER->id != $certrecord->userid and !$canmanageoriginalcert) {
        return false;
    }

    if ($filearea === 'issue') {
        $relativepath = implode('/', $args);
        $fullpath = "/{$context->id}/mod_originalcert/issue/$certrecord->id/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    } else if ($filearea === 'onthefly') {
        require_once($CFG->dirroot.'/mod/originalcert/locallib.php');
        require_once("$CFG->libdir/pdflib.php");

        if (!$originalcert = $DB->get_record('originalcert', array('id' => $certrecord->originalcertid))) {
            return false;
        }

        if ($originalcert->requiredtime && !$canmanageoriginalcert) {
            if (originalcert_get_course_time($course->id) < ($originalcert->requiredtime * 60)) {
                return false;
            }
        }

        // Load the specific originalcert type. It will fill the $pdf var.
        require("$CFG->dirroot/mod/originalcert/type/$originalcert->originalcerttype/originalcert.php");
        $filename = originalcert_get_originalcert_filename($originalcert, $cm, $course) . '.pdf';
        $filecontents = $pdf->Output('', 'S');
        send_file($filecontents, $filename, 0, 0, true, true, 'application/pdf');
    }
}

/**
 * Used for course participation report (in case originalcert is added).
 *
 * @return array
 */
function originalcert_get_view_actions() {
    return array('view', 'view all', 'view report');
}

/**
 * Used for course participation report (in case originalcert is added).
 *
 * @return array
 */
function originalcert_get_post_actions() {
    return array('received');
}
