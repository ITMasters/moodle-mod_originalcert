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
 * originalcert module internal API,
 * this is in separate file to reduce memory use on non-originalcert pages.
 *
 * @package    mod_originalcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/originalcert/lib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/querylib.php');

/** The border image folder */
define('CERT_IMAGE_BORDER', 'borders');
/** The watermark image folder */
define('CERT_IMAGE_WATERMARK', 'watermarks');
/** The signature image folder */
define('CERT_IMAGE_SIGNATURE', 'signatures');
/** The seal image folder */
define('CERT_IMAGE_SEAL', 'seals');

/** Set CERT_PER_PAGE to 0 if you wish to display all originalcerts on the report page */
define('CERT_PER_PAGE', 30);

define('CERT_MAX_PER_PAGE', 200);


/**
 * Returns a list of teachers by group
 * for sending email alerts to teachers
 *
 * @param stdClass $originalcert
 * @param stdClass $user
 * @param stdClass $course
 * @param stdClass $cm
 * @return array the teacher array
 */
function originalcert_get_teachers($originalcert, $user, $course, $cm) {
    global $USER;

    $context = context_module::instance($cm->id);
    $potteachers = get_users_by_capability($context, 'mod/originalcert:manage',
        '', '', '', '', '', '', false, false);
    if (empty($potteachers)) {
        return array();
    }
    $teachers = array();
    if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS) {   // Separate groups are being used
        if ($groups = groups_get_all_groups($course->id, $user->id)) {  // Try to find all groups
            foreach ($groups as $group) {
                foreach ($potteachers as $t) {
                    if ($t->id == $user->id) {
                        continue; // do not send self
                    }
                    if (groups_is_member($group->id, $t->id)) {
                        $teachers[$t->id] = $t;
                    }
                }
            }
        } else {
            // user not in group, try to find teachers without group
            foreach ($potteachers as $t) {
                if ($t->id == $USER->id) {
                    continue; // do not send self
                }
                if (!groups_get_all_groups($course->id, $t->id)) { //ugly hack
                    $teachers[$t->id] = $t;
                }
            }
        }
    } else {
        foreach ($potteachers as $t) {
            if ($t->id == $USER->id) {
                continue; // do not send self
            }
            $teachers[$t->id] = $t;
        }
    }

    return $teachers;
}

/**
 * Alerts teachers by email of received originalcerts. First checks
 * whether the option to email teachers is set for this originalcert.
 *
 * @param stdClass $course
 * @param stdClass $originalcert
 * @param stdClass $certrecord
 * @param stdClass $cm course module
 */
function originalcert_email_teachers($course, $originalcert, $certrecord, $cm) {
    global $USER, $CFG, $DB;

    if ($originalcert->emailteachers == 0) {          // No need to do anything
        return;
    }

    $user = $DB->get_record('user', array('id' => $certrecord->userid));

    if ($teachers = originalcert_get_teachers($originalcert, $user, $course, $cm)) {
        $strawarded = get_string('awarded', 'originalcert');
        foreach ($teachers as $teacher) {
            $info = new stdClass;
            $info->student = fullname($USER);
            $info->course = format_string($course->fullname,true);
            $info->originalcert = format_string($originalcert->name,true);
            $info->url = $CFG->wwwroot.'/mod/originalcert/report.php?id='.$cm->id;
            $from = $USER;
            $postsubject = $strawarded . ': ' . $info->student . ' -> ' . $originalcert->name;
            $posttext = originalcert_email_teachers_text($info);
            $posthtml = ($teacher->mailformat == 1) ? originalcert_email_teachers_html($info) : '';

            @email_to_user($teacher, $from, $postsubject, $posttext, $posthtml);  // If it fails, oh well, too bad.
        }
    }
}

/**
 * Alerts others by email of received originalcerts. First checks
 * whether the option to email others is set for this originalcert.
 * Uses the email_teachers info.
 * Code suggested by Eloy Lafuente
 *
 * @param stdClass $course
 * @param stdClass $originalcert
 * @param stdClass $certrecord
 * @param stdClass $cm course module
 */
function originalcert_email_others($course, $originalcert, $certrecord, $cm) {
    global $USER, $CFG;

    if ($originalcert->emailothers) {
        $others = explode(',', $originalcert->emailothers);
        if ($others) {
            $strawarded = get_string('awarded', 'originalcert');
            foreach ($others as $other) {
                $other = trim($other);
                if (validate_email($other)) {
                    $destination = new stdClass;
                    $destination->id = 1;
                    $destination->email = $other;
                    $info = new stdClass;
                    $info->student = fullname($USER);
                    $info->course = format_string($course->fullname, true);
                    $info->originalcert = format_string($originalcert->name, true);
                    $info->url = $CFG->wwwroot.'/mod/originalcert/report.php?id='.$cm->id;
                    $from = $USER;
                    $postsubject = $strawarded . ': ' . $info->student . ' -> ' . $originalcert->name;
                    $posttext = originalcert_email_teachers_text($info);
                    $posthtml = originalcert_email_teachers_html($info);

                    @email_to_user($destination, $from, $postsubject, $posttext, $posthtml);  // If it fails, oh well, too bad.
                }
            }
        }
    }
}

