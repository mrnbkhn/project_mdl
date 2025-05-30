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
 * Exception for websocket communication.
 * @package    qtype_vplquestion
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_vplquestion;

/**
 * Describes an exception that occured during websocket setup or communication.
 * @copyright  2024 Astor Bizard
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class websocket_exception extends \Exception {
    /**
     * Construct a websocket exception.
     * @param string $str A string identifier from component 'qtype_vplquestion'.
     * @param mixed $code An error code.
     * @param mixed $a Any additional error information.
     */
    public function __construct($str, $code = null, $a = null) {
        $info = '';
        if ($code) {
            $info = " [$code" . ($a ? " - $a" : "") . "]";
        }
        parent::__construct(get_string($str, 'qtype_vplquestion') . $info);
    }
}
