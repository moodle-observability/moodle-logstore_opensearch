Capacity
========

Measure first
-------------

Count the events of one day and the busiest minute in the standard log of the site:

.. code-block:: sql

   SELECT COUNT(*) FROM mdl_logstore_standard_log
    WHERE timecreated > EXTRACT(EPOCH FROM now() - interval '1 day');

   SELECT date_trunc('minute', to_timestamp(timecreated)) AS minute, COUNT(*)
     FROM mdl_logstore_standard_log
    WHERE timecreated > EXTRACT(EPOCH FROM now() - interval '1 day')
 GROUP BY 1 ORDER BY 2 DESC LIMIT 1;

Load test
---------

Measured with ``dev/cli/logstore_load.php`` of the workspace (synthetic course views, 100000 events, 200 per simulated request), Moodle 5.3 and OpenSearch 2.19.3 on one developer machine (single node):

.. list-table::
   :header-rows: 1

   * - Measure
     - Value
   * - Cost of ``logstore_standard`` per event
     - 63 µs
   * - Cost of ``logstore_standard`` + ``logstore_opensearch`` per event
     - 96 µs
   * - Added by ``logstore_opensearch`` (queue insert, no HTTP)
     - about 30 µs per event; 6 ms for a page with 200 events
   * - Document size in the queue (JSON)
     - 450 bytes on average
   * - Throughput of ``ship_queue`` (``_bulk`` of 5000)
     - about 18000 documents per second

Numbers depend on the machine and the cluster; repeat the test on the target infrastructure. Synthetic events are all alike and compress far better than real ones: use the JSON size, not the stored size, for planning.

Formula
-------

* **Queue throughput needed**: busiest minute × number of modes. One run of ``ship_queue`` per minute sends for up to 50 seconds; at 18000 documents per second, one run empties about 900000 documents.
* **Database**: the queue keeps each document until it is sent (about 450 bytes); with OpenSearch down for a day, a queue of one day of events. ``queuemax`` (2 million by default) bounds it.
* **OpenSearch**: events per day × 450 bytes ≈ JSON per day; with ``best_compression``, plan about 40% of it per copy (primary plus replicas), multiplied by the retention in days.

Example: 2 million events per day → about 0.9 GB of JSON per day → about 0.36 GB per copy per day → with one replica and 180 days, about 130 GB.

Tuning
------

* ``batchdocs`` and ``batchbytes`` (``tool_opensearch``): larger batches send faster until the cluster rejects them (HTTP 429); then lower them.
* Filters (:doc:`configuration`): excluding high volume, low value events (for example ``\core\event\course_viewed`` on a busy front page) is the cheapest way to reduce volume.
* Keep an eye on ``moodle_tool_opensearch_queue_oldest_age_seconds``: a growing value means the cluster cannot keep up.
