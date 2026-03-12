<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * BaseModel
 * Base model for all Eloquent models in the application
 * Extends Illuminate\Database\Eloquent\Model
 */
class BaseModel extends EloquentModel
{
    /**
     * Indicates if the model should be timestamped.
     * @var bool
     */
    public $timestamps = true;

    /**
     * The storage format of the model's date columns.
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * The attributes that should be cast to native types.
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all records with optional filtering
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAll(array $filters = [])
    {
        $query = static::query();

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->get();
    }

    /**
     * Find a record by ID
     * @param int|string $id
     * @return static|null
     */
    public static function findById($id)
    {
        return static::find($id);
    }

    /**
     * Create a new record
     * @param array $data
     * @return static
     */
    public static function createRecord(array $data)
    {
        return static::create($data);
    }

    /**
     * Update a record by ID
     * @param int|string $id
     * @param array $data
     * @return bool
     */
    public static function updateRecord($id, array $data): bool
    {
        $record = static::find($id);
        if (!$record) {
            return false;
        }
        return $record->update($data);
    }

    /**
     * Delete a record by ID
     * @param int|string $id
     * @return bool
     */
    public static function deleteRecord($id): bool
    {
        $record = static::find($id);
        if (!$record) {
            return false;
        }
        return $record->delete();
    }

    /**
     * Paginate results
     * @param int $perPage
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function paginateRecords(int $perPage = 15, array $filters = [])
    {
        $query = static::query();

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->paginate($perPage);
    }
}
