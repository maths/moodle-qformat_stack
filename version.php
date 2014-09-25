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
 * Version information for the qformat_stack plugin.
 *
 * @package   qformat_stack
 * @copyright 2012 Matti Pauna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$plugin->version   = 2014092500;
$plugin->requires  = 2013101800;
$plugin->cron      = 0;
$plugin->component = 'qformat_stack';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '3.3 for Moodle 2.6+';

$plugin->dependencies = array(
    'qtype_stack' => 2014092500,
);
