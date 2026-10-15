<?php

declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        protected int $statusCode,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function defaultMessage(int $code): string
    {
        return match ($code) {
            403 => 'Anda tidak memiliki akses untuk halaman ini.',
            404 => 'Halaman yang Anda cari tidak ditemukan.',
            419 => 'Sesi telah berakhir. Silakan muat ulang halaman.',
            422 => 'Data yang dikirim tidak valid.',
            500 => 'Terjadi kesalahan pada server.',
            default => 'Permintaan tidak dapat diproses.',
        };
    }
}
