<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Group;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * During the testing phase there is no login form: this screen lets a
     * tester impersonate any user so each role can be exercised.
     */
    public function index(): string
    {
        $userModel = new User();

        $users = $userModel->query(
            "SELECT u.*,
                    (SELECT GROUP_CONCAT(g.code ORDER BY g.code SEPARATOR ', ')
                       FROM user_groups ug JOIN `groups` g ON g.id = ug.group_id
                      WHERE ug.user_id = u.id) AS group_codes
             FROM users u
             ORDER BY FIELD(u.role, 'poweradmin', 'adminsto', 'audit', 'observer'), u.name"
        );

        $current = Auth::user();

        return $this->view('profile/index', [
            'title'   => __('Profil & Akses'),
            'users'   => $users,
            'current' => $current,
            'groups'  => $current !== null
                ? (new Group())->query(
                    'SELECT g.id, g.code, g.name
                     FROM `groups` g
                     JOIN user_groups ug ON ug.group_id = g.id
                     WHERE ug.user_id = ? ORDER BY g.name',
                    [(int) $current['id']]
                )
                : [],
            'roles'   => (array) config('app.roles', []),
            'abilities' => (array) config('app.abilities', []),
        ]);
    }

    public function switch(array $params): string
    {
        $this->verifyCsrf();

        if (! (bool) config('app.auth.dev_switcher', true)) {
            return $this->redirectWith('/profile', 'error', 'Pergantian user tidak diizinkan.');
        }

        $userId = Request::int('user_id');
        if (! Auth::switchUser($userId)) {
            return $this->redirectWith('/profile', 'error', 'User tidak ditemukan atau tidak aktif.');
        }

        $user = Auth::user();

        return $this->redirectWith('/dashboard', 'success', 'Sekarang aktif sebagai ' . ($user['name'] ?? '-') . ' (' . role_label($user['role'] ?? '') . ').');
    }
}
/* I18N5-CTRL */
