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

namespace logstore_opensearch;

use logstore_opensearch\local\options;
use logstore_opensearch\local\transformer\standard;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the log store and of the standard mode.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(log\store::class)]
#[CoversClass(standard::class)]
#[CoversClass(options::class)]
#[CoversClass(hook_callbacks::class)]
final class store_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard,logstore_opensearch', 'tool_log');
        set_config('jsonformat', 1, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        set_config('enabled', 1, 'tool_opensearch');
        set_config('hosts', 'http://opensearch:9200', 'tool_opensearch');
        set_config('prefix', 'test', 'tool_opensearch');
        $this->reset_stores();
    }

    #[\Override]
    protected function tearDown(): void {
        \tool_opensearch\local\queue::reset();
        \tool_opensearch\api::reset();
        parent::tearDown();
    }

    /**
     * Forgets the stores and the caches of tool_opensearch after a settings change.
     *
     * @return void
     */
    private function reset_stores(): void {
        \tool_opensearch\api::reset();
        \tool_opensearch\local\queue::reset();
        get_log_manager(true);
    }

    /**
     * Writes the buffers and forgets the events so far (those of the data generator).
     *
     * @return void
     */
    private function clear(): void {
        global $DB;
        $this->documents();
        $DB->delete_records('tool_opensearch_queue');
        $DB->delete_records('logstore_standard_log');
    }

    /**
     * Writes the buffers of the stores and of tool_opensearch, and returns the queued documents.
     *
     * @return array[] Documents, in the order of the events.
     */
    private function documents(): array {
        global $DB;
        get_log_manager()->dispose();
        \tool_opensearch\local\queue::flush();
        $docs = [];
        foreach ($DB->get_records('tool_opensearch_queue', ['stream' => 'logstore'], 'id') as $row) {
            $docs[] = json_decode($row->payload, true);
        }
        return $docs;
    }

    /**
     * A row of logstore_standard_log with the types of the document.
     *
     * @param \stdClass $row Row.
     * @return array
     */
    private function row(\stdClass $row): array {
        $row = (array) $row;
        unset($row['id']);
        foreach ($row as $key => $value) {
            if (in_array($key, ['objectid', 'relateduserid', 'realuserid'], true)) {
                $row[$key] = $value === null ? null : (int) $value;
            } else if (
                in_array($key, ['edulevel', 'contextid', 'contextlevel', 'contextinstanceid', 'userid', 'courseid',
                    'anonymous', 'timecreated'], true)
            ) {
                $row[$key] = (int) $value;
            }
        }
        $row['ip'] = $row['ip'] === '' ? null : $row['ip'];
        ksort($row);
        return $row;
    }

    /**
     * For real events, the document has the same keys and values as the row of logstore_standard_log.
     */
    public function test_document_equals_standard_log_row(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->clear();
        $this->setUser($user);

        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        \mod_page\event\course_module_viewed::create([
            'objectid' => $page->id,
            'context' => \context_module::instance($page->cmid),
        ])->trigger();
        \core\event\user_loggedin::create([
            'userid' => $user->id,
            'objectid' => $user->id,
            'other' => ['username' => $user->username],
        ])->trigger();
        \core\event\user_login_failed::create([
            'userid' => $user->id,
            'other' => ['username' => $user->username, 'reason' => AUTH_LOGIN_FAILED],
        ])->trigger();

        $docs = $this->documents();
        $rows = array_values($DB->get_records('logstore_standard_log', null, 'id'));
        $this->assertCount(4, $docs);
        $this->assertCount(4, $rows);
        foreach ($docs as $i => $doc) {
            $this->assertSame(gmdate('Y-m-d\TH:i:s\Z', (int) $rows[$i]->timecreated), $doc['@timestamp']);
            $this->assertSame('test', $doc['moodle']['site']);
            unset($doc['@timestamp'], $doc['moodle']);
            ksort($doc);
            $this->assertSame($this->row($rows[$i]), $doc, $doc['eventname']);
        }
    }

    /**
     * Privacy options: no IP, pseudonyms (stable, different per salt) and keys removed from "other".
     */
    public function test_privacy_options(): void {
        $entry = [
            'eventname' => '\core\event\user_login_failed', 'component' => 'core', 'action' => 'failed',
            'target' => 'user_login', 'objecttable' => 'user', 'objectid' => 7, 'crud' => 'r', 'edulevel' => 0,
            'contextid' => 1, 'contextlevel' => 10, 'contextinstanceid' => 0, 'userid' => 7, 'courseid' => 0,
            'relateduserid' => null, 'anonymous' => 0, 'other' => '{"username":"ana","reason":3}',
            'timecreated' => 1767225600, 'origin' => 'web', 'ip' => '10.0.0.1', 'realuserid' => null,
        ];
        $options = options::load((object) ['includeip' => 0, 'pseudonymize' => 1, 'otherdenylist' => "username\n",
            'otherfields' => 1], ['MOODLE_OPENSEARCH_LOG_SALT' => 'one']);
        $doc = standard::document($entry, $options);
        $this->assertNull($doc['ip']);
        $this->assertSame('{"reason":3}', $doc['other']);
        $this->assertSame(['reason' => 3], $doc['other_flat']);
        $this->assertNotSame(7, $doc['userid']);
        $this->assertSame($doc['userid'], $doc['objectid']);
        $this->assertSame($options->pseudonym(7), $doc['userid']);
        $this->assertLessThan(2 ** 60, $doc['userid']);
        $this->assertNull($doc['relateduserid']);

        // Anonymous events keep the user, as the core writes them; with pseudonyms, never the user id in clear.
        $anonymous = standard::document(['anonymous' => 1, 'objecttable' => null, 'objectid' => null] + $entry, $options);
        $this->assertSame(1, $anonymous['anonymous']);
        $this->assertSame($options->pseudonym(7), $anonymous['userid']);
        $plain = standard::document(['anonymous' => 1] + $entry, options::load((object) [], []));
        $this->assertSame(7, $plain['userid']);

        $other = options::load((object) ['pseudonymize' => 1], ['MOODLE_OPENSEARCH_LOG_SALT' => 'two']);
        $this->assertNotSame($options->pseudonym(7), $other->pseudonym(7));

        // Without a salt, one is generated and stored encrypted.
        $generated = options::load((object) ['pseudonymize' => 1], []);
        $pseudonym = $generated->pseudonym(7);
        $this->assertSame($pseudonym, options::load(null, [])->pseudonym(7));
        $this->assertNotSame('', get_config('logstore_opensearch', 'salt'));
    }

    /**
     * Filters: education level, CRUD, excluded events and guests.
     */
    public function test_filters(): void {
        $course = $this->getDataGenerator()->create_course();
        set_config('crud', 'c,u,d', 'logstore_opensearch');
        set_config('excludeevents', "core\\event\\user_loggedin\n", 'logstore_opensearch');
        set_config('logguests', 0, 'logstore_opensearch');
        $this->reset_stores();

        $user = $this->getDataGenerator()->create_user();
        $this->clear();
        $this->setUser($user);
        // Read: filtered by CRUD.
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        // Excluded event.
        \core\event\user_loggedin::create(['userid' => $user->id, 'objectid' => $user->id,
            'other' => ['username' => $user->username]])->trigger();
        // Created: written.
        \core\event\course_updated::create(['objectid' => $course->id,
            'context' => \context_course::instance($course->id)])->trigger();
        // Not logged in: skipped.
        $this->setUser(0);
        \core\event\course_updated::create(['objectid' => $course->id,
            'context' => \context_course::instance($course->id)])->trigger();

        $names = array_column($this->documents(), 'eventname');
        $this->assertSame(['\core\event\course_updated'], $names);

        set_config('edulevels', '1,2', 'logstore_opensearch');
        set_config('crud', '', 'logstore_opensearch');
        $this->reset_stores();
        $this->clear();
        $this->setUser($user);
        // Education level "other": skipped; "teaching": written (an empty CRUD setting means every action).
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        \core\event\course_updated::create(['objectid' => $course->id,
            'context' => \context_course::instance($course->id)])->trigger();
        $this->assertSame(['\core\event\course_updated'], array_column($this->documents(), 'eventname'));
    }

    /**
     * Nothing is written when tool_opensearch or the standard mode is disabled.
     */
    public function test_disabled(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->clear();
        set_config('mode_standard', 0, 'logstore_opensearch');
        $this->reset_stores();
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        $this->assertSame([], $this->documents());

        set_config('mode_standard', 1, 'logstore_opensearch');
        set_config('enabled', 0, 'tool_opensearch');
        $this->reset_stores();
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        $this->assertSame([], $this->documents());
    }

    /**
     * The stream is declared to tool_opensearch with the columns of the standard log.
     */
    public function test_stream(): void {
        \tool_opensearch\local\registry::reset();
        $stream = \tool_opensearch\local\registry::get('logstore');
        $this->assertNotNull($stream);
        $this->assertSame('logstore_opensearch', $stream->component);
        $this->assertSame(180, $stream->retentiondays);
        global $DB;
        $columns = array_diff(array_keys($DB->get_columns('logstore_standard_log')), ['id']);
        foreach ($columns as $column) {
            $this->assertArrayHasKey($column, $stream->properties, $column);
        }
    }
}
