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
 * Privacy provider tests.
 *
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard, 2021 The Open university
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qtype_vplquestion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/vplquestion/classes/privacy/provider.php');

/**
 * Privacy provider tests class.
 *
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard, 2021 The Open university
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends provider_testcase {
    // Include the privacy helper which has assertions on it.

    public function test_get_metadata(): void {
        $collection = new collection('qtype_vplquestion');
        $actual = provider::get_metadata($collection);
        $this->assertEquals($collection, $actual);
    }

    public function test_export_user_preferences_no_pref(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Test the export_user_preferences given different inputs
     * @dataProvider user_preference_provider

     * @param string $name The name of the user preference to get/set
     * @param string $value The value stored in the database
     * @param string $expected The expected transformed value
     */
    public function test_export_user_preferences($name, $value, $expected): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference("qtype_vplquestion_$name", $value, $user);
        provider::export_user_preferences($user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $preferences = $writer->get_user_preferences('qtype_vplquestion');
        foreach ($preferences as $key => $pref) {
            $preference = get_user_preferences("qtype_vplquestion_{$key}", null, $user->id);
            if ($preference === null) {
                continue;
            }
            $desc = get_string("privacy:preference:{$key}", 'qtype_vplquestion');
            $this->assertEquals($expected, $pref->value);
            $this->assertEquals($desc, $pref->description);
        }
    }

    /**
     * Create an array of valid user preferences for the multiple choice question type.
     *
     * @return array Array of valid user preferences.
     */
    public function user_preference_provider() {
        return [
                'default mark 1' => [ 'defaultmark', 1, 1 ],
                'penalty 33.33333%' => [ 'penalty', 0.3333333, '33.33333%' ],
                'precheck none' => [ 'precheckpreference', 'none', get_string('noprecheck', 'qtype_vplquestion') ],
                'precheck debug' => [ 'precheckpreference', 'dbg', get_string('precheckisdebug', 'qtype_vplquestion') ],
                'precheck same' => [ 'precheckpreference', 'same', get_string('precheckhassamefiles', 'qtype_vplquestion') ],
                'precheck ownfiles' => [ 'precheckpreference', 'diff', get_string('precheckhasownfiles', 'qtype_vplquestion') ],
                'grading allornothing' => [ 'gradingmethod', 0, get_string('allornothing', 'qtype_vplquestion') ],
                'grading scaling' => [ 'gradingmethod', 1, get_string('scaling', 'qtype_vplquestion') ],
                'deletesubs yes' => [ 'deletesubmissions', 1, 'Yes' ],
                'deletesubs no' => [ 'deletesubmissions', 0, 'No' ],
                'useasynceval yes' => [ 'useasynceval', 1, 'Yes' ],
                'useasynceval no' => [ 'useasynceval', 0, 'No' ],
        ];
    }
}
