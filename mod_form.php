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
* Instance add/edit form
*
* @package    mod_originalcert
* @copyright  Mark Nelson <markn@moodle.com>
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/originalcert/locallib.php');

class mod_originalcert_mod_form extends moodleform_mod {

    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('originalcertname', 'originalcert'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('intro', 'originalcert'));

        // Issue options
        $mform->addElement('header', 'issueoptions', get_string('issueoptions', 'originalcert'));
        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'originalcert'), $ynoptions);
        $mform->setDefault('emailteachers', 0);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'originalcert');

        $mform->addElement('text', 'emailothers', get_string('emailothers', 'originalcert'), array('size'=>'40', 'maxsize'=>'200'));
        $mform->setType('emailothers', PARAM_TEXT);
        $mform->addHelpButton('emailothers', 'emailothers', 'originalcert');

        $deliveryoptions = array( 0 => get_string('openbrowser', 'originalcert'), 1 => get_string('download', 'originalcert'), 2 => get_string('emailoriginalcert', 'originalcert'));
        $mform->addElement('select', 'delivery', get_string('delivery', 'originalcert'), $deliveryoptions);
        $mform->setDefault('delivery', 0);
        $mform->addHelpButton('delivery', 'delivery', 'originalcert');

        $mform->addElement('select', 'savecert', get_string('savecert', 'originalcert'), $ynoptions);
        $mform->setDefault('savecert', 0);
        $mform->addHelpButton('savecert', 'savecert', 'originalcert');

        $reportfile = "$CFG->dirroot/originalcerts/index.php";
        if (file_exists($reportfile)) {
            $mform->addElement('select', 'reportcert', get_string('reportcert', 'originalcert'), $ynoptions);
            $mform->setDefault('reportcert', 0);
            $mform->addHelpButton('reportcert', 'reportcert', 'originalcert');
        }

        $mform->addElement('text', 'requiredtime', get_string('coursetimereq', 'originalcert'), array('size'=>'3'));
        $mform->setType('requiredtime', PARAM_INT);
        $mform->addHelpButton('requiredtime', 'coursetimereq', 'originalcert');

        // Text Options
        $mform->addElement('header', 'textoptions', get_string('textoptions', 'originalcert'));

        $modules = originalcert_get_mods();
        $dateoptions = originalcert_get_date_options() + $modules;
        $mform->addElement('select', 'printdate', get_string('printdate', 'originalcert'), $dateoptions);
        $mform->setDefault('printdate', 'N');
        $mform->addHelpButton('printdate', 'printdate', 'originalcert');

        $dateformatoptions = array( 1 => 'January 1, 2000', 2 => 'January 1st, 2000', 3 => '1 January 2000',
            4 => 'January 2000', 5 => get_string('userdateformat', 'originalcert'));
        $mform->addElement('select', 'datefmt', get_string('datefmt', 'originalcert'), $dateformatoptions);
        $mform->setDefault('datefmt', 0);
        $mform->addHelpButton('datefmt', 'datefmt', 'originalcert');

        $mform->addElement('select', 'printnumber', get_string('printnumber', 'originalcert'), $ynoptions);
        $mform->setDefault('printnumber', 0);
        $mform->addHelpButton('printnumber', 'printnumber', 'originalcert');

        $gradeoptions = originalcert_get_grade_options() + originalcert_get_grade_categories($this->current->course) + $modules;
        $mform->addElement('select', 'printgrade', get_string('printgrade', 'originalcert'),$gradeoptions);
        $mform->setDefault('printgrade', 0);
        $mform->addHelpButton('printgrade', 'printgrade', 'originalcert');

        $gradeformatoptions = array( 1 => get_string('gradepercent', 'originalcert'), 2 => get_string('gradepoints', 'originalcert'),
            3 => get_string('gradeletter', 'originalcert'));
        $mform->addElement('select', 'gradefmt', get_string('gradefmt', 'originalcert'), $gradeformatoptions);
        $mform->setDefault('gradefmt', 0);
        $mform->addHelpButton('gradefmt', 'gradefmt', 'originalcert');

        $outcomeoptions = originalcert_get_outcomes();
        $mform->addElement('select', 'printoutcome', get_string('printoutcome', 'originalcert'),$outcomeoptions);
        $mform->setDefault('printoutcome', 0);
        $mform->addHelpButton('printoutcome', 'printoutcome', 'originalcert');

        $mform->addElement('text', 'printhours', get_string('printhours', 'originalcert'), array('size'=>'5', 'maxlength' => '255'));
        $mform->setType('printhours', PARAM_TEXT);
        $mform->addHelpButton('printhours', 'printhours', 'originalcert');

        $mform->addElement('select', 'printteacher', get_string('printteacher', 'originalcert'), $ynoptions);
        $mform->setDefault('printteacher', 0);
        $mform->addHelpButton('printteacher', 'printteacher', 'originalcert');

        $mform->addElement('textarea', 'customtext', get_string('customtext', 'originalcert'), array('cols'=>'40', 'rows'=>'4', 'wrap'=>'virtual'));
        $mform->setType('customtext', PARAM_RAW);
        $mform->addHelpButton('customtext', 'customtext', 'originalcert');

        // Design Options
        $mform->addElement('header', 'designoptions', get_string('designoptions', 'originalcert'));
        $mform->addElement('select', 'originalcerttype', get_string('originalcerttype', 'originalcert'), originalcert_types());
        $mform->setDefault('originalcerttype', 'A4_non_embedded');
        $mform->addHelpButton('originalcerttype', 'originalcerttype', 'originalcert');

        $orientation = array( 'L' => get_string('landscape', 'originalcert'), 'P' => get_string('portrait', 'originalcert'));
        $mform->addElement('select', 'orientation', get_string('orientation', 'originalcert'), $orientation);
        $mform->setDefault('orientation', 'L');
        $mform->addHelpButton('orientation', 'orientation', 'originalcert');

        $mform->addElement('select', 'borderstyle', get_string('borderstyle', 'originalcert'), originalcert_get_images(CERT_IMAGE_BORDER));
        $mform->setDefault('borderstyle', '0');
        $mform->addHelpButton('borderstyle', 'borderstyle', 'originalcert');

        $printframe = array( 0 => get_string('no'), 1 => get_string('borderblack', 'originalcert'), 2 => get_string('borderbrown', 'originalcert'),
            3 => get_string('borderblue', 'originalcert'), 4 => get_string('bordergreen', 'originalcert'));
        $mform->addElement('select', 'bordercolor', get_string('bordercolor', 'originalcert'), $printframe);
        $mform->setDefault('bordercolor', '0');
        $mform->addHelpButton('bordercolor', 'bordercolor', 'originalcert');

        $mform->addElement('select', 'printwmark', get_string('printwmark', 'originalcert'), originalcert_get_images(CERT_IMAGE_WATERMARK));
        $mform->setDefault('printwmark', '0');
        $mform->addHelpButton('printwmark', 'printwmark', 'originalcert');

        $mform->addElement('select', 'printsignature', get_string('printsignature', 'originalcert'), originalcert_get_images(CERT_IMAGE_SIGNATURE));
        $mform->setDefault('printsignature', '0');
        $mform->addHelpButton('printsignature', 'printsignature', 'originalcert');

        $mform->addElement('select', 'printseal', get_string('printseal', 'originalcert'), originalcert_get_images(CERT_IMAGE_SEAL));
        $mform->setDefault('printseal', '0');
        $mform->addHelpButton('printseal', 'printseal', 'originalcert');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Some basic validation
     *
     * @param $data
     * @param $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check that the required time entered is valid
        if ((!is_number($data['requiredtime']) || $data['requiredtime'] < 0)) {
            $errors['requiredtime'] = get_string('requiredtimenotvalid', 'originalcert');
        }

        return $errors;
    }
}
