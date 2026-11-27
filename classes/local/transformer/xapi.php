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

namespace logstore_opensearch\local\transformer;

use logstore_opensearch\local\options;

/**
 * xAPI mode: xAPI 1.0.3 statements for a set of Moodle events, in their own data stream.
 *
 * The verbs and activity types follow the ADL registry and the mapping of logstore_xapi (used as a reference, no code
 * copied). The actor is an account, as in core_xapi: homePage is wwwroot and name the user id (or its pseudonym);
 * never an email. The statement id is a UUID v5 of the event, so sending it again does not duplicate it.
 *
 * "object.definition.name_en" (keyword) copies the English name for aggregations. A "moodle_event" object, outside the
 * statement, keeps the event name, the ids needed by the privacy requests and the course name (in every statement of a
 * course, even when the course is the object) for the dashboards.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class xapi {
    /** Stream (data stream <prefix>-xapi). */
    public const STREAM = 'xapi';

    /** Default retention of the stream, in days. */
    public const RETENTION_DAYS = 365;

    /** xAPI version of the statements. */
    public const VERSION = '1.0.3';

    /** Namespace of the UUID v5 statement ids. */
    public const NAMESPACE = '6f1c7f7e-2b1a-5d0a-9c5e-3f2a8b7c4d1e';

    /** Verbs: name => [IRI, English display]. */
    public const VERBS = [
        'viewed' => ['http://id.tincanapi.com/verb/viewed', 'viewed'],
        'completed' => ['http://adlnet.gov/expapi/verbs/completed', 'completed'],
        'attempted' => ['http://adlnet.gov/expapi/verbs/attempted', 'attempted'],
        'scored' => ['http://adlnet.gov/expapi/verbs/scored', 'scored'],
        'registered' => ['http://adlnet.gov/expapi/verbs/registered', 'registered'],
        'submitted' => ['http://activitystrea.ms/schema/1.0/submit', 'submitted'],
        'created' => ['http://activitystrea.ms/schema/1.0/create', 'created'],
        'replied' => ['http://id.tincanapi.com/verb/replied', 'replied to'],
        'answered' => ['http://adlnet.gov/expapi/verbs/answered', 'answered'],
        'earned' => ['http://id.tincanapi.com/verb/earned', 'earned'],
        'loggedin' => ['https://w3id.org/xapi/adl/verbs/logged-in', 'logged in to'],
        'loggedout' => ['https://w3id.org/xapi/adl/verbs/logged-out', 'logged out of'],
    ];

    /** Activity types. */
    public const TYPES = [
        'site' => 'http://id.tincanapi.com/activitytype/lms',
        'course' => 'http://adlnet.gov/expapi/activities/course',
        'module' => 'http://adlnet.gov/expapi/activities/module',
        'assessment' => 'http://adlnet.gov/expapi/activities/assessment',
        'discussion' => 'http://id.tincanapi.com/activitytype/discussion',
        'badge' => 'http://activitystrea.ms/schema/1.0/badge',
    ];

    /** Events with a transformer: event name => method. Module views of every activity use "module_viewed". */
    public const EVENTS = [
        '\core\event\course_viewed' => 'course_viewed',
        '\core\event\course_module_completion_updated' => 'module_completed',
        '\core\event\course_completed' => 'course_completed',
        '\core\event\user_graded' => 'user_graded',
        '\core\event\user_enrolment_created' => 'enrolled',
        '\core\event\user_loggedin' => 'logged_in',
        '\core\event\user_loggedout' => 'logged_out',
        '\core\event\badge_awarded' => 'badge_awarded',
        '\mod_quiz\event\attempt_started' => 'quiz_attempt_started',
        '\mod_quiz\event\attempt_submitted' => 'quiz_attempt_submitted',
        '\mod_assign\event\assessable_submitted' => 'assign_submitted',
        '\mod_forum\event\discussion_created' => 'discussion_created',
        '\mod_forum\event\post_created' => 'post_created',
        '\mod_choice\event\answer_created' => 'choice_answered',
        '\mod_feedback\event\response_submitted' => 'feedback_submitted',
        '\mod_lesson\event\lesson_ended' => 'lesson_ended',
    ];

    /** @var array Names of courses and modules read in this request. */
    private static array $names = [];

    /**
     * Mapping of the stream: the parts of the statement that are searched or aggregated; the rest is stored only.
     *
     * @return array
     */
    public static function properties(): array {
        $keyword = ['type' => 'keyword'];
        $stored = ['type' => 'object', 'enabled' => false];
        $activities = ['properties' => ['id' => $keyword, 'objectType' => $keyword, 'definition' => [
            'properties' => ['type' => $keyword, 'name' => $stored, 'name_en' => $keyword],
        ]]];
        return [
            'id' => $keyword,
            'version' => $keyword,
            'timestamp' => ['type' => 'date'],
            'actor' => ['properties' => [
                'objectType' => $keyword,
                'account' => ['properties' => ['homePage' => $keyword, 'name' => $keyword]],
            ]],
            'verb' => ['properties' => ['id' => $keyword, 'display' => $stored]],
            'object' => ['properties' => [
                'objectType' => $keyword,
                'id' => $keyword,
                'definition' => ['properties' => [
                    'type' => $keyword,
                    'name' => $stored,
                    'name_en' => $keyword,
                ]],
            ]],
            'result' => ['properties' => [
                'success' => ['type' => 'boolean'],
                'completion' => ['type' => 'boolean'],
                'duration' => $keyword,
                'response' => $stored,
                'score' => ['properties' => [
                    'scaled' => ['type' => 'float'],
                    'raw' => ['type' => 'float'],
                    'min' => ['type' => 'float'],
                    'max' => ['type' => 'float'],
                ]],
            ]],
            'context' => ['properties' => [
                'platform' => $keyword,
                'language' => $keyword,
                'contextActivities' => ['properties' => [
                    'parent' => $activities,
                    'grouping' => $activities,
                    'category' => $activities,
                ]],
            ]],
            'moodle_event' => ['properties' => [
                'eventname' => $keyword,
                'contextid' => ['type' => 'long'],
                'courseid' => ['type' => 'long'],
                'course' => $keyword,
                'cmid' => ['type' => 'long'],
            ]],
        ];
    }

    /**
     * Statement of a log entry, or null when the event has no transformer.
     *
     * @param array $entry Log entry: the columns of logstore_standard_log ("other" as JSON).
     * @param options $options Options.
     * @return array|null
     */
    public static function document(array $entry, options $options): ?array {
        $eventname = '\\' . ltrim((string) $entry['eventname'], '\\');
        $method = self::EVENTS[$eventname] ?? null;
        if (
            $method === null && str_starts_with((string) $entry['component'], 'mod_')
                && $entry['action'] === 'viewed' && $entry['target'] === 'course_module'
        ) {
            $method = 'module_viewed';
        }
        if ($method === null) {
            return null;
        }
        $entry['eventname'] = $eventname;
        $entry['other'] = is_string($entry['other'] ?? null) ? (json_decode($entry['other'], true) ?: []) : [];
        try {
            $statement = self::$method($entry);
        } catch (\Throwable $e) {
            // An event whose data no longer exists (deleted record): no statement.
            debugging('logstore_opensearch: no xAPI statement for ' . $eventname . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
        if ($statement === null) {
            return null;
        }
        [$actorid, $verb, $object] = $statement;
        $doc = [
            'id' => self::uuid5(implode('|', [$eventname, $entry['timecreated'], $entry['contextid'], $entry['objectid'] ?? '',
                $entry['userid'], $entry['relateduserid'] ?? ''])),
            'version' => self::VERSION,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z', (int) $entry['timecreated']),
            '@timestamp' => gmdate('Y-m-d\TH:i:s\Z', (int) $entry['timecreated']),
            'actor' => self::actor($actorid, !empty($entry['anonymous']), $options),
            'verb' => ['id' => self::VERBS[$verb][0], 'display' => ['en' => self::VERBS[$verb][1]]],
            'object' => $object,
            'context' => self::context($entry),
            'moodle_event' => [
                'eventname' => $eventname,
                'contextid' => (int) $entry['contextid'],
                'courseid' => (int) $entry['courseid'],
                'course' => $entry['courseid'] > SITEID ? self::course((int) $entry['courseid'])['definition']['name_en'] : null,
                'cmid' => (int) $entry['contextlevel'] === CONTEXT_MODULE ? (int) $entry['contextinstanceid'] : null,
            ],
        ];
        if (isset($statement[3])) {
            $doc['result'] = $statement[3];
        }
        return $doc;
    }

    /**
     * Course viewed.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function course_viewed(array $e): array {
        return [$e['userid'], 'viewed', self::course((int) $e['courseid'])];
    }

    /**
     * Module viewed (any activity).
     *
     * @param array $e Entry.
     * @return array
     */
    private static function module_viewed(array $e): array {
        return [$e['userid'], 'viewed', self::module((int) $e['contextinstanceid'])];
    }

    /**
     * Activity completion: only when it becomes complete (or passed).
     *
     * @param array $e Entry.
     * @return array|null
     */
    private static function module_completed(array $e): ?array {
        $state = (int) ($e['other']['completionstate'] ?? 0);
        // COMPLETION_COMPLETE (1) and COMPLETION_COMPLETE_PASS (2) of lib/completionlib.php.
        if (!in_array($state, [1, 2], true)) {
            return null;
        }
        return [$e['relateduserid'], 'completed', self::module((int) $e['contextinstanceid']),
            ['completion' => true] + ($state === 2 ? ['success' => true] : [])];
    }

    /**
     * Course completed.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function course_completed(array $e): array {
        return [$e['relateduserid'], 'completed', self::course((int) $e['courseid']), ['completion' => true]];
    }

    /**
     * Grade given to a user: the activity of the grade item (or the course for course totals), with the score.
     *
     * @param array $e Entry.
     * @return array|null
     */
    private static function user_graded(array $e): ?array {
        global $DB;
        $grade = $DB->get_record('grade_grades', ['id' => $e['objectid']], 'id, itemid, finalgrade, rawgrademin, rawgrademax');
        if (!$grade || $grade->finalgrade === null) {
            return null;
        }
        $item = $DB->get_record('grade_items', ['id' => $grade->itemid], 'id, itemtype, itemmodule, iteminstance, courseid,
            grademin, grademax, gradepass');
        if (!$item) {
            return null;
        }
        $object = self::course((int) $item->courseid);
        if ($item->itemtype === 'mod') {
            $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid);
            if ($cm) {
                $object = self::module((int) $cm->id);
            }
        }
        $min = (float) $item->grademin;
        $max = (float) $item->grademax;
        $raw = (float) $grade->finalgrade;
        $result = ['score' => ['raw' => $raw, 'min' => $min, 'max' => $max,
            'scaled' => $max > $min ? round(($raw - $min) / ($max - $min), 4) : 0.0]];
        if ((float) $item->gradepass > 0) {
            $result['success'] = $raw >= (float) $item->gradepass;
        }
        return [$e['relateduserid'], 'scored', $object, $result];
    }

    /**
     * Enrolled in a course.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function enrolled(array $e): array {
        return [$e['relateduserid'], 'registered', self::course((int) $e['courseid'])];
    }

    /**
     * Logged in.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function logged_in(array $e): array {
        return [$e['userid'], 'loggedin', self::site()];
    }

    /**
     * Logged out.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function logged_out(array $e): array {
        return [$e['userid'], 'loggedout', self::site()];
    }

    /**
     * Badge awarded.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function badge_awarded(array $e): array {
        global $CFG, $DB;
        $name = (string) $DB->get_field('badge', 'name', ['id' => $e['objectid']]);
        return [$e['relateduserid'], 'earned', self::activity(
            $CFG->wwwroot . '/badges/overview.php?id=' . $e['objectid'],
            'badge',
            $name
        )];
    }

    /**
     * Quiz attempt started.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function quiz_attempt_started(array $e): array {
        return [$e['relateduserid'] ?: $e['userid'], 'attempted', self::module((int) $e['contextinstanceid'], 'assessment')];
    }

    /**
     * Quiz attempt submitted, with the score of the attempt.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function quiz_attempt_submitted(array $e): array {
        global $DB;
        $attempt = $DB->get_record('quiz_attempts', ['id' => $e['objectid']], 'id, quiz, sumgrades');
        $result = ['completion' => true];
        if ($attempt && $attempt->sumgrades !== null) {
            $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], 'id, sumgrades');
            if ($quiz && (float) $quiz->sumgrades > 0) {
                $result['score'] = ['raw' => (float) $attempt->sumgrades, 'min' => 0.0, 'max' => (float) $quiz->sumgrades,
                    'scaled' => round((float) $attempt->sumgrades / (float) $quiz->sumgrades, 4)];
            }
        }
        return [$e['relateduserid'] ?: $e['userid'], 'completed', self::module((int) $e['contextinstanceid'], 'assessment'),
            $result];
    }

    /**
     * Assignment submitted for grading.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function assign_submitted(array $e): array {
        return [$e['userid'], 'submitted', self::module((int) $e['contextinstanceid'], 'assessment')];
    }

    /**
     * Forum discussion created.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function discussion_created(array $e): array {
        global $CFG;
        return [$e['userid'], 'created', self::activity(
            $CFG->wwwroot . '/mod/forum/discuss.php?d=' . $e['objectid'],
            'discussion',
            ''
        )];
    }

    /**
     * Forum post: a reply to its discussion.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function post_created(array $e): array {
        global $CFG;
        $discussion = (int) ($e['other']['discussionid'] ?? 0);
        return [$e['userid'], 'replied', self::activity(
            $CFG->wwwroot . '/mod/forum/discuss.php?d=' . $discussion,
            'discussion',
            ''
        )];
    }

    /**
     * Choice answered.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function choice_answered(array $e): array {
        return [$e['userid'], 'answered', self::module((int) $e['contextinstanceid'])];
    }

    /**
     * Feedback (survey) submitted; anonymous feedbacks have no identifiable actor.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function feedback_submitted(array $e): array {
        return [$e['userid'], 'completed', self::module((int) $e['contextinstanceid']), ['completion' => true]];
    }

    /**
     * Lesson ended.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function lesson_ended(array $e): array {
        return [$e['userid'], 'completed', self::module((int) $e['contextinstanceid']), ['completion' => true]];
    }

    /**
     * Actor: an account of the site (or an anonymous agent).
     *
     * @param int|string|null $userid User id.
     * @param bool $anonymous Whether the event is anonymous.
     * @param options $options Options.
     * @return array
     */
    private static function actor(int|string|null $userid, bool $anonymous, options $options): array {
        global $CFG;
        $userid = (int) $userid;
        if ($anonymous || $userid <= 0) {
            $name = 'anonymous';
        } else {
            $name = (string) ($options->pseudonymize ? $options->pseudonym($userid) : $userid);
        }
        return ['objectType' => 'Agent', 'account' => ['homePage' => $CFG->wwwroot, 'name' => $name]];
    }

    /**
     * Context: platform and the course as grouping.
     *
     * @param array $e Entry.
     * @return array
     */
    private static function context(array $e): array {
        $context = ['platform' => 'Moodle', 'language' => current_language()];
        if (
            (int) $e['courseid'] > 0 && (int) $e['courseid'] !== SITEID
                && !in_array($e['eventname'], ['\core\event\course_viewed', '\core\event\course_completed',
                    '\core\event\user_enrolment_created'], true)
        ) {
            $context['contextActivities'] = ['grouping' => [self::course((int) $e['courseid'])]];
        }
        return $context;
    }

    /**
     * The site as an activity.
     *
     * @return array
     */
    private static function site(): array {
        global $CFG, $SITE;
        return self::activity($CFG->wwwroot, 'site', (string) ($SITE->fullname ?? ''));
    }

    /**
     * A course as an activity.
     *
     * @param int $courseid Course id.
     * @return array
     */
    private static function course(int $courseid): array {
        global $CFG, $DB;
        $key = 'course' . $courseid;
        self::$names[$key] ??= (string) $DB->get_field('course', 'fullname', ['id' => $courseid]);
        return self::activity($CFG->wwwroot . '/course/view.php?id=' . $courseid, 'course', self::$names[$key]);
    }

    /**
     * A course module as an activity.
     *
     * @param int $cmid Course module id.
     * @param string $type Activity type.
     * @return array
     */
    private static function module(int $cmid, string $type = 'module'): array {
        global $CFG, $DB;
        $key = 'cm' . $cmid;
        if (!isset(self::$names[$key])) {
            $cm = $DB->get_record_sql('SELECT cm.id, cm.instance, m.name AS modname
                                         FROM {course_modules} cm
                                         JOIN {modules} m ON m.id = cm.module
                                        WHERE cm.id = ?', [$cmid]);
            $name = $cm ? (string) $DB->get_field($cm->modname, 'name', ['id' => $cm->instance]) : '';
            self::$names[$key] = [$cm ? $cm->modname : 'unknown', $name];
        }
        [$modname, $name] = self::$names[$key];
        return self::activity($CFG->wwwroot . '/mod/' . $modname . '/view.php?id=' . $cmid, $type, $name);
    }

    /**
     * An activity object.
     *
     * @param string $id IRI.
     * @param string $type Key of TYPES.
     * @param string $name English name (empty: none).
     * @return array
     */
    private static function activity(string $id, string $type, string $name): array {
        $definition = ['type' => self::TYPES[$type]];
        if ($name !== '') {
            $definition['name'] = ['en' => $name];
            $definition['name_en'] = \core_text::substr($name, 0, 250);
        }
        return ['objectType' => 'Activity', 'id' => $id, 'definition' => $definition];
    }

    /**
     * Name based UUID (version 5, SHA-1), as RFC 4122.
     *
     * @param string $name Name.
     * @param string $namespace Namespace UUID.
     * @return string
     */
    public static function uuid5(string $name, string $namespace = self::NAMESPACE): string {
        $hash = sha1(hex2bin(str_replace('-', '', $namespace)) . $name);
        return sprintf(
            '%s-%s-%04x-%04x-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    /**
     * Forgets the names read in this request (tests).
     *
     * @return void
     */
    public static function reset(): void {
        self::$names = [];
    }
}
