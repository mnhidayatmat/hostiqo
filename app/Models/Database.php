<?php

namespace App\Models;

use App\Services\Contracts\DatabaseServiceInterface;
use App\Services\DatabaseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Database extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'name',
        'username',
        'host',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the database service for this database instance.
     *
     * @return DatabaseServiceInterface
     */
    public function getService(): DatabaseServiceInterface
    {
        return app(DatabaseService::class)->connection($this->type ?? 'mysql');
    }

    /**
     * Get the human-readable database type label.
     *
     * @return string
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'postgresql' => 'PostgreSQL',
            default => 'MySQL',
        };
    }

    /**
     * Get the database type icon class.
     *
     * @return string
     */
    public function getTypeIcon(): string
    {
        return 'bi-database';
    }

    /**
     * Get the database type badge color.
     *
     * @return string
     */
    public function getTypeBadgeColor(): string
    {
        return match ($this->type) {
            'postgresql' => 'blue',
            default => 'orange',
        };
    }

    /**
     * Scope to filter by database type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