/**
 * Creates the text content for emails to teachers -- needs to be finished with cron
 *
 * @param $info object The info used by the 'emailteachermail' language string
 * @return string
 */
function originalcert_email_teachers_text($info) {
    $posttext = get_string('emailteachermail', 'originalcert', $info) . "\n";

    return $posttext;
}

/**
 * Creates the html content for emails to teachers
 *
 * @param $info object The info used by the 'emailteachermailhtml' language string
 * @return string
 */
function originalcert_email_teachers_html($info) {
    $posthtml  = '<font face="sans-serif">';
    $posthtml .= '<p>' . get_string('emailteachermailhtml', 'originalcert', $info) . '</p>';
    $posthtml .= '</font>';

    return $posthtml;
}

/**
 * Sends the student their issued originalcert from moddata as an email
 * attachment.
 *
 * @param stdClass $course
 * @param stdClass $originalcert
 * @param stdClass $certrecord
 * @param stdClass $context
 * @param string $filecontents the PDF file contents
 * @param string $filename
 * @return bool Returns true if mail was sent OK and false if there was an error.
 */
function originalcert_email_student($course, $originalcert, $certrecord, $context, $filecontents, $filename) {
    global $USER;

    // Get teachers
    if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
        '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    }

    // If we haven't found a teacher yet, look for a non-editing teacher in this course.
    if (empty($teacher) && $users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
            '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    }

    // Ok, no teachers, use administrator name
    if (empty($teacher)) {
        $teacher = fullname(get_admin());
    }

    $info = new stdClass;
    $info->username = fullname($USER);
    $info->originalcert = format_string($originalcert->name, true);
    $info->course = format_string($course->fullname, true);
    $from = fullname($teacher);
    $subject = $info->course . ': ' . $info->originalcert;
    $message = get_string('emailstudenttext', 'originalcert', $info) . "\n";

    // Make the HTML version more XHTML happy  (&amp;)
    $messagehtml = text_to_html(get_string('emailstudenttext', 'originalcert', $info));

    $tempdir = make_temp_directory('originalcert/attachment');
    if (!$tempdir) {
        return false;
    }

    $tempfile = $tempdir.'/'.md5(sesskey().microtime().$USER->id.'.pdf');
    $fp = fopen($tempfile, 'w+');
    fputs($fp, $filecontents);
    fclose($fp);

    $prevabort = ignore_user_abort(true);
    $result = email_to_user($USER, $from, $subject, $message, $messagehtml, $tempfile, $filename);
    @unlink($tempfile);
    ignore_user_abort($prevabort);

    return $result;
}

/**
 * This function returns success or failure of file save
 *
 * @param string $pdf is the string contents of the pdf
 * @param int $certrecordid the originalcert issue record id
 * @param string $filename pdf filename
 * @param int $contextid context id
 * @return bool return true if successful, false otherwise
 */
function originalcert_save_pdf($pdf, $certrecordid, $filename, $contextid) {
    global $USER;

    if (empty($certrecordid)) {
        return false;
    }

    if (empty($pdf)) {
        return false;
    }

    $fs = get_file_storage();

    // Prepare file record object
    $component = 'mod_originalcert';
    $filearea = 'issue';
    $filepath = '/';
    $fileinfo = array(
        'contextid' => $contextid,   // ID of context
        'component' => $component,   // usually = table name
        'filearea'  => $filearea,     // usually = table name
        'itemid'    => $certrecordid,  // usually = ID of row in table
        'filepath'  => $filepath,     // any path beginning and ending in /
        'filename'  => $filename,    // any filename
        'mimetype'  => 'application/pdf',    // any filename
        'userid'    => $USER->id);

    // We do not know the previous file name, better delete everything here,
    // luckily there is supposed to be always only one originalcert here.
    $fs->delete_area_files($contextid, $component, $filearea, $certrecordid);

    $fs->create_file_from_string($fileinfo, $pdf);

    return true;
}

/**
 * Produces a list of links to the issued originalcerts.  Used for report.
 *
 * @param stdClass $originalcert
 * @param int $userid
 * @param int $contextid
 * @return string return the user files
 */
function originalcert_print_user_files($originalcert, $userid, $contextid) {
    global $CFG, $DB, $OUTPUT;

    $output = '';

    $certrecord = $DB->get_record('originalcert_issues', array('userid' => $userid, 'originalcertid' => $originalcert->id));
    $fs = get_file_storage();

    $component = 'mod_originalcert';
    $filearea = 'issue';
    $files = $fs->get_area_files($contextid, $component, $filearea, $certrecord->id);
    foreach ($files as $file) {
        $filename = $file->get_filename();
        $link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$contextid.'/mod_originalcert/issue/'.$certrecord->id.'/'.$filename);

        $output = '<img src="'.$OUTPUT->pix_url(file_mimetype_icon($file->get_mimetype())).'" height="16" width="16" alt="'.$file->get_mimetype().'" />&nbsp;'.
            '<a href="'.$link.'" >'.s($filename).'</a>';

    }
    $output .= '<br />';
    $output = '<div class="files">'.$output.'</div>';

    return $output;
}

