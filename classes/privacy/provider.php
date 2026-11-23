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

namespace logstore_opensearch\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use logstore_opensearch\local\options;
use logstore_opensearch\local\transformer\standard;
use tool_opensearch\local\client;
use tool_opensearch\local\config;

/**
 * Privacy provider: the log is kept in an OpenSearch cluster; requests are answered with search and delete by query.
 *
 * Requests reach the cluster whenever tool_opensearch has hosts, even with sending disabled (the documents are still
 * there); an unreachable cluster makes the request fail, so that it is not marked as done.
 *
 * With pseudonyms on, documents keep the pseudonym of each user: requests search the user id and its pseudonym
 * (current salt). Documents written with an older salt cannot be found again (the salt is the key of the pseudonym).
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \tool_log\local\privacy\logstore_provider,
    \tool_log\local\privacy\logstore_userlist_provider {
    /** Documents read per search. */
    private const SIZE = 10000;

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('opensearch', [
            'eventname' => 'privacy:metadata:log:eventname',
            'userid' => 'privacy:metadata:log:userid',
            'relateduserid' => 'privacy:metadata:log:relateduserid',
            'anonymous' => 'privacy:metadata:log:anonymous',
            'other' => 'privacy:metadata:log:other',
            'timecreated' => 'privacy:metadata:log:timecreated',
            'origin' => 'privacy:metadata:log:origin',
            'ip' => 'privacy:metadata:log:ip',
            'realuserid' => 'privacy:metadata:log:realuserid',
        ], 'privacy:metadata:log');
        return $collection;
    }

    /**
     * Contexts with documents of the user.
     *
     * @param contextlist $contextlist List to fill.
     * @param int $userid User id.
     * @return void
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        if (!self::connected()) {
            return;
        }
        $response = self::search([
            'size' => 0,
            'query' => self::user_query(self::user_ids((int) $userid, options::load())),
            'aggs' => ['contexts' => ['terms' => ['field' => 'contextid', 'size' => self::SIZE]]],
        ]);
        $ids = array_column($response['aggregations']['contexts']['buckets'] ?? [], 'key');
        if (!$ids) {
            return;
        }
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $ids), SQL_PARAMS_NAMED);
        $contextlist->add_from_sql("SELECT id FROM {context} WHERE id {$insql}", $params);
    }

    /**
     * Users with documents in the context (user ids written without pseudonyms only).
     *
     * @param userlist $userlist List to fill.
     * @return void
     */
    public static function add_userids_for_context(userlist $userlist) {
        if (!self::connected()) {
            return;
        }
        $aggs = [];
        foreach (['userid', 'relateduserid', 'realuserid'] as $field) {
            $aggs[$field] = ['terms' => ['field' => $field, 'size' => self::SIZE]];
        }
        $response = self::search([
            'size' => 0,
            'query' => ['term' => ['contextid' => (int) $userlist->get_context()->id]],
            'aggs' => $aggs,
        ]);
        $ids = [];
        foreach ($aggs as $field => $unused) {
            $ids = array_merge($ids, array_column($response['aggregations'][$field]['buckets'] ?? [], 'key'));
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids) {
            global $DB;
            // Pseudonyms are not user ids: keep only ids of existing users.
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $userlist->add_from_sql('id', "SELECT id FROM {user} WHERE id {$insql}", $params);
        }
    }

    /**
     * Exports the documents of the user, in the same format as logstore_standard.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        if (!self::connected()) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        $options = options::load();
        $ids = self::user_ids($userid, $options);
        foreach ($contextlist->get_contexts() as $context) {
            $response = self::search([
                'size' => self::SIZE,
                'sort' => [['timecreated' => 'asc']],
                'query' => ['bool' => ['filter' => [
                    ['term' => ['contextid' => (int) $context->id]],
                    self::user_query($ids),
                ]]],
            ]);
            $logs = [];
            foreach ($response['hits']['hits'] ?? [] as $hit) {
                // The core helper expects the id of the table row.
                $record = (object) (['id' => $hit['_id'] ?? 0] + $hit['_source']);
                $me = in_array((int) $record->userid, $ids, true) ? (int) $record->userid : $userid;
                $logs[] = \tool_log\local\privacy\helper::transform_standard_log_record_for_userid($record, $me);
            }
            if ($logs) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_opensearch')],
                    (object) ['logs' => $logs]
                );
            }
        }
    }

    /**
     * Deletes every document of the context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (self::connected()) {
            self::delete_by_query(['term' => ['contextid' => (int) $context->id]]);
        }
    }

    /**
     * Deletes the documents of the user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (!self::connected()) {
            return;
        }
        $ids = self::user_ids((int) $contextlist->get_user()->id, options::load());
        foreach ($contextlist->get_contextids() as $contextid) {
            self::delete_by_query(['bool' => ['filter' => [
                ['term' => ['contextid' => (int) $contextid]],
                self::user_query($ids),
            ]]]);
        }
    }

    /**
     * Deletes the documents of the users in the context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_userlist(approved_userlist $userlist) {
        if (!self::connected() || !$userlist->get_userids()) {
            return;
        }
        $options = options::load();
        $ids = [];
        foreach ($userlist->get_userids() as $userid) {
            $ids = array_merge($ids, self::user_ids((int) $userid, $options));
        }
        self::delete_by_query(['bool' => ['filter' => [
            ['term' => ['contextid' => (int) $userlist->get_context()->id]],
            self::user_query($ids),
        ]]]);
    }

    /**
     * Whether tool_opensearch has hosts: then documents may exist in the cluster, even with sending disabled.
     *
     * @return bool
     */
    private static function connected(): bool {
        return config::load()->hosts !== [];
    }

    /**
     * Searches the logstore data stream.
     *
     * @param array $body Body of _search.
     * @return array
     * @throws \tool_opensearch\local\client_exception When the cluster cannot answer.
     */
    private static function search(array $body): array {
        $config = config::load();
        [$status, $response] = (new client($config))->request(
            'POST',
            '/' . $config->data_stream(standard::STREAM) . '/_search?ignore_unavailable=true',
            $body
        );
        if ($status === 404) {
            // No document was ever sent.
            return [];
        }
        if ($status < 200 || $status > 299) {
            throw client::status_error($status, $response);
        }
        return $response;
    }

    /**
     * Deletes documents of the logstore data stream.
     *
     * @param array $query Query.
     * @return void
     * @throws \tool_opensearch\local\client_exception When the cluster cannot answer.
     */
    private static function delete_by_query(array $query): void {
        $config = config::load();
        [$status, $response] = (new client($config))->request(
            'POST',
            '/' . $config->data_stream(standard::STREAM) . '/_delete_by_query?conflicts=proceed&refresh=true',
            ['query' => $query]
        );
        if ($status !== 404 && ($status < 200 || $status > 299)) {
            throw client::status_error($status, $response);
        }
    }

    /**
     * The user id and, when there is a salt, its pseudonym.
     *
     * @param int $userid User id.
     * @param options $options Options.
     * @return int[]
     */
    private static function user_ids(int $userid, options $options): array {
        $ids = [$userid];
        if ($options->pseudonymize || $options->salt !== '') {
            $ids[] = $options->pseudonym($userid);
        }
        return array_values(array_unique($ids));
    }

    /**
     * Documents where any of the ids is the author, the related user or the real user.
     *
     * @param int[] $ids User ids (or pseudonyms).
     * @return array
     */
    private static function user_query(array $ids): array {
        $should = [];
        foreach (['userid', 'relateduserid', 'realuserid'] as $field) {
            $should[] = ['terms' => [$field => array_values($ids)]];
        }
        return ['bool' => ['should' => $should, 'minimum_should_match' => 1]];
    }
}
