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
        $fieldoptions = ["Topic" => "topic", "Text" => "text", "Upload file" => "Upload file", "URL" => "url", "Math" => "math"];
        $mform->addElement(
            'select',
            'field',
            'Input field type',
            $fieldoptions
        );
        $mform->setDefault('field', 'Topic'); // Set default value
        $mform->setType('field', PARAM_RAW);

        // Container for dynamically changing input field.
        $mform->addElement('html', '<div class="dynamic-field-container" style="margin-top: 10px; margin-left: 150px;"></div>');

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
                            fieldInputContainer.innerHTML = "<input type=\'file\' name=\'uploadedfile\' style=\'margin-left: 0px;\' />";
                        } else {
                            fieldInputContainer.innerHTML = "<textarea name=\'textinput\' rows=\'4\' cols=\'50\' style=\'margin-left: 0px;\'></textarea>";
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
        $mform->addElement(
            'select',
            'skills',
            'Skills',
            $skills,
            ['multiple' => true, 'size' => 3]
        );

        // Story.
        $mform->addElement(
            'textarea',
            'story',
            get_string('story', 'local_aiquestions'),
            ['rows' => 10, 'cols' => 50]
        );
        $mform->setType('story', PARAM_RAW);
        $mform->addHelpButton('story', 'story', 'local_aiquestions');

        // Preset.
        $presets = [];
        for ($i = 0; $i < 10; $i++) {
            if ($presetname = get_config('local_aiquestions', 'presetname' . $i)) {
                $presets[] = $presetname;
            }
        }
        $mform->addElement('select', 'preset', get_string('preset', 'local_aiquestions'), $presets);

        // Edit preset.
        $mform->addElement('checkbox', 'editpreset', get_string('editpreset', 'local_aiquestions'));
        $mform->addElement('html', get_string('shareyourprompts', 'local_aiquestions'));

        // Create elements for all presets.
        for ($i = 0; $i < 10; $i++) {

            $primer = $i + 1;

            // Primer.
            $mform->addElement(
                'textarea',
                'primer' . $i,
                get_string('primer', 'local_aiquestions'),
                ['rows' => 10, 'cols' => 50]
            );
            $mform->setType('primer' . $i, PARAM_RAW);
            $mform->setDefault('primer' . $i, get_config('local_aiquestions', 'presettprimer' . $primer));
            $mform->addHelpButton('primer' . $i, 'primer', 'local_aiquestions');
            $mform->hideIf('primer' . $i, 'editpreset');
            $mform->hideIf('primer' . $i, 'preset', 'neq', $i);

            // Instructions.
            $mform->addElement(
                'textarea',
                'instructions' . $i,
                get_string('instructions', 'local_aiquestions'),
                ['rows' => 10, 'cols' => 50]
            );
            $mform->setType('instructions' . $i, PARAM_RAW);
            $mform->setDefault('instructions' . $i, get_config('local_aiquestions', 'presetinstructions' . $primer));
            $mform->addHelpButton('instructions' . $i, 'instructions', 'local_aiquestions');
            $mform->hideIf('instructions' . $i, 'editpreset');
            $mform->hideIf('instructions' . $i, 'preset', 'neq', $i);

            // Example.
            $mform->addElement(
                'textarea',
                'example' . $i,
                get_string('example', 'local_aiquestions'),
                ['rows' => 10, 'cols' => 50]
            );
            $mform->setType('example' . $i, PARAM_RAW);
            $mform->setDefault('example' . $i, get_config('local_aiquestions', 'presetexample' . $primer));
            $mform->addHelpButton('example' . $i, 'example', 'local_aiquestions');
            $mform->hideIf('example' . $i, 'editpreset');
            $mform->hideIf('example' . $i, 'preset', 'neq', $i);
        }

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
