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
 * A4_embedded originalcert type
 *
 * @package    mod_originalcert
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$pdf = new PDF($originalcert->orientation, 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetTitle($originalcert->name);
$pdf->SetProtection(array('modify'));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

// Define variables
// Landscape
if ($originalcert->orientation == 'L') {
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

// Get font families.
$fontsans = get_config('originalcert', 'fontsans');
$fontserif = get_config('originalcert', 'fontserif');

// Add images and lines
originalcert_print_image($pdf, $originalcert, CERT_IMAGE_BORDER, $brdrx, $brdry, $brdrw, $brdrh);
originalcert_draw_frame($pdf, $originalcert);
// Set alpha to semi-transparency
$pdf->SetAlpha(0.2);
originalcert_print_image($pdf, $originalcert, CERT_IMAGE_WATERMARK, $wmarkx, $wmarky, $wmarkw, $wmarkh);
$pdf->SetAlpha(1);
originalcert_print_image($pdf, $originalcert, CERT_IMAGE_SEAL, $sealx, $sealy, '', '');
originalcert_print_image($pdf, $originalcert, CERT_IMAGE_SIGNATURE, $sigx, $sigy, '', '');

// Add text
$pdf->SetTextColor(0, 0, 120);
originalcert_print_text($pdf, $x, $y, 'C', $fontsans, '', 30, get_string('title', 'originalcert'));
$pdf->SetTextColor(0, 0, 0);
originalcert_print_text($pdf, $x, $y + 20, 'C', $fontserif, '', 20, get_string('certify', 'originalcert'));
originalcert_print_text($pdf, $x, $y + 36, 'C', $fontsans, '', 30, fullname($USER));
originalcert_print_text($pdf, $x, $y + 55, 'C', $fontsans, '', 20, get_string('statement', 'originalcert'));
originalcert_print_text($pdf, $x, $y + 72, 'C', $fontsans, '', 20, format_string($course->fullname));
originalcert_print_text($pdf, $x, $y + 92, 'C', $fontsans, '', 14,  originalcert_get_date($originalcert, $certrecord, $course));
originalcert_print_text($pdf, $x, $y + 102, 'C', $fontserif, '', 10, originalcert_get_grade($originalcert, $course));
originalcert_print_text($pdf, $x, $y + 112, 'C', $fontserif, '', 10, originalcert_get_outcome($originalcert, $course));
if ($originalcert->printhours) {
    originalcert_print_text($pdf, $x, $y + 122, 'C', $fontserif, '', 10, get_string('credithours', 'originalcert') . ': ' . $originalcert->printhours);
}
originalcert_print_text($pdf, $x, $codey, 'C', $fontserif, '', 10, originalcert_get_code($originalcert, $certrecord));
$i = 0;
if ($originalcert->printteacher) {
    $context = context_module::instance($cm->id);
    if ($teachers = get_users_by_capability($context, 'mod/originalcert:printteacher', '', $sort = 'u.lastname ASC', '', '', '', '', false)) {
        foreach ($teachers as $teacher) {
            $i++;
            originalcert_print_text($pdf, $sigx, $sigy + ($i * 4), 'L', $fontserif, '', 12, fullname($teacher));
        }
    }
}

originalcert_print_text($pdf, $custx, $custy, 'L', null, null, null, $originalcert->customtext);
