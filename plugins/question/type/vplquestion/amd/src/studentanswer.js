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
 * Defines the behavior of the student's answer form for a vplquestion.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* globals Terminal */
/* globals VPLTerminal */
define([
    'jquery',
    'core/url',
    'qtype_vplquestion/codeeditors',
    'qtype_vplquestion/vplservice',
    'qtype_vplquestion/scriptsloader',
    ], function($, url, CodeEditors, VPLService, ScriptsLoader) {

    /**
     * For VPLTerminal constructor - determines what to display on titlebar.
     * In our case, we just want to display if process is running or exited.
     * @param {String} key Key to map.
     * @return {String} Mapped string.
     */
    function str(key) {
        switch (key) {
            case 'console': return '[Process';
            case 'connected':
            case 'connecting':
            case 'running': return 'running]';
            case 'connection_closed': return 'exited]';
            default: return key;
        }
    }

    /**
     * Escape special html characters in a text.
     * @param {String} text HTML text to escape.
     * @return {String} Escaped text.
     */
    function escapeHtml(text) {
        var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return text.replace(/[&<>"']/g, function(c) {
            return map[c];
        });
    }

    /**
     * Build an html string to display the specified field of the result,
     * formatting titles (field name) and subtitles (lines starting with '-').
     * @param {Object} result Evaluation/execution result object.
     * @param {String} field Field of result to display.
     * @param {String} level CSS class fragment for error level.
     * @return {String} Formatted result as HTML fragment.
     */
    function makeResultHtml(result, field, level) {
        if (result[field]) {
            var formattedText = '';
            escapeHtml(result[field]).split('\n').forEach(function(line) {
                if (line.charAt(0) == '-') {
                    line = '<span class="vpl-test-title font-italic rounded px-1">' + line + '</span>';
                }
                formattedText += line + '\n';
            });
            return '<span class="vpl-result-title vpl-title-' + level + ' d-block font-weight-bold border border-dark pl-1 mb-1">' +
                        M.util.get_string(field, 'qtype_vplquestion') +
                    '</span>' +
                    formattedText.trim();
        }
        return '';
    }

    /**
     * Display result on screen in specified display.
     * If result is null, this method will try to get it from data-result attribute.
     * @param {String} displayId ID of DOM element in which result should be displayed.
     * @param {?Object} result Evaluation/execution result object, or null.
     */
    function displayResult(displayId, result) {
        var $display = $('#' + displayId);
        if (result === null) {
            // This method parses the JSON by itself - no need to parse it.
            result = $display.data('result');
        }
        var html = makeResultHtml(result, 'compilation', 'error')
            + makeResultHtml(result, 'evaluation', 'info')
            + makeResultHtml(result, 'execerror', 'error')
            + makeResultHtml(result, 'evaluationerror', 'error');
        if (!html) {
            html = makeResultHtml(result, 'execution', 'error');
        }
        $display[html ? 'show' : 'hide']();
        $display.html(html);
    }

    /**
     * Set up student answer box (ace editor, terminal and reset/correction, run and pre-check buttons).
     * @param {String|Number} questionId Question ID, used for DOM identifiers.
     * @param {String|Number} vplId VPL ID, used for ajax calls.
     * @param {String|Number} userId User ID, used for ajax calls.
     * @param {String} textareaName HTML name attribute of textarea used for student answer.
     * @param {Number} vplVersion Version number of mod_vpl (used to decide how to load scripts as they moved in 3.4 and 4.2).
     */
    function setup(questionId, vplId, userId, textareaName, vplVersion) {
        // This is the textarea that will recieve student's answer.
        var $textarea = $('textarea[name="' + textareaName + '"]');

        var $resetAndCorrectionButtons = $('#qvpl_reset_q' + questionId + ', #qvpl_correction_q' + questionId);

        // Setup ace editor THEN buttons (so Run and Check correctly take current ace text).
        CodeEditors.setupQuestionEditor($textarea, $resetAndCorrectionButtons, $textarea.data('lineoffset'))
        .done(function() {

            if ($textarea.attr('readonly') == 'readonly') {
                // We are in review (readonly) mode - do nothing more.
                return;
            }

            ScriptsLoader.loadVPLTerminal(vplVersion, function() {
                // Setup terminal to fix number of rows (default is too much to fit within a quiz).
                var oldPrototype = Terminal.prototype;
                Terminal = function(data) { // eslint-disable-line no-global-assign
                    data.rows = 10;
                    return new oldPrototype.constructor(data);
                };
                Terminal.prototype = oldPrototype;

                // Initialize the terminal on the wrapper.
                var wrapperId = 'terminal_wrapper_q' + questionId;
                var terminal = new VPLTerminal(wrapperId, wrapperId, str);
                if (typeof $('#' + wrapperId).dialog === 'function') { // Check needed for VPL before 3.4.
                    $('#' + wrapperId).dialog('option', 'draggable', false);
                }

                // Deactivate message function (it normally displays a ticking timer, which is annoying).
                terminal.setMessage = function() {
                    return;
                };

                // Move the terminal to a nice place within the question box.
                var qvplButtons = '#qvpl_buttons_q' + questionId;
                var $globalTerminalWrapper = $('#' + wrapperId).parent();
                $globalTerminalWrapper.insertAfter(qvplButtons);

                // Override connect function, that indirectly sets the terminal to be displayed somewhere else.
                var oldConnect = terminal.connect;
                terminal.connect = function() {
                    oldConnect.apply(terminal, arguments);
                    $globalTerminalWrapper.css('top', 0).css('left', 0);
                    $('body > .ui-widget-overlay.ui-front').first().remove(); // Remove the modal lock overlay.
                };

                // Change close button style to match the general question style.
                $globalTerminalWrapper.find('.ui-dialog-titlebar-close')
                .html('<i class="fa fa-close"></i>')
                .addClass('btn btn-secondary close-terminal');

                // Setup a VPL button (run, debug, or evaluate).
                var setupButton = function(action, icon, filestype) {
                    var $button = $(qvplButtons + ' button[data-action="' + action + '"]');
                    var $icon = $('<i class="fa fa-' + icon + '"></i>');
                    $('<span class="ml-2">').append($icon).appendTo($button);
                    var reenableButtons = function() {
                        $icon.attr('class', 'fa fa-' + icon);
                        $('.qvpl-buttons *').removeAttr('disabled');
                    };

                    $button.click(function() {
                        $('.qvpl-buttons *').attr('disabled', 'disabled');
                        $('.close-terminal').trigger('click');
                        $icon.attr('class', 'fa fa-refresh fa-spin');
                        // We got nested callbacks, but we can't promisify them,
                        // as callback may be called several times depending on the underlying websocket messages order.
                        VPLService.call('save', vplId, questionId, $textarea.val(), filestype)
                        .then(function() {
                            return VPLService.call('exec', action, vplId, userId, terminal, function(result) {
                                displayResult('vpl_result_q' + questionId, result);
                                reenableButtons();
                            });
                        })
                        .fail(function(details) {
                            displayResult('vpl_result_q' + questionId, {execerror: details});
                            reenableButtons();
                        });
                    });
                };

                setupButton('run', 'rocket', 'run');
                setupButton('debug', 'check-square-o', 'precheck');
                setupButton('evaluate', 'check-square-o', 'precheck');
            });
        })
        .done(function() {
            CodeEditors.getEditorPreferences()
            .done(function(prefs) {

                var $form = $('#qvpl_editor_preferences_form' + questionId);

                var $fontSizeInput = $('[name="vpl_fontsize' + questionId + '"]');
                $fontSizeInput.val(prefs.fontSize);

                var $aceThemeInput = $('[name="vpl_editortheme' + questionId + '"]');
                $aceThemeInput.val(prefs.aceTheme);

                // Some validation around font size.
                var prevFontSize = $fontSizeInput.val();
                $fontSizeInput.on('input', function() {
                    var newFontSize = Number($(this).val());
                    if (isNaN(newFontSize)) {
                        $(this).val(prevFontSize);
                    } else {
                        // Clamp in [1..48].
                        if (newFontSize < 1) {
                            newFontSize = 1;
                        } else if (newFontSize > 48) {
                            newFontSize = 48;
                        }
                        $(this).val(newFontSize);
                        prevFontSize = newFontSize;
                        CodeEditors.changeFontSize(newFontSize); // Apply changes as a preview.
                    }
                });

                // Setup increase and decrease font size buttons.
                $form.find('[data-role="fontsizeincr"]').click(function() {
                    $fontSizeInput.val(Number($fontSizeInput.val()) + 1);
                    $fontSizeInput.trigger('input');
                });
                $form.find('[data-role="fontsizedecr"]').click(function() {
                    $fontSizeInput.val(Number($fontSizeInput.val()) - 1);
                    $fontSizeInput.trigger('input');
                });

                $aceThemeInput.on('change', function() {
                    CodeEditors.changeTheme($(this).val()); // Apply changes as a preview.
                });

                $('#qvpl_editor_preferences' + questionId).click(function() {
                    $form.dialog({
                        autoOpen: true,
                        dialogClass: 'bg-white border border-primary qtype_vplquestion_editor_preferences_dialog',
                        title: M.util.get_string('editoroptions', 'qtype_vplquestion'),
                        closeOnEscape: true,
                        modal: true,
                        width: 400,
                        buttons:
                        [{
                            text: M.util.get_string('savechanges', 'moodle'),
                            'class': 'btn btn-primary mx-1',
                            click: function() {
                                $fontSizeInput.data('save', $fontSizeInput.val());
                                $aceThemeInput.data('save', $aceThemeInput.val());
                                CodeEditors.saveEditorPreferences($aceThemeInput.val(), $fontSizeInput.val());
                                $(this).dialog('close');
                            }
                        },
                        {
                            text: M.util.get_string('cancel', 'moodle'),
                            'class': 'btn btn-secondary mx-1',
                            click: function() {
                                $(this).dialog('close');
                            }
                        }],
                        create: function() {
                            $form.dialog('widget').find('.ui-dialog-title').before('<i class="fa fa-fw fa-cog icon"></i>');
                        },
                        open: function() {
                            $fontSizeInput.data('save', $fontSizeInput.val());
                            $aceThemeInput.data('save', $aceThemeInput.val());
                            var $widget = $form.dialog('widget');
                            $widget.focus();
                            $widget.next('.ui-widget-overlay').addClass('qtype_vplquestion_dialog_overlay');
                        },
                        close: function() {
                            $fontSizeInput.val($fontSizeInput.data('save'));
                            $aceThemeInput.val($aceThemeInput.data('save'));
                            CodeEditors.changeFontSize($fontSizeInput.val());
                            CodeEditors.changeTheme($aceThemeInput.val());
                            $('[name^="vpl_fontsize"]').val($fontSizeInput.val());
                            $('[name^="vpl_editortheme"]').val($aceThemeInput.val());
                        },
                    });
                });
            });
        });
    }

    return {
        setup: setup,
        displayResult: displayResult
    };
});
