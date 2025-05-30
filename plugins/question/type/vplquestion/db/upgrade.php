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
 * Plug-in version and dependencies description.
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs database actions to upgrade from older versions, if required.
 * @param int $oldversion
 * @return boolean
 */
function xmldb_qtype_vplquestion_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2019091700) {
        $table = new xmldb_table('question_vplquestion');

        // Define field precheckpreference to be added to question_vplquestion.
        $field = new xmldb_field('precheckpreference', XMLDB_TYPE_CHAR, '4', null, null, null, null, 'execfiles');

        // Conditionally launch add field precheckpreference.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field precheckexecfiles to be added to question_vplquestion.
        $field = new xmldb_field('precheckexecfiles', XMLDB_TYPE_TEXT, null, null, null, null, null, 'precheckpreference');

        // Conditionally launch add field precheckexecfiles.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field allowcheck to be dropped from question_vplquestion.
        $field = new xmldb_field('allowcheck');

        // Conditionally launch drop field allowcheck.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Vplquestion savepoint reached.
        upgrade_plugin_savepoint(true, 2019091700, 'qtype', 'vplquestion');
    }

    if ($oldversion < 2024101500) {

        // Define field deletesubmissions to be added to question_vplquestion.
        $table = new xmldb_table('question_vplquestion');
        $field = new xmldb_field('deletesubmissions', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'gradingmethod');

        // Conditionally launch add field deletesubmissions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $deletesubmissions = (int) get_config('qtype_vplquestion', 'deletevplsubmissions');
        $chunksize = 30;
        $i = 0;
        while ($records = $DB->get_records('question_vplquestion', null, '', 'id', $i, $chunksize)) {
            foreach ($records as $record) {
                $record->deletesubmissions = $deletesubmissions;
                $DB->update_record('question_vplquestion', $record);
            }
            $i += $chunksize;
        }

        // Vplquestion savepoint reached.
        upgrade_plugin_savepoint(true, 2024101500, 'qtype', 'vplquestion');
    }

    if ($oldversion < 2024102100) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/vpl/vpl.class.php');
        require_once($CFG->dirroot . '/mod/vpl/locallib.php');
        $chunksize = 30;
        $i = 0;
        $currenttemplate = null;
        $templatefiles = null;
        while ($records = $DB->get_records('question_vplquestion', null, 'templatevpl ASC', '*', $i, $chunksize)) {
            foreach ($records as $record) {
                if ($record->templatevpl != $currenttemplate) {
                    try {
                        $vpl = new mod_vpl($record->templatevpl);
                    } catch (Throwable $e) {
                        // Unable to instanciate VPL, skip the question.
                        continue;
                    }
                    $currenttemplate = $record->templatevpl;
                    $templatefiles = $vpl->get_execution_fgm()->getallfiles();
                }
                foreach ([ 'execfiles', 'precheckexecfiles' ] as $field) {
                    $files = json_decode($record->$field);
                    $newfiles = [];
                    foreach ($files as $name => $contents) {
                        if (!isset($templatefiles[$name])) {
                            // For some reason, file is not there anymore - don't touch anything.
                            $newfiles[$name] = $contents;
                        } else if (vpl_is_binary($name, $contents)) {
                            // Binary file are inherited - skip.
                            continue;
                        } else if (trim($contents) === trim($templatefiles[$name])) {
                            // Content is the same, switch to inherited - skip.
                            continue;
                        } else if (substr($contents, 0, 6) === 'UNUSED') {
                            // Use of legacy "UNUSED" keyword.
                            if (trim($contents) === 'UNUSED' || trim(substr($contents, 6)) === trim($templatefiles[$name])) {
                                // The file is empty or identical to template, switch to inherited - skip.
                                continue;
                            } else {
                                // There is other data in the file, keep it to avoid any loss.
                                $newfiles[$name] = $contents;
                            }
                        } else {
                            // File differs and is "normal", it is a proper overwritten file.
                            $newfiles[$name] = $contents;
                        }
                    }
                    $record->$field = json_encode($newfiles, JSON_FORCE_OBJECT); // Force object for consistency.
                }
                $DB->update_record('question_vplquestion', $record);
            }
            $i += $chunksize;
        }

        // Vplquestion savepoint reached.
        upgrade_plugin_savepoint(true, 2024102100, 'qtype', 'vplquestion');
    }

    if ($oldversion < 2024103100) {

        // Define field useasynceval to be added to question_vplquestion.
        $table = new xmldb_table('question_vplquestion');
        $field = new xmldb_field('useasynceval', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'deletesubmissions');

        // Conditionally launch add field useasynceval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table question_vplquestion_queue to be created.
        $table = new xmldb_table('question_vplquestion_queue');

        // Adding fields to table question_vplquestion_queue.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usageid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('slot', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table question_vplquestion_queue.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table question_vplquestion_queue.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch create table for question_vplquestion_queue.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Vplquestion savepoint reached.
        upgrade_plugin_savepoint(true, 2024103100, 'qtype', 'vplquestion');
    }

    return true;
}
