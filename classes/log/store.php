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

namespace logstore_opensearch\log;

use logstore_opensearch\local\options;
use logstore_opensearch\local\transformer\standard;
use logstore_opensearch\local\transformer\xapi;

/**
 * Log store that sends the Moodle events to OpenSearch through tool_opensearch.
 *
 * Write only: the Moodle reports keep reading logstore_standard_log. Documents go to the queue of
 * tool_opensearch (no HTTP call during the request) and are sent by its ship_queue task.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store implements \tool_log\log\writer {
    use \tool_log\helper\store;
    use \tool_log\helper\buffered_writer;

    /** @var options Options. */
    protected options $options;

    /**
     * Constructor.
     *
     * @param \tool_log\log\manager $manager Log manager.
     */
    public function __construct(\tool_log\log\manager $manager) {
        $this->helper_setup($manager);
        $this->options = options::load();
        // JSON in "other", as logstore_standard writes it by default.
        $this->jsonformat = true;
    }

    #[\Override]
    protected function is_event_ignored(\core\event\base $event) {
        if ((!$this->options->standard && !$this->options->xapi) || !\tool_opensearch\api::is_enabled()) {
            return true;
        }
        if ((!CLI_SCRIPT || PHPUNIT_TEST) && !$this->options->logguests && (!isloggedin() || isguestuser())) {
            // Always log in CLI scripts, where nobody logs in.
            return true;
        }
        if (!in_array((int) $event->edulevel, $this->options->edulevels, true)) {
            return true;
        }
        if (!in_array($event->crud, $this->options->crud, true)) {
            return true;
        }
        return in_array('\\' . ltrim($event->eventname, '\\'), $this->options->excludeevents, true);
    }

    #[\Override]
    protected function insert_event_entries($evententries) {
        foreach ($evententries as $entry) {
            if ($this->options->standard) {
                \tool_opensearch\api::index(standard::STREAM, standard::document($entry, $this->options));
            }
            if ($this->options->xapi) {
                $statement = xapi::document($entry, $this->options);
                if ($statement !== null) {
                    \tool_opensearch\api::index(xapi::STREAM, $statement);
                }
            }
        }
    }

    /**
     * Options of this store (tests).
     *
     * @return options
     */
    public function get_options(): options {
        return $this->options;
    }
}
