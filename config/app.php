<?php

/**
 * StockForge - Application configuration.
 *
 * Returns a plain array that is merged into the global config store by
 * app/bootstrap.php and accessed with the config('key.subkey') helper.
 */

return [
    // ---------------------------------------------------------------------
    // Identity
    // ---------------------------------------------------------------------
    'name'     => 'StockForge',
    'tagline'  => 'Inventory Counting & Audit Platform',
    'company'  => 'STO',
    'version'  => '1.0.0',

    'timezone' => 'Asia/Jakarta',
    'locale'   => 'id',

    // Debug controls detailed error rendering. NEVER enable on production.
    'debug'    => true,

    // ---------------------------------------------------------------------
    // Session / security
    // ---------------------------------------------------------------------
    'session_name'  => 'stockforge_session',
    'csrf_token_key' => '_csrf',

    // ---------------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------------
    // Login is intentionally disabled for the current testing phase: the app
    // resolves the active user from the session "dev switch" instead.
    'auth' => [
        'login_enabled'  => true,
        'default_user'   => 'WE2015072', // username used when no session user
        'dev_switcher'   => false,        // false = login wajib, tidak ada auto-login
    ],

    // ---------------------------------------------------------------------
    // Roles & abilities
    // ---------------------------------------------------------------------
    'roles' => [
        'poweradmin' => 'Power Admin',
        'adminsto'   => 'Admin STO',
        'audit'      => 'Auditor',
        'observer'   => 'Observer',
    ],

    'role_descriptions' => [
        'poweradmin' => 'Mengelola seluruh user, role akses, group, lokasi, dan device.',
        'adminsto'   => 'Admin pengelola semua data STO, seluruh group, dan data yang masuk.',
        'audit'      => 'Melakukan pengecekan minimal 10% data per group yang didelegasikan.',
        'observer'   => 'Input dan menghitung semua data yang ada di lokasi.',
    ],

    // Map of ability => roles that own it.
    'abilities' => [
        'manage_users'        => ['poweradmin'],
        'manage_roles'        => ['poweradmin'],
        'manage_groups'       => ['poweradmin'],
        'manage_warehouses'   => ['poweradmin'],            // gudang / situs
        'manage_master'       => ['poweradmin'],            // sto, lokasi, device
        'manage_data'         => ['poweradmin', 'adminsto'],   // tambah/ubah/hapus data
        'view_all_groups'     => ['poweradmin', 'adminsto'],
        'count_data'          => ['poweradmin', 'adminsto', 'observer'],
        'post_data'           => ['poweradmin', 'adminsto', 'observer'],
        'audit_data'          => ['poweradmin', 'audit'],
        'manage_discrepancy'  => ['poweradmin', 'adminsto'],
        'search_discrepancy'  => ['observer'],
        'unlock_data'         => ['poweradmin', 'adminsto'],
        'import_data'         => ['poweradmin', 'adminsto'],
        'export_data'         => ['poweradmin', 'adminsto'],
        'view_activity'       => ['poweradmin', 'adminsto'],
    ],

    // Minimum audit coverage required per group (percentage).
    'audit' => [
        'min_percent'      => 10,
        'sample_tag'       => 'audit-sample',
    ],

    // ---------------------------------------------------------------------
    // Uploads / storage
    // ---------------------------------------------------------------------
    'upload' => [
        'max_size'  => 8 * 1024 * 1024, // 8 MB
        // Legacy binary .xls is not supported natively; re-save as .xlsx.
        'allowed'   => ['xlsx', 'csv']
    ],

    // Number of rows per page on listing screens.
    'per_page' => 15,
];
