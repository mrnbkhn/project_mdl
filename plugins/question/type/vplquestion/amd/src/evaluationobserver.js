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
 * Module checking the state of an evaluation task and updating the question feedback when it has been evaluated.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/url', 'core/log'], function($, url, log) {

    /**
     * Check if evaluation is finished and update displayed message.
     * @param {String} divid HTML id of question wrapping div.
     * @param {String|Object} questiondata Data for checkevaluationstate.json.php.
     */
    function updateEvaluationState(divid, questiondata) {
        $.ajax({
            url: url.relativeUrl('/question/type/vplquestion/ajax/checkevaluationstate.json.php'),
            data: questiondata,
        })
        .then(function(outcome) {
            if (!outcome.success) {
                throw new Error(outcome.error);
            }

            var $qdiv = $('#' + divid);

            var response = outcome.response;
            if (response.finished) {

                // Update the question feedback.
                $qdiv.find('.feedback, .im-feedback').remove();
                $qdiv.find('.outcome').prepend(response.qfeedback + response.bfeedback);
                $qdiv.append(response.javascript);
                if ($qdiv.find('.outcome').html() == $qdiv.find('.outcome .accesshide')[0].outerHTML) {
                    // No feedback: remove.
                    $qdiv.find('.outcome').remove();
                }

                // Update the state and grade in the question info block.
                $qdiv.find('.info .state').html(response.qinfo.state);
                $qdiv.find('.info .grade').html(response.qinfo.grade);

                // Update the navigation button color and title according to question state.
                $('#' + response.navbutton.id)
                .attr('title', response.navbutton.title)
                .removeClass(response.navbutton.oldclass).addClass(response.navbutton.newclass);

                // Add a message in the summary table if there is one, indicating that the overall quiz grade may have changed.
                $('table.quizreviewsummary th').each(function() {
                    if ($(this).text() == M.util.get_string('grade', 'quiz')) {
                        if ($(this).next().find('[data-role="reload-page-message"]').length == 0) {
                            var message = M.util.get_string('gradehaschangedreload', 'qtype_vplquestion',
                                    {aattr: 'href="#" onclick="window.location.reload();return false;"'});
                            var icon = '<i class="fa fa-info-circle ml-2 mr-1 text-info"></i>';
                            $(this).next().append('<span data-role="reload-page-message">' + icon + message + '</span>');
                        }
                    }
                });

                // Update step history with new state and new marks.
                $qdiv.find('.history thead th').each(function(i) {
                    if ($(this).text() == M.util.get_string('state', 'question')) {
                        $qdiv.find('.history tbody tr.current td.c' + i).text(response.qinfo.state);
                    } else if ($(this).text() == M.util.get_string('marks', 'question')) {
                        $qdiv.find('.history tbody tr.current td.c' + i).text(response.qinfo.marks);
                    }
                });
            } else {
                $qdiv.find('[data-qtype_vplquestion-role="async-eval-info"]').text(response.progressmessage);
                setTimeout(function() {
                    // Retry in 2 seconds.
                    updateEvaluationState(divid, questiondata);
                }, 2000);
            }

            return outcome;
        })
        .fail(log.error);
    }

    return {
        init: updateEvaluationState,
    };
});
