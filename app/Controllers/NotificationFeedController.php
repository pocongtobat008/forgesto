<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;

/**
 * Endpoint JSON ringan untuk popup notifikasi header.
 * GET  /notifications/feed  -> daftar notifikasi + jumlah belum dibaca
 * POST /notifications/read-all -> tandai semua dibaca (JSON, dipakai popup)
 */
class NotificationFeedController extends Controller
{
    private const TYPES = ['info', 'success', 'warning', 'danger'];

    private const ICONS = [
        'info'    => 'info',
        'success' => 'check',
        'warning' => 'alert',
        'danger'  => 'alert',
    ];

    public function feed(): string
    {
        $limit = max(1, min(30, (int) ($_GET['limit'] ?? 12)));

        $model = new Notification();
        $rows  = $model->forActiveUser($limit, false);

        $items = [];
        foreach ($rows as $n) {
            $type = (string) $n['type'];
            if (! in_array($type, self::TYPES, true)) {
                $type = 'info';
            }

            $items[] = [
                'id'       => (int) $n['id'],
                'title'    => (string) $n['title'],
                'message'  => (string) ($n['message'] ?? ''),
                'type'     => $type,
                'icon'     => self::ICONS[$type] ?? 'info',
                'link'     => $n['link'] !== null && $n['link'] !== ''
                    ? url(ltrim((string) $n['link'], '/'))
                    : null,
                'is_read'  => (int) $n['is_read'] === 1,
                'group'    => $n['group_code'] ?? null,
                'time'     => self::relative((string) $n['created_at']),
                'time_raw' => (string) $n['created_at'],
            ];
        }

        return $this->json([
            'ok'     => true,
            'unread' => $model->unreadCount(),
            'total'  => count($items),
            'items'  => $items,
        ]);
    }

    public function readAllJson(): string
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (! csrf_valid((string) $token)) {
            return $this->json(['ok' => false, 'error' => 'CSRF token tidak valid.'], 419);
        }

        (new Notification())->markAllRead();

        return $this->json(['ok' => true, 'unread' => 0]);
    }

    /**
     * "3 menit lalu" style relative timestamp.
     */
    private static function relative(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '-';
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return 'baru saja';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . ' menit lalu';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . ' jam lalu';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . ' hari lalu';
        }

        return date('d M Y', $ts);
    }
}
