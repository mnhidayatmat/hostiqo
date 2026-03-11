<?php

namespace App\Contracts;

interface FirewallInterface
{
    /**
     * Get firewall type name.
     *
     * @return string The firewall type (e.g., 'ufw', 'firewalld')
     */
    public function getType(): string;

    /**
     * Get firewall status.
     *
     * @return array{active: bool, status: string, output: string}
     */
    public function getStatus(): array;

    /**
     * Enable firewall.
     *
     * @return array{success: bool, message: string, output?: string, error?: string}
     */
    public function enable(): array;

    /**
     * Disable firewall.
     *
     * @return array{success: bool, message: string, output?: string, error?: string}
     */
    public function disable(): array;

    /**
     * Add a firewall rule.
     *
     * @param string $port The port number or range
     * @param string $protocol The protocol (tcp, udp)
     * @return array{success: bool, message: string, output?: string, error?: string}
     */
    public function addRule(string $port, string $protocol = 'tcp'): array;

    /**
     * Delete a firewall rule.
     *
     * @param string $port The port number or range
     * @param string $protocol The protocol (tcp, udp)
     * @return array{success: bool, message: string, output?: string, error?: string}
     */
    public function deleteRule(string $port, string $protocol = 'tcp'): array;

    /**
     * Reset firewall to default.
     *
     * @return array{success: bool, message: string, output?: string, error?: string}
     */
    public function reset(): array;

    /**
     * Get list of rules.
     *
     * @return array<int, array{number: int, rule: string}>
     */
    public function getRules(): array;
}
