<?php

namespace App\Services\Firewall;

use App\Contracts\FirewallInterface;
use Illuminate\Support\Facades\Process;

abstract class AbstractFirewallService implements FirewallInterface
{
    /**
     * Run a command with sudo.
     *
     * @param string $command The command to run
     * @return array{success: bool, output: string, error: string}
     */
    protected function runCommand(string $command): array
    {
        $result = Process::run($command);
        
        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
        ];
    }

    /**
     * Parse command output into array of lines.
     *
     * @param string $output The command output
     * @return array<int, string> Array of trimmed lines
     */
    protected function parseOutput(string $output): array
    {
        return array_filter(array_map('trim', explode("\n", $output)));
    }
}