/**
 * Inserts preliminary user data when a originalcert is viewed.
 * Prevents form from issuing a originalcert upon browser refresh.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $originalcert
 * @param stdClass $cm
 * @return stdClass the newly created originalcert issue
 */
function originalcert_get_issue($course, $user, $originalcert, $cm) {
    global $DB;

    // Check if there is an issue already, should only ever be one
    if ($certissue = $DB->get_record('originalcert_issues', array('userid' => $user->id, 'originalcertid' => $originalcert->id))) {
        return $certissue;
    }

    // Create new originalcert issue record
    $certissue = new stdClass();
    $certissue->originalcertid = $originalcert->id;
    $certissue->userid = $user->id;
    $certissue->code = originalcert_generate_code();
    $certissue->timecreated =  time();
    $certissue->id = $DB->insert_record('originalcert_issues', $certissue);

    // Email to the teachers and anyone else
    originalcert_email_teachers($course, $originalcert, $certissue, $cm);
    originalcert_email_others($course, $originalcert, $certissue, $cm);

    return $certissue;
}

/**
 * Returns a list of issued originalcerts - sorted for report.
 *
 * @param int $originalcertid
 * @param string $sort the sort order
 * @param bool $groupmode are we in group mode ?
 * @param stdClass $cm the course module
 * @param int $page offset
 * @param int $perpage total per page
 * @return stdClass the users
 */
function originalcert_get_issues($originalcertid, $sort="ci.timecreated ASC", $groupmode, $cm, $page = 0, $perpage = 0) {
    global $DB, $USER;

    $context = context_module::instance($cm->id);
    $conditionssql = '';
    $conditionsparams = array();

    // Get all users that can manage this originalcert to exclude them from the report.
    $certmanagers = array_keys(get_users_by_capability($context, 'mod/originalcert:manage', 'u.id'));
    $certmanagers = array_merge($certmanagers, array_keys(get_admins()));
    list($sql, $params) = $DB->get_in_or_equal($certmanagers, SQL_PARAMS_NAMED, 'cert');
    $conditionssql .= "AND NOT u.id $sql \n";
    $conditionsparams += $params;

    if ($groupmode) {
        $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
        $currentgroup = groups_get_activity_group($cm);

        // If we are viewing all participants and the user does not have access to all groups then return nothing.
        if (!$currentgroup && !$canaccessallgroups) {
            return array();
        }

        if ($currentgroup) {
            if (!$canaccessallgroups) {
                // Guest users do not belong to any groups.
                if (isguestuser()) {
                    return array();
                }

                // Check that the user belongs to the group we are viewing.
                $usersgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
                if ($usersgroups) {
                    if (!isset($usersgroups[$currentgroup])) {
                        return array();
                    }
                } else { // They belong to no group, so return an empty array.
                    return array();
                }
            }

            $groupusers = array_keys(groups_get_members($currentgroup, 'u.*'));
            if (empty($groupusers)) {
                return array();
            }

            list($sql, $params) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED, 'grp');
            $conditionssql .= "AND u.id $sql ";
            $conditionsparams += $params;
        }
    }

    $page = (int) $page;
    $perpage = (int) $perpage;

    // Get all the users that have originalcerts issued, should only be one issue per user for a originalcert
    $allparams = $conditionsparams + array('originalcertid' => $originalcertid);

    // The picture fields also include the name fields for the user.
    $picturefields = user_picture::fields('u', get_extra_user_fields($context));
    $users = $DB->get_records_sql("SELECT $picturefields, u.idnumber, ci.code, ci.timecreated
                                     FROM {user} u
                               INNER JOIN {originalcert_issues} ci
                                       ON u.id = ci.userid
                                    WHERE u.deleted = 0
                                      AND ci.originalcertid = :originalcertid $conditionssql
                                 ORDER BY {$sort}", $allparams, $page * $perpage, $perpage);

    return $users;
}

/**
 * Returns a list of previously issued originalcerts--used for reissue.
 *
 * @param int $originalcertid
 * @return stdClass the attempts else false if none found
 */
function originalcert_get_attempts($originalcertid) {
    global $DB, $USER;

    $sql = "SELECT *
              FROM {originalcert_issues} i
             WHERE originalcertid = :originalcertid
               AND userid = :userid";
    if ($issues = $DB->get_records_sql($sql, array('originalcertid' => $originalcertid, 'userid' => $USER->id))) {
        return $issues;
    }

    return false;
}

/**
 * Prints a table of previously issued originalcerts--used for reissue.
 *
 * @param stdClass $course
 * @param stdClass $originalcert
 * @param stdClass $attempts
 * @return string the attempt table
 */
