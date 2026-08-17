<?php

return [

    'permissions' => [
        'organization:update',
        'organization:delete',
        'member:add',
        'member:update',
        'member:remove',
        'invitation:create',
        'invitation:cancel',
        'activity:view',
        'team:create',
        'team:update',
        'team:delete',
        'project:create',
        'project:update',
        'project:delete',
        'client:create',
        'client:update',
        'client:delete',
    ],

    'defaults' => [

        'owner' => '*',

        'admin' => [
            'organization:update',
            'member:add',
            'member:update',
            'member:remove',
            'invitation:create',
            'invitation:cancel',
            'activity:view',
            'team:create',
            'team:update',
            'project:create',
            'project:update',
            'client:create',
            'client:update',
        ],

        'member' => [],

        'client' => [],

    ],

];
