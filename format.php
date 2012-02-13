<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file defines the main qformat_stack plugin.
 *
 * @package   qformat_stack
 * @copyright 2012 Matti Pauna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * This question importer class will import the Stack 2.0 XML format.
 *
 * This makes it possible to import existing stack questions into Moodle.
 *
 * @copyright 2012 Matti Pauna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_stack extends qformat_default {

    public function provide_import() {
        return true;
    }

    public function readquestions($lines) {
        $data = $this->questionstoarray(implode($lines));
        var_dump($data);

        return array();
    }

    public function mime_type() {
        return 'application/xml';
    }

    protected function text_field($text) {
        return array(
            'text' => htmlspecialchars(trim($text), ENT_NOQUOTES),
            'format' => FORMAT_HTML,
            'files' => array(),
        );
    }

    public function readquestion($lines) {
        //this is no longer needed but might still be called by default.php
        return;
    }

    /**
     * Read STACK questions from a string and process it to a list of question arrays
     * @param string $xmlstr STACK questions as an XML string
     * @return array containing question arrays
     */
    protected function questionstoarray($xmlstr) {
        $root = new SimpleXMLElement($xmlstr);
        $result = array();

        if ($root->getName() == 'assessmentItem') {
            $result[] = $this->questiontoarray($root);
        } else if ($root->getName() == 'mathQuiz') {
            foreach ($root->assessmentItem as $assessmentItem) {
                $result[] = $this->questiontoarray($assessmentItem);
            }
        }

        return $result;
    }

    /**
     * Utility function for processing next potential response tree elements
     * @param string $tf which node, true or false
     * @param SimpleXMLElement $PRXML potential response element
     * @return subelements as an array
     */
     protected function getnextPR($tf, $PRXML) {
        $result = array();

        $node = $PRXML->$tf;

        $result['rawModMark'] = (string) $node->rawModMark;
        $result['rawMark'] = (string) $node->rawMark;
        $result['feedback'] = (string) $node->feedback;
        $result['ansnote'] = (string) $node->ansnote;
        $result['nextPR'] = (string) $node->nextPR;

        return $result;
    }

    /**
     * Process a single question into an array
     * @param SimpleXMLElement $assessmentItem
     * @return the question as an array
     */
    protected function questiontoarray($assessmentItem) {
        $question = array();
        $questionCasValues = array();

        $questionCasValues['questionStem'] =
                (string) $assessmentItem->questionCasValues->questionStem->castext;

        $questionCasValues['questionVariables'] =
                (string) $assessmentItem->questionCasValues->questionVariables->rawKeyVals;

        $questionCasValues['workedSolution'] =
                (string) $assessmentItem->questionCasValues->workedSolution->castext;

        $questionCasValues['questionNote'] =
                (string) $assessmentItem->questionCasValues->questionNote->castext;

        $question['questionCasValues'] = $questionCasValues;

        $questionparts = array();
        foreach ($assessmentItem->questionparts->questionpart as $questionpartXML) {
            $questionpart = array();

            $questionpart['name'] = (string) $questionpartXML->name;
            $questionpart['inputType'] = (string) $questionpartXML->inputType->selection;
            $questionpart['teachersAns'] = (string) $questionpartXML->teachersAns->casString;
            $questionpart['studentAnsKey'] = (string) $questionpartXML->studentAnsKey;
            $questionpart['syntax'] = (string) $questionpartXML->syntax;
            $questionpart['studentAnsKey'] = (string) $questionpartXML->studentAnsKey;

            $stackoptions = array();
            foreach ($questionpartXML->stackoption as $stackoptionXML) {
                $name = (string) $stackoptionXML->name;
                $value = (string) $stackoptionXML->selected;
                $stackoptions[$name] = $value;
            }
            $questionpart['stackoptions'] = $stackoptions;

            $questionpart['forbiddenWords'] = (string) $questionpartXML->forbiddenWords->Forbid;
            $questionparts[] = $questionpart;
        }
        $question['questionparts'] = $questionparts;

        $PotentialResponseTrees = array();
        foreach ($assessmentItem->PotentialResponseTrees->PotentialResponseTree as $prtXML) {
            $prt = array();
            $prt['prtName'] = (string) $prtXML->prtname;
            $prt['questionValue'] = (string) $prtXML->questionValue;
            $prt['autoSimplify'] = (string) $prtXML->autoSimplify;
            $prt['feedbackVariables'] = (string) $prtXML->feedbackVariables;

            $PotentialResponses = array();
            foreach ($prtXML->PotentialResponses->PR as $PRXML) {
                $PR = array();
                $PR['id'] = (string) $PRXML['id'];
                $PR['answerTest'] = (string) $PRXML->answerTest;
                $PR['teachersAns'] = (string) $PRXML->teachersAns;
                $PR['studentAns'] = (string) $PRXML->studentAns;
                $PR['testoptions'] = (string) $PRXML->testoptions;
                $PR['quietAnsTest'] = (string) $PRXML->quietAnsTest;
                $PR['true'] = $this->getnextPR('true', $PRXML);
                $PR['false'] = $this->getnextPR('false', $PRXML);
                $PR['teacherNote'] = (string) $PRXML->teacherNote;

                $PotentialResponses[] = $PR;
            }
            $prt['PotentialResponses'] = $PotentialResponses;

            $PotentialResponseTrees[] = $prt;
        }
        $question['PotentialResponseTrees'] = $PotentialResponseTrees;

        $ItemOptions = array();
        foreach ($assessmentItem->ItemOptions->stackoption as $stackoptionXML) {
            $name = (string) $stackoptionXML->name;
            $value = (string) $stackoptionXML->selected;
            $ItemOptions[$name] = $value;
        }
        $question['ItemOptions'] = $ItemOptions;

        $ItemTests = array();
        if ($assessmentItem->ItemTests) {
            foreach ($assessmentItem->ItemTests->test as $testXML) {
                $col1 = array(
                    'key' => (string) $testXML->col[0]->key,
                    'value' => (string) $testXML->col[0]->value,
                );
                $col2 = array(
                    'key' => (string) $testXML->col[1]->key,
                    'value' => (string) $testXML->col[1]->value
                );
                $test = array($col1, $col2);
                $ItemTests[] = $test;
            }
        }
        $question['ItemTests'] = $ItemTests;

        return $question;
    }
}
