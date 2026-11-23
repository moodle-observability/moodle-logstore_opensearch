Installation
============

1. Install and configure ``tool_opensearch`` 0.1.2 or later, and check its connection test.
2. Download the release, or clone the repository, into ``public/admin/tool/log/store/opensearch``:

   .. code-block:: bash

      git clone https://github.com/moodle-observability/moodle-logstore_opensearch.git public/admin/tool/log/store/opensearch

3. Run ``php admin/cli/upgrade.php``.
4. Enable it in *Site administration > Plugins > Logging > Manage log stores*. Keep the standard log enabled: the Moodle reports read it.

The plugin has no tables: documents wait in the queue of ``tool_opensearch``.