function originalcert_print_attempts($course, $originalcert, $attempts) {
    global $OUTPUT;

    echo $OUTPUT->heading(get_string('summaryofattempts', 'originalcert'));

    // Prepare table header
    $table = new html_table();
    $table->class = 'generaltable';
    $table->head = array(get_string('issued', 'originalcert'));
    $table->align = array('left');
    $table->attributes = array("style" => "width:20%; margin:auto");
    $gradecolumn = $originalcert->printgrade;
    if ($gradecolumn) {
        $table->head[] = get_string('grade');
        $table->align[] = 'center';
        $table->size[] = '';
    }
    // One row for each attempt
    foreach ($attempts as $attempt) {
        $row = array();

        // prepare strings for time taken and date completed
        $datecompleted = userdate($attempt->timecreated);
        $row[] = $datecompleted;

        if ($gradecolumn) {
            $attemptgrade = originalcert_get_grade($originalcert, $course);
            $row[] = $attemptgrade;
        }

        $table->data[$attempt->id] = $row;
    }

    echo html_writer::table($table);
}

/**
 * Get the time the user has spent in the course
 *
 * @param int $courseid
 * @return int the total time spent in seconds
 */
function originalcert_get_course_time($courseid) {
    global $CFG, $DB, $USER;

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers();
    $enabledreaders = get_config('tool_log', 'enabled_stores');
    $enabledreaders = explode(',', $enabledreaders);

    // Go through all the readers until we find one that we can use.
    foreach ($enabledreaders as $enabledreader) {
        $reader = $readers[$enabledreader];
        if ($reader instanceof \logstore_legacy\log\store) {
            $logtable = 'log';
            $coursefield = 'course';
            $timefield = 'time';
            break;
        } else if ($reader instanceof \core\log\sql_internal_table_reader) {
            $logtable = $reader->get_internal_log_table_name();
            $coursefield = 'courseid';
            $timefield = 'timecreated';
            break;
        }
    }

    // If we didn't find a reader then return 0.
    if (!isset($logtable)) {
        return 0;
    }

    $sql = "SELECT id, $timefield
              FROM {{$logtable}}
             WHERE userid = :userid
               AND $coursefield = :courseid
          ORDER BY $timefield ASC";
    $params = array('userid' => $USER->id, 'courseid' => $courseid);

    $totaltime = 0;
    if ($logs = $DB->get_recordset_sql($sql, $params)) {
        foreach ($logs as $log) {
            if (!isset($login)) {
                // For the first time $login is not set so the first log is also the first login
                $login = $log->$timefield;
                $lasthit = $log->$timefield;
                $totaltime = 0;
            }
            $delay = $log->$timefield - $lasthit;
            if ($delay > ($CFG->sessiontimeout * 60)) {
                // The difference between the last log and the current log is more than
                // the timeout Register session value so that we have found a session!
                $login = $log->$timefield;
            } else {
                $totaltime += $delay;
            }
            // Now the actual log became the previous log for the next cycle
            $lasthit = $log->$timefield;
        }

        return $totaltime;
    }

    return 0;
}

/**
 * Get all the modules
 *
 * @return array
 */
function originalcert_get_mods() {
    global $COURSE, $DB;

    $strtopic = get_string("topic");
    $strweek = get_string("week");
    $strsection = get_string("section");

    // Collect modules data
    $modinfo = get_fast_modinfo($COURSE);
    $mods = $modinfo->get_cms();

    $modules = array();
    $sections = $modinfo->get_section_info_all();
    for ($i = 0; $i <= count($sections) - 1; $i++) {
        // should always be true
        if (isset($sections[$i])) {
            $section = $sections[$i];
            if ($section->sequence) {
                switch ($COURSE->format) {
                    case "topics":
                        $sectionlabel = $strtopic;
                        break;
                    case "weeks":
                        $sectionlabel = $strweek;
                        break;
                    default:
                        $sectionlabel = $strsection;
                }

                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    if (empty($mods[$sectionmod])) {
                        continue;
                    }
                    $mod = $mods[$sectionmod];
                    $instance = $DB->get_record($mod->modname, array('id' => $mod->instance));
                    if ($grade_items = grade_get_grade_items_for_activity($mod)) {
                        $mod_item = grade_get_grades($COURSE->id, 'mod', $mod->modname, $mod->instance);
                        $item = reset($mod_item->items);
                        if (isset($item->grademax)){
                            $modules[$mod->id] = $sectionlabel . ' ' . $section->section . ' : ' . $instance->name;
                        }
                    }
                }
            }
        }
    }

    return $modules;
}

/**
 * Search through all the modules for grade data for mod_form.
 *
 * @return array
 */
function originalcert_get_grade_options() {
    $gradeoptions['0'] = get_string('no');
    $gradeoptions['1'] = get_string('coursegrade', 'originalcert');

    return $gradeoptions;
}

