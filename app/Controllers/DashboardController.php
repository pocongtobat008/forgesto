<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Discrepancy;
use App\Models\Group;
use App\Models\ImportLog;
use App\Models\InfoMessage;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(): string
    {
        $currentPeriod = \App\Models\BookPeriod::current();
        $isReadOnly = $currentPeriod !== null && (($currentPeriod['status'] ?? 'open') === 'closed' || ! empty($currentPeriod['is_archive_view']));

        $itemModel = new Item();
        $filters = $itemModel->normaliseFilters($_GET);

        if ($isReadOnly && $currentPeriod !== null) {
            $periodId = (int) $currentPeriod['id'];
            $summary  = $itemModel->summarySnapshot($periodId, $filters);
            $progress = $itemModel->groupProgressSnapshot($periodId, $filters);
        } else {
            $summary = $itemModel->summary($filters);
            $progress = $itemModel->groupProgress($filters);
        }
        $groups = (new Group())->forActiveUser();
        $trend = $itemModel->activityTrend(30);

        $chart = [
            'labels'      => array_map(static fn (array $row): string => (string) $row['name'], $progress),
            'counted'     => array_map(static fn (array $row): int => (int) $row['counted'], $progress),
            'not_counted' => array_map(static fn (array $row): int => (int) $row['not_counted'], $progress),
            'audited'     => array_map(static fn (array $row): int => (int) $row['audited'], $progress),
            'not_audited' => array_map(static fn (array $row): int => (int) $row['not_audited'], $progress),
        ];

        return $this->view('dashboard/index', [
            'title'            => __('Dashboard'),
            'summary'          => $summary,
            'progress'         => $progress,
            'chart'            => $chart,
            'trend'            => $trend,
            'filters'          => $filters,
            'groups'           => $groups,
            'selectedGroup'    => $filters['group_id'],
            'discrepancyCount' => (new Discrepancy())->openCount(),
            'recentActivity'   => (new ActivityLog())->recent(6),
            'recentImports'    => (new ImportLog())->recent(4),
            'infos'            => (new InfoMessage())->activeForUser(\App\Core\Auth::role(), 3),
            'totals'           => [
                'groups'    => count($progress),
                'users'     => (new User())->countWhere('is_active = 1'),
                'locations' => (new Location())->countScoped(),
                'devices'   => (new Device())->countScoped(),
            ],
        ]);
    }
}
/* I18N5-CTRL */
