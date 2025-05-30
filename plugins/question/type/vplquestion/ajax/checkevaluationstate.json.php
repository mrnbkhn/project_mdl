<?php
// This file is part of Moodle - https://moodle.org/
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
 * AJAX script checking the state of an evaluation task and rendering the question feedback for display when it has been evaluated.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\quiz_attempt;
use qtype_vplquestion\locallib;

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

require_login();

global $PAGE;

$outcome = new stdClass();
$outcome->success = true;
$outcome->response = new stdClass();
$outcome->error = '';
try {
    $usageid = required_param('usageid', PARAM_INT);
    $slot = required_param('slot', PARAM_INT);
    $url = required_param('url', PARAM_RAW);
    list($queued, $message) = locallib::get_async_evaluation_status($usageid, $slot, true);
    if ($queued) {
        // Evaluation is still queued, question has not been evaluated yet.
        $outcome->response->finished = false;
        $outcome->response->progressmessage = $message;
    } else {
        // Evaluation is not queued anymore, question has been evaluated.
        $outcome->response->finished = true;

        // Get context, renderers and display options.
        $quizattempt = quiz_attempt::create_from_usage_id($usageid);
        $PAGE->set_context(context_module::instance($quizattempt->get_cmid()));
        $reviewing = (new moodle_url($url))->get_path(false) == '/mod/quiz/review.php';
        $questionattempt = $quizattempt->get_question_attempt($slot);
        $displayoptions = $quizattempt->get_display_options_with_edit_link($reviewing, $slot, $url);
        $qoutput = $PAGE->get_renderer('core', 'question');
        $qtoutput = $questionattempt->get_question()->get_renderer($PAGE);
        $behaviouroutput = $questionattempt->get_behaviour()->get_renderer($qoutput->get_page());

        // Retrieve feedback.
        $PAGE->start_collecting_javascript_requirements();
        $qfeedback = $qtoutput->feedback($questionattempt, $displayoptions);
        $bfeedback = $behaviouroutput->feedback($questionattempt, $displayoptions);
        $javascript = $PAGE->requires->get_end_code();
        $PAGE->end_collecting_javascript_requirements();

        // Build response object.
        $outcome->response->qfeedback = html_writer::nonempty_tag('div', $qfeedback, [ 'class' => 'feedback' ]);
        $outcome->response->bfeedback = html_writer::nonempty_tag('div', $bfeedback, [ 'class' => 'im-feedback' ]);
        $outcome->response->javascript = $javascript;
        $outcome->response->qinfo = [
                'state' => $questionattempt->get_state_string($displayoptions->correctness),
                'grade' => $behaviouroutput->mark_summary($questionattempt, $qoutput, $displayoptions),
        ];
        if ($displayoptions->marks >= question_display_options::MARK_AND_MAX) {
            $outcome->response->qinfo['marks'] = $questionattempt->format_fraction_as_mark(
                    $questionattempt->get_fraction(), $displayoptions->markdp);
        } else {
            $outcome->response->qinfo['marks'] = '';
        }
        $outcome->response->navbutton = [
                'id' => 'quiznavbutton' . $slot,
                'title' => $questionattempt->get_state_string($displayoptions->correctness),
                'oldclass' => question_state::$needsgrading->get_state_class($displayoptions->correctness),
                'newclass' => $questionattempt->get_state_class($displayoptions->correctness),
        ];
        $outcome->response->sequencecheck = [
                'name' => $questionattempt->get_control_field_name('sequencecheck'),
                'value' => $questionattempt->get_sequence_check_count(),
        ];
    }
} catch (Exception $e) {
    $outcome->success = false;
    $outcome->error = $e->getMessage();
}
echo json_encode($outcome);
die();
