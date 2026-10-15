<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Models\Warehouse;

/**
 * Resolves the active user and answers permission / group-scope questions.
 *
 * Login is not implemented yet (testing phase): the active user comes from the
 * session, falling back to the configured default user so every screen can be
 * exercised. Switch the active user with Auth::switchUser().
 *
 * A user additionally has an ACTIVE WAREHOUSE (gudang/situs). Every data query
 * that is group-scoped is narrowed further to the groups of that warehouse, so
 * switching the warehouse in the header moves the whole application to that
 * site (workspace / warehouse-location behaviour).
 */
class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;
    private static bool $allGroups = false;

    /** @var list<int> */
    private static array $groupIds = [];

    private static bool $warehouseResolved = false;
    private static ?int $warehouseId = null;

    /** @var array<int, list<int>> warehouse id => group ids (memoised) */
    private static array $groupsByWarehouse = [];

    public static function user(): ?array
    {
        if (! self::$resolved) {
            self::resolve();
        }

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        $user = self::user();

        return $user['role'] ?? null;
    }

    public static function can(string $ability): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }

        $userId = self::id();
        if ($userId !== null && class_exists(\App\Models\UserRule::class)) {
            $mapped = \App\Models\UserRule::checkAbility($userId, $role, $ability);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        $abilities = (array) config('app.abilities', []);

        return in_array($role, $abilities[$ability] ?? [], true);
    }

    /** True when the user may see every group (poweradmin / adminsto). */
    public static function allGroups(): bool
    {
        if (! self::$resolved) {
            self::resolve();
        }

        return self::$allGroups;
    }

    /** @return list<int> */
    public static function groupIds(): array
    {
        if (! self::$resolved) {
            self::resolve();
        }

        return self::$groupIds;
    }

    public static function canSeeGroup(int $groupId): bool
    {
        return self::allGroups() || in_array($groupId, self::groupIds(), true);
    }

    /*
    |--------------------------------------------------------------------------
    | Warehouse (gudang / situs) scope
    |--------------------------------------------------------------------------
    */

    /**
     * The id of the currently active warehouse, or null for "all warehouses".
     * Resolved from the session, validated against what the user may see, and
     * defaulting to the first visible warehouse.
     */
    public static function warehouseId(): ?int
    {
        if (! self::$warehouseResolved) {
            self::resolveWarehouse();
        }

        return self::$warehouseId;
    }

    public static function hasWarehouse(): bool
    {
        return self::warehouseId() !== null;
    }

    /** The active warehouse row, or null when no single warehouse is active. */
    public static function activeWarehouse(): ?array
    {
        $id = self::warehouseId();
        if ($id === null) {
            return null;
        }

        return (new Warehouse())->find($id);
    }

    /**
     * Warehouse ids the active user is allowed to switch between.
     *
     * @return list<int>
     */
    public static function visibleWarehouseIds(): array
    {
        if (self::allGroups()) {
            $rows = (new Warehouse())->query('SELECT id FROM warehouses ORDER BY name');

            return array_map(static fn (array $r): int => (int) $r['id'], $rows);
        }

        $ids = self::groupIds();
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = (new Warehouse())->query(
            "SELECT DISTINCT warehouse_id FROM `groups`
              WHERE warehouse_id IS NOT NULL AND id IN ({$placeholders})
              ORDER BY warehouse_id",
            $ids
        );

        return array_map(static fn (array $r): int => (int) $r['warehouse_id'], $rows);
    }

    public static function canSeeWarehouse(int $warehouseId): bool
    {
        return in_array($warehouseId, self::visibleWarehouseIds(), true);
    }

    /**
     * Switch the active warehouse. Passing $all = true means "every warehouse"
     * and is only honoured for users who may see all groups.
     */
    public static function switchWarehouse(int $warehouseId, bool $all = false): bool
    {
        if ($all) {
            if (! self::allGroups()) {
                return false;
            }
            Session::put('warehouse_id', 'all');
            self::reset();

            return true;
        }

        $warehouse = (new Warehouse())->find($warehouseId);
        if ($warehouse === null || (int) $warehouse['is_active'] !== 1) {
            return false;
        }
        if (! self::canSeeWarehouse($warehouseId)) {
            return false;
        }

        Session::put('warehouse_id', $warehouseId);
        self::reset();

        return true;
    }

    /** @return list<int> group ids belonging to a warehouse (memoised) */
    public static function groupIdsForWarehouse(int $warehouseId): array
    {
        if (! isset(self::$groupsByWarehouse[$warehouseId])) {
            self::$groupsByWarehouse[$warehouseId] = (new Warehouse())->groupIds($warehouseId);
        }

        return self::$groupsByWarehouse[$warehouseId];
    }

    /**
     * Build a SQL fragment + bindings that restricts rows to the groups the
     * active user may access AND the active warehouse. An empty fragment means
     * "no restriction".
     *
     * @return array{0:string,1:list<int>}
     */
    public static function groupScope(string $column = 'group_id'): array
    {
        $warehouse = self::warehouseId();

        if (self::allGroups()) {
            if ($warehouse === null) {
                return ['', []];
            }
            $ids = self::groupIdsForWarehouse($warehouse);

            return self::inFragment($column, $ids);
        }

        $ids = self::groupIds();

        if ($warehouse !== null && $ids !== []) {
            $allowed = self::groupIdsForWarehouse($warehouse);
            $ids = array_values(array_intersect($ids, $allowed));
        }

        return self::inFragment($column, $ids);
    }

    /**
     * @param list<int> $ids
     * @return array{0:string,1:list<int>}
     */
    private static function inFragment(string $column, array $ids): array
    {
        if ($ids === []) {
            return ["{$column} IN (0)", []];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        return ["{$column} IN ({$placeholders})", $ids];
    }

    public static function switchUser(int $id): bool
    {
        $user = (new User())->find($id);

        if (! $user || (int) $user['is_active'] !== 1) {
            return false;
        }

        Session::put('user_id', (int) $user['id']);
        // A different user has a different visible set of warehouses.
        Session::forget('warehouse_id');
        self::reset();

        return true;
    }

    public static function reset(): void
    {
        self::$resolved = false;
        self::$user = null;
        self::$allGroups = false;
        self::$groupIds = [];

        self::$warehouseResolved = false;
        self::$warehouseId = null;
    }

    private static function resolve(): void
    {
        self::$resolved = true;
        $model = new User();

        $sessionId = Session::get('user_id');
        if ($sessionId) {
            $user = $model->find((int) $sessionId);
            if ($user && (int) $user['is_active'] === 1) {
                self::activate($user);

                return;
            }
        }

        if (config('app.auth.dev_switcher')) {
            $fallback = $model->findByUsername((string) config('app.auth.default_user'));
            if ($fallback) {
                Session::put('user_id', (int) $fallback['id']);
                self::activate($fallback);
            }
        }
    }

    private static function activate(array $user): void
    {
        self::$user = $user;
        $role = (string) ($user['role'] ?? '');

        self::$allGroups = in_array($role, ['poweradmin', 'adminsto'], true);
        self::$groupIds = self::$allGroups ? [] : (new User())->groupIds((int) $user['id']);
    }

    private static function resolveWarehouse(): void
    {
        self::$warehouseResolved = true;
        self::$warehouseId = null;

        if (self::user() === null) {
            return;
        }

        $visible = self::visibleWarehouseIds();
        $session = Session::get('warehouse_id');

        // Explicit "all warehouses" selection.
        if ($session === 'all' && self::allGroups()) {
            self::$warehouseId = null;

            return;
        }

        if ($session !== null && $session !== 'all' && in_array((int) $session, $visible, true)) {
            self::$warehouseId = (int) $session;

            return;
        }

        // Default to the first warehouse that actually contains groups, so the
        // app never opens on an empty site. Falls back to the first visible one.
        self::$warehouseId = self::defaultWarehouseId($visible);
    }

    /**
     * Pick a sensible default warehouse: the first visible warehouse that has
     * at least one group, else the first visible warehouse, else null.
     *
     * @param list<int> $visible
     */
    private static function defaultWarehouseId(array $visible): ?int
    {
        if ($visible === []) {
            return null;
        }

        $model = new Warehouse();
        foreach ($visible as $id) {
            $has = (int) $model->scalar('SELECT COUNT(*) FROM `groups` WHERE warehouse_id = ?', [$id]);
            if ($has > 0) {
                return $id;
            }
        }

        return $visible[0];
    }
}