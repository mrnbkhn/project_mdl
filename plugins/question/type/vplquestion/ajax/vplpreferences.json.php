<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Retrieve or submit user preferences about editor.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define( 'AJAX_SCRIPT', true );

require(__DIR__ . '/../../../../config.php');

$outcome = new stdClass();
$outcome->success = true;
$outcome->response = new stdClass();
$outcome->error = '';
try {
    require_login();

    $newprefs = optional_param('set', null, PARAM_RAW);

    if ($newprefs !== null) {
        confirm_sesskey();
        $newprefs = (object)$newprefs;
        if (isset($newprefs->aceTheme)) {
            set_user_preference('vpl_acetheme', $newprefs->aceTheme);
        }
        if (isset($newprefs->fontSize)) {
            set_user_preference('vpl_editor_fontsize', $newprefs->fontSize);
        }
    }

    $outcome->response->aceTheme = get_user_preferences('vpl_acetheme', get_config('mod_vpl', 'editor_theme') ?: 'chrome');
    $outcome->response->fontSize = get_user_preferences('vpl_editor_fontsize', 12);

} catch ( Exception $e ) {
    $outcome->success = false;
    $outcome->error = $e->getMessage();
}
echo json_encode( $outcome );
die();
