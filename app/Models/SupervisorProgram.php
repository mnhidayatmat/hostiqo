<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorProgram extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'command',
        'directory',
        'numprocs',
        'user',
        'autostart',
        'autorestart',
        'startsecs',
        'stopwaitsecs',
        'stdout_logfile',
        'stdout_logfile_maxbytes',
        'stdout_logfile_backups',
        'redirect_stderr',
        'stopasgroup',
        'killasgroup',
        'environment',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'autostart' => 'boolean',
        'autorestart' => 'boolean',
        'is_active' => 'boolean',
        'environment' => 'array',
        'numprocs' => 'integer',
        'startsecs' => 'integer',
        'stopwaitsecs' => 'integer',
        'stdout_logfile_maxbytes' => 'integer',
        'stdout_logfile_backups' => 'integer',
        'stopasgroup' => 'integer',
        'killasgroup' => 'integer',
    ];

    /**
     * Get the supervisor config file name.
     *
     * @return string The config file name
     */
    public function getConfigFileName(): string
    {
        return $this->name . '.conf';
    }

    /**
     * Get the supervisor config file path.
     *
     * @return string The config file path
     */
    public function getConfigFilePath(): string
    {
        return '/etc/supervisor/conf.d/' . $this->getConfigFileName();
    }

    /**
     * Get the log file path.
     *
     * @return string The log file path
     */
    public function getLogFilePath(): string
    {
        return $this->stdout_logfile ?? "/var/log/supervisor/{$this->name}.log";
    }
}
