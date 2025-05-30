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
 * Restore functions for Moodle 2.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides information to restore VPL Questions.
 * @copyright  Astor Bizard, 2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_vplquestion_plugin extends restore_qtype_extrafields_plugin {
    // This question type uses extra_question_fields(), so almost nothing to do.

    /**
     * @var array All VPL Questions processed during the restore.
     */
    protected $questions = [];

    /**
     * Process the qtype/vplquestion element
     * @param array $data question data
     */
    public function process_vplquestion($data) {
        // Store processed questions in order to link questions to new template VPLs.
        // We need to do this because after_restore_question() is only called once even if several questions are restored.
        $this->questions[] = [ 'qid' => $this->get_new_parentid('question'), 'oldtemplatevpl' => $data['templatevpl'] ];

        // Use the standard way for question fields.
        $this->really_process_extra_question_fields($data);
    }

    /**
     * Do some post-processing after questions and course modules are restored.
     * In this case, we link questions to the new template VPLs.
     */
    public function after_restore_question() {
        global $DB;
        foreach ($this->questions as $question) {
            $newtemplatevpl = $this->get_mappingid('course_module', $question['oldtemplatevpl']);
            if ($newtemplatevpl) {
                $qrecord = $DB->get_record('question_vplquestion', [ 'questionid' => $question['qid'] ]);
                $qrecord->templatevpl = $newtemplatevpl;
                $DB->update_record('question_vplquestion', $qrecord);
            }
        }
    }
}