/**
 * Search through all the modules for grade dates for mod_form.
 *
 * @return array
 */
function originalcert_get_date_options() {
    $dateoptions['0'] = get_string('no');
    $dateoptions['1'] = get_string('issueddate', 'originalcert');
    $dateoptions['2'] = get_string('completiondate', 'originalcert');

    return $dateoptions;
}

/**
 * Fetch all grade categories from the specified course.
 *
 * @param int $courseid the course id
 * @return array
 */
function originalcert_get_grade_categories($courseid) {
    $grade_category_options = array();

    if ($grade_categories = grade_category::fetch_all(array('courseid' => $courseid))) {
        foreach ($grade_categories as $grade_category) {
            if (!$grade_category->is_course_category()) {
                $grade_category_options[-$grade_category->id] = get_string('category') . ' : ' . $grade_category->get_name();
            }
        }
    }

    return $grade_category_options;
}

/**
 * Get the course outcomes for for mod_form print outcome.
 *
 * @return array
 */
function originalcert_get_outcomes() {
    global $COURSE;

    // get all outcomes in course
    $grade_seq = new grade_tree($COURSE->id, false, true, '', false);
    if ($grade_items = $grade_seq->items) {
        // list of item for menu
        $printoutcome = array();
        foreach ($grade_items as $grade_item) {
            if (isset($grade_item->outcomeid)){
                $itemmodule = $grade_item->itemmodule;
                $printoutcome[$grade_item->id] = $itemmodule . ': ' . $grade_item->get_name();
            }
        }
    }
    if (isset($printoutcome)) {
        $outcomeoptions['0'] = get_string('no');
        foreach ($printoutcome as $key => $value) {
            $outcomeoptions[$key] = $value;
        }
    } else {
        $outcomeoptions['0'] = get_string('nooutcomes', 'originalcert');
    }

    return $outcomeoptions;
}


/**
 * Get originalcert types indexed and sorted by name for mod_form.
 *
 * @return array containing the originalcert type
 */
function originalcert_types() {
    $types = array();
    $names = get_list_of_plugins('mod/originalcert/type');
    $sm = get_string_manager();
    foreach ($names as $name) {
        if ($sm->string_exists('type'.$name, 'originalcert')) {
            $types[$name] = get_string('type'.$name, 'originalcert');
        } else {
            $types[$name] = ucfirst($name);
        }
    }
    asort($types);
    return $types;
}

/**
 * Get images for mod_form.
 *
 * @param string $type the image type
 * @return array
 */
function originalcert_get_images($type) {
    global $CFG;

    switch($type) {
        case CERT_IMAGE_BORDER :
            $path = "$CFG->dirroot/mod/originalcert/pix/borders";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/borders";
            break;
        case CERT_IMAGE_SEAL :
            $path = "$CFG->dirroot/mod/originalcert/pix/seals";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/seals";
            break;
        case CERT_IMAGE_SIGNATURE :
            $path = "$CFG->dirroot/mod/originalcert/pix/signatures";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/signatures";
            break;
        case CERT_IMAGE_WATERMARK :
            $path = "$CFG->dirroot/mod/originalcert/pix/watermarks";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/watermarks";
            break;
    }
    // If valid path
    if (!empty($path)) {
        $options = array();
        $options += originalcert_scan_image_dir($path);
        $options += originalcert_scan_image_dir($uploadpath);

        // Sort images
        ksort($options);

        // Add the 'no' option to the top of the array
        $options = array_merge(array('0' => get_string('no')), $options);

        return $options;
    } else {
        return array();
    }
}

/**
 * Prepare to print an activity grade.
 *
 * @param stdClass $course
 * @param int $moduleid
 * @param int $userid
 * @return stdClass|bool return the mod object if it exists, false otherwise
 */
function originalcert_get_mod_grade($course, $moduleid, $userid) {
    global $DB;

    $cm = $DB->get_record('course_modules', array('id' => $moduleid));
    $module = $DB->get_record('modules', array('id' => $cm->module));

    $grade_item = grade_get_grades($course->id, 'mod', $module->name, $cm->instance, $userid);
    if (!empty($grade_item)) {
        $item = new grade_item();
        $itemproperties = reset($grade_item->items);
        foreach ($itemproperties as $key => $value) {
            $item->$key = $value;
        }
        $modinfo = new stdClass;
        $modname = $DB->get_field($module->name, 'name', array('id' => $cm->instance));
        $modinfo->name = format_string($modname, true, array('context' => context_module::instance($cm->id)));
        $grade = $item->grades[$userid]->grade;
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->courseid = $course->id;

        $modinfo->points = grade_format_gradevalue($grade, $item, true, GRADE_DISPLAY_TYPE_REAL, $decimals = 2);
        $modinfo->percentage = grade_format_gradevalue($grade, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, $decimals = 2);
        $modinfo->letter = grade_format_gradevalue($grade, $item, true, GRADE_DISPLAY_TYPE_LETTER, $decimals = 0);

        if ($grade) {
            $modinfo->dategraded = $item->grades[$userid]->dategraded;
        } else {
            $modinfo->dategraded = time();
        }
        return $modinfo;
    }

    return false;
}

