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
 * Perform a submission on a VPL during a quiz attempt.
 *
 * This is an ajax call for Run and Pre-check, actual evaluation (Check) is done only on server side.
 *
 * @package qtype_vplquestion
 * @copyright 2022 Astor Bizard
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_vplquestion\locallib;

define( 'AJAX_SCRIPT', true );

require(__DIR__ . '/../../../../config.php');

global $USER, $DB;

$outcome = new stdClass();
$outcome->success = true;
$outcome->response = new stdClass();
$outcome->error = '';
try {
    require_once(__DIR__ . '/../../../../mod/vpl/vpl.class.php');
    require_once(__DIR__ . '/../../../../mod/vpl/forms/edit.class.php');
    if (! isloggedin()) {
        throw new Exception( get_string( 'loggedinnot' ) );
    }

    $id = required_param( 'id', PARAM_INT );
    $userid = $USER->id;
    $qid = required_param( 'qid', PARAM_INT );
    $answer = required_param( 'answer', PARAM_RAW );
    $filestype = required_param( 'filestype', PARAM_RAW );
    $vpl = new mod_vpl( $id );
    require_login( $vpl->get_course(), false );

    if (! $vpl->is_submit_able()) {
        throw new Exception( get_string( 'notavailable' ) );
    }

    $question = $DB->get_record('question_vplquestion', [ 'questionid' => $qid ]);
    $reqfile = locallib::get_reqfile_for_submission($question, $answer);

    if ($filestype == 'run') {
        $filestokeep = $vpl->get_execution_fgm()->getfilekeeplist();
        $execfiles = locallib::format_execution_files(json_decode($question->execfiles), $filestokeep);
    } else if ($filestype == 'precheck') {
        $execfilesdata = $question->precheckpreference == 'diff' ? $question->precheckexecfiles : $question->execfiles;
        $execfiles = locallib::format_execution_files(json_decode($execfilesdata));
    }

    $files = $reqfile + $execfiles;

    $outcome->response = mod_vpl_edit::save( $vpl, $userid, $files );

} catch ( Exception $e ) {
    $outcome->success = false;
    $outcome->error = $e->getMessage();
}
echo json_encode( $outcome );
die();
