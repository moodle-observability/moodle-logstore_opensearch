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

namespace logstore_opensearch\local;

/**
 * Settings of the log store: filters and privacy options.
 *
 * @package    logstore_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class options {
    /** Environment variable that wins over the salt setting. */
    public const SALT_ENV = 'MOODLE_OPENSEARCH_LOG_SALT';

    /** Default retention of the logstore stream, in days. */
    public const RETENTION_DAYS = 180;

    /** @var bool Whether the standard mode (stream "logstore") is on. */
    public bool $standard = true;

    /** @var bool Whether the xAPI mode (stream "xapi") is on. */
    public bool $xapi = false;

    /** @var bool Whether events of guests and of users not logged in are written. */
    public bool $logguests = true;

    /** @var int[] Education levels written. */
    public array $edulevels = [0, 1, 2];

    /** @var string[] CRUD values written. */
    public array $crud = ['c', 'r', 'u', 'd'];

    /** @var string[] Event names not written (with the leading backslash). */
    public array $excludeevents = [];

    /** @var bool Whether the IP address is written. */
    public bool $includeip = true;

    /** @var bool Whether user ids are replaced by pseudonyms. */
    public bool $pseudonymize = false;

    /** @var string Secret of the pseudonyms (decrypted). */
    public string $salt = '';

    /** @var string[] Keys removed from "other". */
    public array $otherdenylist = [];

    /** @var bool Whether "other" is also copied to "other_flat" (searchable). */
    public bool $otherfields = false;

    /**
     * Options of the site.
     *
     * @param \stdClass|null $settings Plugin settings; null reads get_config().
     * @param array|null $env Variables (name => value); null reads getenv().
     * @return self
     */
    public static function load(?\stdClass $settings = null, ?array $env = null): self {
        $settings = $settings ?? (get_config('logstore_opensearch') ?: new \stdClass());
        $get = fn(string $name, $default) => isset($settings->$name) && $settings->$name !== '' ? $settings->$name : $default;
        $options = new self();
        $options->standard = (bool) $get('mode_standard', 1);
        $options->xapi = (bool) $get('mode_xapi', 0);
        $options->logguests = (bool) $get('logguests', 1);
        $options->edulevels = array_map('intval', self::split((string) $get('edulevels', '0,1,2'), ','));
        $options->crud = self::split((string) $get('crud', 'c,r,u,d'), ',');
        $options->excludeevents = array_map(
            fn(string $name): string => '\\' . ltrim($name, '\\'),
            self::split((string) $get('excludeevents', ''), "\n")
        );
        $options->includeip = (bool) $get('includeip', 1);
        $options->pseudonymize = (bool) $get('pseudonymize', 0);
        $options->otherdenylist = self::split((string) $get('otherdenylist', ''), "\n");
        $options->otherfields = (bool) $get('otherfields', 0);

        $envsalt = $env === null ? getenv(self::SALT_ENV) : ($env[self::SALT_ENV] ?? false);
        if ($envsalt !== false && trim((string) $envsalt) !== '') {
            $options->salt = trim((string) $envsalt);
        } else if (!empty($settings->salt)) {
            try {
                $options->salt = \core\encryption::decrypt((string) $settings->salt);
            } catch (\Throwable $e) {
                $options->salt = '';
            }
        }
        return $options;
    }

    /**
     * Non empty, trimmed items of a list.
     *
     * @param string $value List.
     * @param string $separator Separator.
     * @return string[]
     */
    public static function split(string $value, string $separator): array {
        return array_values(array_filter(array_map('trim', explode($separator, str_replace("\r", '', $value))), 'strlen'));
    }

    /**
     * Creates and stores a random salt when pseudonyms are needed and there is none.
     *
     * @return string The salt.
     */
    public function ensure_salt(): string {
        if ($this->salt === '') {
            $this->salt = random_string(64);
            set_config('salt', \core\encryption::encrypt($this->salt), 'logstore_opensearch');
        }
        return $this->salt;
    }

    /**
     * Pseudonym of a user id: HMAC-SHA256 with the salt, cut to 60 bits so that it stays a positive "long".
     *
     * @param int $userid User id.
     * @return int
     */
    public function pseudonym(int $userid): int {
        return (int) hexdec(substr(hash_hmac('sha256', (string) $userid, $this->ensure_salt()), 0, 15));
    }
}
