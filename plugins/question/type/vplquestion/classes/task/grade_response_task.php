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
 * Adhoc task evaluating a question asynchronously.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion\task;

use core\task\adhoc_task;
use context_module;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use moodle_exception;
use question_attempt_pending_step;
use question_engine;
use question_state;

/**
 * Adhoc task evaluating a question asynchronously.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_response_task extends adhoc_task {

    /**
     * Create a task given an user id.
     * @param int $userid
     * @return grade_response_task
     */
    public static function create($userid) {
        $task = new grade_response_task();
        $task->set_component('qtype_vplquestion');
        $task->set_userid($userid);
        return $task;
    }

    /**
     * {@inheritDoc}
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;

        $userid = $this->get_userid();

        while ($pendingevaluation = $DB->get_record('question_vplquestion_queue', [ 'userid' => $userid ], '*', IGNORE_MULTIPLE)) {
            $this->process_one_question($pendingevaluation->usageid, $pendingevaluation->slot);
            // Question has been processed, delete record.
            $DB->delete_records('question_vplquestion_queue', [ 'id' => $pendingevaluation->id ]);
        }
    }

    /**
     * Evaluate one question. This will call qtype_vplquestion_question::do_grade_response().
     * @param int $usageid The question usage id the question attempt belongs to.
     * @param int $slot The slot of the question within the usage.
     */
    protected function process_one_question($usageid, $slot) {
        global $DB;

        try {
            $quizattempt = quiz_attempt::create_from_usage_id($usageid);
            $questionattempt = $quizattempt->get_question_attempt($slot);
        } catch (moodle_exception $e) {
            // The attempt may not exist anymore. It is fine, just skip.
            return;
        }

        // Build a step with the last existing step.
        // Not creating a new step is essential to avoid sequence check errors.
        $lastquestionstep = $questionattempt->get_last_step();
        if ($lastquestionstep->get_state() != question_state::$needsgrading) {
            // Last step is not the one that needs grading. Question might have been manually graded. Skip.
            return;
        }
        $pendingstep = new question_attempt_pending_step(
                $lastquestionstep->get_all_data(),
                $lastquestionstep->get_timecreated(),
                $lastquestionstep->get_user_id(),
                $lastquestionstep->get_id()
                );

        try {
            // Grade the response. This is the long call to external server.
            $question = $questionattempt->get_question();
            $question->apply_attempt_state($pendingstep);
            list($fraction, $state) = $question->do_grade_response($questionattempt->get_last_qt_data());

            $transaction = $DB->start_delegated_transaction();

            // Apply the result of the evaluation (fraction and state) to the step and commit.
            $pendingstep->set_state($state);
            $pendingstep->set_fraction($fraction);
            $quba = question_engine::load_questions_usage_by_activity($usageid);
            $observer = $quba->get_observer();
            $observer->notify_step_modified($pendingstep, $questionattempt, $questionattempt->get_sequence_check_count());
            $observer->notify_attempt_modified($questionattempt);
            question_engine::save_questions_usage_by_activity($quba);

            // Commit the changes onto the whole quiz attempt.
            $attemptrecord = $DB->get_record('quiz_attempts', [ 'id' => $quizattempt->get_attemptid() ]);
            $attemptrecord->timemodified = time();
            if ($attemptrecord->state == quiz_attempt::FINISHED) {
                // Reload marks from database, as the ones stored in $quba are the old ones.
                $quba = question_engine::load_questions_usage_by_activity($usageid);
                $attemptrecord->sumgrades = $quba->get_total_mark();
            }
            $DB->update_record('quiz_attempts', $attemptrecord);
            if (!$quizattempt->is_preview() && $attemptrecord->state == quiz_attempt::FINISHED) {
                $quizattempt->get_quizobj()->get_grade_calculator()->recompute_final_grade($quizattempt->get_userid());
            }
            if ($regrade = $DB->get_record('quiz_overview_regrades', [ 'questionusageid' => $usageid, 'slot' => $slot ])) {
                if (abs($regrade->oldfraction - $fraction) > 1e-7) {
                    $regrade->newfraction = $fraction;
                    $regrade->timemodified = time();
                    $DB->update_record('quiz_overview_regrades', $regrade);
                } else {
                    $DB->delete_records('quiz_overview_regrades', [ 'id' => $regrade->id ]);
                }
            }

            $transaction->allow_commit();

            // Everything went fine, log.
            $event = \qtype_vplquestion\event\question_async_evaluated::create([
                    'objectid' => $question->id,
                    'relateduserid' => $quizattempt->get_userid(),
                    'other' => [ 'attemptid' => $quizattempt->get_attemptid(), 'taskid' => $this->get_id() ],
                    'context' => context_module::instance($quizattempt->get_cmid()),
            ]);
            $event->trigger();

        } catch (moodle_exception $e) {

            // An error occured, log it (but keep going).
            $event = \qtype_vplquestion\event\question_evaluation_failed::create([
                    'objectid' => $question->id,
                    'relateduserid' => $quizattempt->get_userid(),
                    'other' => [ 'attemptid' => $quizattempt->get_attemptid() ],
                    'context' => context_module::instance($quizattempt->get_cmid()),
            ]);
            $event->trigger();

        }
    }
}
