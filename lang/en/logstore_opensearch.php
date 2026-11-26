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
 * Strings.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['connection_desc'] = 'Events are queued and sent by tool_opensearch: configure the cluster in its <a href="{$a->settings}">settings</a> and check it with the <a href="{$a->test}">connection test</a>. The retention of the stream is set there too.';
$string['crud'] = 'Actions';
$string['crud_desc'] = 'Only events with these actions are written.';
$string['edulevels'] = 'Education levels';
$string['edulevels_desc'] = 'Only events of these education levels are written.';
$string['excludeevents'] = 'Excluded events';
$string['excludeevents_desc'] = 'Event names not written, one per line (for example \\core\\event\\user_loggedin).';
$string['filters'] = 'Filters';
$string['includeip'] = 'Include IP address';
$string['includeip_desc'] = 'Write the IP address of the request, as logstore_standard does. Disable it if nobody uses it (data minimisation).';
$string['mode_standard'] = 'Standard mode';
$string['mode_standard_desc'] = 'Write every event to the "logstore" stream with exactly the columns of the standard log (logstore_standard_log), plus @timestamp.';
$string['mode_xapi'] = 'xAPI mode';
$string['mode_xapi_desc'] = 'Also write xAPI 1.0.3 statements for a set of events (views, completions, grades, quiz attempts, submissions, forum posts...) to the "xapi" stream, for learning analytics. OpenSearch is not an LRS: tools that need a certified LRS should use logstore_xapi.';
$string['otherdenylist'] = 'Keys removed from "other"';
$string['otherdenylist_desc'] = 'Keys of the "other" field not written, one per line (for example "username", which user_login_failed keeps). Empty keeps the field as logstore_standard writes it.';
$string['otherfields'] = 'Searchable "other"';
$string['otherfields_desc'] = 'Also copy "other" to "other_flat" (flat_object), so that its keys can be searched. It uses more disk space.';
$string['pluginname'] = 'OpenSearch log';
$string['pluginname_desc'] = 'A log store that sends the events to OpenSearch through tool_opensearch.';
$string['privacy:metadata:log'] = 'Events are sent to the OpenSearch cluster configured in tool_opensearch, with the same fields as the standard log.';
$string['privacy:metadata:log:anonymous'] = 'Whether the event was flagged as anonymous';
$string['privacy:metadata:log:eventname'] = 'The event name';
$string['privacy:metadata:log:ip'] = 'The IP address used at the time of the event';
$string['privacy:metadata:log:origin'] = 'The origin of the event';
$string['privacy:metadata:log:other'] = 'Additional information about the event';
$string['privacy:metadata:log:realuserid'] = 'The ID of the real user behind the event, when masquerading a user.';
$string['privacy:metadata:log:relateduserid'] = 'The ID of a user related to this event';
$string['privacy:metadata:log:timecreated'] = 'The time when the event occurred';
$string['privacy:metadata:log:userid'] = 'The ID of the user who triggered this event';
$string['privacyheading'] = 'Privacy';
$string['privacyheading_desc'] = 'With the defaults, OpenSearch keeps the same personal data as the standard log.';
$string['pseudonymize'] = 'Pseudonyms';
$string['pseudonymize_desc'] = 'Replace the user ids (userid, relateduserid, realuserid and the id of user objects) by a keyed hash (HMAC-SHA256 with the secret below). The documents stop being equal to the standard log.';
$string['salt'] = 'Pseudonym secret';
$string['salt_desc'] = 'Key of the pseudonyms, stored encrypted; generated on first use when empty. MOODLE_OPENSEARCH_LOG_SALT wins over it. Changing it changes every pseudonym: older documents can no longer be linked to their users.';
$string['stream_logstore'] = 'Moodle events with the columns of the standard log.';
$string['stream_xapi'] = 'xAPI statements of a set of Moodle events.';
