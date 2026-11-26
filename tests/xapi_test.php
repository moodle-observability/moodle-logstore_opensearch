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
use logstore_opensearch\local\transformer\xapi;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the xAPI mode.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(xapi::class)]
final class xapi_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_opensearch', 'tool_log');
        set_config('mode_standard', 0, 'logstore_opensearch');
        set_config('mode_xapi', 1, 'logstore_opensearch');
        set_config('enabled', 1, 'tool_opensearch');
        set_config('hosts', 'http://opensearch:9200', 'tool_opensearch');
        set_config('prefix', 'test', 'tool_opensearch');
        \tool_opensearch\api::reset();
        \tool_opensearch\local\queue::reset();
        xapi::reset();
        get_log_manager(true);
    }

    #[\Override]
    protected function tearDown(): void {
        \tool_opensearch\local\queue::reset();
        \tool_opensearch\api::reset();
        parent::tearDown();
    }

    /**
     * Statements written to the xapi stream since the last call.
     *
     * @return array[]
     */
    private function statements(): array {
        global $DB;
        get_log_manager()->dispose();
        \tool_opensearch\local\queue::flush();
        $docs = [];
        foreach ($DB->get_records('tool_opensearch_queue', ['stream' => 'xapi'], 'id') as $row) {
            $docs[] = json_decode($row->payload, true);
        }
        $DB->delete_records('tool_opensearch_queue');
        return $docs;
    }

    /**
     * Checks the required parts of an xAPI 1.0.3 statement.
     *
     * @param array $s Statement.
     * @return void
     */
    private function assert_statement(array $s): void {
        $iri = '#^https?://\S+$#';
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $s['id']);
        $this->assertSame('1.0.3', $s['version']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $s['timestamp']);
        $this->assertSame('Agent', $s['actor']['objectType']);
        $this->assertMatchesRegularExpression($iri, $s['actor']['account']['homePage']);
        $this->assertIsString($s['actor']['account']['name']);
        $this->assertArrayNotHasKey('mbox', $s['actor']);
        $this->assertMatchesRegularExpression($iri, $s['verb']['id']);
        $this->assertIsString($s['verb']['display']['en']);
        $this->assertSame('Activity', $s['object']['objectType']);
        $this->assertMatchesRegularExpression($iri, $s['object']['id']);
        $this->assertMatchesRegularExpression($iri, $s['object']['definition']['type']);
        if (isset($s['result']['score']['scaled'])) {
            $this->assertGreaterThanOrEqual(-1, $s['result']['score']['scaled']);
            $this->assertLessThanOrEqual(1, $s['result']['score']['scaled']);
        }
        foreach ($s['context']['contextActivities']['grouping'] ?? [] as $activity) {
            $this->assertMatchesRegularExpression($iri, $activity['id']);
        }
    }

    /**
     * Real events become statements; events without a transformer do not.
     */
    public function test_real_events(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Physics']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Reading']);
        $user = $this->getDataGenerator()->create_user();
        $this->statements();
        $this->setUser($user);

        $coursecontext = \context_course::instance($course->id);
        $modulecontext = \context_module::instance($page->cmid);
        \core\event\course_viewed::create(['context' => $coursecontext])->trigger();
        \mod_page\event\course_module_viewed::create(['objectid' => $page->id, 'context' => $modulecontext])->trigger();
        $other = ['username' => $user->username];
        \core\event\user_loggedin::create(['userid' => $user->id, 'objectid' => $user->id, 'other' => $other])->trigger();
        \core\event\user_login_failed::create(['userid' => $user->id,
            'other' => ['username' => $user->username, 'reason' => AUTH_LOGIN_FAILED]])->trigger();

        $statements = $this->statements();
        $this->assertCount(3, $statements);
        foreach ($statements as $statement) {
            $this->assert_statement($statement);
            $this->assertSame((string) $user->id, $statement['actor']['account']['name']);
        }
        [$courseview, $pageview, $login] = $statements;
        $this->assertSame(xapi::VERBS['viewed'][0], $courseview['verb']['id']);
        $this->assertStringEndsWith('/course/view.php?id=' . $course->id, $courseview['object']['id']);
        $this->assertSame('Physics', $courseview['object']['definition']['name']['en']);
        $this->assertStringEndsWith('/mod/page/view.php?id=' . $page->cmid, $pageview['object']['id']);
        $this->assertSame('Reading', $pageview['object']['definition']['name_en']);
        $grouping = $pageview['context']['contextActivities']['grouping'][0];
        $this->assertStringEndsWith('/course/view.php?id=' . $course->id, $grouping['id']);
        $this->assertSame((int) $page->cmid, $pageview['moodle_event']['cmid']);
        $this->assertSame($course->fullname, $pageview['moodle_event']['course']);
        $this->assertSame(xapi::VERBS['loggedin'][0], $login['verb']['id']);
    }

    /**
     * A log entry with the columns of logstore_standard_log.
     *
     * @param string $eventname Event name.
     * @param array $fields Other fields.
     * @return array
     */
    private function entry(string $eventname, array $fields = []): array {
        return $fields + [
            'eventname' => $eventname, 'component' => 'core', 'action' => 'x', 'target' => 'x',
            'objecttable' => null, 'objectid' => null, 'crud' => 'r', 'edulevel' => 2, 'contextid' => 1,
            'contextlevel' => CONTEXT_SYSTEM, 'contextinstanceid' => 0, 'userid' => 5, 'courseid' => 0,
            'relateduserid' => null, 'anonymous' => 0, 'other' => 'null', 'timecreated' => 1767225600,
            'origin' => 'web', 'ip' => '10.0.0.1', 'realuserid' => null,
        ];
    }

    /**
     * Transformers that read the database: grades, quiz attempts, completion, badges, enrolments, forum posts.
     */
    public function test_transformers(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $options = options::load();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'sumgrades' => 10]);
        $DB->set_field('quiz', 'sumgrades', 10, ['id' => $quiz->id]);
        $student = $generator->create_user();
        $cmcontext = \context_module::instance($quiz->cmid);
        $module = ['contextid' => $cmcontext->id, 'contextlevel' => CONTEXT_MODULE,
            'contextinstanceid' => $quiz->cmid, 'courseid' => $course->id];

        // Quiz attempt submitted: completed with the score of the attempt.
        $attemptid = $DB->insert_record('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $student->id, 'attempt' => 1,
            'uniqueid' => 1, 'layout' => '', 'currentpage' => 0, 'preview' => 0, 'state' => 'finished',
            'timestart' => 1, 'timefinish' => 2, 'timemodified' => 2, 'sumgrades' => 7.5]);
        $s = xapi::document($this->entry('\mod_quiz\event\attempt_submitted', $module + ['objectid' => $attemptid,
            'relateduserid' => $student->id]), $options);
        $this->assert_statement($s);
        $this->assertSame(xapi::VERBS['completed'][0], $s['verb']['id']);
        $this->assertSame(0.75, $s['result']['score']['scaled']);
        $this->assertSame(xapi::TYPES['assessment'], $s['object']['definition']['type']);

        // User graded: scored, with success from the pass grade.
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id,
            'courseid' => $course->id]);
        $item->gradepass = 5;
        $item->update();
        $item->update_final_grade($student->id, 8, 'test');
        $gradeid = $DB->get_field('grade_grades', 'id', ['itemid' => $item->id, 'userid' => $student->id]);
        $s = xapi::document($this->entry('\core\event\user_graded', ['objectid' => $gradeid,
            'relateduserid' => $student->id, 'courseid' => $course->id]), $options);
        $this->assert_statement($s);
        $this->assertSame(xapi::VERBS['scored'][0], $s['verb']['id']);
        $this->assertTrue($s['result']['success']);
        $this->assertStringEndsWith('/mod/quiz/view.php?id=' . $quiz->cmid, $s['object']['id']);
        $this->assertSame((string) $student->id, $s['actor']['account']['name']);

        // Activity completion: only when complete.
        $completed = $this->entry('\core\event\course_module_completion_updated', $module + [
            'relateduserid' => $student->id, 'other' => json_encode(['completionstate' => 1])]);
        $this->assertSame(xapi::VERBS['completed'][0], xapi::document($completed, $options)['verb']['id']);
        $incomplete = ['other' => json_encode(['completionstate' => 0])] + $completed;
        $this->assertNull(xapi::document($incomplete, $options));

        // Enrolment: registered in the course; course completion: completed.
        $s = xapi::document($this->entry('\core\event\user_enrolment_created', ['relateduserid' => $student->id,
            'courseid' => $course->id]), $options);
        $this->assertSame(xapi::VERBS['registered'][0], $s['verb']['id']);
        $this->assertSame(xapi::TYPES['course'], $s['object']['definition']['type']);
        $this->assertArrayNotHasKey('contextActivities', $s['context']);

        // Forum post: a reply to its discussion.
        $s = xapi::document($this->entry('\mod_forum\event\post_created', ['component' => 'mod_forum',
            'other' => json_encode(['discussionid' => 42])]), $options);
        $this->assertStringEndsWith('/mod/forum/discuss.php?d=42', $s['object']['id']);
        $this->assertSame(xapi::VERBS['replied'][0], $s['verb']['id']);

        // Every event of the list has a transformer method.
        foreach (xapi::EVENTS as $method) {
            $this->assertTrue(method_exists(xapi::class, $method), $method);
        }
    }

    /**
     * The id is deterministic; anonymous events and pseudonyms never show the user id.
     */
    public function test_id_anonymous_and_pseudonym(): void {
        $options = options::load();
        $entry = $this->entry('\core\event\user_loggedin', ['objectid' => 5]);
        $first = xapi::document($entry, $options);
        $this->assertSame($first['id'], xapi::document($entry, $options)['id']);
        $this->assertNotSame($first['id'], xapi::document(['timecreated' => 1767225601] + $entry, $options)['id']);
        // RFC 4122 vector: the DNS namespace and "python.org".
        $dns = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $this->assertSame('886313e1-3b8a-5372-9b90-0c9aee199e5d', xapi::uuid5('python.org', $dns));

        $anonymous = xapi::document(['anonymous' => 1] + $entry, $options);
        $this->assertSame('anonymous', $anonymous['actor']['account']['name']);

        $pseudonyms = options::load((object) ['pseudonymize' => 1, 'mode_xapi' => 1], ['MOODLE_OPENSEARCH_LOG_SALT' => 's']);
        $s = xapi::document($entry, $pseudonyms);
        $this->assertSame((string) $pseudonyms->pseudonym(5), $s['actor']['account']['name']);
        $this->assertNull(xapi::document($this->entry('\core\event\user_login_failed'), $options));
    }

    /**
     * Both modes together: one document in each stream for a mapped event.
     */
    public function test_both_modes(): void {
        global $DB;
        set_config('mode_standard', 1, 'logstore_opensearch');
        \tool_opensearch\api::reset();
        get_log_manager(true);
        $course = $this->getDataGenerator()->create_course();
        get_log_manager()->dispose();
        \tool_opensearch\local\queue::flush();
        $DB->delete_records('tool_opensearch_queue');
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        get_log_manager()->dispose();
        \tool_opensearch\local\queue::flush();
        $this->assertSame(1, $DB->count_records('tool_opensearch_queue', ['stream' => 'logstore']));
        $this->assertSame(1, $DB->count_records('tool_opensearch_queue', ['stream' => 'xapi']));
    }
}
