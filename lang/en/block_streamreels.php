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
 * English strings.
 *
 * @package    block_streamreels
 * @copyright  2026 CentricApp
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Stream reels';
$string['streamreels:addinstance'] = 'Add a new Stream reels block';
$string['streamreels:myaddinstance'] = 'Add a new Stream reels block to Dashboard';

$string['adminmissingstreamconfig'] = 'Stream URL or Stream key is not set. Configure them under Site administration → Plugins → Local plugins → Stream.';
$string['noteachers'] = 'No teacher is assigned in this course, so reels cannot be loaded.';
$string['noreels'] = 'No reels were found for the course teachers on Stream.';
$string['untitled'] = 'Untitled';
$string['nextreel'] = 'Next reel';
$string['previousreel'] = 'Previous reel';
$string['openinstream'] = 'Open in Stream';
$string['viewerregion'] = 'Teacher reels';
$string['navigationshint'] = 'Swipe up or tap the right side for the next video. Swipe down or tap the left for the previous one.';

$string['privacy:metadata:streamreels'] = 'The block requests reels from your Stream instance using teacher email addresses (as configured in the local Stream plugin). The block does not store this data in Moodle.';
$string['privacy:metadata:streamreels:email'] = 'Teacher email is sent to Stream to match the lecturer’s reels.';
