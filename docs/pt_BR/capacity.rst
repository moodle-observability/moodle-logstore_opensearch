Capacidade
==========

Meça antes
----------

Conte os eventos de um dia e o minuto de pico no log padrão do site:

.. code-block:: sql

   SELECT COUNT(*) FROM mdl_logstore_standard_log
    WHERE timecreated > EXTRACT(EPOCH FROM now() - interval '1 day');

   SELECT date_trunc('minute', to_timestamp(timecreated)) AS minute, COUNT(*)
     FROM mdl_logstore_standard_log
    WHERE timecreated > EXTRACT(EPOCH FROM now() - interval '1 day')
 GROUP BY 1 ORDER BY 2 DESC LIMIT 1;

Teste de carga
--------------

Medido com o ``dev/cli/logstore_load.php`` do workspace (visualizações de curso sintéticas, 100000 eventos, 200 por requisição simulada), Moodle 5.3 e OpenSearch 2.19.3 numa máquina de desenvolvimento (um nó):

.. list-table::
   :header-rows: 1

   * - Medida
     - Valor
   * - Custo do ``logstore_standard`` por evento
     - 63 µs
   * - Custo do ``logstore_standard`` + ``logstore_opensearch`` por evento
     - 96 µs
   * - Acrescentado pelo ``logstore_opensearch`` (insert na fila, sem HTTP)
     - cerca de 30 µs por evento; 6 ms numa página com 200 eventos
   * - Tamanho do documento na fila (JSON)
     - 450 bytes em média
   * - Vazão do ``ship_queue`` (``_bulk`` de 5000)
     - cerca de 18000 documentos por segundo

Os números dependem da máquina e do cluster; repita o teste na infraestrutura de destino. Eventos sintéticos são todos parecidos e comprimem muito melhor que os reais: use o tamanho do JSON, não o tamanho armazenado, para planejar.

Fórmula
-------

* **Vazão da fila necessária**: minuto de pico × número de modos. Uma execução do ``ship_queue`` por minuto envia por até 50 segundos; a 18000 documentos por segundo, uma execução esvazia cerca de 900000 documentos.
* **Banco**: a fila guarda cada documento até o envio (cerca de 450 bytes); com o OpenSearch fora do ar por um dia, uma fila de um dia de eventos. O ``queuemax`` (2 milhões por padrão) limita.
* **OpenSearch**: eventos por dia × 450 bytes ≈ JSON por dia; com ``best_compression``, planeje cerca de 40% disso por cópia (primária mais réplicas), vezes a retenção em dias.

Exemplo: 2 milhões de eventos por dia → cerca de 0,9 GB de JSON por dia → cerca de 0,36 GB por cópia por dia → com uma réplica e 180 dias, cerca de 130 GB.

Ajustes
-------

* ``batchdocs`` e ``batchbytes`` (``tool_opensearch``): lotes maiores enviam mais rápido até o cluster recusar (HTTP 429); então reduza.
* Filtros (:doc:`configuration`): excluir eventos de muito volume e pouco valor (por exemplo ``\core\event\course_viewed`` numa página inicial movimentada) é o jeito mais barato de reduzir o volume.
* Acompanhe o ``moodle_tool_opensearch_queue_oldest_age_seconds``: um valor crescendo indica que o cluster não acompanha.
