<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Sto extends Model
{
    protected string $table = 'sto';

    protected array $fillable = ['code', 'name', 'address', 'is_active'];

    public function options(): array
    {
        return $this->all([], 'name ASC');
    }

    /**
     * STO rows with the number of groups inside each.
     */
    public function withGroupCounts(): array
    {
        return $this->query(
            'SELECT s.*, COUNT(g.id) AS group_count
             FROM sto s
             LEFT JOIN `groups` g ON g.sto_id = s.id
             GROUP BY s.id
             ORDER BY s.name'
        );
    }
}
