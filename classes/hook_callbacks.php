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

/**
 * Hook callbacks.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Declares the "logstore" stream.
     *
     * @param \tool_opensearch\hook\register_streams $hook Hook.
     * @return void
     */
    public static function register_streams(\tool_opensearch\hook\register_streams $hook): void {
        $hook->add_stream(
            'logstore_opensearch',
            standard::STREAM,
            standard::properties(),
            options::RETENTION_DAYS,
            get_string('stream_logstore', 'logstore_opensearch')
        );
    }
}
