Privacy
=======

With the defaults, OpenSearch keeps the same personal data as the standard log, in another system: user ids, IP address, related user, real user (when logged in as) and the ``other`` field of each event.

Options
-------

* ``includeip`` off: the IP address is not written. Recommended when nobody uses it (data minimisation, LGPD art. 6, III).
* ``pseudonymize`` on: ``userid``, ``relateduserid``, ``realuserid`` and the ``objectid`` of user objects become a keyed hash (HMAC-SHA256 with the secret, cut to 60 bits so that they stay numbers). The same user always has the same pseudonym, so the activity of a person can still be followed, but not named without the secret. Changing the secret changes every pseudonym: older documents can no longer be found for a user.
* ``otherdenylist``: keys removed from ``other``, for example ``username`` (kept by ``user_login_failed``).

Requests of the users
---------------------

The plugin implements the Moodle privacy API:

* **Export**: the documents of the user (as author, related user or real user) in the requested contexts, in the same format as the standard log, and the xAPI statements of which the user is the actor (subfolder ``xAPI``).
* **Deletion**: delete by query in OpenSearch, in both streams, by context, by user or by list of users. With pseudonyms, the user id and its pseudonym (current secret) are searched.
* Requests reach the cluster whenever ``tool_opensearch`` has hosts, even with sending disabled. If the cluster cannot answer, the request fails and Moodle tries it again later: it is never marked as done without touching the data.

The retention of the ``logstore`` (180 days by default) and ``xapi`` (365 days) streams, in ``tool_opensearch``, deletes old documents automatically.

In the xAPI statements the actor is the user id (or its pseudonym): no name, email or IP address is written.
