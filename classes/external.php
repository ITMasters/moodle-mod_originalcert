<?php
// This file is part of Moodle - http://moodle.org/
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
 * originalcert module external API
 *
 * @package    mod_originalcert
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/originalcert/locallib.php');

/**
 * originalcert module external functions
 *
 * @package    mod_originalcert
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_originalcert_external extends external_api {

    /**
     * Describes the parameters for get_originalcerts_by_courses.
     *
     * @return external_function_parameters
     */
    public static function get_originalcerts_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of originalcerts in a provided list of courses,
     * if no list is provided all originalcerts that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array the originalcert details
     */
    public static function get_originalcerts_by_courses($courseids = array()) {
        global $CFG;

        $returnedoriginalcerts = array();
        $warnings = array();

        $params = self::validate_parameters(self::get_originalcerts_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            $params['courseids'] = array_keys(enrol_get_my_courses());
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids']);

            // Get the originalcerts in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $originalcerts = get_all_instances_in_courses("originalcert", $courses);

            foreach ($originalcerts as $originalcert) {

                $context = context_module::instance($originalcert->coursemodule);

                // Entry to return.
                $module = array();

                // First, we return information that any user can see in (or can deduce from) the web interface.
                $module['id'] = $originalcert->id;
                $module['coursemodule'] = $originalcert->coursemodule;
                $module['course'] = $originalcert->course;
                $module['name']  = external_format_string($originalcert->name, $context->id);

                $viewablefields = [];
                if (has_capability('mod/originalcert:view', $context)) {
                    list($module['intro'], $module['introformat']) =
                        external_format_text($originalcert->intro, $originalcert->introformat, $context->id,
                                                'mod_originalcert', 'intro', $originalcert->id);

                    // Check originalcert requeriments for current user.
                    $viewablefields[] = 'requiredtime';
                    $module['requiredtimenotmet'] = 0;
                    if ($originalcert->requiredtime && !has_capability('mod/originalcert:manage', $context)) {
                        if (originalcert_get_course_time($originalcert->course) < ($originalcert->requiredtime * 60)) {
                            $module['requiredtimenotmet'] = 1;
                        }
                    }
                }

                // Check additional permissions for returning optional private settings.
                if (has_capability('moodle/course:manageactivities', $context)) {

                    $additionalfields = array('emailteachers', 'emailothers', 'savecert',
                        'reportcert', 'delivery', 'originalcerttype', 'orientation', 'borderstyle', 'bordercolor',
                        'printwmark', 'printdate', 'datefmt', 'printnumber', 'printgrade', 'gradefmt', 'printoutcome',
                        'printhours', 'printteacher', 'customtext', 'printsignature', 'printseal', 'timecreated', 'timemodified',
                        'section', 'visible', 'groupmode', 'groupingid');
                    $viewablefields = array_merge($viewablefields, $additionalfields);

                }

                foreach ($viewablefields as $field) {
                    $module[$field] = $originalcert->{$field};
                }

                $returnedoriginalcerts[] = $module;
            }
        }

        $result = array();
        $result['originalcerts'] = $returnedoriginalcerts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_originalcerts_by_courses return value.
     *
     * @return external_single_structure
     */
    public static function get_originalcerts_by_courses_returns() {

        return new external_single_structure(
            array(
                'originalcerts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'originalcert id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_INT, 'Course id'),
                            'name' => new external_value(PARAM_RAW, 'originalcert name'),
                            'intro' => new external_value(PARAM_RAW, 'The originalcert intro', VALUE_OPTIONAL),
                            'introformat' => new external_format_value('intro', VALUE_OPTIONAL),
                            'requiredtimenotmet' => new external_value(PARAM_INT, 'Whether the time req is met', VALUE_OPTIONAL),
                            'emailteachers' => new external_value(PARAM_INT, 'Email teachers?', VALUE_OPTIONAL),
                            'emailothers' => new external_value(PARAM_RAW, 'Email others?', VALUE_OPTIONAL),
                            'savecert' => new external_value(PARAM_INT, 'Save originalcert?', VALUE_OPTIONAL),
                            'reportcert' => new external_value(PARAM_INT, 'Report originalcert?', VALUE_OPTIONAL),
                            'delivery' => new external_value(PARAM_INT, 'Delivery options', VALUE_OPTIONAL),
                            'requiredtime' => new external_value(PARAM_INT, 'Required time', VALUE_OPTIONAL),
                            'originalcerttype' => new external_value(PARAM_RAW, 'Type', VALUE_OPTIONAL),
                            'orientation' => new external_value(PARAM_ALPHANUM, 'Orientation', VALUE_OPTIONAL),
                            'borderstyle' => new external_value(PARAM_RAW, 'Border style', VALUE_OPTIONAL),
                            'bordercolor' => new external_value(PARAM_RAW, 'Border color', VALUE_OPTIONAL),
                            'printwmark' => new external_value(PARAM_RAW, 'Print water mark?', VALUE_OPTIONAL),
                            'printdate' => new external_value(PARAM_RAW, 'Print date?', VALUE_OPTIONAL),
                            'datefmt' => new external_value(PARAM_INT, 'Date format', VALUE_OPTIONAL),
                            'printnumber' => new external_value(PARAM_INT, 'Print number?', VALUE_OPTIONAL),
                            'printgrade' => new external_value(PARAM_INT, 'Print grade?', VALUE_OPTIONAL),
                            'gradefmt' => new external_value(PARAM_INT, 'Grade format', VALUE_OPTIONAL),
                            'printoutcome' => new external_value(PARAM_INT, 'Print outcome?', VALUE_OPTIONAL),
                            'printhours' => new external_value(PARAM_TEXT, 'Print hours?', VALUE_OPTIONAL),
                            'printteacher' => new external_value(PARAM_INT, 'Print teacher?', VALUE_OPTIONAL),
                            'customtext' => new external_value(PARAM_RAW, 'Custom text', VALUE_OPTIONAL),
                            'printsignature' => new external_value(PARAM_RAW, 'Print signature?', VALUE_OPTIONAL),
                            'printseal' => new external_value(PARAM_RAW, 'Print seal?', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'Time created', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Tool'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function view_originalcert_parameters() {
        return new external_function_parameters(
            array(
                'originalcertid' => new external_value(PARAM_INT, 'originalcert instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $originalcertid the originalcert instance id
     * @return array of warnings and status result
     * @throws moodle_exception
     */
    public static function view_originalcert($originalcertid) {
        global $DB;

        $params = self::validate_parameters(self::view_originalcert_parameters(),
                                            array(
                                                'originalcertid' => $originalcertid
                                            )
        );
        $warnings = array();

        // Request and permission validation.
        $originalcert = $DB->get_record('originalcert', array('id' => $params['originalcertid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($originalcert, 'originalcert');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/originalcert:view', $context);

        $event = \mod_originalcert\event\course_module_viewed::create(array(
            'objectid' => $originalcert->id,
            'context' => $context,
        ));
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('originalcert', $originalcert);
        $event->trigger();

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function view_originalcert_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Check if the user can issue originalcerts.
     *
     * @param  int $originalcertid originalcert instance id
     * @return array array containing context related data
     */
    private static function check_can_issue($originalcertid) {
        global $DB;

        $originalcert = $DB->get_record('originalcert', array('id' => $originalcertid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($originalcert, 'originalcert');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/originalcert:view', $context);

        // Check if the user can view the originalcert.
        if ($originalcert->requiredtime && !has_capability('mod/originalcert:manage', $context)) {
            if (originalcert_get_course_time($course->id) < ($originalcert->requiredtime * 60)) {
                $a = new stdClass();
                $a->requiredtime = $originalcert->requiredtime;
                throw new moodle_exception('requiredtimenotmet', 'originalcert', '', $a);
            }
        }
        return array($originalcert, $course, $cm, $context);
    }

    /**
     * Returns a issued originalcertd structure
     *
     * @return external_single_structure External single structure
     */
    private static function issued_structure() {
        return new external_single_structure(
            array(
            'id' => new external_value(PARAM_INT, 'Issue id'),
            'userid' => new external_value(PARAM_INT, 'User id'),
            'originalcertid' => new external_value(PARAM_INT, 'originalcert id'),
            'code' => new external_value(PARAM_RAW, 'originalcert code'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'filename' => new external_value(PARAM_FILE, 'Time created'),
            'fileurl' => new external_value(PARAM_URL, 'Time created'),
            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
            'grade' => new external_value(PARAM_NOTAGS, 'originalcert grade', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Add extra required information to the issued originalcert
     *
     * @param stdClass $issue       issue object
     * @param stdClass $originalcert originalcert object
     * @param stdClass $course      course object
     * @param stdClass $cm          course module object
     * @param stdClass $context     context object
     */
    private static function add_extra_issue_data($issue, $originalcert, $course, $cm, $context) {
        global $CFG;

        // Grade data.
        if ($originalcert->printgrade) {
            $issue->grade = originalcert_get_grade($originalcert, $course);
        }

        // File data.
        $issue->mimetype = 'application/pdf';
        $issue->filename = originalcert_get_originalcert_filename($originalcert, $cm, $course) . '.pdf';
        // We need to use a special file area to be able to download originalcerts (in most cases are not stored in the site).
        $issue->fileurl = moodle_url::make_webservice_pluginfile_url(
                                $context->id, 'mod_originalcert', 'onthefly', $issue->id, '/', $issue->filename)->out(false);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function issue_originalcert_parameters() {
        return new external_function_parameters(
            array(
                'originalcertid' => new external_value(PARAM_INT, 'originalcert instance id')
            )
        );
    }

    /**
     * Create new originalcert record, or return existing record.
     *
     * @param int $originalcertid the originalcert instance id
     * @return array of warnings and status result
     * @throws moodle_exception
     */
    public static function issue_originalcert($originalcertid) {
        global $USER;

        $params = self::validate_parameters(self::issue_originalcert_parameters(),
                                            array(
                                                'originalcertid' => $originalcertid
                                            )
        );
        $warnings = array();

        // Request and permission validation.
        list($originalcert, $course, $cm, $context) = self::check_can_issue($params['originalcertid']);

        $issue = originalcert_get_issue($course, $USER, $originalcert, $cm);
        self::add_extra_issue_data($issue, $originalcert, $course, $cm, $context);

        $result = array();
        $result['issue'] = $issue;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function issue_originalcert_returns() {
        return new external_single_structure(
            array(
                'issue' => self::issued_structure(),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_issued_originalcerts_parameters() {
        return new external_function_parameters(
            array(
                'originalcertid' => new external_value(PARAM_INT, 'originalcert instance id')
            )
        );
    }

    /**
     * Get the list of issued originalcerts for the current user.
     *
     * @param int $originalcertid the originalcert instance id
     * @return array of warnings and status result
     * @throws moodle_exception
     */
    public static function get_issued_originalcerts($originalcertid) {

        $params = self::validate_parameters(self::get_issued_originalcerts_parameters(),
                                            array(
                                                'originalcertid' => $originalcertid
                                            )
        );
        $warnings = array();

        // Request and permission validation.
        list($originalcert, $course, $cm, $context) = self::check_can_issue($params['originalcertid']);

        $issues = originalcert_get_attempts($originalcert->id);

        if ($issues !== false ) {
            foreach ($issues as $issue) {
                self::add_extra_issue_data($issue, $originalcert, $course, $cm, $context);

            }
        } else {
            $issues = array();
        }

        $result = array();
        $result['issues'] = $issues;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_issued_originalcerts_returns() {
        return new external_single_structure(
            array(
                'issues' => new external_multiple_structure(self::issued_structure()),
                'warnings' => new external_warnings()
            )
        );
    }

}
