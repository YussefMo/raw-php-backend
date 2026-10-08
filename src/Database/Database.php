<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private ?PDO $conn = null;

    public function __construct(
        private Config $config
    ) {
    }

    public function connect(): PDO
    {
        $dbHost = $this->config->get('DB_HOST');
        $dbPort = $this->config->get('DB_PORT');
        $dbName = $this->config->get('DB_DATABASE');
        $dbUser = $this->config->get('DB_USERNAME');
        $dbPassword = $this->config->get('DB_PASSWORD');

        try {
            $this->conn = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};port={$dbPort}",
                $dbUser,
                $dbPassword
            );

            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            return $this->conn;
        } catch (PDOException $err) {
            throw new RuntimeException(
                "Something went wrong: {$err->getMessage()}"
            );
        }
    }
}
