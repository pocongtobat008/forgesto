<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\Group;

class ActivityController extends Controller
{
    /** Nilai distinct untuk popup filter kolom (JSON). */
    public function distinct(array $params = []): never
    {
        $col = \App\Models\ActivityLog::normaliseColFilters($_GET['col'] ?? null, \App\Models\ActivityLog::COL_MAP);
        $key = (string) ($_GET['key'] ?? '');
        [$scope, $sb] = Auth::groupScope('a.group_id');
        $vals = (new \App\Models\ActivityLog())->distinctColValues($key, $col, \App\Models\ActivityLog::COL_MAP, 'FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN `groups` g ON g.id = a.group_id', $scope, $sb);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['values' => array_map('strval', $vals)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function index(): string
    {
        $this->authorize('view_activity');

        $filters = [
            'group_id' => Request::has('group_id') && Request::string('group_id') !== '' ? Request::int('group_id') : null,
            'action'   => Request::string('action'),
            'search'   => Request::string('search'),
        ];
        $filters['col'] = ActivityLog::normaliseColFilters($_GET['col'] ?? null, ActivityLog::COL_MAP);
        $filters['adv'] = \App\Support\AdvancedSearch::normalise($_GET['adv'] ?? null, ActivityLog::COL_MAP);
        $filters['text'] = is_array($_GET['text'] ?? null) ? array_map(static fn ($v) => trim((string) $v), $_GET['text']) : [];
        $filters['date'] = Request::string('date');
        $page = max(1, Request::int('page', 1));

        return $this->view('activity/index', [
            'title'   => __('Aktivitas'),
            'logs'    => (new ActivityLog())->listDetailed($filters, $page, 25),
            'filters' => $filters,
            'advCols' => ['action' => 'Aksi', 'group' => 'Group', 'user' => 'User'],
            'advNumeric' => [],
            'advMax' => \App\Support\AdvancedSearch::MAX_CONDITIONS,
            'groups'  => (new Group())->forActiveUser(),
            'actions' => ['create', 'update', 'delete', 'count', 'post', 'unlock', 'audit', 'resolve', 'search', 'import', 'login'],
        ]);
    }
}
/* I18N5-CTRL */
