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

Planejado: statements xAPI 1.0.3 num data stream próprio (``<prefixo>-xapi``), para análise de aprendizagem. OpenSearch não é LRS: ferramentas que exigem um LRS certificado devem usar o ``logstore_xapi``.
