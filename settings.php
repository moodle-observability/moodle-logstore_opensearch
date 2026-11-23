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
 * Settings of the log store.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading(
        'logstore_opensearch/connection',
        '',
        get_string('connection_desc', 'logstore_opensearch', (object) [
            'settings' => (new moodle_url('/admin/settings.php', ['section' => 'tool_opensearch']))->out(),
            'test' => (new moodle_url('/admin/tool/opensearch/test.php'))->out(),
        ])
    ));
    $settings->add(new admin_setting_configcheckbox(
        'logstore_opensearch/mode_standard',
        get_string('mode_standard', 'logstore_opensearch'),
        get_string('mode_standard_desc', 'logstore_opensearch'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'logstore_opensearch/filters',
        get_string('filters', 'logstore_opensearch'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'logstore_opensearch/logguests',
        new lang_string('logguests', 'core_admin'),
        new lang_string('logguests_help', 'core_admin'),
        1
    ));
    $settings->add(new admin_setting_configmulticheckbox(
        'logstore_opensearch/edulevels',
        get_string('edulevels', 'logstore_opensearch'),
        get_string('edulevels_desc', 'logstore_opensearch'),
        ['0' => 1, '1' => 1, '2' => 1],
        [
            '0' => get_string('edulevelother'),
            '1' => get_string('edulevelteacher'),
            '2' => get_string('edulevelparticipating'),
        ]
    ));
    $settings->add(new admin_setting_configmulticheckbox(
        'logstore_opensearch/crud',
        get_string('crud', 'logstore_opensearch'),
        get_string('crud_desc', 'logstore_opensearch'),
        ['c' => 1, 'r' => 1, 'u' => 1, 'd' => 1],
        [
            'c' => get_string('create'),
            'r' => get_string('view'),
            'u' => get_string('update'),
            'd' => get_string('delete'),
        ]
    ));
    $settings->add(new admin_setting_configtextarea(
        'logstore_opensearch/excludeevents',
        get_string('excludeevents', 'logstore_opensearch'),
        get_string('excludeevents_desc', 'logstore_opensearch'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'logstore_opensearch/privacy',
        get_string('privacyheading', 'logstore_opensearch'),
        get_string('privacyheading_desc', 'logstore_opensearch')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'logstore_opensearch/includeip',
        get_string('includeip', 'logstore_opensearch'),
        get_string('includeip_desc', 'logstore_opensearch'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'logstore_opensearch/pseudonymize',
        get_string('pseudonymize', 'logstore_opensearch'),
        get_string('pseudonymize_desc', 'logstore_opensearch'),
        0
    ));
    $settings->add(new admin_setting_encryptedpassword(
        'logstore_opensearch/salt',
        get_string('salt', 'logstore_opensearch'),
        get_string('salt_desc', 'logstore_opensearch')
    ));
    $settings->add(new admin_setting_configtextarea(
        'logstore_opensearch/otherdenylist',
        get_string('otherdenylist', 'logstore_opensearch'),
        get_string('otherdenylist_desc', 'logstore_opensearch'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'logstore_opensearch/otherfields',
        get_string('otherfields', 'logstore_opensearch'),
        get_string('otherfields_desc', 'logstore_opensearch'),
        0
    ));
}
