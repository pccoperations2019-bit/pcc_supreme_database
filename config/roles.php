<?php

return [

    'roles' => [
        'agency-admin' => [
            'manage-agency',
            'manage-center',
            'view-center',
            'manage-users',
            'import-data',
            'view-farmers',
            'manage-farmers',
            'view-users',
            'create-users',
            'update-users',
            'deactivate-users',
            'approve-users',
            'assign-roles',
            'enter-data',
            'submit-documents',
            'review-documents',
            'view-reports',
            'export-data',
            'view-audits',
            'manage-pl480',
            'manage-epahp',
            'view-monthly-tasks',
            'update-monthly-tasks',
            'generate-monthly-tasks',
            'view-monthly-task-reports',
            'review-monthly-tasks',
        ],

        'executive' => [
            'view-center',
            'view-farmers',
            'view-reports',
            'export-data',
            'view-audits',
            'view-monthly-tasks',
            'view-monthly-task-reports',
        ],

        'center-admin' => [
            'manage-center',
            'view-center',
            'view-farmers',
            'manage-farmers',
            'view-users',
            'create-users',
            'update-users',
            'deactivate-users',
            'enter-data',
            'submit-documents',
            'view-reports',
            'export-own-center',
            'view-audits',
            'view-monthly-tasks',
            'update-monthly-tasks',
        ],

        'encoder' => [
            'view-center',
            'view-farmers',
            'enter-data',
            'submit-documents',
            'view-reports',
            'export-own-center',
            'view-monthly-tasks',
            'update-monthly-tasks',
        ],

        'reviewer' => [
            'view-center',
            'view-farmers',
            'review-documents',
            'view-reports',
            'view-audits',
            'view-monthly-tasks',
        ],
    ],

    'permissions' => [
        'manage-agency',
        'manage-center',
        'view-center',
        'manage-users',
        'import-data',
        'view-farmers',
        'manage-farmers',

        'view-users',
        'create-users',
        'update-users',
        'deactivate-users',
        'approve-users',
        'assign-roles',

        'enter-data',
        'submit-documents',
        'review-documents',

        'view-reports',
        'export-data',

        'view-audits',

        'manage-pl480',
        'manage-epahp',

        'view-monthly-tasks',
        'update-monthly-tasks',
        'generate-monthly-tasks',
        'view-monthly-task-reports',
        'review-monthly-tasks',
        'export-own-center',
    ],

];
