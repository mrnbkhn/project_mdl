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
 * Strings for component 'qtype_vplquestion', language 'en'
 * @package    qtype_vplquestion
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['additionaloptions'] = 'Additional options';
$string['allornothing'] = 'All or nothing';
$string['allowasynceval'] = 'Allow asynchronous evaluations';
$string['allowasynceval_desc'] = 'If enabled, teachers will be able to configure VPL Questions to be evaluated via adhoc tasks.';
$string['answertemplate'] = 'Answer template';
$string['answertemplate_help'] = 'Write here what code will be prefilled in the answer box for the student.';
$string['cannotimportquestionvplnotfound'] = 'Import warning: the VPL module id specified in VPL Question "{$a}" is invalid.';
$string['cannotimportquestionvplunreachable'] = 'Import warning: the VPL specified in VPL Question "{$a}" is not in this course.';
$string['choose'] = 'Choose...';
$string['closerecievednoretrieve'] = 'Operation aborted by execution server. Execution resources limits may have been exceeded.
Reason: {$a}';
$string['compilation'] = 'Compilation:';
$string['correction'] = 'Correction';
$string['deletesubmissions'] = 'Delete VPL submissions';
$string['deletesubmissions_help'] = 'Whether or not submissions of VPL Questions made on the VPL should be discarded on question evaluation.<br>
Caution: this will delete all submissions for concerned user on base VPL upon question evaluation. Make sure that the base VPL is only used for VPL Questions.';
$string['editorfontsize'] = 'Editor font size:';
$string['editoroptions'] = 'Editor options';
$string['editortheme'] = 'Editor theme:';
$string['errorvplgrade'] = 'VPL grade is not properly set (it should be set to "Point").';
$string['evaluating'] = 'This question is being graded...';
$string['evaluatingsoon'] = 'This question will be graded soon...';
$string['evaluatingsoontime'] = 'This question will be graded soon. Estimated wait time: {$a}.';
$string['evaluation'] = 'Evaluation:';
$string['evaluationdetails'] = 'Evaluation details:';
$string['evaluationerror'] = 'Evaluation error:';
$string['eventquestionasyncevaluated'] = 'VPL Question evaluated via adhoc task';
$string['eventquestionevaluationfailed'] = 'VPL Question evaluation failed';
$string['eventquestionevaluationqueued'] = 'VPL Question evaluation queued for evaluation';
$string['execerror'] = 'Execution error:';
$string['execfiles'] = 'Execution files';
$string['execfiles_help'] = 'You can edit here execution files. These are only sent during evaluation (and Pre-check if files are the same), and not during run (except for files specified as "to keep when running" in the VPL).<br>
To add files, add them in the VPL as execution files.<br>
Files marked as "Inherit from VPL" are not saved and use the contents of the corresponding execution file from the VPL activity.<br>
<em>Legacy</em>: Files starting with "UNUSED" will effectively inherit the VPL file contents. Please consider using the "Inherit from VPL" feature for these files.';
$string['execfilesevalsettings'] = 'Execution files and evaluate settings';
$string['execution'] = 'Execution error:';
$string['flagifproblem'] = 'If you think this is a problem with the question, please flag it and contact your teacher.';
$string['gradehaschangedreload'] = 'The grade may just have changed. You can <a {$a->aattr}>reload the page</a> to see the new grade.';
$string['gradetypeerror'] = 'It seems that the evaluation yielded a non-numeric grade.';
$string['gradingmethod'] = 'Grading';
$string['gradingmethod_help'] = 'Determines grading method for this question.
<ul><li>If "All or nothing" is selected, the student will earn either 100% or 0% of the mark for this question, depending on whether they got perfect VPL grade or not.</li>
<li>If "Scaling" is selected, the student\'s mark for this question will scale with their VPL grade.</li></ul>';
$string['informationtext'] = 'VPL Question';
$string['inheritfromvpl'] = 'Inherit from VPL';
$string['lastservermessage'] = 'Last execution server message recieved: "{$a}"';
$string['merge'] = 'Merge';
$string['noanswertag'] = 'Required {{ANSWER}} tag not found. Please include it in the template where student code will be placed.';
$string['nogradeerror'] = 'An error occured during question grading (no grade obtained).
{$a}';
$string['nogradenoerror'] = 'No error raised - raw grade recieved is "{$a}".';
$string['noprecheck'] = 'No Pre-check';
$string['noprevplrun'] = 'This template VPL has no pre_vpl_run.sh file!';
$string['noprevplrun_help'] = 'VPL Questions require template VPL to have a pre_vpl_run.sh execution file with contents specified in <a href="https://moodle.org/plugins/qtype_vplquestion" target="_blank">the documentation</a>.';
$string['noreqfile'] = 'This template VPL has no required file!';
$string['noreqfile_help'] = 'VPL Questions require template VPL to have one required file. The question will not work with the current state of this template.';
$string['overwrite'] = 'Overwrite';
$string['overwriteexecfile'] = 'Replace';
$string['pleaseanswer'] = 'Please provide an answer.';
$string['pluginname'] = 'VPL Question';
$string['pluginname_help'] = 'VPL Questions allow you to make simple coding exercises.<br>
It works with a VPL, but is designed to be a lot simpler on the students\' side.';
$string['pluginnameadding'] = 'Adding a VPL Question';
$string['pluginnameediting'] = 'Editing a VPL Question';
$string['pluginnamesummary'] = 'VPL Questions allow you to make simple coding exercises.<br>
It works with a VPL, but is designed to be a lot simpler on the students\' side.';
$string['possiblesolution'] = 'Possible solution:';
$string['precheck'] = 'Pre-check';
$string['precheckexecfiles'] = 'Pre-check execution files';
$string['precheckexecfiles_help'] = 'You can edit here execution files that will be used for Pre-check. For additional information, see help from "Execution files".';
$string['precheckhasownfiles'] = 'Pre-check uses its own execution files';
$string['precheckhassamefiles'] = 'Pre-check uses the same execution files as Check';
$string['precheckhelp'] = 'Evaluate your answer on a subset of tests';
$string['precheckisdebug'] = 'Pre-check is Debug';
$string['precheckpreference'] = 'Pre-check preference';
$string['precheckpreference_help'] = 'Determines whether the student will have access to a "Pre-check" button during question attempt (with unlimited use).
<ul><li>If "No Pre-check" is selected, no such button will be available.</li>
<li>If "Pre-check is Debug" is selected, the button will act as the "Debug" button on a VPL. Please note that it however does not provide usual graphic interface.</li>
<li>If "Pre-check uses the same execution files as Check" is selected, the button will evaluate the answer with execution files above.</li>
<li>If "Pre-check uses its own execution files" is selected, you will be able to edit specific execution files and they will be used for Pre-check. This is the recommended option, as it allows you to specify a subset of tests the student has access to during attempt.</li></ul>';
$string['privacy:preference:defaultmark'] = 'The default mark set for a given question.';
$string['privacy:preference:penalty'] = 'The penalty for each incorrect try when questions are run using the \'Interactive with multiple tries\' or \'Adaptive mode\' behaviour.';
$string['privacy:preference:deletesubmissions'] = 'Whether VPL submissions should be discarded on question evaluation.';
$string['privacy:preference:gradingmethod'] = 'Whether the grade should scale with VPL grade or be all-or-nothing.';
$string['privacy:preference:precheckpreference'] = 'The behaviour of the \'Pre-check\' button.';
$string['privacy:preference:useasynceval'] = 'Whether the question should be evaluated asynchronously via an adhoc task.';
$string['qvplbase'] = 'VPL Question template';
$string['reschedule_tasks_for_stranded_questions_task'] = 'Re-schedule adhoc tasks for stranded questions';
$string['run'] = 'Run';
$string['scaling'] = 'Scaling';
$string['selectavpl'] = '<a href="{$a}">Select a template VPL</a> to edit execution files.';
$string['serverexecutionerrorstudentmessage'] = 'This might be caused by an external factor. Please try to evaluate again or contact your teacher.';
$string['serverexecutionerrorteachermessage'] = 'This might be caused by an external factor, which means this is not necessarily something you did wrong. Please try to evaluate again or contact the support.';
$string['servermessages'] = 'Server messages:
{$a}';
$string['serverwassilent'] = 'Execution server was silent - no message received';
$string['switchbacktodefaultfile'] = 'Switching to Inherit mode';
$string['switchbacktodefaultfileprompt'] = 'You are about to change the file mode to "Inherit from VPL". This will overwrite the current content of the question file. Proceed?';
$string['teachercorrection'] = 'Teacher Correction';
$string['teachercorrection_help'] = 'Write here your correction for this question.';
$string['templatecontext'] = 'Edit template';
$string['templatecontext_help'] = 'You can edit here the code that will be executed (ie. the content of the required file).<br>
The "{{ANSWER}}" tag will be replaced by the student\'s answer. You can move the tag where you want, but please do not remove it!';
$string['templatevpl'] = 'Template VPL';
$string['templatevpl_help'] = 'Select the VPL this question will be based on.<br>
<b>Note:</b> Please select a VPL dedicated to this purpose, especially if "Delete VPL submissions" is set to "Yes" below.';
$string['templatevplchange'] = 'Template VPL change';
$string['templatevplchange_help'] = 'The template VPL code and execution files currently have content.<br>
Changing the template VPL will overwrite this content, unless you decide to merge the current content into the new one.<br>
Please note that the merge will only work on files with the same name. Files with no name correspondance will be overwritten.';
$string['templatevplchangeprompt'] = 'What do you want to do with the current content of template and execution files?';
$string['unexpectedendofws'] = 'Unexpected end of communication with the execution server.
Reason: {$a}';
$string['unexpectederror'] = 'An unexpected error occured during evaluation.
{$a}';
$string['useasyncevaluation'] = 'Use asynchronous evaluation';
$string['useasyncevaluation_help'] = 'If set to "Yes", the evaluation of the question will be done from adhoc asynchronous tasks. This allows the quiz to be more responsive.';
$string['validateonsave'] = 'Validate';
$string['validateonsave_help'] = 'If checked, the provided code will be tested against provided test cases before saving this question.';
$string['vplnotavailablewarning'] = 'Warning! The VPL this question is based on is not available. The question may not function properly.';
$string['vplnotfounderror'] = 'Error! The VPL this question is based on could not be instantiated:<br>{$a}';
$string['vplnotincoursewarning'] = 'Warning! The VPL this question is based on is not located in this course. The question may not function properly.';
$string['wsconnectionerror'] = 'Could not connect to server.';
$string['wshandshakeerror'] = 'Websocket handshake with server failed.';
$string['wsreaderror'] = 'Failed to read from websocket.';
