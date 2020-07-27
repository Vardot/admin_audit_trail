# Admin Audit Trail

This module track logs of specific events that you'd like to review.
 The events performed by the users (using the forms) are saved in the
 database and can be viewed on the page admin/reports/audit-trail.
 You could use this to track the number of times the
 CUD (Create, Update & Delete) operations performed by specific users.

Currently, the following sub modules of Admin Audit Trail are supported:

* Menu (menu's and menu items CUD operations)
* Node (CUD operations)
* Comment (CUD operations)
* Taxonomy (vocabulary and term CUD operations)
* User (CUD operations)
* User authentication (login/logout/request password)
* File (CUD operations)
* Media (CUD operations)
* Workflows (CUD operations)

The event log tracking could be easily extended with custom events.
