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
 * Test suite for locallib class.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion;

/**
 * Test suite for locallib class.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib_test extends \advanced_testcase {

    public function test_format_execution_files(): void {
        $execfiles = [
                'vpl_evaluate.cases' => "Case=C1\ninput=\noutput=Hello World!\n\nCase=C2\nProgram to run=check.sh\noutput=ok\n",
                'some_useless_file' => "UNUSED\nLorem ipsum dolor sit amet\nconsectetur adipiscing elit.\n",
                'check.sh' => "#!/bin/bash\n\necho \"ok\"\n",
                'execution_helper.c' => "#include <stdio.h>\n\nvoid f() {\n    // This is just a helper\n}\n",
        ];
        $formattedfiles = locallib::format_execution_files($execfiles);
        $this->assertArrayHasKey('vpl_evaluate.cases_qvpl', $formattedfiles,
                'vpl_evaluate.cases should not be filtered out!');
        $this->assertEquals($execfiles['vpl_evaluate.cases'], $formattedfiles['vpl_evaluate.cases_qvpl'],
                'format_execution_files should not alter file contents!');
        $this->assertArrayNotHasKey('some_useless_file_qvpl', $formattedfiles,
                'some_useless_file starting with UNUSED should be filtered out!');
        $this->assertArrayHasKey('check.sh_qvpl', $formattedfiles,
                'check.sh should not be filtered out!');
        $this->assertEquals($execfiles['check.sh'], $formattedfiles['check.sh_qvpl'],
                'format_execution_files should not alter file contents!');
        $this->assertArrayHasKey('execution_helper.c_qvpl', $formattedfiles,
                'execution_helper.c should not be filtered out!');
        $this->assertEquals($execfiles['execution_helper.c'], $formattedfiles['execution_helper.c_qvpl'],
                'format_execution_files should not alter file contents!');
        $formattedfiles = locallib::format_execution_files($execfiles, [ 'execution_helper.c' ]);
        $this->assertArrayNotHasKey('vpl_evaluate.cases_qvpl', $formattedfiles,
                'vpl_evaluate.cases should be filtered out!');
        $this->assertArrayNotHasKey('some_useless_file_qvpl', $formattedfiles,
                'some_useless_file should be filtered out!');
        $this->assertArrayNotHasKey('check.sh_qvpl', $formattedfiles,
                'check.sh should be filtered out!');
        $this->assertArrayHasKey('execution_helper.c_qvpl', $formattedfiles,
                'execution_helper.c should not be filtered out!');
        $this->assertEquals($execfiles['execution_helper.c'], $formattedfiles['execution_helper.c_qvpl'],
                'format_execution_files should not alter file contents!');
    }

    public function test_get_ace_themes(): void {
        global $CFG;
        foreach (locallib::get_ace_themes() as $theme => $name) {
            $this->assertFileExists($CFG->dirroot . '/mod/vpl/editor/ace9/theme-' . $theme . '.js', 'Theme file ' . $name . ' not found!');
        }
    }

    public function test_get_mod_vpl_version(): void {
        global $CFG;
        $plugin = new \stdClass();
        require($CFG->dirroot . '/mod/vpl/version.php');
        $this->assertEquals($plugin->version, locallib::get_mod_vpl_version());
    }
}