/**
 * Returns the date to display for the originalcert.
 *
 * @param stdClass $originalcert
 * @param stdClass $certrecord
 * @param stdClass $course
 * @param int $userid
 * @return string the date
 */
function originalcert_get_date($originalcert, $certrecord, $course, $userid = null) {
    global $DB, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Set originalcert date to current time, can be overwritten later
    $date = $certrecord->timecreated;

    if ($originalcert->printdate == '2') {
        // Get the enrolment end date
        $sql = "SELECT MAX(c.timecompleted) as timecompleted
                  FROM {course_completions} c
                 WHERE c.userid = :userid
                   AND c.course = :courseid";
        if ($timecompleted = $DB->get_record_sql($sql, array('userid' => $userid, 'courseid' => $course->id))) {
            if (!empty($timecompleted->timecompleted)) {
                $date = $timecompleted->timecompleted;
            }
        }
    } else if ($originalcert->printdate > 2) {
        if ($modinfo = originalcert_get_mod_grade($course, $originalcert->printdate, $userid)) {
            $date = $modinfo->dategraded;
        }
    }
    if ($originalcert->printdate > 0) {
        if ($originalcert->datefmt == 1) {
            $originalcertdate = userdate($date, '%B %d, %Y');
        } else if ($originalcert->datefmt == 2) {
            $suffix = originalcert_get_ordinal_number_suffix(userdate($date, '%d'));
            $originalcertdate = userdate($date, '%B %d' . $suffix . ', %Y');
        } else if ($originalcert->datefmt == 3) {
            $originalcertdate = userdate($date, '%d %B %Y');
        } else if ($originalcert->datefmt == 4) {
            $originalcertdate = userdate($date, '%B %Y');
        } else if ($originalcert->datefmt == 5) {
            $originalcertdate = userdate($date, get_string('strftimedate', 'langconfig'));
        }

        return $originalcertdate;
    }

    return '';
}

/**
 * Helper function to return the suffix of the day of
 * the month, eg 'st' if it is the 1st of the month.
 *
 * @param int the day of the month
 * @return string the suffix.
 */
function originalcert_get_ordinal_number_suffix($day) {
    if (!in_array(($day % 100), array(11, 12, 13))) {
        switch ($day % 10) {
            // Handle 1st, 2nd, 3rd
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
        }
    }
    return 'th';
}

/**
 * Returns the grade to display for the originalcert.
 *
 * @param stdClass $originalcert
 * @param stdClass $course
 * @param int $userid
 * @param bool $valueonly if true return only the points, %age, or letter with no prefix
 * @return string the grade result
 */
function originalcert_get_grade($originalcert, $course, $userid = null, $valueonly = false) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if ($originalcert->printgrade > 0) {
        if ($originalcert->printgrade == 1) {
            if ($course_item = grade_item::fetch_course_item($course->id)) {
                // Check we want to add a prefix to the grade.
                $strprefix = '';
                if (!$valueonly) {
                    $strprefix = get_string('coursegrade', 'originalcert') . ': ';
                }

                $grade = new grade_grade(array('itemid' => $course_item->id, 'userid' => $userid));
                $course_item->gradetype = GRADE_TYPE_VALUE;
                $coursegrade = new stdClass;
                $coursegrade->points = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_REAL, $decimals = 2);
                $coursegrade->percentage = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, $decimals = 2);
                $coursegrade->letter = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_LETTER, $decimals = 0);

                if ($originalcert->gradefmt == 1) {
                    $grade = $strprefix . $coursegrade->percentage;
                } else if ($originalcert->gradefmt == 2) {
                    $grade = $strprefix . $coursegrade->points;
                } else if ($originalcert->gradefmt == 3) {
                    $grade = $strprefix . $coursegrade->letter;
                }

                return $grade;
            }
        } else { // Print the mod grade
            if ($modinfo = originalcert_get_mod_grade($course, $originalcert->printgrade, $userid)) {
                // Check we want to add a prefix to the grade.
                $strprefix = '';
                if (!$valueonly) {
                    $strprefix = $modinfo->name . ' ' . get_string('grade', 'originalcert') . ': ';
                }
                if ($originalcert->gradefmt == 1) {
                    $grade = $strprefix . $modinfo->percentage;
                } else if ($originalcert->gradefmt == 2) {
                    $grade = $strprefix . $modinfo->points;
                } else if ($originalcert->gradefmt == 3) {
                    $grade = $strprefix . $modinfo->letter;
                }

                return $grade;
            }
        }
    } else if ($originalcert->printgrade < 0) { // Must be a category id.
        if ($category_item = grade_item::fetch(array('itemtype' => 'category', 'iteminstance' => -$originalcert->printgrade))) {
            $category_item->gradetype = GRADE_TYPE_VALUE;

            $grade = new grade_grade(array('itemid' => $category_item->id, 'userid' => $userid));

            $category_grade = new stdClass;
            $category_grade->points = grade_format_gradevalue($grade->finalgrade, $category_item, true, GRADE_DISPLAY_TYPE_REAL, $decimals = 2);
            $category_grade->percentage = grade_format_gradevalue($grade->finalgrade, $category_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, $decimals = 2);
            $category_grade->letter = grade_format_gradevalue($grade->finalgrade, $category_item, true, GRADE_DISPLAY_TYPE_LETTER, $decimals = 0);

            if ($originalcert->gradefmt == 1) {
                $formattedgrade = $category_grade->percentage;
            } else if ($originalcert->gradefmt == 2) {
                $formattedgrade = $category_grade->points;
            } else if ($originalcert->gradefmt == 3) {
                $formattedgrade = $category_grade->letter;
            }

            return $formattedgrade;
        }
    }

    return '';
}

