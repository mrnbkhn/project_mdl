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
 * Local lib for vplquestion question type.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion;

use Error;
use Exception;
use TypeError;
use core_plugin_manager;
use mod_quiz\quiz_attempt;
use mod_vpl;
use mod_vpl_edit;
use mod_vpl_submission;
use moodle_exception;
use question_attempt_step;
use stdClass;

/**
 * Local lib for vplquestion question type.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib {

    /**
     * Format and filter execution files provided by the user.
     * This method adds a suffix (_qvpl) to file names, and filters out files specified as UNUSED.
     * @param array|object $execfiles The files to format and filter.
     * @param array $selector If specified, only the files with name contained in this array will be considered.
     * @return array The resulting files array.
     */
    public static function format_execution_files($execfiles, $selector=null) {
        $formattedfiles = [];
        foreach ($execfiles as $name => $content) {
            if ($selector === null || in_array($name, $selector)) {
                if (substr($content, 0, 6) != 'UNUSED') {
                    $formattedfiles[$name.'_qvpl'] = $content;
                }
            }
        }
        return $formattedfiles;
    }

    /**
     * Insert answer into required file and format it for submission.
     * @param object $question The question data.
     * @param string $answer The answer to the question, to include in submission.
     * @return array Files ready for submission.
     */
    public static function get_reqfile_for_submission($question, $answer) {
        global $CFG;
        require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
        $vpl = new mod_vpl($question->templatevpl);

        $reqfiles = $vpl->get_required_fgm()->getAllFiles();
        $reqfilename = array_keys($reqfiles)[0];

        // Escape all backslashes, as following operation deletes them.
        $answer = preg_replace('/\\\\/', '$0$0', $answer);
        // Replace the {{ANSWER}} tag, propagating indentation.
        $answeredreqfile = preg_replace('/([ \t]*)(.*)\{\{ANSWER\}\}/i',
                '$1${2}'.implode("\n".'${1}', explode("\n", $answer)),
                $question->templatecontext);

        return [ $reqfilename => $answeredreqfile ];
    }

    /**
     * Evaluate an answer to a question by submitting it to the VPL and requesting an evaluate.
     * @param string $answer The answer to evaluate.
     * @param object $question The question data.
     * @param bool $deletesubmissions Whether user submissions should be discarded at the end of the operation.
     * @return object The evaluation result.
     */
    public static function evaluate($answer, $question, $deletesubmissions) {
        global $USER, $CFG;
        require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
        require_once($CFG->dirroot .'/mod/vpl/vpl_submission.class.php');
        require_once($CFG->dirroot .'/mod/vpl/forms/edit.class.php');

        $userid = $USER->id;
        $vpl = new mod_vpl($question->templatevpl);

        // Forbid simultaneous evaluations on the same VPL (as mod_vpl forbids multiple executions at once for one user on one VPL).
        $lockresource = $question->templatevpl . ':' . $userid;
        $lock = \core\lock\lock_config::get_lock_factory('qtype_vplquestion_evaluate')->get_lock($lockresource, 5, 3600);
        if ($lock === false) {
            throw new moodle_exception('locktimeout');
        }

        try {
            $reqfile = static::get_reqfile_for_submission($question, $answer);
            $execfiles = static::format_execution_files(json_decode($question->execfiles));
            $files = $reqfile + $execfiles;
            $subid = mod_vpl_edit::save($vpl, $userid, $files)->version ?? $vpl->last_user_submission($userid)->id; // VPL pre 3.4.

            $coninfo = mod_vpl_edit::execute($vpl, $userid, 'evaluate');

            // Although not a perfect check for secure connection, this should be enough .
            $isssl = ($_SERVER['HTTPS'] ?? 'off') !== 'off' || $_SERVER['SERVER_PORT'] == 443;
            if ($coninfo->wsProtocol == 'always_use_wss' || ($coninfo->wsProtocol == 'depends_on_https' && $isssl)) {
                $port = $coninfo->securePort;
                $protocol = 'ssl';
            } else {
                $port = $coninfo->port;
                $protocol = 'tcp';
            }

            $ws = new websocket($coninfo->server, $port, $protocol);

            $ws->open("/$coninfo->monitorPath");

            $closeflag = false;
            $retrieveflag = false;
            $servermessages = [];

            while (($message = $ws->read_next_message()) !== false) {
                $servermessages[] = $message;
                $parts = preg_split('/:/', $message);
                if ($parts[0] == 'close') {
                    $closeflag = true;
                }
                if ($parts[0] == 'retrieve') {
                    $retrieveflag = true;
                    break;
                }
            }

            // DO NOT close the connection.
            // If we send a close signal through the monitor websocket, the jail server will clean the task
            // and result retrieval will fail.
            // This is an issue with jail server, and is discussed here:
            // https://github.com/jcrodriguez-dis/vpl-jail-system/issues/75.

            $result = new stdClass();
            if ($retrieveflag) {
                // Only retrieve result if the 'retrieve:' flag was recieved.
                $result->vplresult = mod_vpl_edit::retrieve_result($vpl, $userid, $coninfo->processid ?? -1);
            } else {
                // We got no 'retrieve:' flag - it may be because execution ressources limits have been exceeded.
                $ws->close();
                $reason = 'unknown';
                foreach ($servermessages as $servermessage) {
                    $matches = [];
                    if (preg_match('/message:(timeout|outofmemory)/', $servermessage, $matches)) {
                        $reason = $matches[1];
                    }
                }
                $message = get_string($closeflag ? 'closerecievednoretrieve' : 'unexpectedendofws', 'qtype_vplquestion', $reason);
                if ($reason == 'unknown') {
                    $message .= "\n" . get_string('servermessages', 'qtype_vplquestion', implode("\n", $servermessages));
                    $message .= "\n" . get_string('flagifproblem', 'qtype_vplquestion');
                }

                // Format a result that will be interpreted as a wrong answer.
                $submission = new mod_vpl_submission($vpl, $subid);
                $result->vplresult = $submission->get_ce_for_editor([
                        'compilation' => '',
                        'executed' => 1,
                        'execution' => $message . "\n" . mod_vpl_submission::GRADETAG . " 0\n",
                ]);
            }

            // Now we can close the websocket.
            $ws->close();

            $result->servermessages = $servermessages;
            $result->errormessage = '';

        } catch (Exception | Error $e) {
            // There was an unexpected error during evaluation.
            $result = new stdClass();
            $result->vplresult = new stdClass();
            $result->servermessages = $servermessages ?? [];
            $result->errormessage = $e instanceof TypeError ? get_string('gradetypeerror', 'qtype_vplquestion') : $e->getMessage();
        } finally {
            // Always release locks.
            if ($lock) {
                $lock->release();
            }
        }

        if ($deletesubmissions) {
            try {
                require_once($CFG->dirroot.'/mod/vpl/vpl_submission.class.php');
                foreach ($vpl->user_submissions($userid) as $subrecord) {
                    $submission = new mod_vpl_submission($vpl, $subrecord);
                    $submission->delete();
                }
            } catch (Exception $e) {
                // Something went wrong while deleting submissions - do nothing more.
                return $result;
            }
        }

        return $result;
    }

    /**
     * Compute the fraction (grade between 0 and 1) from the result of an evaluation.
     * @param object $result The evaluation result.
     * @param int $templatevpl The ID of the VPL this evaluation has been executed on.
     * @return float|null The fraction if any, or null if there was no grade.
     */
    public static function extract_fraction($result, $templatevpl) {
        global $CFG;
        if (!empty($result->grade)) {
            require_once($CFG->dirroot .'/mod/vpl/vpl.class.php');
            $maxgrade = (new mod_vpl($templatevpl))->get_grade();
            if ($maxgrade <= 0) {
                throw new moodle_exception('errorvplgrade', 'qtype_vplquestion');
            }
            // Extract grade from "<Proposed grade:> <grade> / <grademax>" string.
            $sep = str_replace('/', '\/', get_string('decsep', 'langconfig'));
            $numbersingrade = null;
            preg_match_all('/[\d.' . $sep . ']+/', $result->grade, $numbersingrade, PREG_SET_ORDER); // Regex for localized float.
            if (empty($numbersingrade)) {
                throw new moodle_exception('gradetypeerror', 'qtype_vplquestion');
            }
            // Use the previous-to-last number if there is one, else the last.
            $offset = count($numbersingrade) < 2 ? 1 : 2;
            $fraction = unformat_float($numbersingrade[count($numbersingrade) - $offset][0]) / $maxgrade;
            return $fraction;
        } else {
            return null;
        }
    }

    /**
     * Create a human-readable error message of why an evaluation went wrong.
     * This is to be called when the grade is null.
     * @param stdClass $evaluateresult Result as returned by evaluate().
     * @param string $usertype Either 'student' or 'teacher'.
     * @return string The formatted error message.
     */
    public static function make_evaluation_error_message($evaluateresult, $usertype = 'student') {
        $details = [];
        if ($evaluateresult->errormessage) {
            $details[] = $evaluateresult->errormessage;
        } else {
            $details[] = get_string('nogradenoerror', 'qtype_vplquestion', $evaluateresult->vplresult->grade);
        }
        if (empty($evaluateresult->servermessages)) {
            $details[] = get_string('serverwassilent', 'qtype_vplquestion');
        } else {
            $lastmessage = '';
            foreach ($evaluateresult->servermessages as $servermessage) {
                $lastmessage = $servermessage ?: $lastmessage; // Get last non-empty message.
            }
            $details[] = get_string('lastservermessage', 'qtype_vplquestion', $lastmessage);
        }
        $servererrormessage = get_string('serverexecutionerror', 'mod_vpl');
        if (strpos(ltrim($evaluateresult->errormessage), $servererrormessage) !== false) {
            $details[] = get_string('serverexecutionerror' . $usertype . 'message', 'qtype_vplquestion');
        }
        return get_string('nogradeerror', 'qtype_vplquestion', implode("\n", $details));
    }

    /**
     * Retrieve information about asynchronous evaluation of a given question attempt.
     * @param int $usageid The question usage id the question attempt belongs to.
     * @param int $slot The slot of the question within the usage.
     * @param boolean $scheduletaskifstranded If true, this method will re-schedule a task if the question is queued for evaluation,
     *                                        but without a task to evaluate it.
     * @return array First value is whether the evaluation is still queued, second is a formatted message about evaluation status.
     */
    public static function get_async_evaluation_status($usageid, $slot, $scheduletaskifstranded = true) {
        global $DB;
        $pendingevaluation = $DB->get_record('question_vplquestion_queue', [ 'usageid' => $usageid, 'slot' => $slot ]);
        if ($pendingevaluation === false) {
            // Evaluation is not queued.
            return [ false, null ];
        }
        $taskrecord = $DB->get_record('task_adhoc', [
                'component' => 'qtype_vplquestion',
                'classname' => '\qtype_vplquestion\task\grade_response_task',
                'userid' => $pendingevaluation->userid,
        ]);
        if ($taskrecord !== false) {
            if (isset($taskrecord->timestarted) && $taskrecord->timestarted !== null) {
                $timeleft = 0;
                $running = true;
            } else {
                $timeleft = (int) max(0, round(($taskrecord->nextruntime - time()) / 60.0) * 60.0);
                $running = false;
            }
        } else if ($scheduletaskifstranded) {
            // No task assigned to evaluate the question.
            // Schedule one.
            $task = \qtype_vplquestion\task\grade_response_task::create($pendingevaluation->userid);
            \core\task\manager::queue_adhoc_task($task, true);
            $timeleft = 0;
            $running = false;
        } else {
            return [ false, null ];
        }
        // Format a message about evaluation status.
        if ($running) {
            $message = get_string('evaluating', 'qtype_vplquestion');
        } else if ($timeleft == 0) {
            $message = get_string('evaluatingsoon', 'qtype_vplquestion');
        } else {
            $message = get_string('evaluatingsoontime', 'qtype_vplquestion', format_time($timeleft));
        }
        return [ true, $message ];
    }

    /**
     * Retrieve information from one question attempt step.
     * @param question_attempt_step $step The question attempt step we want to retrieve information for.
     * @return object Object containing fields:
     *                - quizattempt contains the quiz_attempt object.
     *                - usageid contains the question usage id.
     *                - slot contains the question slot.
     */
    public static function get_info_from_step(question_attempt_step $step) {
        global $DB;
        // Retrieve question attempt data from DB as $this->step does not hold the reference to current attempt.
        $qa = $DB->get_record_sql(
                "SELECT qa.questionusageid as usageid, qa.slot
                   FROM {question_attempts} qa
                   JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                  WHERE qas.id = :qasid",
                [ 'qasid' => $step->get_id() ]);
        return (object)[
                'quizattempt' => quiz_attempt::create_from_usage_id($qa->usageid),
                'usageid' => $qa->usageid,
                'slot' => $qa->slot,
        ];
    }

    /**
     * Get the currently installed version of VPL plugin.
     * @return number The current version of mod_vpl.
     */
    public static function get_mod_vpl_version() {
        global $CFG;
        require_once($CFG->libdir . '/classes/plugin_manager.php');
        return core_plugin_manager::instance()->get_plugin_info('mod_vpl')->versiondisk;
    }

    /**
     * Get the currently installed themes for ace editor.
     * @return string[] Associative array of themes id => name.
     */
    public static function get_ace_themes() {
        global $CFG;
        $themes = [];
        // Search for theme files.
        $acefiles = array_diff(scandir($CFG->dirroot . '/mod/vpl/editor/ace9/'), [ '.', '..' ]);
        $themefiles = array_filter($acefiles, function($name) {
            return substr($name, 0, 6) == 'theme-';
        });
        // Process theme files names to get displayable name,
        // by replacing underscores by spaces and
        // by putting upper case letters at the beginning of words.
        foreach ($themefiles as $themefile) {
            $theme = substr($themefile, 6, -3);
            $themename = preg_replace_callback('/(^|_)([a-z])/', function($matches) {
                return ' ' . strtoupper($matches[2]);
            }, $theme);
                $themes[$theme] = trim($themename);
        }
        // Some exceptions.
        $specialnames = [
                'github' => 'GitHub',
                'idle_fingers' => 'idle Fingers',
                'iplastic' => 'IPlastic',
                'katzenmilch' => 'KatzenMilch',
                'kr_theme' => 'krTheme',
                'kr' => 'kr',
                'pastel_on_dark' => 'Pastel on dark',
                'sqlserver' => 'SQL Server',
                'textmate' => 'TextMate',
                'tomorrow_night_eighties' => 'Tomorrow Night 80s',
                'xcode' => 'XCode',
        ];
        foreach ($specialnames as $theme => $newname) {
            if (isset($themes[$theme])) {
                $themes[$theme] = $newname;
            }
        }
        return $themes;
    }

}
