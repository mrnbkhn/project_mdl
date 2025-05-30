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
 * General Data Protection Regulation directive compliance.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard, 2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for qtype_vplquestion implementing user_preference_provider.
 *
 * @copyright  2024 Astor Bizard, 2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\user_preference_provider {

    /**
     * Returns meta data about this system.
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('qtype_vplquestion_defaultmark', 'privacy:preference:defaultmark');
        $collection->add_user_preference('qtype_vplquestion_penalty', 'privacy:preference:penalty');
        $collection->add_user_preference('qtype_vplquestion_precheckpreference', 'privacy:preference:precheckpreference');
        $collection->add_user_preference('qtype_vplquestion_gradingmethod', 'privacy:preference:gradingmethod');
        $collection->add_user_preference('qtype_vplquestion_deletesubmissions', 'privacy:preference:deletesubmissions');
        $collection->add_user_preference('qtype_vplquestion_useasynceval', 'privacy:preference:useasynceval');
        return $collection;
    }

    /**
     * Export all user preferences for the plugin.
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $preference = get_user_preferences('qtype_vplquestion_defaultmark', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference('qtype_vplquestion', 'defaultmark',
                    $preference,
                    get_string('privacy:preference:defaultmark', 'qtype_vplquestion'));
        }

        $preference = get_user_preferences('qtype_vplquestion_penalty', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference('qtype_vplquestion', 'penalty',
                    transform::percentage($preference),
                    get_string('privacy:preference:penalty', 'qtype_vplquestion'));
        }

        $preference = get_user_preferences('qtype_vplquestion_precheckpreference', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference('qtype_vplquestion', 'precheckpreference',
                    [
                            'none' => get_string('noprecheck', 'qtype_vplquestion'),
                            'dbg' => get_string('precheckisdebug', 'qtype_vplquestion'),
                            'same' => get_string('precheckhassamefiles', 'qtype_vplquestion'),
                            'diff' => get_string('precheckhasownfiles', 'qtype_vplquestion'),
                    ][$preference],
                    get_string('privacy:preference:precheckpreference', 'qtype_vplquestion'));
        }

        $preference = get_user_preferences('qtype_vplquestion_gradingmethod', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference('qtype_vplquestion', 'gradingmethod',
                    [ get_string('allornothing', 'qtype_vplquestion'), get_string('scaling', 'qtype_vplquestion') ][$preference],
                    get_string('privacy:preference:gradingmethod', 'qtype_vplquestion'));
        }

        $preference = get_user_preferences('qtype_vplquestion_deletesubmissions', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference('qtype_vplquestion', 'deletesubmissions',
                    transform::yesno($preference),
                    get_string('privacy:preference:deletesubmissions', 'qtype_vplquestion'));
        }

        $preference = get_user_preferences('qtype_vplquestion_useasynceval', null, $userid);
        if (null !== $preference) {
            writer::export_user_preference('qtype_vplquestion', 'useasynceval',
                    transform::yesno($preference),
                    get_string('privacy:preference:useasynceval', 'qtype_vplquestion'));
        }
    }

}
