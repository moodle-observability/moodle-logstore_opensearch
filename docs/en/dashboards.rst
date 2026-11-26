Dashboards
==========

``tool_opensearch`` ships the dashboards for this data stream in its ``examples/`` folder: **Moodle - log query** and **Moodle - log overview** for OpenSearch Dashboards, and **Moodle - log (tool_opensearch)** for Grafana. They read ``*-logstore*`` and filter by the same criteria as the Moodle logs report (site, course, user, period, activity, actions, origin and event). See the `Dashboards page of tool_opensearch <https://moodle-observability.github.io/moodle-tool_opensearch/en/dashboards.html>`_.

They were checked with the documents of this log store: the field names are the columns of the standard log, as the dashboards expect.
