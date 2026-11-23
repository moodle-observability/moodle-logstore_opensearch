moodle-logstore_opensearch
==========================

**Log store do Moodle que grava os eventos no OpenSearch.**

Um log store (*Administração do site > Plugins > Registros*) que envia todo evento do Moodle ao OpenSearch, pelo ``tool_opensearch``, com exatamente as colunas do log padrão (``logstore_standard_log``). Os eventos são consultados e analisados no OpenSearch Dashboards ou no Grafana; os relatórios do Moodle continuam lendo o log padrão.

.. note::

   Versão 0.1.0 (alfa): modo ``standard``. O modo ``xapi`` vem numa versão seguinte. Acompanhe o andamento nas `issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_.

Funcionalidades
---------------

* **Mesmas colunas do log padrão**: mesmos nomes, tipos e valores, mais ``@timestamp``, no data stream ``<prefixo>-logstore``. Consultas existentes se traduzem diretamente.
* **Nenhum HTTP durante a página**: os eventos vão para a fila do ``tool_opensearch``, que os envia com ``_bulk``. OpenSearch fora do ar nunca deixa o Moodle lento.
* **Filtros**: nível educacional, ações (CRUD), eventos excluídos, convidados.
* **Privacidade**: endereço IP opcional, pseudônimos (hash com chave dos ids de usuário), chaves removidas de ``other``; exportação e exclusão dos dados de um usuário pela API de privacidade do Moodle.
* **Painéis**: o ``tool_opensearch`` traz painéis para este data stream com os filtros do relatório de logs do Moodle.

Requisitos
----------

* Moodle 5.3 ou posterior.
* ``tool_opensearch`` 0.1.2 ou posterior, configurado e conectado.

Links
-----

* `Código-fonte <https://github.com/moodle-observability/moodle-logstore_opensearch>`_
* `Issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_
* `tool_opensearch <https://moodle-observability.github.io/moodle-tool_opensearch/>`_

.. toctree::
   :hidden:
   :maxdepth: 1

   installation
   configuration
   modes
   privacy
