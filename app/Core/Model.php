<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Base model with the small set of query helpers used across StockForge.
 * Child models set $table / $fillable.
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    /** @var list<string> */
    protected array $fillable = [];

    public function table(): string
    {
        return $this->table;
    }

    protected function db(): PDO
    {
        return Database::connection();
    }

    /**
     * Run a raw query and return all rows.
     */
    public function query(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /**
     * Run a raw query and return the first row (or null).
     */
    public function first(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Run a raw query and return the first column of the first row.
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    protected function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($this->normalise($bindings));

        return $statement;
    }

    /**
     * Cast booleans to ints and null-safe bindings so PDO emulation stays happy.
     */
    protected function normalise(array $bindings): array
    {
        return array_map(static function ($value) {
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }

            return $value;
        }, $bindings);
    }

    public function find(int|string $id): ?array
    {
        return $this->first(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1",
            [$id]
        );
    }

    public function findBy(string $column, mixed $value): ?array
    {
        return $this->first(
            "SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1",
            [$value]
        );
    }

    /**
     * @param array<string, mixed> $where
     */
    public function all(array $where = [], string $orderBy = '', int $limit = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $bindings = [];

        if ($where !== []) {
            $clauses = [];
            foreach ($where as $column => $value) {
                if ($value === null) {
                    $clauses[] = "{$column} IS NULL";
                    continue;
                }
                $clauses[] = "{$column} = ?";
                $bindings[] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return $this->query($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $data = $this->filterFillable($data);
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->run($sql, array_values($data));

        return (int) $this->db()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateById(int|string $id, array $data): int
    {
        $data = $this->filterFillable($data);
        if ($data === []) {
            return 0;
        }

        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = "{$column} = ?";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->table,
            implode(', ', $assignments),
            $this->primaryKey
        );

        $bindings = array_values($data);
        $bindings[] = $id;

        return $this->run($sql, $bindings)->rowCount();
    }

    public function deleteById(int|string $id): int
    {
        return $this->run(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        )->rowCount();
    }

    public function countWhere(string $where = '', array $bindings = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        return (int) $this->scalar($sql, $bindings);
    }

    /**
     * Paginate a raw query.
     *
     * @return array{items:list<array>,total:int,page:int,per_page:int,last_page:int,from:int,to:int}
     */
    public function paginate(string $sql, array $bindings = [], int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS __paginate_count';
        $total = (int) $this->scalar($countSql, $bindings);

        $items = $this->query($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $bindings);

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
            'from'      => $total === 0 ? 0 : $offset + 1,
            'to'        => min($offset + $perPage, $total),
        ];
    }

    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }
}
