Configuration
=============

*Site administration > Plugins > Logging > OpenSearch log.* The cluster, the queue and the retention of the ``logstore`` stream (180 days by default) are set in ``tool_opensearch``.

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Setting
     - Default
     - Description
   * - ``mode_standard``
     - on
     - Write the events to the ``logstore`` stream.
   * - ``logguests``
     - on
     - Write events of guests and of users not logged in (always written in CLI scripts).
   * - ``edulevels``
     - all
     - Education levels written: other, teaching, participating.
   * - ``crud``
     - all
     - Actions written: create, view, update, delete.
   * - ``excludeevents``
     - empty
     - Event names not written, one per line.
   * - ``includeip``
     - on
     - Write the IP address.
   * - ``pseudonymize``
     - off
     - Replace user ids by pseudonyms (:doc:`privacy`).
   * - ``salt``
     - generated
     - Secret of the pseudonyms, encrypted. ``MOODLE_OPENSEARCH_LOG_SALT`` wins over it.
   * - ``otherdenylist``
     - empty
     - Keys removed from ``other``, one per line.
   * - ``otherfields``
     - off
     - Also copy ``other`` to ``other_flat`` (``flat_object``), so that its keys can be searched.
