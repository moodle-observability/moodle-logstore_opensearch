Privacidade
===========

Com os padrões, o OpenSearch guarda os mesmos dados pessoais do log padrão, em outro sistema: ids de usuário, endereço IP, usuário relacionado, usuário real (quando "acessar como") e o campo ``other`` de cada evento.

Opções
------

* ``includeip`` desligado: o endereço IP não é gravado. Recomendado quando ninguém usa (minimização, LGPD art. 6º, III).
* ``pseudonymize`` ligado: ``userid``, ``relateduserid``, ``realuserid`` e o ``objectid`` de objetos de usuário viram um hash com chave (HMAC-SHA256 com o segredo, cortado em 60 bits para continuar número). O mesmo usuário tem sempre o mesmo pseudônimo: a atividade de uma pessoa continua sendo acompanhada, mas não identificada sem o segredo. Trocar o segredo troca todos os pseudônimos: documentos antigos deixam de ser encontrados para um usuário.
* ``otherdenylist``: chaves removidas de ``other``, por exemplo ``username`` (guardado pelo ``user_login_failed``).

Pedidos dos titulares
---------------------

O plugin implementa a API de privacidade do Moodle:

* **Exportação**: os documentos do usuário (como autor, usuário relacionado ou usuário real) nos contextos pedidos, no mesmo formato do log padrão.
* **Exclusão**: delete by query no OpenSearch, por contexto, por usuário ou por lista de usuários. Com pseudônimos, são buscados o id e o pseudônimo (segredo atual).
* Os pedidos chegam ao cluster sempre que o ``tool_opensearch`` tem hosts, mesmo com o envio desligado. Se o cluster não responder, o pedido falha e o Moodle tenta de novo depois: nunca é concluído sem tocar nos dados.

A retenção do stream ``logstore`` (180 dias por padrão, no ``tool_opensearch``) apaga os documentos antigos automaticamente.
