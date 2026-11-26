Modos
=====

Modo standard
-------------

Cada evento vira um documento no data stream ``<prefixo>-logstore`` com as colunas do ``logstore_standard_log`` (os mesmos nomes, tipos e valores da tabela, menos ``id``):

.. list-table::
   :header-rows: 1

   * - Campos
     - Tipo no OpenSearch
   * - eventname, component, action, target, objecttable, crud, origin
     - keyword
   * - objectid, contextid, contextinstanceid, userid, courseid, relateduserid, realuserid
     - long
   * - edulevel, contextlevel, anonymous
     - integer
   * - other
     - keyword, não indexado (texto JSON)
   * - timecreated
     - long (Unix time)
   * - ip
     - ip
   * - @timestamp
     - date
   * - moodle.site, moodle.shortname
     - keyword
   * - other_flat
     - flat_object

* ``other`` é o texto JSON gravado pelo core (``logstore_standard`` em formato JSON, o padrão dele), não indexado, para que cada chave de cada evento não vire um campo do mapeamento. Com ``otherfields``, ele também é copiado para ``other_flat`` e pode ser pesquisado (``other_flat.username: ana``).
* O mapeamento é estrito: nenhum outro campo entra no data stream.
* ``@timestamp`` é o ``timecreated`` em ISO 8601; ``moodle.site`` e ``moodle.shortname`` identificam o site (vários sites podem dividir um cluster).

Exemplo: o que um usuário fez num curso, no OpenSearch Dashboards (DQL):

.. code-block:: text

   userid:42 and courseid:7 and crud:(c or u or d)

Modo xAPI
---------

Com ``mode_xapi`` ligado, um conjunto de eventos também vira um statement xAPI 1.0.3 no data stream ``<prefixo>-xapi`` (365 dias de retenção por padrão), para análise de aprendizagem. Os dois modos podem ficar ligados ao mesmo tempo; eventos sem mapeamento são ignorados neste modo.

.. list-table::
   :header-rows: 1

   * - Eventos
     - Verbo
     - Objeto
   * - curso visto, módulo visto (todo ``mod_*``)
     - viewed
     - curso, módulo
   * - conclusão de atividade atualizada (concluída ou aprovada), curso concluído
     - completed
     - módulo, curso
   * - usuário avaliado (nota escalonada, sucesso)
     - scored
     - módulo
   * - inscrição criada
     - registered
     - curso
   * - tentativa de questionário iniciada / enviada (nota, duração)
     - attempted / completed
     - assessment
   * - tarefa enviada, feedback enviado, lição terminada
     - submitted / completed
     - módulo
   * - discussão de fórum criada / post criado
     - created / replied
     - discussion
   * - escolha respondida
     - answered
     - módulo
   * - emblema concedido
     - earned
     - badge
   * - login / logout
     - logged-in / logged-out
     - site

* O ator é uma conta, como no ``core_xapi``: ``homePage`` é a URL do site e ``name`` o id do usuário (ou o pseudônimo, :doc:`privacy`). Nunca e-mail ou nome.
* O ``id`` do statement é um UUID v5 do evento: reenviar não duplica.
* O contexto leva o curso (``grouping``) e a atividade pai, ``platform`` "Moodle" e o idioma.
* ``object.definition.name_en`` (keyword) copia o nome em inglês do objeto, para agregações. ``moodle_event`` guarda o nome do evento, o contexto e os ids de curso (usados pelos pedidos de privacidade) e o nome do curso ``moodle_event.course``, presente em todo statement de um curso, para os painéis.
* Verbos e tipos de atividade seguem o registro da ADL, como o ``logstore_xapi`` (usado como referência).

Exemplo: quem concluiu um questionário num curso (DQL):

.. code-block:: text

   verb.id:"http://adlnet.gov/expapi/verbs/completed" and object.definition.type:"http://adlnet.gov/expapi/activities/assessment" and moodle_event.course:"Curso 1"

OpenSearch não é LRS: não há API de statements, assinaturas nem anulação (voiding). Ferramentas que exigem um LRS certificado devem usar o ``logstore_xapi``.

Painéis
-------

``examples/`` tem o painel *Moodle - learning (xAPI)*, em duas versões:

* ``examples/opensearch-dashboards/moodle-xapi.ndjson``: importe em *Dashboards Management > Saved objects > Import*. Ele cria o index pattern ``*-xapi*``.
* ``examples/grafana/dashboards/moodle-xapi.json``: importe no Grafana, com um datasource do plugin ``grafana-opensearch-datasource`` apontando para ``*-xapi*`` (campo de tempo ``@timestamp``).

Ele mostra statements e aprendizes ativos, engajamento por verbo no tempo, o funil e o desempenho (nota escalonada média) por atividade, a distribuição de notas, sucesso e cursos, com filtros de site (``moodle.shortname``), curso, aprendiz, verbo e tipo de atividade.
