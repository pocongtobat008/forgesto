<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    private const TYPES = ['info', 'success', 'warning', 'danger'];

    public function index(): string
    {
        $unreadOnly = Request::boolean('unread');
        $type = Request::string('type', '');
        if (! in_array($type, self::TYPES, true)) {
            $type = '';
        }

        $model = new Notification();
        $all = $model->forActiveUser(100, false);

        $counts = ['total' => count($all), 'unread' => 0, 'info' => 0, 'success' => 0, 'warning' => 0, 'danger' => 0];
        foreach ($all as $n) {
            if ((int) $n['is_read'] === 0) {
                $counts['unread']++;
            }
            $t = (string) $n['type'];
            if (isset($counts[$t])) {
                $counts[$t]++;
            }
        }

        $list = array_values(array_filter($all, static function (array $n) use ($unreadOnly, $type): bool {
            if ($unreadOnly && (int) $n['is_read'] !== 0) {
                return false;
            }

            return $type === '' || $n['type'] === $type;
        }));

        $pager = $this->paginateRows($list, max(1, \App\Core\Request::int('page', 1)), list_per_page(20));

        return $this->view('notifications/index', [
            'title'         => __('Notifikasi'),
            'paginator'     => $pager,
            'notifications' => $pager['items'],
            'unreadOnly'    => $unreadOnly,
            'typeFilter'    => $type,
            'counts'        => $counts,
            'unread'        => $model->unreadCount(),
        ]);
    }

    public function read(array $params): string
    {
        $this->verifyCsrf();

        $notification = new Notification();
        if ($notification->find((int) $params['id']) === null) {
            $this->notFound('Notifikasi tidak ditemukan.');
        }

        $notification->markRead((int) $params['id']);

        return $this->redirectWith('/notifications', 'success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function readAll(): string
    {
        $this->verifyCsrf();

        (new Notification())->markAllRead();

        return $this->redirectWith('/notifications', 'success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Potong array menjadi satu halaman (format sama dengan Model::paginate).
     *
     * @param list<array> $rows
     * @return array<string, mixed>
     */
    protected function paginateRows(array $rows, int $page, int $perPage): array
    {
        $total    = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, $page), $lastPage);
        $offset   = ($page - 1) * $perPage;

        return [
            'items'     => array_slice($rows, $offset, $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
            'from'      => $total === 0 ? 0 : $offset + 1,
            'to'        => min($offset + $perPage, $total),
        ];
    }
}
/* I18N5-CTRL */
