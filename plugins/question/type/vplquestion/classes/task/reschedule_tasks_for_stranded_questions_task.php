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
 * Scheduled task to ensure questions queued for asynchronous evaluation are not stranded without a task to evaluate them.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion\task;

use core\task\scheduled_task;

/**
 * Scheduled task to ensure questions queued for asynchronous evaluation are not stranded without a task to evaluate them.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reschedule_tasks_for_stranded_questions_task extends scheduled_task {

    /**
     * {@inheritDoc}
     * @see \core\task\scheduled_task::get_name()
     */
    public function get_name() {
        return get_string('reschedule_tasks_for_stranded_questions_task', 'qtype_vplquestion');
    }

    /**
     * {@inheritDoc}
     * @see \core\task\task_base::execute()
     */
    public function execute() {
        global $DB;
        $userswithstrandedquestions = $DB->get_fieldset_sql(
                "SELECT DISTINCT q.userid
                   FROM {question_vplquestion_queue} q
              LEFT JOIN {task_adhoc} t ON t.userid = q.userid
                  WHERE t.userid IS NULL OR t.classname <> :classname OR t.component <> :component",
                [ 'component' => 'qtype_vplquestion', 'classname' => '\qtype_vplquestion\task\grade_response_task' ]);
        foreach ($userswithstrandedquestions as $userwithstrandedquestions) {
            // These users have queued questions without a task assigned to evaluate them.
            // Schedule an appropriate task.
            $task = \qtype_vplquestion\task\grade_response_task::create($userwithstrandedquestions);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

}
