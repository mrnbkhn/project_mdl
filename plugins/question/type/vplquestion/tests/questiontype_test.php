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
 * Test suite for questiontype.php.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/vplquestion/questiontype.php');

/**
 * Test suite for questiontype.php.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_vplquestion_test extends advanced_testcase {

    /**
     * @var question_type|null Question type instance.
     */
    protected $qtype;

    protected function setUp(): void {
        $this->qtype = new qtype_vplquestion();
    }

    protected function tearDown(): void {
        $this->qtype = null;
    }

    public function test_name(): void {
        $this->assertEquals('vplquestion', $this->qtype->name());
    }

    public function test_is_real_question_type(): void {
        $this->assertTrue($this->qtype->is_real_question_type());
    }

    public function test_can_analyse_responses(): void {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_questionid_column_name(): void {
        global $DB;
        $this->assertTrue($DB->get_manager()->field_exists('question_vplquestion', $this->qtype->questionid_column_name()));
    }

    public function test_extra_question_fields(): void {
        $extrafields = $this->qtype->extra_question_fields();
        $this->assertTrue(is_array($extrafields));
        $this->assertNotEmpty($extrafields);
        $this->assertEquals('question_vplquestion', reset($extrafields));
    }
}
