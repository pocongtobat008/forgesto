<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\InfoMessage;
use App\Models\Notification;

/**
 * Menu Informasi: admin menulis informasi penting, sistem mengirimkannya
 * sebagai notifikasi (bell) ke audiens yang dipilih.
 */
class InfoController extends Controller
{
    private const TYPES = ['info', 'success', 'warning', 'danger'];
    private const ROLES = ['poweradmin', 'adminsto', 'audit', 'observer'];

    private function guard(): void
    {
        if (! in_array(Auth::role(), ['poweradmin', 'adminsto'], true)) {
            $this->abort(403, 'Hanya admin yang boleh mengelola Informasi.');
        }
    }

    public function index(): string
    {
        $model = new InfoMessage();

        return $this->view('info/index', [
            'title'     => __('Informasi'),
            'rows'      => $model->listAll(),
            'summary'   => $model->summary(),
            'types'     => self::TYPES,
            'roles'     => self::ROLES,
            'canManage' => $this->canManage(),
        ]);
    }

    private function canManage(): bool
    {
        return in_array(Auth::role(), ['poweradmin', 'adminsto'], true);
    }

    public function create(): string
    {
        $this->guard();

        return $this->view('info/form', [
            'title' => __('Tulis Informasi'),
            'row'   => null,
            'types' => self::TYPES,
            'roles' => self::ROLES,
        ]);
    }

    public function store(): string
    {
        $this->guard();
        $this->verifyCsrf();

        $data = $this->payload();
        if ($data === null) {
            return $this->redirectWith('/informasi/create', 'danger', 'Judul wajib diisi (maks 150 karakter).');
        }

        $data['created_by'] = (int) Auth::id();
        $id = (new InfoMessage())->insert($data);
        $row = (new InfoMessage())->find($id);

        if ($row !== null) {
            $this->notify($row);
            (new ActivityLog())->record('create', 'info', $id, 'Menulis informasi: ' . $data['title']);
        }

        return $this->redirectWith('/informasi', 'success', 'Informasi tersimpan dan notifikasi sudah dikirim.');
    }

    public function edit(array $params): string
    {
        $this->guard();
        $row = (new InfoMessage())->find((int) $params['id']);
        if ($row === null) {
            $this->notFound('Informasi tidak ditemukan.');
        }

        return $this->view('info/form', [
            'title' => __('Edit Informasi'),
            'row'   => $row,
            'types' => self::TYPES,
            'roles' => self::ROLES,
        ]);
    }

    public function update(array $params): string
    {
        $this->guard();
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $model = new InfoMessage();
        if ($model->find($id) === null) {
            $this->notFound('Informasi tidak ditemukan.');
        }

        $data = $this->payload();
        if ($data === null) {
            return $this->redirectWith('/informasi/edit/' . $id, 'danger', 'Judul wajib diisi (maks 150 karakter).');
        }

        $model->updateById($id, $data);
        (new ActivityLog())->record('update', 'info', $id, 'Mengubah informasi: ' . $data['title']);

        return $this->redirectWith('/informasi', 'success', 'Informasi diperbarui.');
    }

    /** Kirim ulang notifikasi untuk satu informasi. */
    public function resend(array $params): string
    {
        $this->guard();
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $row = (new InfoMessage())->find($id);
        if ($row === null) {
            $this->notFound('Informasi tidak ditemukan.');
        }

        $this->notify($row);
        (new ActivityLog())->record('update', 'info', $id, 'Kirim ulang notifikasi: ' . (string) $row['title']);

        return $this->redirectWith('/informasi', 'success', 'Notifikasi dikirim ulang.');
    }

    public function destroy(array $params): string
    {
        $this->guard();
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $model = new InfoMessage();
        $row = $model->find($id);
        if ($row === null) {
            $this->notFound('Informasi tidak ditemukan.');
        }

        $model->deleteById($id);
        (new ActivityLog())->record('delete', 'info', $id, 'Menghapus informasi: ' . (string) $row['title']);

        return $this->redirectWith('/informasi', 'success', 'Informasi dihapus.');
    }

    /** @return array<string,mixed>|null */
    private function payload(): ?array
    {
        $title = trim(Request::string('title'));
        if ($title === '' || mb_strlen($title) > 150) {
            return null;
        }

        $type = Request::string('type');
        if (! in_array($type, self::TYPES, true)) {
            $type = 'info';
        }

        $role = Request::string('audience_role');
        if (! in_array($role, self::ROLES, true)) {
            $role = null;
        }

        $body = trim(Request::string('body'));
        if (mb_strlen($body) > 2000) {
            $body = mb_substr($body, 0, 2000);
        }

        return [
            'title'         => $title,
            'body'          => $body !== '' ? $body : null,
            'audience_role' => $role,
            'type'          => $type,
            'is_active'     => Request::has('is_active') ? 1 : 0,
        ];
    }

    /** Dorong ke tabel notifications sesuai audiens. */
    private function notify(array $row): void
    {
        $title   = (string) $row['title'];
        $body    = trim((string) ($row['body'] ?? ''));
        $message = $body !== '' ? mb_substr($body, 0, 480) : 'Informasi baru dari admin.';
        $type    = (string) $row['type'];
        $role    = (string) ($row['audience_role'] ?? '');

        $notif = new Notification();
        if ($role !== '' && in_array($role, self::ROLES, true)) {
            $notif->toRole($role, $title, $message, $type, 'informasi');
        } else {
            $notif->broadcast($title, $message, $type, 'informasi');
        }
    }
}
