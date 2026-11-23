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
 * Standard mode: the same columns, names and values as logstore_standard_log, plus @timestamp.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class standard {
    /** Stream (data stream <prefix>-logstore). */
    public const STREAM = 'logstore';

    /** Integer columns that may be null. */
    private const NULLABLE_IDS = ['objectid', 'relateduserid', 'realuserid'];

    /** Integer columns that are never null. */
    private const IDS = ['edulevel', 'contextid', 'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'anonymous',
        'timecreated'];

    /** User id columns replaced by pseudonyms. */
    private const USER_COLUMNS = ['userid', 'relateduserid', 'realuserid'];

    /**
     * Mapping of the stream: the columns of logstore_standard_log.
     *
     * @return array
     */
    public static function properties(): array {
        $keyword = ['type' => 'keyword'];
        $long = ['type' => 'long'];
        $integer = ['type' => 'integer'];
        return [
            'eventname' => $keyword,
            'component' => $keyword,
            'action' => $keyword,
            'target' => $keyword,
            'objecttable' => $keyword,
            'objectid' => $long,
            'crud' => $keyword,
            'edulevel' => $integer,
            'contextid' => $long,
            'contextlevel' => $integer,
            'contextinstanceid' => $long,
            'userid' => $long,
            'courseid' => $long,
            'relateduserid' => $long,
            'anonymous' => $integer,
            // The JSON text written by the core, not indexed: one field per key would blow up the mapping.
            'other' => ['type' => 'keyword', 'index' => false, 'doc_values' => false],
            'other_flat' => ['type' => 'flat_object'],
            'timecreated' => $long,
            'origin' => $keyword,
            'ip' => ['type' => 'ip'],
            'realuserid' => $long,
        ];
    }

    /**
     * Document of a log entry, as built by \tool_log\helper\buffered_writer (with "other" as JSON).
     *
     * @param array $entry Log entry: the columns of logstore_standard_log.
     * @param options $options Options.
     * @return array
     */
    public static function document(array $entry, options $options): array {
        $doc = [
            '@timestamp' => gmdate('Y-m-d\TH:i:s\Z', (int) $entry['timecreated']),
            'eventname' => (string) $entry['eventname'],
            'component' => (string) $entry['component'],
            'action' => (string) $entry['action'],
            'target' => (string) $entry['target'],
            'objecttable' => $entry['objecttable'] ?? null,
            'crud' => (string) $entry['crud'],
            'origin' => $entry['origin'] ?? null,
            'ip' => $options->includeip && !empty($entry['ip']) ? (string) $entry['ip'] : null,
        ];
        foreach (self::IDS as $column) {
            $doc[$column] = (int) ($entry[$column] ?? 0);
        }
        foreach (self::NULLABLE_IDS as $column) {
            $doc[$column] = isset($entry[$column]) && $entry[$column] !== '' ? (int) $entry[$column] : null;
        }

        $other = self::other($entry['other'] ?? null, $options);
        if ($options->otherdenylist === [] && (is_string($entry['other'] ?? null) || !isset($entry['other']))) {
            // The text written by the core, unchanged (also "null" for events without "other").
            $doc['other'] = $entry['other'] ?? null;
        } else {
            $doc['other'] = $other === null ? null : json_encode($other, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if ($options->otherfields && is_array($other) && $other !== []) {
            $doc['other_flat'] = $other;
        }

        if ($options->pseudonymize) {
            foreach (self::USER_COLUMNS as $column) {
                if (!empty($doc[$column])) {
                    $doc[$column] = $options->pseudonym($doc[$column]);
                }
            }
            if ($doc['objecttable'] === 'user' && !empty($doc['objectid'])) {
                $doc['objectid'] = $options->pseudonym($doc['objectid']);
            }
        }
        return $doc;
    }

    /**
     * "other" decoded, without the keys of the deny list.
     *
     * @param mixed $other JSON text (or an array).
     * @param options $options Options.
     * @return array|null
     */
    private static function other(mixed $other, options $options): ?array {
        if (is_string($other)) {
            $decoded = json_decode($other, true);
            $other = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        if (!is_array($other)) {
            return null;
        }
        foreach ($options->otherdenylist as $key) {
            unset($other[$key]);
        }
        return $other;
    }
}
