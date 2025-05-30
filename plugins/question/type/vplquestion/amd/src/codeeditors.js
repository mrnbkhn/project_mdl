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
 * Provides utility methods to setup resizable ace editors into a page.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* globals ace */
define(['jquery', 'core/url', 'core/config'], function($, url, cfg) {

    // Global Ace editor theme and font size to use for all editors.
    var aceTheme;
    var fontSize;

    /**
     * Setup each specified textarea with Ace editor, with a vertical resize feature.
     * It inherits readonly attribute from textarea.
     * @param {jQuery} $textareas JQuery set of textareas from which to set up editors.
     * @param {String} aceSize Initial CSS size of editors.
     * @param {String} aceLang (optional) Lang (mode) to setup editors from.
     * @return {Editor} The last editor set up.
     */
    function setupAceEditors($textareas, aceSize, aceLang) {
        var aceEditor;

        // Vertical resizing.
        var prevY;
        var $placeholderBeingResized = null;

        if (aceLang === undefined) {
            aceLang = 'plain_text';
        }

        $textareas.each(function() {
            var $textarea = $(this);
            var $editorPlaceholder = $('<div>', {
                width: '100%',
                height: aceSize,
                'id': 'ace_placeholder_' + $textarea.attr('name'),
                'class': 'ace-placeholder'
            }).insertAfter($textarea);
            $textarea.hide();

            $('<div>', {
                'id': 'ace_resize_' + $textarea.attr('name'),
                'class': 'ace-resize'
            }).insertAfter($editorPlaceholder)
            .mousedown(function(event) {
                prevY = event.clientY;
                $placeholderBeingResized = $editorPlaceholder;
                event.preventDefault();
            });

            // This is what creates the Ace editor within the placeholder div.
            aceEditor = ace.edit($editorPlaceholder[0]);
            aceEditor.setOptions({
                theme: 'ace/theme/' + aceTheme,
                mode: 'ace/mode/' + aceLang
            });
            aceEditor.setFontSize(fontSize);
            aceEditor.$blockScrolling = Infinity; // Disable ace warning.
            aceEditor.getSession().setValue($textarea.val());
            aceEditor.setReadOnly($textarea.is('[readonly]'));

            // On submit or run/check, propagate the changes to textarea.
            $('[type=submit], .qvpl-buttons button').click(function() {
                // Cannot use aceEditor here, as it will have another value later.
                $textarea.val(ace.edit('ace_placeholder_' + $textarea.attr('name')).getValue());
            });
        });

        $(window).mousemove(function(event) {
            if ($placeholderBeingResized) {
                $placeholderBeingResized.height(function(i, height) {
                    return height + event.clientY - prevY;
                });
                prevY = event.clientY;
                ace.edit($placeholderBeingResized[0]).resize();
                event.preventDefault();
            }
        }).mouseup(function() {
            $placeholderBeingResized = null;
        });

        return aceEditor;
    }

    /**
     * Loads Ace script from VPL plugin.
     * @return {Promise} A promise that resolves upon load.
     */
    function loadAce() {
        if (typeof ace !== 'undefined' && typeof aceTheme !== 'undefined') {
            return $.Deferred().resolve();
        }
        var ACESCRIPTLOCATION = url.relativeUrl("/mod/vpl/editor/ace9");
        return $.when(
            $.ajax({
                url: ACESCRIPTLOCATION + '/ace.js',
                dataType: 'script',
                cache: true,
                success: function() {
                    ace.config.set('basePath', ACESCRIPTLOCATION);
                }
            }),
            getEditorPreferences().then(function(prefs) {
                aceTheme = prefs.aceTheme;
                fontSize = prefs.fontSize;
                return prefs;
            }),
        );
    }

    /**
     * Get current preferences for font size and editor theme.
     * @return {Promise} A promise that resolves upon load with an argument that is an object containing fontSize and aceTheme keys.
     */
    function getEditorPreferences() {
        return $.ajax({
            url: url.relativeUrl('/question/type/vplquestion/ajax/vplpreferences.json.php'),
            cache: true,
        }).promise().then(function(outcome) {
            return {
                aceTheme: outcome.success ? outcome.response.aceTheme : 'chrome',
                fontSize: outcome.success ? Number(outcome.response.fontSize) : 12,
            };
        });
    }

    /**
     * Save preferences for font size and editor theme.
     * @param {String} aceTheme The new theme.
     * @param {String|Number} fontSize The new font size.
     */
    function saveEditorPreferences(aceTheme, fontSize) {
        $.ajax({
            url: url.relativeUrl('/question/type/vplquestion/ajax/vplpreferences.json.php'),
            cache: false,
            method: 'POST',
            data: {
                set: {
                    aceTheme: aceTheme,
                    fontSize: Number(fontSize),
                },
                sesskey: cfg.sesskey,
            },
        });
    }

    return {
        // Setup editors in question edition form.
        setupFormEditors: function() {
            return loadAce().done(function() {
                setupAceEditors($('textarea[data-role="code-editor"]'), '170px');
            });
        },

        // Setup editor in answer form.
        setupQuestionEditor: function($textarea, $setTextButtons, lineOffset) {
            return loadAce().done(function() {
                // Setup question editor.
                var aceEditor = setupAceEditors($textarea, '200px', $textarea.data('templatelang'));
                // Set first line number to match compilation messages.
                aceEditor.setOption('firstLineNumber', lineOffset);
                // Setup reset and correction buttons (if present, ie. not review mode).
                $setTextButtons.each(function() {
                    var text = $(this).data('text');
                    $(this).removeAttr('data-text');
                    $(this).click(function(event) {
                        if (aceEditor.getValue() != text) {
                            aceEditor.setValue(text);
                        }
                        event.preventDefault();
                    });
                });
            });
        },

        getEditorPreferences: getEditorPreferences,

        saveEditorPreferences: saveEditorPreferences,

        changeFontSize: function(newFontSize) {
            $('.ace-placeholder').each(function() {
                ace.edit(this).setFontSize(Number(newFontSize));
            });
        },

        changeTheme: function(newTheme) {
            $('.ace-placeholder').each(function() {
                ace.edit(this).setOptions({
                    theme: 'ace/theme/' + newTheme
                });
            });
        },
    };
});
