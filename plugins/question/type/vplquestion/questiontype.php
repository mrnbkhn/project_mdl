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
 * Question type class for the vplquestion question type.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/questiontypebase.php');

/**
 * The vplquestion type class.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion extends question_type {

    /**
     * {@inheritDoc}
     * @see question_type::extra_question_fields()
     */
    public function extra_question_fields() {
        return [ "question_vplquestion",
            "templatevpl",
            "templatelang",
            "templatecontext",
            "answertemplate",
            "teachercorrection",
            "validateonsave",
            "execfiles",
            "precheckpreference",
            "precheckexecfiles",
            "gradingmethod",
            "deletesubmissions",
            "useasynceval",
        ];
    }

    public function save_defaults_for_new_questions(stdClass $fromform): void {
        parent::save_defaults_for_new_questions($fromform);
        $this->set_default_value('precheckpreference', $fromform->precheckpreference);
        $this->set_default_value('gradingmethod', $fromform->gradingmethod);
        $this->set_default_value('deletesubmissions', $fromform->deletesubmissions);
        $this->set_default_value('useasynceval', $fromform->useasynceval);
    }

    /**
     * Imports question from the Moodle XML format.
     *
     * This function uses the default behavior and checks that template VPL is valid.
     *
     * @param mixed $data
     * @param mixed $question
     * @param qformat_xml $format
     * @param mixed|null $extra
     *
     * @see question_type::import_from_xml()
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        global $COURSE, $OUTPUT;
        $importdata = parent::import_from_xml($data, $question, $format, $extra);
        try {
            if (get_course_and_cm_from_cmid($importdata->templatevpl, 'vpl')[0]->id != $COURSE->id) {
                echo $OUTPUT->notification(
                        get_string('cannotimportquestionvplunreachable', 'qtype_vplquestion', $importdata->name),
                        'warning');
            }
        } catch (moodle_exception $e) {
            echo $OUTPUT->notification(
                    get_string('cannotimportquestionvplnotfound', 'qtype_vplquestion', $importdata->name),
                    'warning');
        }
        return $importdata;
    }
}
