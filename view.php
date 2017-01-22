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
 * Handles viewing a originalcert
 *
 * @package    mod_originalcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("$CFG->dirroot/mod/originalcert/locallib.php");
require_once("$CFG->dirroot/mod/originalcert/deprecatedlib.php");
require_once("$CFG->libdir/pdflib.php");

$id = required_param('id', PARAM_INT);    // Course Module ID
$action = optional_param('action', '', PARAM_ALPHA);
$edit = optional_param('edit', -1, PARAM_BOOL);

if (!$cm = get_coursemodule_from_id('originalcert', $id)) {
    print_error('Course Module ID was incorrect');
}
if (!$course = $DB->get_record('course', array('id'=> $cm->course))) {
    print_error('course is misconfigured');
}
if (!$originalcert = $DB->get_record('originalcert', array('id'=> $cm->instance))) {
    print_error('course module is incorrect');
}

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/originalcert:view', $context);

$event = \mod_originalcert\event\course_module_viewed::create(array(
    'objectid' => $originalcert->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('originalcert', $originalcert);
$event->trigger();

$completion=new completion_info($course);
$completion->set_module_viewed($cm);

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/originalcert/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_title(format_string($originalcert->name));
$PAGE->set_heading(format_string($course->fullname));

if (($edit != -1) and $PAGE->user_allowed_editing()) {
     $USER->editing = $edit;
}

// Add block editing button
if ($PAGE->user_allowed_editing()) {
    $editvalue = $PAGE->user_is_editing() ? 'off' : 'on';
    $strsubmit = $PAGE->user_is_editing() ? get_string('blockseditoff') : get_string('blocksediton');
    $url = new moodle_url($CFG->wwwroot . '/mod/originalcert/view.php', array('id' => $cm->id, 'edit' => $editvalue));
    $PAGE->set_button($OUTPUT->single_button($url, $strsubmit));
}

// Check if the user can view the originalcert
if ($originalcert->requiredtime && !has_capability('mod/originalcert:manage', $context)) {
    if (originalcert_get_course_time($course->id) < ($originalcert->requiredtime * 60)) {
        $a = new stdClass;
        $a->requiredtime = $originalcert->requiredtime;
        notice(get_string('requiredtimenotmet', 'originalcert', $a), "$CFG->wwwroot/course/view.php?id=$course->id");
        die;
    }
}

// Create new originalcert record, or return existing record
$certrecord = originalcert_get_issue($course, $USER, $originalcert, $cm);

make_cache_directory('tcpdf');

// Load the specific originalcert type.
require("$CFG->dirroot/mod/originalcert/type/$originalcert->originalcerttype/originalcert.php");

if (empty($action)) { // Not displaying PDF
    echo $OUTPUT->header();

    $viewurl = new moodle_url('/mod/originalcert/view.php', array('id' => $cm->id));
    groups_print_activity_menu($cm, $viewurl);
    $currentgroup = groups_get_activity_group($cm);
    $groupmode = groups_get_activity_groupmode($cm);

    if (has_capability('mod/originalcert:manage', $context)) {
        $numusers = count(originalcert_get_issues($originalcert->id, 'ci.timecreated ASC', $groupmode, $cm));
        $url = html_writer::tag('a', get_string('vieworiginalcertviews', 'originalcert', $numusers),
            array('href' => $CFG->wwwroot . '/mod/originalcert/report.php?id=' . $cm->id));
        echo html_writer::tag('div', $url, array('class' => 'reportlink'));
    }

    if (!empty($originalcert->intro)) {
        echo $OUTPUT->box(format_module_intro('originalcert', $originalcert, $cm->id), 'generalbox', 'intro');
    }

    if ($attempts = originalcert_get_attempts($originalcert->id)) {
        echo originalcert_print_attempts($course, $originalcert, $attempts);
    }
    if ($originalcert->delivery == 0)    {
        $str = get_string('openwindow', 'originalcert');
    } elseif ($originalcert->delivery == 1)    {
        $str = get_string('opendownload', 'originalcert');
    } elseif ($originalcert->delivery == 2)    {
        $str = get_string('openemail', 'originalcert');
    }
    echo html_writer::tag('p', $str, array('style' => 'text-align:center'));
    $linkname = get_string('getoriginalcert', 'originalcert');

    $link = new moodle_url('/mod/originalcert/view.php?id='.$cm->id.'&action=get');
    $button = new single_button($link, $linkname);
    if ($originalcert->delivery != 1) {
        $button->add_action(new popup_action('click', $link, 'view' . $cm->id, array('height' => 600, 'width' => 800)));
    }

    echo html_writer::tag('div', $OUTPUT->render($button), array('style' => 'text-align:center'));
    echo $OUTPUT->footer($course);
    exit;
} else { // Output to pdf

    // No debugging here, sorry.
    $CFG->debugdisplay = 0;
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');

    $filename = originalcert_get_originalcert_filename($originalcert, $cm, $course) . '.pdf';

    // PDF contents are now in $file_contents as a string.
    $filecontents = $pdf->Output('', 'S');

    if ($originalcert->savecert == 1) {
        originalcert_save_pdf($filecontents, $certrecord->id, $filename, $context->id);
    }

    if ($originalcert->delivery == 0) {
        // Open in browser.
        send_file($filecontents, $filename, 0, 0, true, false, 'application/pdf');
    } elseif ($originalcert->delivery == 1) {
        // Force download.
        send_file($filecontents, $filename, 0, 0, true, true, 'application/pdf');
    } elseif ($originalcert->delivery == 2) {
        originalcert_email_student($course, $originalcert, $certrecord, $context, $filecontents, $filename);
        // Open in browser after sending email.
        send_file($filecontents, $filename, 0, 0, true, false, 'application/pdf');
    }
}
