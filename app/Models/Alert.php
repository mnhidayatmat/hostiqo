<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\AlertRule;

class Alert extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'alert_rule_id',
        'title',
        'message',
        'severity',
        'is_resolved',
        'resolved_at',
        'notification_sent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_resolved' => 'boolean',
        'notification_sent' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the alert rule that owns the alert.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function alertRule()
    {
        return $this->belongsTo(AlertRule::class);
    }
}
