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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Story Form Class is defined here.
 *
 * @package     local_aiquestions
 * @category    admin
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to get the story from the user.
 *
 * @package     local_aiquestions
 * @category    admin
 */
class local_aiquestions_story_form extends moodleform
{
    /**
     * Defines forms elements
     */
    public function definition()
    {
        global $courseid;
        $mform = $this->_form;

        // Add some CSS to improve the UI
        $mform->addElement('html', '
            <style>
                .mform .fitem .fitemtitle {
                    width: 20%;
                }
                .mform .fitem .felement {
                    width: 75%;
                }
                .mform .dynamic-field-container {
                    margin-top: 10px;
                    margin-left: 20%;
                }
                .mform .fitem .felement select, 
                .mform .fitem .felement input[type="file"],
                .mform .fitem .felement textarea {
                    width: 100%;
                }
            </style>
        ');

        // Question category.
        $contexts = [context_course::instance($courseid)];
        $mform->addElement(
            'questioncategory',
            'category',
            get_string('category', 'question'),
            ['contexts' => $contexts]
        );
        $mform->addHelpButton('category', 'category', 'local_aiquestions');

        // Number of open questions.
        $mform->addElement(
            'select',
            'numofopenquestions',
            'Number of open questions',
            range(0, 10)
        );
        $mform->setType('numofopenquestions', PARAM_INT);

        // Number of multiple choice questions.
        $mform->addElement(
            'select',
            'numofmultiplechoicequestions',
            'Number of multiple choice questions',
            range(0, 10)
        );
        $mform->setType('numofmultiplechoicequestions', PARAM_INT);

        // JavaScript for validation.
        $mform->addElement('html', '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var numofopenquestions = document.querySelector("select[name=\'numofopenquestions\']");
                    var numofmultiplechoicequestions = document.querySelector("select[name=\'numofmultiplechoicequestions\']");
                    var form = document.querySelector("form");

                    function validateTotalQuestions() {
                        var openQuestions = parseInt(numofopenquestions.value);
                        var multipleChoiceQuestions = parseInt(numofmultiplechoicequestions.value);
                        var totalQuestions = openQuestions + multipleChoiceQuestions;

                        if (totalQuestions > 10) {
                            if (this === numofopenquestions) {
                                numofopenquestions.value = 10 - multipleChoiceQuestions;
                            } else {
                                numofmultiplechoicequestions.value = 10 - openQuestions;
                            }
                            alert("The total number of open and multiple choice questions cannot exceed 10. Adjusted the number automatically.");
                        }
                    }

                    numofopenquestions.addEventListener("change", validateTotalQuestions);
                    numofmultiplechoicequestions.addEventListener("change", validateTotalQuestions);
                });
            </script>
        ');

        // Checkbox for determining number of questions based on length or number.
        $mform->addElement('checkbox', 'basedonlength', 'Determine number of questions based on length');
        $mform->setType('basedonlength', PARAM_BOOL);

        // Numerical text box for the number of questions if based on length.
        $mform->addElement('text', 'numofquestionslength', 'Number of questions based on length');
        $mform->setType('numofquestionslength', PARAM_INT);
        $mform->disabledIf('numofquestionslength', 'basedonlength', 'notchecked');

        // Exam focus.
        $mform->addElement(
            'textarea',
            'examFocus',
            'Questions focus',
            ['rows' => 6, 'cols' => 50]
        );
        $mform->setDefault('examFocus', ''); // Set default value
        $mform->setType('examFocus', PARAM_RAW);

        // Language.
        $languages = ['English' => "English", 'Hebrew' => "Hebrew", 'Hindi' => "Hindi", 'Spanish' => 'Spanish', 'German' => "German", 'French' => "French", 'Russian' => "Russian", 'Arabic' => "Arabic"];
        $mform->addElement(
            'select',
            'examLanguage',
            'Questions language',
            $languages
        );

        // Field (exam type).
        $fieldoptions = ["topic" => "topic", "Text" => "text", "Upload file" => "Upload file", "URL" => "url", "Math" => "math"];
        $mform->addElement(
            'select',
            'field',
            'Input field type',
            $fieldoptions
        );
        $mform->setDefault('field', 'topic'); // Set default value
        $mform->setType('field', PARAM_RAW);

        // Container for dynamically changing input field.
        $mform->addElement('html','<div class="dynamic-field-container"></div>');

        $mform->addElement('hidden', 'textinput', 'hiddenfieldvalue');
        $mform->setType('textinput', PARAM_RAW);

        // Add a listener to dynamically change the input field based on the selected field type.
        $mform->addElement('html', '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var fieldSelect = document.querySelector("select[name=\'field\']");
                    var fieldInputContainer = document.querySelector(".dynamic-field-container");
                    
                    function updateFieldInput() {
                        var selectedValue = fieldSelect.value;
                        fieldInputContainer.innerHTML = "";
                        
                        if (selectedValue === "Upload file") {
                            fieldInputContainer.innerHTML = "<input type=\'file\' name=\'uploadedfile\' />";
                        } else {
                            fieldInputContainer.innerHTML = "<textarea name=\'textinput\' rows=\'4\' cols=\'50\'></textarea>";
                        }
                    }
                    
                    fieldSelect.addEventListener("change", updateFieldInput);
                    updateFieldInput(); // Initial call to set the correct input
                });
            </script>
        ');

        // Question level.
        $mform->addElement(
            'select',
            'questionLevel',
            'Questions Level',
            ["Academic" => "Academic"]
        );

        // Exam tags.
        $skills = ["Cognitive literacy" => "Cognitive literacy", "Mathematical literacy" => "Mathematical literacy", "Scientific literacy" => "Scientific literacy", "Critical Thinking" => "Critical Thinking"];
        $select = $mform->addElement(
            'select',
            'skills',
            'Skills',
            $skills,
            ['multiple' => true, 'size' => 3]
        );
        $select->setMultiple(true);
        
        // Courseid.
        $mform->addElement('hidden', 'courseid', $courseid);

        $mform->setType('courseid', PARAM_INT);

        // Buttons.
        $buttonarray = [];
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('generate', 'local_aiquestions'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancel', get_string('backtocourse', 'local_aiquestions'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files)
    {
        $errors = [];
        $totalquestions = $data['numofopenquestions'] + $data['numofmultiplechoicequestions'];
        if ($totalquestions > 10) {
            $errors['numofopenquestions'] = get_string('exceedstotalquestions', 'local_aiquestions');
            $errors['numofmultiplechoicequestions'] = get_string('exceedstotalquestions', 'local_aiquestions');
        }
        return $errors;
    }
}
?>
