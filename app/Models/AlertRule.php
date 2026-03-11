<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Alert;

class AlertRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'metric',
        'condition',
        'threshold',
        'service_name',
        'duration',
        'channel',
        'email',
        'slack_webhook',
        'is_active',
        'last_triggered_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'threshold' => 'decimal:2',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Get the alerts for the rule.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
