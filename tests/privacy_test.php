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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use logstore_opensearch\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use tool_opensearch\local\client;

/**
 * Tests of the privacy provider (OpenSearch answers simulated).
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /** @var \ArrayObject Requests sent to OpenSearch. */
    private \ArrayObject $history;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'tool_opensearch');
        set_config('hosts', 'http://opensearch:9200', 'tool_opensearch');
        set_config('prefix', 'test', 'tool_opensearch');
        \tool_opensearch\api::reset();
    }

    #[\Override]
    public function tearDown(): void {
        client::phpunit_set_handler(null);
        \tool_opensearch\api::reset();
        parent::tearDown();
    }

    /**
     * Simulated answers of OpenSearch, in order.
     *
     * @param array[] $bodies JSON bodies (status 200).
     * @return void
     */
    private function answers(array $bodies): void {
        $mock = new MockHandler(array_map(fn(array $body) => new Response(200, [], json_encode($body)), $bodies));
        $this->history = new \ArrayObject();
        $history = $this->history;
        client::phpunit_set_handler(function (RequestInterface $request, array $options) use ($mock, $history) {
            $history[] = $request;
            return $mock($request, $options);
        });
    }

    /**
     * Body of a recorded request.
     *
     * @param int $i Index.
     * @return array
     */
    private function body(int $i): array {
        return json_decode((string) $this->history[$i]->getBody(), true);
    }

    /**
     * Metadata: the external location with the fields of the standard log.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('logstore_opensearch'));
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('opensearch', $items[0]->get_name());
        $this->assertArrayHasKey('ip', $items[0]->get_privacy_fields());
    }

    /**
     * Contexts of a user come from an aggregation on contextid, over userid, relateduserid and realuserid.
     */
    public function test_contexts_for_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->answers([
            ['aggregations' => ['contexts' => ['buckets' => [['key' => $course->id], ['key' => 999999]]]]],
            ['aggregations' => ['contexts' => ['buckets' => [['key' => $course->id]]]]],
        ]);
        $contextlist = new contextlist();
        provider::add_contexts_for_userid($contextlist, $user->id);
        $this->assertSame([(int) $course->id], array_map('intval', $contextlist->get_contextids()));
        $this->assertSame('/test-logstore/_search', $this->history[0]->getUri()->getPath());
        $should = $this->body(0)['query']['bool']['should'];
        $this->assertSame(['userid' => [(int) $user->id]], $should[0]['terms']);
        $this->assertSame(['realuserid' => [(int) $user->id]], $should[2]['terms']);
        $this->assertSame('/test-xapi/_search', $this->history[1]->getUri()->getPath());
        $this->assertSame(['actor.account.name' => [(string) $user->id]], $this->body(1)['query']['terms']);
    }

    /**
     * Users of a context: only ids of existing users (pseudonyms are not users).
     */
    public function test_users_in_context(): void {
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->answers([['aggregations' => [
            'userid' => ['buckets' => [['key' => $user->id], ['key' => 123456789012]]],
            'relateduserid' => ['buckets' => []],
            'realuserid' => ['buckets' => []],
        ]], ['aggregations' => ['actors' => ['buckets' => [['key' => (string) $user->id], ['key' => 'anonymous']]]]]]);
        $userlist = new userlist($context, 'logstore_opensearch');
        provider::add_userids_for_context($userlist);
        $this->assertSame([(int) $user->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * Export: the documents of the user, in the format of logstore_standard.
     */
    public function test_export(): void {
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->answers([['hits' => ['hits' => [['_source' => [
            'eventname' => '\core\event\course_viewed', 'component' => 'core', 'action' => 'viewed',
            'target' => 'course', 'objecttable' => null, 'objectid' => null, 'crud' => 'r', 'edulevel' => 2,
            'contextid' => $context->id, 'contextlevel' => CONTEXT_COURSE, 'contextinstanceid' => $context->instanceid,
            'userid' => $user->id, 'courseid' => $context->instanceid, 'relateduserid' => null, 'anonymous' => 0,
            'other' => null, 'timecreated' => 1767225600, 'origin' => 'web', 'ip' => '10.0.0.1', 'realuserid' => null,
        ]]]]], ['hits' => ['hits' => [['_source' => ['id' => 'x', 'verb' => ['id' => 'viewed'],
            'moodle_event' => ['contextid' => $context->id]]]]]]]);
        provider::export_user_data(new approved_contextlist($user, 'logstore_opensearch', [$context->id]));
        $data = writer::with_context($context)->get_data([get_string('privacy:path:logs', 'tool_log'),
            get_string('pluginname', 'logstore_opensearch')]);
        $this->assertCount(1, $data->logs);
        $this->assertSame('10.0.0.1', $data->logs[0]['ip']);
        $this->assertSame((int) $context->id, $this->body(0)['query']['bool']['filter'][0]['term']['contextid']);
        $xapi = writer::with_context($context)->get_data([get_string('privacy:path:logs', 'tool_log'),
            get_string('pluginname', 'logstore_opensearch'), 'xAPI']);
        $this->assertSame([['id' => 'x', 'verb' => ['id' => 'viewed']]], $xapi->statements);
    }

    /**
     * Deletion by context, by user and by user list use delete by query; pseudonyms are deleted too.
     */
    public function test_delete(): void {
        set_config('pseudonymize', 1, 'logstore_opensearch');
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $this->answers(array_fill(0, 6, ['deleted' => 1]));

        provider::delete_data_for_all_users_in_context($context);
        provider::delete_data_for_user(new approved_contextlist($user, 'logstore_opensearch', [$context->id]));
        provider::delete_data_for_userlist(new approved_userlist($context, 'logstore_opensearch', [$user->id]));

        $this->assertSame('/test-logstore/_delete_by_query', $this->history[0]->getUri()->getPath());
        $this->assertSame(['term' => ['contextid' => (int) $context->id]], $this->body(0)['query']);
        $this->assertSame('/test-xapi/_delete_by_query', $this->history[1]->getUri()->getPath());
        $this->assertSame(['term' => ['moodle_event.contextid' => (int) $context->id]], $this->body(1)['query']);
        $ids = $this->body(2)['query']['bool']['filter'][1]['bool']['should'][0]['terms']['userid'];
        $this->assertSame([(int) $user->id, local\options::load()->pseudonym((int) $user->id)], $ids);
        $this->assertCount(6, $this->history);
    }

    /**
     * With sending disabled but hosts configured, requests still reach the cluster (the documents are there).
     */
    public function test_disabled_sending_still_reaches_cluster(): void {
        set_config('enabled', 0, 'tool_opensearch');
        $this->answers([['deleted' => 1], ['deleted' => 0]]);
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertCount(2, $this->history);
    }

    /**
     * An unreachable cluster makes the request fail, so that it is not marked as done.
     */
    public function test_cluster_error_fails_request(): void {
        $this->answers([]);
        $mock = new MockHandler([new Response(503, [], '{}')]);
        client::phpunit_set_handler($mock);
        $this->expectException(\tool_opensearch\local\client_exception::class);
        provider::delete_data_for_all_users_in_context(\context_system::instance());
    }

    /**
     * Without hosts nothing was ever sent: nothing is asked.
     */
    public function test_no_hosts(): void {
        set_config('hosts', '', 'tool_opensearch');
        $this->answers([]);
        $contextlist = new contextlist();
        provider::add_contexts_for_userid($contextlist, 2);
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertCount(0, $this->history);
    }

    /**
     * Searching before any document was sent (no data stream yet) finds nothing.
     */
    public function test_no_data_stream(): void {
        $notfound = '{"error":{"type":"index_not_found_exception"}}';
        $mock = new MockHandler([new Response(404, [], $notfound), new Response(404, [], $notfound)]);
        client::phpunit_set_handler($mock);
        $contextlist = new contextlist();
        provider::add_contexts_for_userid($contextlist, 2);
        $this->assertSame([], $contextlist->get_contextids());
    }
}
