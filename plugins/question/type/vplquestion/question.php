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
 * Vplquestion definition class.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use qtype_vplquestion\locallib;

global $CFG;
require_once($CFG->dirroot . '/question/type/questionbase.php');

/**
 * Represents a vplquestion.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion_question extends question_graded_automatically {

    /** @var int VPL this question is based on (id in table course_modules). */
    public $templatevpl;
    /** @var string Programming language used for this question (infered from template). */
    public $templatelang;
    /** @var string Contents of required file, must contain {{ANSWER}} tag. */
    public $templatecontext;
    /** @var string Initial contents of file presented to the students. */
    public $answertemplate;
    /** @var string Correction. */
    public $teachercorrection;
    /** @var int|bool Whether the question should attempt to validate provided correction when saving the question. */
    public $validateonsave;
    /** @var string JSON-encoded execution files. */
    public $execfiles;
    /** @var string How Pre-check behaves, one of 'none'/'dbg'/'same'/'diff'. */
    public $precheckpreference;
    /** @var string JSON-encoded execution files for Pre-check action. */
    public $precheckexecfiles;
    /** @var int How to decide the final grade, 0 => All or nothing, 1 => Relative grade. */
    public $gradingmethod;
    /** @var int|bool Whether VPL submissions made by the question should be deleted. */
    public $deletesubmissions;
    /** @var int|bool Whether the question should use an asynchronous evaluation. */
    public $useasynceval;

    /**
     * Question attempt step.
     * @var question_attempt_step $step
     */
    private $step = null;

    /**
     * {@inheritDoc}
     * @see question_definition::get_expected_data()
     */
    public function get_expected_data() {
        return [ 'answer' => PARAM_RAW ];
    }

    /**
     * {@inheritDoc}
     * @see question_definition::get_correct_response()
     */
    public function get_correct_response() {
        return [ 'answer' => $this->teachercorrection ];
    }

    /**
     * Wrapper to get the answer in a response object, handling unset variable.
     * @param array $response the response object, as defined in get_expected_data().
     * @return string the answer
     */
    private function get_answer(array $response) {
        return isset($response['answer']) ? $response['answer'] : '';
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_manually_gradable::summarise_response()
     */
    public function summarise_response(array $response) {
        return str_replace("\r", "", $this->get_answer($response));
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_manually_gradable::is_complete_response()
     */
    public function is_complete_response(array $response) {
        return $this->get_answer($response) != $this->answertemplate;
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_automatically_gradable::get_validation_error()
     */
    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseanswer', 'qtype_vplquestion');
    }

    /**
     * {@inheritDoc}
     * @param array $prevresponse the response previously recorded for this question, as defined in get_expected_data().
     * @param array $newresponse the new response, in the same format.
     * @see question_manually_gradable::is_same_response()
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer');
    }

    /**
     * {@inheritDoc}
     * @param question_attempt_step $step The first step of the question_attempt being started. Can be used to store state.
     * @param int $variant which variant of this question to start. Will be between 1 and get_num_variants(), inclusive.
     * @see question_definition::start_attempt()
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        parent::start_attempt($step, $variant);
        // Store initial attempt step, to save evaluation details as a qt var in grade_response().
        $this->step = $step;
    }

    /**
     * {@inheritDoc}
     * @param question_attempt_step $step The first step of the question_attempt being loaded.
     * @see question_definition::apply_attempt_state()
     */
    public function apply_attempt_state(question_attempt_step $step) {
        parent::apply_attempt_state($step);
        // Store attempt step, to save evaluation details as a qt var in grade_response().
        $this->step = $step;
    }

    /**
     * {@inheritDoc}
     * @param array $response a response, as defined in get_expected_data().
     * @see question_automatically_gradable::grade_response()
     */
    public function grade_response(array $response) {
        if (!$this->use_async_evaluation()) {
            return $this->do_grade_response($response);
        } else {
            $this->queue_evaluation_task();
            return [ 0, question_state::$needsgrading ];
        }
    }

    /**
     * Determine if this question should use asynchronous evaluation or not.
     * @return boolean
     */
    public function use_async_evaluation() {
        // Check that the question uses async evaluation, that it is allowed from admin settigns and that a step is set.
        // For dry regrades, do not use async evaluation (because we can not properly apply the "needs grading" state).
        return !empty($this->useasynceval) && $this->step !== null && get_config('qtype_vplquestion', 'allowasynceval')
            && !optional_param('regradealldry', 0, PARAM_BOOL);
    }

    /**
     * Do the actual grading of the question, not queuing any task.
     * @param array $response a response, as defined in get_expected_data().
     * @see qtype_vplquestion_question::grade_response()
     */
    public function do_grade_response(array $response) {
        global $DB;
        try {
            $result = locallib::evaluate($this->get_answer($response), $this, (bool) $this->deletesubmissions);
            $vplresult = $result->vplresult;
            $grade = locallib::extract_fraction($vplresult, $this->templatevpl);
            if ($grade === null) {
                throw new moodle_exception('gradeisnull', 'qtype_vplquestion');
            }
            if ($this->gradingmethod == 0) {
                // All or nothing.
                $grade = floor($grade);
            }
            $gradingresult = [ $grade, question_state::graded_state_for_fraction($grade) ];
        } catch (moodle_exception $e) {
            // No grade obtained. Something went wrong, display a message as explicit as possible.
            $result->errormessage .= ($result->errormessage ? "\n" : '') . $e->getMessage();
            $vplresult->evaluationerror = locallib::make_evaluation_error_message($result, 'student');
            $gradingresult = [ 0, question_state::$gradedwrong ];

            if ($this->step !== null) {
                $quizattempt = locallib::get_info_from_step($this->step)->quizattempt;
                $event = \qtype_vplquestion\event\question_evaluation_failed::create([
                        'objectid' => $this->id,
                        'relateduserid' => $quizattempt->get_userid(),
                        'other' => [ 'attemptid' => $quizattempt->get_attemptid() ],
                        'context' => context_module::instance($quizattempt->get_cmid()),
                ]);
                $event->trigger();
            }
        }

        if ($this->step !== null) {
            // Store evaluation details as a qt var of initial attempt step,
            // to retrieve it from renderer (in order display the details from the renderer).
            $newvalue = json_encode($vplresult);
            if ($this->step instanceof question_attempt_step_read_only) {
                // The step is readonly, which means this is a standard attempt.
                // In that case, we store evaluation data directly in database.
                $table = 'question_attempt_step_data';
                $params = [ 'attemptstepid' => $this->step->get_id(), 'name' => '_evaldata' ];
                $currentrecord = $DB->get_record($table, $params);
                if ($currentrecord === false) {
                    $newrecord = array_merge($params, [ 'value' => $newvalue ]);
                    $DB->insert_record($table, $newrecord, false);
                } else {
                    $currentrecord->value = $newvalue;
                    $DB->update_record($table, $currentrecord);
                }
            } else {
                // The step is not readonly, which usually means this is a regrade.
                // In that case, cached qt data will be inserted in database, so we store evaluation data in a cached qt var.
                $this->step->set_qt_var('_evaldata', $newvalue);
            }
        }

        return $gradingresult;
    }

    /**
     * Create and queue an adhoc task that will do the grading when executed.
     */
    public function queue_evaluation_task() {
        global $DB;
        if ($this->step === null) {
            throw new moodle_exception('internal_undefinedstep', 'qtype_vplquestion');
        }
        $info = locallib::get_info_from_step($this->step);
        $userid = $this->step->get_user_id();
        $task = \qtype_vplquestion\task\grade_response_task::create($userid);

        // Check that evaluation is not already queued.
        // This can happen when regrading while some questions are still queued.
        $alreadyqueued = $DB->get_record('question_vplquestion_queue', [ 'usageid' => $info->usageid, 'slot' => $info->slot ]);
        if ($alreadyqueued) {
            $queueid = $alreadyqueued->id;
        } else {
            $queueid = $DB->insert_record('question_vplquestion_queue',
                    [ 'userid' => $userid, 'usageid' => $info->usageid, 'slot' => $info->slot ]);
        }
        \core\task\manager::queue_adhoc_task($task, true); // Queue at most one task per user.

        $taskid = $DB->get_field('task_adhoc', 'id', [
                'component' => 'qtype_vplquestion',
                'classname' => '\qtype_vplquestion\task\grade_response_task',
                'userid' => $userid,
        ]);
        $event = \qtype_vplquestion\event\question_evaluation_queued::create([
                'objectid' => $this->id,
                'relateduserid' => $info->quizattempt->get_userid(),
                'other' => [ 'attemptid' => $info->quizattempt->get_attemptid(), 'taskid' => $taskid, 'queueid' => $queueid ],
                'context' => context_module::instance($info->quizattempt->get_cmid()),
        ]);
        $event->trigger();

    }

}
