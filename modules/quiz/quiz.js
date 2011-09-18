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
 * Javascript for displaying quizzes in the AJAX Marking block
 *
 * @package    block
 * @subpackage ajax_marking
 * @copyright  2011 Matt Gibson
 * @author     Matt Gibson {@link http://moodle.org/user/view.php?id=81450}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



M.block_ajax_marking.quiz = (function() {

    return {
        
        pop_up_arguments : function() {
            return 'menubar=0,location=0,scrollbars,resizable,width=780,height=670';
        },
        
        /**
         * Handles any module specific overrides for the trees
         */ 
        nextnodetype : function(defaultnexttype) {
            
            // TODO this will read from user settings later on
            var userchoosesquestions = true;
            
            switch (defaultnexttype) {
                
                case 'coursemoduleid':
                    if (userchoosesquestions === true) {
                        return 'questionid';
                    } else {
                        return 'userid'; // TODO this will cause duplicates if we're not adjusting it.
                    }
                    break;
                    
                case 'questionid':
                    return 'userid';
                    break;
                    
                default:
                    return null; // should never happen
                
            }
            
        }
        
    };
})();
          