<?php

return [

    /*
    | Role lists are the default grant for each capability (Gate names).
    */

    'users.manage' => ['super_admin', 'admin'],
    'portal.access' => ['client'],
    'staff.access' => ['super_admin', 'admin', 'user'],
    'site.edit' => ['super_admin', 'admin'],
    'connectors.manage' => ['super_admin', 'admin'],

];
