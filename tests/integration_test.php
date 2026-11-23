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
use core_privacy\local\request\contextlist;
use logstore_opensearch\privacy\provider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use tool_opensearch\local\client;
use tool_opensearch\local\config;
use tool_opensearch\local\queue;
use tool_opensearch\local\shipper;

/**
 * Tests against a real OpenSearch, only when TOOL_OPENSEARCH_TEST_HOSTS is set (security plugin disabled).
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
#[Group('tool_opensearch_integration')]
final class integration_test extends \advanced_testcase {
    /** @var string Prefix of this run. */
    private string $prefix;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $hosts = getenv('TOOL_OPENSEARCH_TEST_HOSTS');
        if (!$hosts) {
            $this->markTestSkipped('TOOL_OPENSEARCH_TEST_HOSTS is not set');
        }
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->prefix = 'phpunit-' . strtolower(random_string(6));
        set_config('enabled_stores', 'logstore_opensearch', 'tool_log');
        set_config('enabled', 1, 'tool_opensearch');
        set_config('hosts', $hosts, 'tool_opensearch');
        set_config('auth', 'none', 'tool_opensearch');
        set_config('prefix', $this->prefix, 'tool_opensearch');
        set_config('replicas', 0, 'tool_opensearch');
        \tool_opensearch\api::reset();
        queue::reset();
        get_log_manager(true);
    }

    #[\Override]
    protected function tearDown(): void {
        if (isset($this->prefix)) {
            $client = new client(config::load());
            $client->request('DELETE', '/_data_stream/' . $this->prefix . '-logstore');
            $client->request('DELETE', '/_index_template/' . $this->prefix . '-logstore');
            $client->request('DELETE', '/_plugins/_ism/policies/' . $this->prefix . '-logstore-ism');
        }
        queue::reset();
        \tool_opensearch\api::reset();
        parent::tearDown();
    }

    /**
     * Events go through the queue to the data stream; the mapping accepts every column; privacy finds and deletes them.
     */
    public function test_end_to_end(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->setUser($user);
        \core\event\course_viewed::create(['context' => $context])->trigger();
        \core\event\user_login_failed::create(['userid' => $user->id,
            'other' => ['username' => $user->username, 'reason' => AUTH_LOGIN_FAILED]])->trigger();
        get_log_manager()->dispose();
        queue::flush();

        $config = config::load();
        $totals = (new shipper($config))->run();
        $this->assertSame(0, $totals['failed'], json_encode(\tool_opensearch\local\stats::get('logstore')));
        $this->assertGreaterThanOrEqual(2, $totals['sent']);
        (new client($config))->call('POST', '/' . $this->prefix . '-logstore/_refresh');

        $contextlist = new contextlist();
        provider::add_contexts_for_userid($contextlist, $user->id);
        $this->assertContains((int) $context->id, array_map('intval', $contextlist->get_contextids()));

        provider::delete_data_for_user(new approved_contextlist($user, 'logstore_opensearch', [$context->id]));
        (new client($config))->call('POST', '/' . $this->prefix . '-logstore/_refresh');
        $left = \tool_opensearch\api::search('logstore', ['size' => 0, 'track_total_hits' => true,
            'query' => ['bool' => ['filter' => [
                ['term' => ['contextid' => (int) $context->id]],
                ['term' => ['userid' => (int) $user->id]],
            ]]]]);
        $this->assertSame(0, $left['hits']['total']['value']);
    }
}
