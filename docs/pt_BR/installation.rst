Instalação
==========

1. Instale e configure o ``tool_opensearch`` 0.1.2 ou posterior, e confira o teste de conexão dele.
2. Baixe a release, ou clone o repositório, em ``public/admin/tool/log/store/opensearch``:

   .. code-block:: bash

      git clone https://github.com/moodle-observability/moodle-logstore_opensearch.git public/admin/tool/log/store/opensearch

3. Rode ``php admin/cli/upgrade.php``.
4. Habilite em *Administração do site > Plugins > Registros > Gerenciar log stores*. Mantenha o log padrão habilitado: os relatórios do Moodle leem dele.

O plugin não tem tabelas: os documentos esperam na fila do ``tool_opensearch``.
