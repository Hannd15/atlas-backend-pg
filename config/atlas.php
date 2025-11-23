<?php

return [
    'permissions' => [
        'assignable_to_pg_group' => env('ATLAS_PERMISSION_ASSIGNABLE_TO_PG_GROUP', 'asignable a un grupo de proyectos de grado'),
        'assignable_to_pg_staff' => env('ATLAS_PERMISSION_ASSIGNABLE_TO_PG_STAFF', 'asignable a staff de proyectos de grado'),
        'pg_committee_member' => env('ATLAS_PERMISSION_PG_COMMITTEE_MEMBER', 'parte del comité de proyectos de grado'),
        'view_proposals' => env('ATLAS_PERMISSION_VIEW_PROPOSALS', 'ver propuestas'),
        'create_projects' => env('ATLAS_PERMISSION_CREATE_PROJECTS', 'crear proyectos'),
        'edit_projects' => env('ATLAS_PERMISSION_EDIT_PROJECTS', 'editar proyectos'),
        'delete_projects' => env('ATLAS_PERMISSION_DELETE_PROJECTS', 'eliminar proyectos'),
        'view_project_staff' => env('ATLAS_PERMISSION_VIEW_PROJECT_STAFF', 'ver personal de proyectos'),
        'create_proposals' => env('ATLAS_PERMISSION_CREATE_PROPOSALS', 'crear propuestas'),
        'edit_proposals' => env('ATLAS_PERMISSION_EDIT_PROPOSALS', 'editar propuestas'),
        'delete_proposals' => env('ATLAS_PERMISSION_DELETE_PROPOSALS', 'eliminar propuestas'),
        'view_project_statuses' => env('ATLAS_PERMISSION_VIEW_PROJECT_STATUSES', 'ver estados de proyectos'),
        'change_project_status' => env('ATLAS_PERMISSION_CHANGE_PROJECT_STATUS', 'cambiar estado de proyectos'),
    ],
];
