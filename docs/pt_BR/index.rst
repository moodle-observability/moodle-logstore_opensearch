moodle-logstore_opensearch
==========================

**Log store do Moodle que grava os eventos no OpenSearch.**

Log store (subplugin do ``tool_log``) que grava os eventos do Moodle no OpenSearch pelo ``tool_opensearch``, em dois modos independentes, cada um no seu data stream.

.. note::

   Fase de planejamento. Ainda não há versão publicada. Acompanhe o andamento nas `issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_.

Funcionalidades previstas
-------------------------

* Modo ``standard``: exatamente as colunas do ``logstore_standard_log`` (mesmos nomes, tipos e valores) mais ``@timestamp``, no data stream ``logstore``.
* Modo ``xapi``: statements xAPI 1.0.3 no data stream ``xapi`` (o OpenSearch não é um LRS: sem Statement API).
* Opções de privacidade: não gravar o endereço IP, pseudonimizar os ids de usuário (HMAC com sal cifrado), remover chaves de ``other``; exportação e exclusão pela Privacy API.
* Filtros por nível educacional, CRUD, eventos excluídos e convidados.
* Só escrita: os relatórios do Moodle continuam lendo o ``logstore_standard_log``; as consultas são feitas no OpenSearch Dashboards.

Requisitos
----------

* Moodle 5.3 ou posterior.
* Um cluster OpenSearch (2.x) com os plugins de segurança e ISM.
* `tool_opensearch <https://moodle-observability.github.io/moodle-tool_opensearch/>`_.

Links
-----

* `Código-fonte <https://github.com/moodle-observability/moodle-logstore_opensearch>`_
* `Issues <https://github.com/moodle-observability/moodle-logstore_opensearch/issues>`_
* `Moodle Observability <https://moodle-observability.github.io/>`_

.. toctree::
   :hidden:
   :maxdepth: 1

   installation
   configuration
   usage
