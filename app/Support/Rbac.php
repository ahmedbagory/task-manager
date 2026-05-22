<?php

namespace App\Support;

final class Rbac
{
    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const DISPATCHER = 'dispatcher';

    public const SUPERVISOR = 'supervisor';

    public const EMPLOYEE = 'employee';

    public const ROLES = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::DISPATCHER,
        self::SUPERVISOR,
        self::EMPLOYEE,
    ];

    public const PERMISSIONS = [
        'dashboard.view',
        'inbox.view',
        'inbox.convert_to_task',
        'departments.view',
        'departments.manage',
        'task_categories.view',
        'task_categories.manage',
        'tasks.view',
        'tasks.create',
        'tasks.manage',
        'tasks.assign',
        'tasks.reassign',
        'tasks.update_status',
        'tasks.complete',
        'tasks.comment',
        'tasks.attachments.view',
        'whatsapp_messages.view',
        'whatsapp_contacts.view',
        'whatsapp_contacts.manage',
        'users.view',
        'users.manage',
        'roles.view',
        'roles.manage',
        'reports.view',
        'settings.api.manage',
        'mobile_notifications.send',
    ];

    public const ROLE_PERMISSIONS = [
        self::SUPER_ADMIN => self::PERMISSIONS,
        self::ADMIN => self::PERMISSIONS,
        self::DISPATCHER => [
            'dashboard.view',
            'inbox.view',
            'inbox.convert_to_task',
            'departments.view',
            'task_categories.view',
            'tasks.view',
            'tasks.create',
            'tasks.manage',
            'tasks.assign',
            'tasks.reassign',
            'tasks.comment',
            'tasks.attachments.view',
            'whatsapp_messages.view',
            'whatsapp_contacts.view',
            'whatsapp_contacts.manage',
            'reports.view',
            'mobile_notifications.send',
        ],
        self::SUPERVISOR => [
            'dashboard.view',
            'tasks.view',
            'tasks.assign',
            'tasks.reassign',
            'tasks.update_status',
            'tasks.complete',
            'tasks.comment',
            'tasks.attachments.view',
            'whatsapp_messages.view',
            'whatsapp_contacts.view',
            'reports.view',
        ],
        self::EMPLOYEE => [
            'dashboard.view',
            'tasks.view',
            'tasks.comment',
            'tasks.attachments.view',
        ],
    ];

    private function __construct() {}
}
