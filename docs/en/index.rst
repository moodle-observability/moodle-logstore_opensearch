moodle-logstore_opensearch
==========================

**Moodle log store that writes events to OpenSearch.**

Log store (``tool_log`` subplugin) that writes Moodle events to OpenSearch through ``tool_opensearch``, in two independent modes, each in its own data stream.

.. note::

   Planning phase. There is no release yet. Follow the progress in the `issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_.

Planned features
----------------

* Mode ``standard``: exactly the columns of ``logstore_standard_log`` (same names, types and values) plus ``@timestamp``, in the ``logstore`` data stream.
* Mode ``xapi``: xAPI 1.0.3 statements in the ``xapi`` data stream (OpenSearch is not an LRS: no Statement API).
* Privacy options: leave out the IP address, pseudonymise user ids (HMAC with an encrypted salt), remove keys from ``other``; Privacy API export and deletion.
* Filters by educational level, CRUD, excluded events and guests.
* Write only: Moodle reports keep reading ``logstore_standard_log``; queries happen in OpenSearch Dashboards.

Requirements
------------

* Moodle 5.3 or later.
* An OpenSearch cluster (2.x) with the security and ISM plugins.
* `tool_opensearch <https://moodle-observability.github.io/moodle-tool_opensearch/>`_.

Links
-----

* `Source code <https://github.com/moodle-observability/moodle-logstore_opensearch>`_
* `Issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_
* `Moodle Observability <https://moodle-observability.github.io/>`_

.. toctree::
   :hidden:
   :maxdepth: 1

   installation
   configuration
   usage
