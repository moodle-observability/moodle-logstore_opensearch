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

With ``mode_xapi`` on, a set of events also becomes an xAPI 1.0.3 statement in the data stream ``<prefix>-xapi`` (365 days of retention by default), for learning analytics. Both modes can be on at the same time; events without a mapping are skipped in this mode.

.. list-table::
   :header-rows: 1

   * - Events
     - Verb
     - Object
   * - course viewed, module viewed (every ``mod_*``)
     - viewed
     - course, module
   * - activity completion updated (complete or pass), course completed
     - completed
     - module, course
   * - user graded (scaled score, success)
     - scored
     - module
   * - user enrolment created
     - registered
     - course
   * - quiz attempt started / submitted (score, duration)
     - attempted / completed
     - assessment
   * - assignment submitted, feedback submitted, lesson ended
     - submitted / completed
     - module
   * - forum discussion created / post created
     - created / replied
     - discussion
   * - choice answered
     - answered
     - module
   * - badge awarded
     - earned
     - badge
   * - user logged in / out
     - logged-in / logged-out
     - site

* The actor is an account, as in ``core_xapi``: ``homePage`` is the site URL and ``name`` the user id (or its pseudonym, :doc:`privacy`). Never an email or a name.
* The statement ``id`` is a UUID v5 of the event: sending it again does not duplicate it.
* The context carries the course (``grouping``) and the parent activity, ``platform`` "Moodle" and the language.
* ``object.definition.name_en`` (keyword) copies the English name of the object, for aggregations. ``moodle_event`` keeps the event name, the context and course ids (used by the privacy requests) and the course name ``moodle_event.course``, present in every statement of a course, for the dashboards.
* Verbs and activity types follow the ADL registry, like ``logstore_xapi`` (used as a reference).

Example: who completed a quiz in a course (DQL):

.. code-block:: text

   verb.id:"http://adlnet.gov/expapi/verbs/completed" and object.definition.type:"http://adlnet.gov/expapi/activities/assessment" and moodle_event.course:"Course 1"

OpenSearch is not an LRS: there is no statements API, signatures or voiding. Tools that need a certified LRS should use ``logstore_xapi``.

Dashboards
----------

``examples/`` has the dashboard *Moodle - learning (xAPI)*, in two versions:

* ``examples/opensearch-dashboards/moodle-xapi.ndjson``: import it in *Dashboards Management > Saved objects > Import*. It creates the index pattern ``*-xapi*``.
* ``examples/grafana/dashboards/moodle-xapi.json``: import it in Grafana, with a datasource of the ``grafana-opensearch-datasource`` plugin pointing to ``*-xapi*`` (time field ``@timestamp``).

It shows statements and active learners, engagement by verb over time, the funnel and the performance (average scaled score) by activity, the score distribution, success and courses, with filters for the site (``moodle.shortname``), course, learner, verb and activity type.