/**
 * Returns the outcome to display on the originalcert
 *
 * @param stdClass $originalcert
 * @param stdClass $course
 * @return string the outcome
 */
function originalcert_get_outcome($originalcert, $course) {
    global $USER;

    if ($originalcert->printoutcome > 0) {
        if ($grade_item = new grade_item(array('id' => $originalcert->printoutcome))) {
            $outcomeinfo = new stdClass;
            $outcomeinfo->name = $grade_item->get_name();
            $outcome = new grade_grade(array('itemid' => $grade_item->id, 'userid' => $USER->id));
            $outcomeinfo->grade = grade_format_gradevalue($outcome->finalgrade, $grade_item, true, GRADE_DISPLAY_TYPE_REAL);

            return $outcomeinfo->name . ': ' . $outcomeinfo->grade;
        }
    }

    return '';
}

/**
 * Returns the code to display on the originalcert.
 *
 * @param stdClass $originalcert
 * @param stdClass $certrecord
 * @return string the code
 */
function originalcert_get_code($originalcert, $certrecord) {
    if ($originalcert->printnumber) {
        return $certrecord->code;
    }

    return '';
}

/**
 * Sends text to output given the following params.
 *
 * @param stdClass $pdf
 * @param int $x horizontal position
 * @param int $y vertical position
 * @param char $align L=left, C=center, R=right
 * @param string $font any available font in font directory
 * @param char $style ''=normal, B=bold, I=italic, U=underline
 * @param int $size font size in points
 * @param string $text the text to print
 * @param int $width horizontal dimension of text block
 */
function originalcert_print_text($pdf, $x, $y, $align, $font='freeserif', $style, $size = 10, $text, $width = 0) {
    $pdf->setFont($font, $style, $size);
    $pdf->SetXY($x, $y);
    $pdf->writeHTMLCell($width, 0, '', '', $text, 0, 0, 0, true, $align);
}

/**
 * Creates rectangles for line border for A4 size paper.
 *
 * @param stdClass $pdf
 * @param stdClass $originalcert
 */
function originalcert_draw_frame($pdf, $originalcert) {
    if ($originalcert->bordercolor > 0) {
        if ($originalcert->bordercolor == 1) {
            $color = array(0, 0, 0); // black
        }
        if ($originalcert->bordercolor == 2) {
            $color = array(153, 102, 51); // brown
        }
        if ($originalcert->bordercolor == 3) {
            $color = array(0, 51, 204); // blue
        }
        if ($originalcert->bordercolor == 4) {
            $color = array(0, 180, 0); // green
        }
        switch ($originalcert->orientation) {
            case 'L':
                // create outer line border in selected color
                $pdf->SetLineStyle(array('width' => 1.5, 'color' => $color));
                $pdf->Rect(10, 10, 277, 190);
                // create middle line border in selected color
                $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
                $pdf->Rect(13, 13, 271, 184);
                // create inner line border in selected color
                $pdf->SetLineStyle(array('width' => 1.0, 'color' => $color));
                $pdf->Rect(16, 16, 265, 178);
                break;
            case 'P':
                // create outer line border in selected color
                $pdf->SetLineStyle(array('width' => 1.5, 'color' => $color));
                $pdf->Rect(10, 10, 190, 277);
                // create middle line border in selected color
                $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
                $pdf->Rect(13, 13, 184, 271);
                // create inner line border in selected color
                $pdf->SetLineStyle(array('width' => 1.0, 'color' => $color));
                $pdf->Rect(16, 16, 178, 265);
                break;
        }
    }
}

/**
 * Creates rectangles for line border for letter size paper.
 *
 * @param stdClass $pdf
 * @param stdClass $originalcert
 */
