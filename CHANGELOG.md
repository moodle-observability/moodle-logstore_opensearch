# Changelog

## 0.1.0 - 2026-11-24

First release (alpha), `standard` mode.

- Log store (write only) that sends every Moodle event to the `logstore` stream of tool_opensearch (data stream
  `<prefix>-logstore`) with exactly the columns of `logstore_standard_log`, plus `@timestamp`; `other` as JSON,
  not indexed, optionally copied to a searchable `other_flat`.
- Filters: education level, actions (CRUD), excluded events, guests.
- Privacy: optional IP address, pseudonyms (HMAC-SHA256 of the user ids with an encrypted secret), keys removed
  from `other`; privacy provider with export and deletion by query in OpenSearch.
