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
 * Unit tests for qformat_stack.
 *
 * @package   qformat_stack
 * @copyright 2012 The University of Birmingham
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/stack/format.php');


/**
 * Unit tests for parts of the STACK 2.0 importer.
 *
 * @copyright 2012 The University of Birmingham
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group qtype_stack
 */
class qformat_stack_format_test extends basic_testcase {

    public function test_convert_keyvals_1() {
        $qf = new qformat_stack();
        $kv = 'x=1';
        $kvo = 'x:1;';
        $this->assertEquals($kvo, $qf->convert_keyvals($kv));
    }

    public function test_convert_keyvals_2() {
        $qf = new qformat_stack();
        $kv = "x=sin(x^2)\n m=rand(3);";
        $kvo = "x:sin(x^2);\nm:rand(3);";;
        $this->assertEquals($kvo, $qf->convert_keyvals($kv));
    }

    public function test_convert_keyvals_3() {
        // Function.
        $qf = new qformat_stack();
        $kv = "x=sin(x^2)\n f(x):=x^2\nm=rand(3);";
        $kvo = "x:sin(x^2);\nf(x):=x^2;\nm:rand(3);";;
        $this->assertEquals($kvo, $qf->convert_keyvals($kv));
    }

    public function test_convert_keyvals_4() {
        // Equation and no variable name.
        $qf = new qformat_stack();
        $kv = "p=x^2-x=1\n sin(x)^2;";
        $kvo = "p:x^2-x=1;\nsin(x)^2;";;
        $this->assertEquals($kvo, $qf->convert_keyvals($kv));
    }
}