function originalcert_draw_frame_letter($pdf, $originalcert) {
    if ($originalcert->bordercolor > 0) {
        if ($originalcert->bordercolor == 1)    {
            $color = array(0, 0, 0); //black
        }
        if ($originalcert->bordercolor == 2)    {
            $color = array(153, 102, 51); //brown
        }
        if ($originalcert->bordercolor == 3)    {
            $color = array(0, 51, 204); //blue
        }
        if ($originalcert->bordercolor == 4)    {
            $color = array(0, 180, 0); //green
        }
        switch ($originalcert->orientation) {
            case 'L':
                // create outer line border in selected color
                $pdf->SetLineStyle(array('width' => 4.25, 'color' => $color));
                $pdf->Rect(28, 28, 736, 556);
                // create middle line border in selected color
                $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
                $pdf->Rect(37, 37, 718, 538);
                // create inner line border in selected color
                $pdf->SetLineStyle(array('width' => 2.8, 'color' => $color));
                $pdf->Rect(46, 46, 700, 520);
                break;
            case 'P':
                // create outer line border in selected color
                $pdf->SetLineStyle(array('width' => 1.5, 'color' => $color));
                $pdf->Rect(25, 20, 561, 751);
                // create middle line border in selected color
                $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
                $pdf->Rect(40, 35, 531, 721);
                // create inner line border in selected color
                $pdf->SetLineStyle(array('width' => 1.0, 'color' => $color));
                $pdf->Rect(51, 46, 509, 699);
                break;
        }
    }
}

/**
 * Prints border images from the borders folder in PNG or JPG formats.
 *
 * @param stdClass $pdf
 * @param stdClass $originalcert
 * @param string $type the type of image
 * @param int $x x position
 * @param int $y y position
 * @param int $w the width
 * @param int $h the height
 */
function originalcert_print_image($pdf, $originalcert, $type, $x, $y, $w, $h) {
    global $CFG;

    switch($type) {
        case CERT_IMAGE_BORDER :
            $attr = 'borderstyle';
            $path = "$CFG->dirroot/mod/originalcert/pix/$type/$originalcert->borderstyle";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/$type/$originalcert->borderstyle";
            break;
        case CERT_IMAGE_SEAL :
            $attr = 'printseal';
            $path = "$CFG->dirroot/mod/originalcert/pix/$type/$originalcert->printseal";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/$type/$originalcert->printseal";
            break;
        case CERT_IMAGE_SIGNATURE :
            $attr = 'printsignature';
            $path = "$CFG->dirroot/mod/originalcert/pix/$type/$originalcert->printsignature";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/$type/$originalcert->printsignature";
            break;
        case CERT_IMAGE_WATERMARK :
            $attr = 'printwmark';
            $path = "$CFG->dirroot/mod/originalcert/pix/$type/$originalcert->printwmark";
            $uploadpath = "$CFG->dataroot/mod/originalcert/pix/$type/$originalcert->printwmark";
            break;
    }
    // Has to be valid
    if (!empty($path)) {
        switch ($originalcert->$attr) {
            case '0' :
            case '' :
                break;
            default :
                if (file_exists($path)) {
                    $pdf->Image($path, $x, $y, $w, $h);
                }
                if (file_exists($uploadpath)) {
                    $pdf->Image($uploadpath, $x, $y, $w, $h);
                }
                break;
        }
    }
}

/**
 * Generates a 10-digit code of random letters and numbers.
 *
 * @return string
 */
function originalcert_generate_code() {
    global $DB;

    $uniquecodefound = false;
    $code = random_string(10);
    while (!$uniquecodefound) {
        if (!$DB->record_exists('originalcert_issues', array('code' => $code))) {
            $uniquecodefound = true;
        } else {
            $code = random_string(10);
        }
    }

    return $code;
}

/**
 * Scans directory for valid images
 *
 * @param string the path
 * @return array
 */
function originalcert_scan_image_dir($path) {
    // Array to store the images
    $options = array();

    // Start to scan directory
    if (is_dir($path)) {
        $iterator = new DirectoryIterator($path);
        foreach ($iterator as $fileinfo) {
            $filename = $fileinfo->getFilename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($fileinfo->isFile() && in_array($extension, array('png', 'jpg', 'jpeg'))) {
                $options[$filename] = pathinfo($filename, PATHINFO_FILENAME);
            }
        }
    }
    return $options;
}

/**
 * Get normalised originalcert file name without file extension.
 *
 * @param stdClass $originalcert
 * @param stdClass $cm
 * @param stdClass $course
 * @return string file name without extension
 */
function originalcert_get_originalcert_filename($originalcert, $cm, $course) {
    $coursecontext = context_course::instance($course->id);
    $coursename = format_string($course->shortname, true, array('context' => $coursecontext));

    $context = context_module::instance($cm->id);
    $name = format_string($originalcert->name, true, array('context' => $context));

    $filename = $coursename . '_' . $name;
    $filename = core_text::entities_to_utf8($filename);
    $filename = strip_tags($filename);
    $filename = rtrim($filename, '.');

    // Ampersand is not a valid filename char, let's replace it with something else.
    $filename = str_replace('&', '_', $filename);

    $filename = clean_filename($filename);

    if (empty($filename)) {
        // This is weird, but we need some file name.
        $filename = 'originalcert';
    }

    return $filename;
}
