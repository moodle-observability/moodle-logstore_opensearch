Painéis
=======

O ``tool_opensearch`` traz os painéis deste data stream na pasta ``examples/``: **Moodle - log query** e **Moodle - log overview** para o OpenSearch Dashboards, e **Moodle - log (tool_opensearch)** para o Grafana. Eles leem ``*-logstore*`` e filtram pelos mesmos critérios do relatório de logs do Moodle (site, curso, usuário, período, atividade, ações, origem e evento). Veja a `página Painéis do tool_opensearch <https://moodle-observability.github.io/moodle-tool_opensearch/pt_BR/dashboards.html>`_.

Eles foram conferidos com os documentos deste log store: os nomes dos campos são as colunas do log padrão, como os painéis esperam.
