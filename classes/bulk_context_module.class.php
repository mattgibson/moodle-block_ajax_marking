<?php
// This file is part of Moodle - http://moodle.org/
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
 * Class to facilitate a hacky way to get mny contexts in one go via SQL and then instantiate
 * context objects. Necessary to avoid 2500 DB calls with apply_sql_visible().
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2012 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Because the normal context classes have a protected constructor (to make sure the caching thingy
 * is used via context_module::instance()), we can't get 2500 or more contexts in less than 2500
 * DB queries because the cache only hold 2500 and doesn't work across sessions anyway. The reason
 * for doing this is to get a list of contexts for which the teacher has no marking capabilities
 * e.g. if there is an override in place. Too complex to put all that into SQL so we make use of
 * has_capability()'s caching in the session ot do it. This ugly hack allows us to instantiate
 * context objects to keep has_capability() happy, after getting
 * them via bulk SQL. This has a huge effect on performance, as on a large site without this, it
 * takes more time to construct the query than it does to execute it.
 */
class bulk_context_module extends context_module {

    /**
     * Just calls the normally protected constructor.
     *
     * @param \stdClass $record
     */
    public function __construct($record) {
        $uselessvariabletostopcodecheckercomplainingbecauseitdoesntcheckaccessors = '';
        parent::__construct($record);
    }

}
