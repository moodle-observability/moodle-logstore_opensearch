Modes
=====

Standard mode
-------------

Every event becomes a document in the data stream ``<prefix>-logstore`` with the columns of ``logstore_standard_log`` (the same names, types and values as the table, ``id`` excepted):

.. list-table::
   :header-rows: 1

   * - Fields
     - OpenSearch type
   * - eventname, component, action, target, objecttable, crud, origin
     - keyword
   * - objectid, contextid, contextinstanceid, userid, courseid, relateduserid, realuserid
     - long
   * - edulevel, contextlevel, anonymous
     - integer
   * - other
     - keyword, not indexed (JSON text)
   * - timecreated
     - long (Unix time)
   * - ip
     - ip
   * - @timestamp
     - date
   * - moodle.site, moodle.shortname
     - keyword
   * - other_flat
     - flat_object

* ``other`` is the JSON text written by the core (``logstore_standard`` with JSON format, its default), not indexed, so that each key of each event does not become a field of the mapping. With ``otherfields``, it is also copied to ``other_flat`` and can be searched (``other_flat.username: ana``).
* The mapping is strict: no other field enters the data stream.
* ``@timestamp`` is ``timecreated`` in ISO 8601; ``moodle.site`` and ``moodle.shortname`` identify the site (several sites may share a cluster).

Example: what a user did in a course, in OpenSearch Dashboards (DQL):

.. code-block:: text

   userid:42 and courseid:7 and crud:(c or u or d)

xAPI mode
---------

Planned: xAPI 1.0.3 statements in their own data stream (``<prefix>-xapi``), for learning analytics. OpenSearch is not an LRS: tools that need a certified LRS should use ``logstore_xapi``.
