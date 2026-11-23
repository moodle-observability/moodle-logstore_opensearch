Configuração
============

*Administração do site > Plugins > Registros > OpenSearch log.* O cluster, a fila e a retenção do stream ``logstore`` (180 dias por padrão) ficam no ``tool_opensearch``.

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Configuração
     - Padrão
     - Descrição
   * - ``mode_standard``
     - ligado
     - Grava os eventos no stream ``logstore``.
   * - ``logguests``
     - ligado
     - Grava eventos de convidados e de usuários não logados (em scripts CLI, sempre).
   * - ``edulevels``
     - todos
     - Níveis educacionais gravados: outros, ensino, participação.
   * - ``crud``
     - todos
     - Ações gravadas: criar, visualizar, atualizar, apagar.
   * - ``excludeevents``
     - vazio
     - Nomes de eventos não gravados, um por linha.
   * - ``includeip``
     - ligado
     - Grava o endereço IP.
   * - ``pseudonymize``
     - desligado
     - Troca os ids de usuário por pseudônimos (:doc:`privacy`).
   * - ``salt``
     - gerado
     - Segredo dos pseudônimos, cifrado. ``MOODLE_OPENSEARCH_LOG_SALT`` vence a configuração.
   * - ``otherdenylist``
     - vazio
     - Chaves removidas de ``other``, uma por linha.
   * - ``otherfields``
     - desligado
     - Copia ``other`` também para ``other_flat`` (``flat_object``), para permitir busca pelas chaves.
