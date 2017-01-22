<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * A4_non_embedded certificate type
 *
 * @package    mod_certificate
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function certificate_get_itm_grade($certificate, $course, $userid = null, $valueonly = false) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if ($certificate->printgrade > 0) {
		if ($certificate->printgrade == 1) {
            if ($course_item = grade_item::fetch_course_item($course->id)) {
                $grade = new grade_grade(array('itemid' => $course_item->id, 'userid' => $userid));
                $course_item->gradetype = GRADE_TYPE_VALUE;
                $coursegrade = new stdClass;
                $coursegrade->points = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_REAL, $decimals = 2);
                $coursegrade->percentage = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, $decimals = 2);
                $coursegrade->letter = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_LETTER, $decimals = 0);

                $grade = round(str_replace('%', '',$coursegrade->percentage));
            }
        } else {
			if ($modinfo = certificate_get_mod_grade($course, $certificate->printgrade, $userid)) {
				// Check we want to add a prefix to the grade.
				$grade = round(str_replace('%', '', $modinfo->percentage));
			}
		}
		if ($grade) {
			$grade_class = '';
			if($grade >= 85){
				$grade_class = 'High Distinction';
			} elseif ($grade >= 75) {
				$grade_class = 'Distinction';
			} elseif ($grade >= 65) {
				$grade_class = 'Credit';
			} elseif ($grade >= 50) {
				$grade_class = 'Pass';
			} else {
				$grade_class = 'Fail';
			}
			$grade = $grade_class . ' (' .$grade . '/100)';
		} 
		return $grade;
    }
    return '';
}

$pdf = new PDF($certificate->orientation, 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetTitle($certificate->name);
$pdf->SetProtection(array('modify'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

// Define variables
// Landscape
if ($certificate->orientation == 'L') {
    $x = 10;
    $y = 30;
    $sealx = 230;
    $sealy = 150;
    $sigx = 47;
    $sigy = 155;
    $custx = 47;
    $custy = 155;
    $wmarkx = 40;
    $wmarky = 31;
    $wmarkw = 212;
    $wmarkh = 148;
    $brdrx = 0;
    $brdry = 0;
    $brdrw = 297;
    $brdrh = 210;
    $codey = 175;
} else { // Portrait
    $x = 10;
    $y = 40;
    $sealx = 150;
    $sealy = 220;
    $sigx = 30;
    $sigy = 230;
    $custx = 30;
    $custy = 230;
    $wmarkx = 26;
    $wmarky = 58;
    $wmarkw = 158;
    $wmarkh = 170;
    $brdrx = 0;
    $brdry = 0;
    $brdrw = 210;
    $brdrh = 297;
    $codey = 250;
}

// Add images and lines
certificate_print_image($pdf, $certificate, CERT_IMAGE_BORDER, $brdrx, $brdry, $brdrw, $brdrh);
certificate_draw_frame($pdf, $certificate);
// Set alpha to semi-transparency
$pdf->SetAlpha(0.2);
certificate_print_image($pdf, $certificate, CERT_IMAGE_WATERMARK, $wmarkx, $wmarky, $wmarkw, $wmarkh);
$pdf->SetAlpha(1);
certificate_print_image($pdf, $certificate, CERT_IMAGE_SEAL, $sealx, $sealy, '', '');
certificate_print_image($pdf, $certificate, CERT_IMAGE_SIGNATURE, $sigx, $sigy, '', '');

// Add text
$pdf->SetTextColor(229, 27, 36);
certificate_print_text($pdf, $x, $y - 20, 'C', 'caladeab', '', 40, 'Certificate of Achievement');
certificate_print_text($pdf, $x, $y - 2, 'C', 'caladeab', '', 28, 'Short Course: ' . format_string($course->fullname));

$pdf->SetTextColor(0, 0, 0);
certificate_print_text($pdf, $x, $y + 36, 'C', 'carlito', '', 14, 'This is to certify that');
certificate_print_text($pdf, $x, $y + 46, 'C', 'carlitob', '', 20, fullname($USER));
certificate_print_text($pdf, $x, $y + 60, 'C', 'carlito', '', 14, 'has successfully completed the Short Course');
certificate_print_text($pdf, $x, $y + 70, 'C', 'carlitob', '', 20, format_string($course->fullname));

certificate_print_text($pdf, $x, $y + 85, 'C', 'carlito', '', 14, 'Grade: ' . certificate_get_itm_grade($certificate, $course));
if ($certificate->printteacher) {
    $context = context_module::instance($cm->id);
    if ($teachers = get_users_by_capability($context, 'mod/certificate:printteacher', '', $sort = 'u.lastname ASC', '', '', '', '', false)) {
        if(count($teachers) == 1){
            foreach ($teachers as $teacher) {
                certificate_print_text($pdf, $x, $y + 97, 'C', 'carlito', '', 14, 'Lecturer: ' . fullname($teacher));
            }
        } else {
            $numItems = count($teachers);
            $i = 0;
            $lecturers = '';
            foreach ($teachers as $teacher) {
                if(++$i === $numItems) {
                    $lecturers = $lecturers . fullname($teacher);
                } else{
                    $lecturers = $lecturers . fullname($teacher) . ', ';
                }
            }
            certificate_print_text($pdf, $x, $y + 97, 'C', 'carlito', '', 14, 'Lecturers: ' . $lecturers);
        }
    }
}
certificate_print_text($pdf, $x, $y + 109, 'C', 'carlito', '', 14, 'Completed: ' . certificate_get_date($certificate, $certrecord, $course));
certificate_print_text($pdf, $x, $y + 129, 'C', 'carlito', '', 14, certificate_get_outcome($certificate, $course));
if ($certificate->printhours) {
    certificate_print_text($pdf, $x, $y + 139, 'C', 'carlito', '', 14, get_string('credithours', 'certificate') . ': ' . $certificate->printhours);
}
certificate_print_text($pdf, $x, $codey, 'C', 'carlito', '', 14, certificate_get_code($certificate, $certrecord));

certificate_print_text($pdf, $custx, $custy, 'L', null, null, null, $certificate->customtext);
