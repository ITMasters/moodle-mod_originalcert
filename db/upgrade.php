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
 * This file keeps track of upgrades to the originalcert module
 *
 * @package    mod_originalcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_originalcert_upgrade($oldversion=0) {

    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();

    // ===== 1.9.0 or older upgrade line ======//
    if ($oldversion < 2007061300) {
        // Add new fields to originalcert table
        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('emailothers');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'emailteachers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('printhours');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'gradefmt');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('lockgrade');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'printhours');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('requiredgrade');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'lockgrade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rename field save to savecert
        $field = new xmldb_field('save');
        if ($dbman->field_exists($table, $field)) {
            $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'emailothers');
            // Launch rename field savecert
            $dbman->rename_field($table, $field, 'savecert');
        } else {
            $field = new xmldb_field('savecert');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'emailothers');

            $dbman->add_field($table, $field);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2007061300, 'originalcert');
    }

    if ($oldversion < 2007061301) {
        $table = new xmldb_table('originalcert_linked_modules');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE,  null, null);
        $table->add_field('originalcert_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        $table->add_field('linkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'originalcert_id');
        $table->add_field('linkgrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'linkid');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'linkgrade');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);
        $table->add_index('originalcert_id', XMLDB_INDEX_NOTUNIQUE, array('originalcert_id'));
        $table->add_index('linkid', XMLDB_INDEX_NOTUNIQUE, array('linkid'));
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2007061301, 'originalcert');
    }

    if ($oldversion < 2007102800) {
        // Add new fields to originalcert table
        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('reportcert');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'savecert');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('originalcert_issues');
        $field = new xmldb_field('reportgrade');
        $field->set_attributes(XMLDB_TYPE_CHAR, '10', null, null, null, null, 'certdate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2007102800, 'originalcert');
    }

    if ($oldversion < 2007102806) {
        // Add new fields to originalcert table
        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('printoutcome');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'gradefmt');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2007102806, 'originalcert');
    }

    if ($oldversion < 2008080904) {
        // Add new fields to originalcert table if they dont already exist
        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('intro');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2008080904, 'originalcert');
    }

    //===== 2.0 or older upgrade line ======//

    // Note, fresh 1.9 installs add the version 2009080900, so they miss this when upgrading from 1.9 -> 2.0.
    if ($oldversion < 2009062900) {
        // Add new field to originalcert table
        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'intro');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('orientation', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, ' ', 'originalcerttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('reissuecert', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'reportcert');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set default orientation accordingly
        $DB->set_field('originalcert', 'orientation', 'P', array('originalcerttype' => 'portrait'));
        $DB->set_field('originalcert', 'orientation', 'P', array('originalcerttype' => 'letter_portrait'));
        $DB->set_field('originalcert', 'orientation', 'P', array('originalcerttype' => 'unicode_portrait'));
        $DB->set_field('originalcert', 'orientation', 'L', array('originalcerttype' => 'landscape'));
        $DB->set_field('originalcert', 'orientation', 'L', array('originalcerttype' => 'letter_landscape'));
        $DB->set_field('originalcert', 'orientation', 'L', array('originalcerttype' => 'unicode_landscape'));

        // Update all the originalcert types
        $DB->set_field('originalcert', 'originalcerttype', 'A4_non_embedded', array('originalcerttype' => 'landscape'));
        $DB->set_field('originalcert', 'originalcerttype', 'A4_non_embedded', array('originalcerttype' => 'portrait'));
        $DB->set_field('originalcert', 'originalcerttype', 'A4_embedded', array('originalcerttype' => 'unicode_landscape'));
        $DB->set_field('originalcert', 'originalcerttype', 'A4_embedded', array('originalcerttype' => 'unicode_portrait'));
        $DB->set_field('originalcert', 'originalcerttype', 'letter_non_embedded', array('originalcerttype' => 'letter_landscape'));
        $DB->set_field('originalcert', 'originalcerttype', 'letter_non_embedded', array('originalcerttype' => 'letter_portrait'));

        // savepoint reached
        upgrade_mod_savepoint(true, 2009062900, 'originalcert');
    }

    if ($oldversion < 2011030105) {

        // Define field id to be added to originalcert
        $table = new xmldb_table('originalcert');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'intro');

        // Conditionally launch add field id
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2011030105, 'originalcert');
    }

    // The Moodle 2.0 CVS originalcert version sets it to 2011110101, if the user performed an upgrade
    // then this upgrade will take care of several issues, if it's a fresh install then nothing is done.
    if ($oldversion < 2011110102) {
        require_once($CFG->libdir.'/conditionlib.php');

        $table = new xmldb_table('originalcert');

        // It is possible for these fields not to be added, ever, it is included in the upgrade
        // process but fresh originalcert 1.9 install from CVS MOODLE_19_STABLE set the Moodle version
        // to 2009080900, which means it missed all the earlier code written for upgrading to 2.0.
        $reissuefield = new xmldb_field('reissuecert', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'reportcert');
        $orientationfield = new xmldb_field('orientation', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, ' ', 'originalcerttype');

        // Have to check, may be added during earlier upgrade, or may be missing due to not being included in install.xml
        if (!$dbman->field_exists($table, $reissuefield)) {
            $dbman->add_field($table, $reissuefield);
        }

        if (!$dbman->field_exists($table, $orientationfield)) {
            $dbman->add_field($table, $orientationfield);
        }

        // Fresh 2.0 installs won't have this table, but upgrades from 1.9 will
        if ($dbman->table_exists('originalcert_linked_modules')) {
            // No longer need lock grade, or required grade, but first need to
            // convert so that the restrictions are still in place for Moodle 2.0
            if ($certs = $DB->get_records('originalcert')) {
                foreach ($certs as $cert) {
                    if ($cert->lockgrade == 0) {
                        // Can skip this originalcert, no course grade required
                        continue;
                    }
                    if (!$cm = get_coursemodule_from_instance('originalcert', $cert->id)) {
                        // Not valid skip it
                        continue;
                    }
                    if (!$gradeitem = $DB->get_record('grade_items', array('courseid' => $cm->course, 'itemtype' => 'course'))) {
                        // Not valid skip it
                        continue;
                    }
                    $condition_info = new condition_info($cm, CONDITION_MISSING_EVERYTHING);
                    $condition_info->add_grade_condition($gradeitem->id, $cert->requiredgrade, '110');
                }
            }
            // Fresh installs won't have this table, but upgrades will
            // Lock grade and required grade field are not needed anymore
            if ($dbman->field_exists($table, 'lockgrade')) {
                $field = new xmldb_field('lockgrade');
                $dbman->drop_field($table, $field);
            }
            if ($dbman->field_exists($table, 'requiredgrade')) {
                $field = new xmldb_field('requiredgrade');
                $dbman->drop_field($table, $field);
            }
            // Now we need to loop through the restrictions in the originalcert_linked_modules
            // table and convert it into the new Moodle 2.0 restrictions
            if ($certlinks = $DB->get_records('originalcert_linked_modules')) {
                foreach ($certlinks as $link) {
                    // If the link id is '-1' then the setting applies to the time spent in the course and is not
                    // related to a module, meaning we can skip it for this section
                    if ($link->linkid == '-1') {
                        continue;
                    }
                    // Get the course module
                    if (!$cm = get_coursemodule_from_instance('originalcert', $link->originalcert_id)) {
                        // Not valid skip it
                        continue;
                    }
                    // Get grade item for module specified - is there an API function for this ??
                    $sql = "SELECT gi.id
                            FROM {course_modules} cm
                            INNER JOIN {modules} m
                            ON cm.module = m.id
                            INNER JOIN {grade_items} gi
                            ON m.name = gi.itemmodule
                            WHERE cm.id = :cmid
                            AND cm.course = :courseid
                            AND cm.instance = gi.iteminstance";
                    if (!$gradeitem = $DB->get_record_sql($sql, array('cmid'=>$link->linkid, 'courseid'=>$cm->course))) {
                        // Not valid skip it
                        continue;
                    }
                    $condition_info = new condition_info($cm, CONDITION_MISSING_EVERYTHING);
                    $condition_info->add_grade_condition($gradeitem->id, $link->linkgrade, '110', true);
                }
            }
        }
        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2011110102, 'originalcert');
    }

    // Note - the date has not changed as it has been set in the future, so I am incrementing
    // last digits. Actual date - 15/09/11
    if ($oldversion < 2011110103) {
        // New orientation field needs a value in order to view the cert, otherwise you get
        // an issue with FPDF and invalid orientation. This should be done during the upgrade,
        // but due to version number issues it is possible it was not executed, so do it now.
        $DB->set_field('originalcert', 'orientation', 'P', array('originalcerttype' => 'portrait'));
        $DB->set_field('originalcert', 'orientation', 'P', array('originalcerttype' => 'letter_portrait'));
        $DB->set_field('originalcert', 'orientation', 'P', array('originalcerttype' => 'unicode_portrait'));
        $DB->set_field('originalcert', 'orientation', 'L', array('originalcerttype' => 'landscape'));
        $DB->set_field('originalcert', 'orientation', 'L', array('originalcerttype' => 'letter_landscape'));
        $DB->set_field('originalcert', 'orientation', 'L', array('originalcerttype' => 'unicode_landscape'));

        // If the originalcert type does not match any of the orientations in the above then set to 'L'
        $sql = "UPDATE {originalcert}
                SET orientation = 'L'
                WHERE orientation = ''";
        $DB->execute($sql);

        // Update all the originalcert types
        $DB->set_field('originalcert', 'originalcerttype', 'A4_non_embedded', array('originalcerttype' => 'landscape'));
        $DB->set_field('originalcert', 'originalcerttype', 'A4_non_embedded', array('originalcerttype' => 'portrait'));
        $DB->set_field('originalcert', 'originalcerttype', 'A4_embedded', array('originalcerttype' => 'unicode_landscape'));
        $DB->set_field('originalcert', 'originalcerttype', 'A4_embedded', array('originalcerttype' => 'unicode_portrait'));
        $DB->set_field('originalcert', 'originalcerttype', 'letter_non_embedded', array('originalcerttype' => 'letter_landscape'));
        $DB->set_field('originalcert', 'originalcerttype', 'letter_non_embedded', array('originalcerttype' => 'letter_portrait'));

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2011110103, 'originalcert');
    }

    if ($oldversion < 2012022001) {
        // CONTRIB-3470 - certdate remaining 0 on issued originalcerts, need to update
        $sql = "UPDATE {originalcert_issues}
                SET certdate = timecreated
                WHERE certdate = 0";
        $DB->execute($sql);

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2012022001, 'originalcert');
    }

    if ($oldversion < 2012060901) {
        // Editing this table
        $table = new xmldb_table('originalcert');

        // Get rid of the reissue cert column, this was a hack introduced later
        // in 1.9 when the bug was brought up that grades were not refreshing
        // since they were being stored in the issues table.
        // The originalcert will now always return the current grade, student name
        // and course name.
        $field = new xmldb_field('reissuecert');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // The poor originalcert_issues table is going to have a lot of
        // duplicates, we don't need that now, just keep the latest one
        $sql = "SELECT MAX(id) id1, MAX(id) as id2
                FROM {originalcert_issues}
                GROUP BY originalcertid, userid";
        if ($arrids = $DB->get_records_sql_menu($sql)) {
            $idstokeep = implode(",", $arrids);
            $sql = "DELETE
                    FROM {originalcert_issues}
                    WHERE id NOT IN ($idstokeep)";
            $DB->execute($sql);
        }

        // Going to be editing this table
        $table = new xmldb_table('originalcert_issues');

        // Conditionally remove columns no longer needed
        $field = new xmldb_field('studentname');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('classname');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('certdate');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('reportgrade');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $field = new xmldb_field('mailed');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2012060901, 'originalcert');
    }

    if ($oldversion < 2012072501) {
        // Add a column to store the required grade
        $table = new xmldb_table('originalcert');
        $requiredtimefield = new xmldb_field('requiredtime', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'delivery');

        if (!$dbman->field_exists($table, $requiredtimefield)) {
            $dbman->add_field($table, $requiredtimefield);
        }

        // If this table still exists, then the install was from a 1.9 version
        if ($dbman->table_exists('originalcert_linked_modules')) {
            // Now we need to loop through the restrictions in the originalcert_linked_modules
            // table and check if there were any required time conditions
            if ($certlinks = $DB->get_records('originalcert_linked_modules')) {
                foreach ($certlinks as $link) {
                    // If the link id is '-1' then the setting applies to the time spent in the course
                    if ($link->linkid == '-1') {
                        // Make sure the originalcert exists
                        if ($originalcert = $DB->get_record('originalcert', array('id' => $link->originalcert_id))) {
                            $originalcert->requiredtime = $link->linkgrade;
                            $DB->update_record('originalcert', $originalcert);
                        }
                    }
                }
            }
            // We can now get rid of this table
            $table = new xmldb_table('originalcert_linked_modules');
            $dbman->drop_table($table);
        }

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2012072501, 'originalcert');
    }

    if ($oldversion < 2012082401) {
        $table = new xmldb_table('originalcert');

        // Change length of the fields that store images, so longer image names can be stored
        $field = new xmldb_field('borderstyle', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('printwmark', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('printsignature', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('printseal', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0);
        $dbman->change_field_precision($table, $field);

        // Change length of fields that are unnecessarily large
        $field = new xmldb_field('printnumber', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, 0, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('printhours', XMLDB_TYPE_CHAR, '255', null, false, 0, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('emailteachers', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, 0, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('savecert', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, 0, 0);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('reportcert', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, 0, 0);
        $dbman->change_field_precision($table, $field);

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2012082401, 'originalcert');
    }

    if ($oldversion < 2012090901) {
        $table = new xmldb_table('originalcert');

        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, 0, 0, 'printseal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set the time created to the time modified
        $sql = "UPDATE {originalcert}
                SET timecreated = timemodified";
        $DB->execute($sql);

        // originalcert savepoint reached
        upgrade_mod_savepoint(true, 2012090901, 'originalcert');
    }

    if ($oldversion < 2014081901) {
        // Fix previous upgrades.

        $table = new xmldb_table('originalcert');

        $field = new xmldb_field('borderstyle', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0, '0');
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('printwmark', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0, '0');
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('printhours', XMLDB_TYPE_CHAR, '255', null, false, 0, null);
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('printsignature', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0, '0');
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('printseal', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, 0, '0');
        $dbman->change_field_default($table, $field);

        // originalcert savepoint reached.
        upgrade_mod_savepoint(true, 2014081901, 'originalcert');
    }

    return true;
}
