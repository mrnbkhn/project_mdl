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
 * Defines the behavior of the editing form for a vplquestion.
 * @copyright  Astor Bizard, 2019
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* globals ace */
define([
    'jquery',
    'core/log',
    'qtype_vplquestion/vplservice',
    'qtype_vplquestion/codeeditors',
    'qtype_vplquestion/scriptsloader',
    ], function($, log, VPLService, CodeEditors, ScriptsLoader) {

    var execfilesEditor = null;
    var precheckExecfilesEditor = null;
    var programaticChangeEditor = null;

    /**
     * Set editor content to a new content.
     * @param {Editor} aceEditor Ace editor object to reset.
     * @param {String} newContent The new content to put in the editor.
     * @param {Boolean} clearUndoHistory Whether to clear the undo history (hard reset).
     */
    function updateEditorContent(aceEditor, newContent, clearUndoHistory) {
        programaticChangeEditor = aceEditor;
        aceEditor.getSession().getDocument().setValue(newContent); // Call setValue on Document directly to bypass undo reset.
        programaticChangeEditor = null;
        if (clearUndoHistory) {
            aceEditor.getSession().setUndoManager(new ace.UndoManager());
        }
    }

    /**
     * Update display of execution files editors.
     * This does not include file tabs nor content updating. This is only a visibility update on some elements.
     */
    function updateExecfilesVisibility() {
        var selectedVpl = $('#id_templatevpl').val() > '';
        $('[data-role="novplmessage"]').toggle(!selectedVpl);
        $(`#execfileslist,
           #fitem_id_execfile,
           #fitem_id_execfileslist [data-role="scrollerarrow"],
           #fitem_id_execfileslist [data-role="filemanagement"]`).toggle(selectedVpl);

        var showPrecheckSection = $('#id_precheckpreference').val() == 'diff';
        var showPrecheckEditor = selectedVpl && showPrecheckSection;
        $('#fitem_id_precheckexecfileslist').toggle(showPrecheckSection);
        $(`#precheckexecfileslist,
           #fitem_id_precheckexecfile,
           #fitem_id_precheckexecfileslist [data-role="scrollerarrow"],
           #fitem_id_precheckexecfileslist [data-role="filemanagement"]`).toggle(showPrecheckEditor);

        // Refresh previously hidden editor (which otherwise does not display correctly).
        if ($('#ace_placeholder_precheckexecfile').length) {
            ace.edit('ace_placeholder_precheckexecfile').resize();
        }

        $('[data-role="filesloadicon"]').hide();
    }

    /**
     * Apply the current VPL template choice to form elements that depends on it (template content, execution files, ...).
     * @param {Boolean} keepContents Whether editors contents should be kept.
     *  This is typically false on initialization, true otherwise.
     */
    function applyTemplateChoice(keepContents) {
        var selectedVpl = $('#id_templatevpl').val();
        $('#fitem_id_templatecontext').toggle(selectedVpl > '');
        updateExecfilesVisibility();
        if (selectedVpl) {
            // Update template content.
            VPLService.call('info', 'reqfile', selectedVpl)
            .then(function(reqfile) {
                // Check that a required file is present. If not, display a warning.
                $('[data-role="no-reqfile-warning"]').toggle(!reqfile);
                reqfile = reqfile || {name: 'ERROR.txt', contents: ''}; // Fill reqfile with default value if needed.

                var lang = VPLService.langOfFile(reqfile.name);
                // Apply language change on editors.
                $('[data-role="code-editor"]:not([data-manylangs])~.ace-placeholder').each(function() {
                    ace.edit(this).getSession().setMode('ace/mode/' + lang);
                });

                // Store lang in hidden form element.
                $('[name=templatelang]').val(lang);

                if (!keepContents) {
                    // Choice changed (it is not initialization):
                    // Reinitialize template content to its original (VPL) state.
                    updateEditorContent(ace.edit('ace_placeholder_templatecontext'), reqfile.contents, true);
                }
                return reqfile;
            })
            .fail(function(message) { // The .catch method doesn't exist as it is a $.Deferred object (and not Promise).
                log.error(message, 'Error retrieving required files info for VPL ' + selectedVpl);
            });

            // Update execution files.
            VPLService.call('info', 'execfiles', selectedVpl)
            .then(function(execfiles) {
                // Check that pre_vpl_run.sh is present. If not, display a warning.
                $('[data-role="no-pre_vpl_run-warning"]').toggle(!execfiles.find((execfile) => execfile.name == 'pre_vpl_run.sh'));
                // Filter new exec files to exclude standard scripts.
                var standardScripts = ['vpl_run.sh', 'vpl_debug.sh', 'vpl_evaluate.sh', 'pre_vpl_run.sh'];
                execfiles = execfiles.filter((execfile) => !standardScripts.includes(execfile.name));

                updateNewExecfiles(execfiles, keepContents, '#execfileslist', execfilesEditor, $('[name=execfiles]'));
                updateNewExecfiles(execfiles, keepContents, '#precheckexecfileslist', precheckExecfilesEditor,
                        $('[name=precheckexecfiles]'));
                return execfiles;
            })
            .fail(function(message) { // The .catch method doesn't exist as it is a $.Deferred object (and not Promise).
                log.error(message, 'Error retrieving execution files info for VPL ' + selectedVpl);
            });
        }
    }

    /**
     * Generate HTML for a status chip (overwrite or inherit from VPL).
     * @param {String} status Typically either 'overwrite' or 'inherit'
     * @returns {String} HTML fragment.
     */
    function getStatusChipHTML(status) {
        return '<span class="d-inline-block ml-1 status-chip status-chip-' + status + '" data-status-chip="' + status + '"></span>';
    }

    /**
     * Setup one editor for execution files (with file tabs). Call this once on loading, then use updateNewExecfiles when changing.
     * @param {String} fileTabs Tabs selector for execution files.
     * @param {Editor} aceEditor Ace editor object.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function setupExecfilesEditor(fileTabs, aceEditor, $hiddenField) {
        var $fileTabs = $(fileTabs);
        $fileTabs.parent().find('[data-role="scrollerarrow"]')
        .click(function() {
            if ($(this).data('direction') === 'left') {
                $fileTabs[0].scrollLeft = Math.max($fileTabs[0].scrollLeft - 42, 0);
            } else {
                $fileTabs[0].scrollLeft = Math.min($fileTabs[0].scrollLeft + 42, $fileTabs[0].scrollLeftMax);
            }
        });

        // On form submit, write current editor value to the field that will be saved.
        $('input[type=submit]').click(function() {
            var $fileTab = $(fileTabs + ' .currentfile');
            var status = $fileTab.find('[data-status-chip]').data('status-chip');
            storeExecfile($fileTab.text(), status == 'overwrite' ? aceEditor.getValue() : null, $hiddenField);
        });

        $fileTabs.siblings('[data-role="filemanagement"]').find('input:radio')
        .each(function() {
            // Add status chip to radio button for readability.
            $(this).closest('label').append(getStatusChipHTML($(this).val()));
        })
        .click(function() {
            // Update status chip on current file.
            var newStatus = $(this).val();
            $fileTabs.find('.currentfile [data-status-chip]').replaceWith(getStatusChipHTML(newStatus));
        });

        var switchBackToDefaultFileMessage = M.util.get_string('switchbacktodefaultfileprompt', 'qtype_vplquestion');
        var $switchBackToDefaultFileDialog = $('<div class="py-3">' + switchBackToDefaultFileMessage + '</div>').dialog({
            autoOpen: false,
            dialogClass: 'editformdialog p-3 bg-white',
            title: M.util.get_string('switchbacktodefaultfile', 'qtype_vplquestion'),
            closeOnEscape: false,
            modal: true,
            buttons:
            [{
                text: M.util.get_string('confirm', 'moodle'),
                'class': 'btn btn-primary mx-1',
                click: function() {
                    updateEditorContent(aceEditor, $hiddenField.data('default_' + $fileTabs.find('.currentfile').text()), false);
                    $(this).dialog('close');
                }
            },
            {
                text: M.util.get_string('cancel', 'moodle'),
                'class': 'btn btn-secondary mx-1',
                click: function() {
                    $fileTabs.siblings('[data-role="filemanagement"]').find('input:radio[value="overwrite"]').click();
                    $(this).dialog('close');
                }
            }],
            open: function() {
                $('.editformdialog').focus();
            }
        });
        $fileTabs.siblings('[data-role="filemanagement"]').find('input:radio[value="inherit"]')
        .click(function() {
            if ($hiddenField.data('default_' + $fileTabs.find('.currentfile').text()).trim() !== aceEditor.getValue().trim()) {
                $switchBackToDefaultFileDialog.dialog('open');
            }
        });
    }

    /**
     * Update execution files and tabs to specified files.
     * @param {Object[]} execfiles Array of execution files.
     * @param {String} execfiles.name File name.
     * @param {?String} execfiles.contents File contents, null means inherit from VPL.
     * @param {Boolean} keepContents Whether current contents should be kept, or overwritten.
     * @param {String} fileTabs Tabs selector for execution files.
     * @param {Editor} aceEditor Ace editor object.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function updateNewExecfiles(execfiles, keepContents, fileTabs, aceEditor, $hiddenField) {
        // Empty exec files list.
        var $fileTabs = $(fileTabs).html('');

        // Create exec files object to store in hidden form element, and create file tabs.
        var execfilesObj = {};
        var initialContents = $hiddenField.val().length > 0 ? JSON.parse($hiddenField.val()) : {};
        execfiles.forEach(function(execfile) {
            var content = keepContents ? initialContents[execfile.name] : execfile.contents;
            var status = (keepContents && content !== undefined) ? 'overwrite' : 'inherit';
            var defaultContent = execfile.contents;
            if (VPLService.isBinary(execfile.name)) {
                // Binary files are not editable here.
                status = 'inherit';
                defaultContent = null;
            }
            if (status == 'overwrite') {
                execfilesObj[execfile.name] = content;
            }
            $fileTabs.append('<li class="execfilename">' +
                                 '<span class="clickable rounded-top">' +
                                     execfile.name + getStatusChipHTML(status) +
                                 '</span>' +
                             '</li>');
            $hiddenField.data('default_' + execfile.name, defaultContent);
        });
        $hiddenField.val(JSON.stringify(execfilesObj));

        // Setup file tabs navigation.
        $(fileTabs + ' .execfilename > span').click(function(event) {
            if (!$(this).is('.currentfile')) {
                updateExecfile($(fileTabs + ' .currentfile'), $(this), aceEditor, $hiddenField);
            }
            event.preventDefault();
        });

        aceEditor.on('change', function() {
            if (programaticChangeEditor == aceEditor) {
                // This event is fired by setValue, do not treat it as a user input.
                return;
            }
            // When user types something, change status to "overwrite".
            $fileTabs.siblings('[data-role="filemanagement"]').find('input:radio[value="overwrite"]').click();
        });

        // Initialize/re-initialize editor.
        updateExecfile(null, $(fileTabs + ' .execfilename > span').first(), aceEditor, $hiddenField);
    }

    /**
     * Store a file's content to hidden form element.
     * @param {String} fileName File name.
     * @param {String} fileContent File content.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function storeExecfile(fileName, fileContent, $hiddenField) {
        var execfiles = JSON.parse($hiddenField.val());
        if (fileContent === null) {
            delete execfiles[fileName];
        } else {
            execfiles[fileName] = fileContent;
        }
        $hiddenField.val(JSON.stringify(execfiles));
    }

    /**
     * Update Ace editor and tabs after user swapped to another file.
     * @param {jQuery} $prevFile The previously selected tab.
     * @param {jQuery} $newFile The new tab selected by the user.
     * @param {Editor} aceEditor Ace editor object.
     * @param {jQuery} $hiddenField Hidden form field in which files data is stored.
     */
    function updateExecfile($prevFile, $newFile, aceEditor, $hiddenField) {
        if ($prevFile !== null) {
            $prevFile.removeClass('currentfile');
            var prevStatus = $prevFile.find('[data-status-chip]').data('status-chip');
            storeExecfile($prevFile.text(), prevStatus == 'overwrite' ? aceEditor.getValue() : null, $hiddenField);
        }
        $newFile.addClass('currentfile');
        var $fileManagement = $newFile.closest('ul').siblings('[data-role="filemanagement"]');

        // Update radio selection.
        var newStatus = $newFile.find('[data-status-chip]').data('status-chip');
        $fileManagement.find('input:radio[value="' + newStatus + '"]').prop('checked', true);

        var newFileName = $newFile.text();

        if (VPLService.isBinary(newFileName)) {
            // If binary file, replace editor with blank space (and display "Binary file").
            $(aceEditor.container).addClass('invisible');
            $('<pre class="visible bg-white text-center h-100 p-3 position-relative" style="z-index:1" data-role="binaryfile">')
            .text(M.util.get_string('binaryfile', 'mod_vpl'))
            .appendTo($(aceEditor.container));
            $fileManagement.find('input:radio[value="overwrite"]').attr('disabled', 'disabled');
        } else {
            // Normal file, display editor.
            $(aceEditor.container).find('[data-role="binaryfile"]').remove();
            $(aceEditor.container).removeClass('invisible');
            $fileManagement.find('input:radio[value="overwrite"]').removeAttr('disabled');

            aceEditor.getSession().setMode('ace/mode/' + VPLService.langOfFile(newFileName));
            var newFileContents = JSON.parse($hiddenField.val())[newFileName];
            if (newFileContents === undefined) {
                newFileContents = $hiddenField.data('default_' + newFileName);
            }
            updateEditorContent(aceEditor, newFileContents, true);
        }
    }

    /**
     * Setup behaviour for template VPL change, by displaying a dialog with Overwrite/Merge/Cancel options.
     * The dialog will only show up if there is data to merge/overwrite.
     * @param {String} helpButton HTML fragment for help button.
     */
    function setupTemplateChangeManager(helpButton) {
        var $templateSelect = $('#id_templatevpl');
        var templateChangeMessage = M.util.get_string('templatevplchangeprompt', 'qtype_vplquestion') + helpButton;
        var $templateChangeDialog = $('<div class="py-3">' + templateChangeMessage + '</div>').dialog({
            autoOpen: false,
            dialogClass: 'editformdialog p-3 bg-white',
            title: M.util.get_string('templatevplchange', 'qtype_vplquestion'),
            closeOnEscape: false,
            modal: true,
            buttons:
            [{
                text: M.util.get_string('overwrite', 'qtype_vplquestion'),
                'class': 'btn btn-primary mx-1',
                click: function() {
                    // Apply the change without merging.
                    $templateSelect.data('current', $templateSelect.val());
                    $(this).dialog('close');
                    applyTemplateChoice(false);
                }
            },
            {
                text: M.util.get_string('merge', 'qtype_vplquestion'),
                'class': 'btn btn-primary mx-1',
                click: function() {
                    // Apply the change with merging.
                    $templateSelect.data('current', $templateSelect.val());
                    $(this).dialog('close');
                    applyTemplateChoice(true);
                }
            },
            {
                text: M.util.get_string('cancel', 'moodle'),
                'class': 'btn btn-secondary mx-1',
                click: function() {
                    // Undo select change.
                    $templateSelect.val($templateSelect.data('current'));
                    $(this).dialog('close');
                }
            }],
            open: function() {
                // By default, focus is on help button - make it less aggressive by focusing on the dialog.
                $('.editformdialog').focus();
            }
        });
        $templateSelect.focus(function() {
            // Save the previous value to manage cancel.
            $(this).data('current', $(this).val());
        }).change(function() {
            if ($templateSelect.val() && $('[name=execfiles]').val() != '') {
                // There is data to merge/overwrite, open a dialog to prompt the user.
                $templateChangeDialog.dialog('open');
            } else {
                // There is nothing to merge/overwrite, simply apply the change.
                $templateSelect.data('current', $templateSelect.val());
                applyTemplateChoice(false);
            }
        });
    }

    return {
        setup: function(templateChangeHelpButton, vplVersion) {
            // Setup all form editors.
            CodeEditors.setupFormEditors()
            .done(function() {
                ScriptsLoader.loadVPLUtil(vplVersion, function() {
                    execfilesEditor = ace.edit('ace_placeholder_execfile');
                    precheckExecfilesEditor = ace.edit('ace_placeholder_precheckexecfile');
                    setupExecfilesEditor('#execfileslist', execfilesEditor, $('[name=execfiles]'));
                    setupExecfilesEditor('#precheckexecfileslist', precheckExecfilesEditor, $('[name=precheckexecfiles]'));
                    // Setup form behavior (VPL template, execution files, etc).
                    applyTemplateChoice(true);
                    // Manage VPL template change.
                    setupTemplateChangeManager(templateChangeHelpButton);
                    $('#id_precheckpreference').change(updateExecfilesVisibility);
                });
            });
        }
    };
});
