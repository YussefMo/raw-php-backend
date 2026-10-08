<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

class Config
{
    private array $config;

    public function __construct(string $envPath)
    {
        if (!file_exists($envPath)) {
            throw new RuntimeException(
                "Environment file not found: {$envPath}"
            );
        }

        $config = parse_ini_file($envPath);

        if ($config === false) {
            throw new RuntimeException(
                'Failed to parse environment file.'
            );
        }

        $this->config = $config;
    }

    public function get(string $key): string
    {
        if (!array_key_exists($key, $this->config)) {
            throw new RuntimeException(
                "Environment variable '{$key}' is not defined."
            );
        }

        return (string) $this->config[$key];
    }
}
