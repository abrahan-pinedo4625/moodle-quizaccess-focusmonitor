<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upgrade steps for quizaccess_focusmonitor.
 *
 * @package    quizaccess_focusmonitor
 * @copyright  2026 Abrahan Pinedo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute plugin upgrade steps.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_quizaccess_focusmonitor_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071503) {
        $table = new xmldb_table('quizaccess_focusmonitor');

        $field = new xmldb_field('focusmonitorwarningthreshold', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '10', 'focusmonitorshowwarnings');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('focusmonitormaxwarnings', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '3', 'focusmonitorwarningthreshold');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071503, 'quizaccess', 'focusmonitor');
    }

    if ($oldversion < 2026071504) {
        upgrade_plugin_savepoint(true, 2026071504, 'quizaccess', 'focusmonitor');
    }

    // Rescue savepoint for the broken 0.5-alpha package. That package attempted to
    // add a field with a name longer than Moodle 3.3 allows. This corrected upgrade
    // path intentionally does not create that long field.
    if ($oldversion < 2026071505) {
        upgrade_plugin_savepoint(true, 2026071505, 'quizaccess', 'focusmonitor');
    }

    if ($oldversion < 2026071506) {
        $table = new xmldb_table('quizaccess_focusmonitor');

        $field = new xmldb_field('focusmonitoraction', XMLDB_TYPE_CHAR, '20', null,
            XMLDB_NOTNULL, null, 'review', 'focusmonitormaxwarnings');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071506, 'quizaccess', 'focusmonitor');
    }

    if ($oldversion < 2026071507) {
        upgrade_plugin_savepoint(true, 2026071507, 'quizaccess', 'focusmonitor');
    }

    if ($oldversion < 2026071508) {
        // No database change. This version fixes report aggregation and adds
        // a stricter server-side block mode using the existing action field.
        upgrade_plugin_savepoint(true, 2026071508, 'quizaccess', 'focusmonitor');
    }


    if ($oldversion < 2026071509) {
        // No database change. This release candidate caps visible warning counts
        // in the student script and report, and removes experimental labels from UI strings.
        upgrade_plugin_savepoint(true, 2026071509, 'quizaccess', 'focusmonitor');
    }

    if ($oldversion < 2026101000) {
        // Context is stored in the existing eventdata JSON. Preserve settings and logs.
        upgrade_plugin_savepoint(true, 2026101000, 'quizaccess', 'focusmonitor');
    }

    if ($oldversion < 2026101001) {
        // No schema changes. Fix mobile navigation, event delivery and clock alignment.
        upgrade_plugin_savepoint(true, 2026101001, 'quizaccess', 'focusmonitor');
    }

    return true;
}
