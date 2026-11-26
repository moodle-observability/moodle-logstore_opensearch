moodle-logstore_opensearch
==========================

**Moodle log store that writes the events to OpenSearch.**

A log store (*Site administration > Plugins > Logging*) that sends every Moodle event to OpenSearch, through ``tool_opensearch``, with exactly the columns of the standard log (``logstore_standard_log``). The events are queried and analysed in OpenSearch Dashboards or Grafana; the Moodle reports keep reading the standard log.

.. note::

   Version 0.1.0 (alpha): ``standard`` mode. The ``xapi`` mode comes in a later version. Follow the progress in the `issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_.

Features
--------

* **Same columns as the standard log**: same names, types and values, plus ``@timestamp``, in the data stream ``<prefix>-logstore``. Existing queries translate directly.
* **No HTTP during a page**: events go to the queue of ``tool_opensearch``, which sends them with ``_bulk``. OpenSearch down never slows Moodle down.
* **Filters**: education level, actions (CRUD), excluded events, guests.
* **Privacy**: IP address optional, pseudonyms (keyed hash of the user ids), keys removed from ``other``; export and deletion of a user's data with the Moodle privacy API.
* **Dashboards**: ``tool_opensearch`` ships dashboards for this data stream with the filters of the Moodle logs report.

Requirements
------------

* Moodle 5.3 or later.
* ``tool_opensearch`` 0.1.2 or later, configured and connected.

Links
-----

* `Source code <https://github.com/moodle-observability/moodle-logstore_opensearch>`_
* `Issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_
* `tool_opensearch <https://moodle-observability.github.io/moodle-tool_opensearch/>`_

.. toctree::
   :hidden:
   :maxdepth: 1

   installation
   configuration
   modes
   privacy
   capacity
   dashboards
