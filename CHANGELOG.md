# Changelog

## 0.2.0 - 2026-11-26

`xapi` mode.

- New setting `mode_xapi` (off by default): a set of events (course and module views, completions, grades, enrolments,
  quiz attempts, submissions, forum discussions and posts, choice answers, badges, login and logout) also becomes an
  xAPI 1.0.3 statement in the `xapi` stream (data stream `<prefix>-xapi`, 365 days of retention). Both modes can be on.
- Actor as an account (site URL and user id or pseudonym); statement id as a UUID v5 of the event, so a resend does
  not duplicate it; ADL verbs and activity types; `object.definition.name_en` and `moodle_event` for aggregations.
- Privacy: export (subfolder `xAPI`) and deletion of the statements in both streams.
- Dashboard "Moodle - learning (xAPI)" in `examples/`, for OpenSearch Dashboards and Grafana.

## 0.1.0 - 2026-11-24

First release (alpha), `standard` mode.

- Log store (write only) that sends every Moodle event to the `logstore` stream of tool_opensearch (data stream
  `<prefix>-logstore`) with exactly the columns of `logstore_standard_log`, plus `@timestamp`; `other` as JSON,
  not indexed, optionally copied to a searchable `other_flat`.
- Filters: education level, actions (CRUD), excluded events, guests.
- Privacy: optional IP address, pseudonyms (HMAC-SHA256 of the user ids with an encrypted secret), keys removed
  from `other`; privacy provider with export and deletion by query in OpenSearch.
