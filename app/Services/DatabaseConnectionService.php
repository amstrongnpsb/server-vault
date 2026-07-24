<?php

namespace App\Services;

use App\Exceptions\DatabaseConnectionException;
use App\Models\ServerDatabase;
use PDO;

class DatabaseConnectionService
{
    public function connect(ServerDatabase $database): PDO
    {
        $host = $database->server->host;
        $port = $database->port;
        $username = $database->username;
        $password = $database->decrypted_credentials;
        $dbname = $database->name;

        if (empty($password)) {
            throw new DatabaseConnectionException('No credentials configured for this database.');
        }

        return match ($database->type) {
            'MySQL' => $this->connectMysql($host, $port, $username, $password, $dbname),
            'PostgreSQL' => $this->connectPostgres($host, $port, $username, $password, $dbname),
            default => throw new DatabaseConnectionException("Unsupported database type: {$database->type}"),
        };
    }

    private function connectMysql(string $host, ?int $port, string $username, string $password, ?string $dbname): PDO
    {
        $dsn = 'mysql:host=' . $host . ';port=' . ($port ?? 3306) . ';charset=utf8mb4';
        if ($dbname) {
            $dsn .= ';dbname=' . $dbname;
        }

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30,
        ]);
    }

    private function connectPostgres(string $host, ?int $port, string $username, string $password, ?string $dbname): PDO
    {
        $dsn = 'pgsql:host=' . $host . ';port=' . ($port ?? 5432) . ';sslmode=prefer';
        if ($dbname) {
            $dsn .= ';dbname=' . $dbname;
        }

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30,
        ]);
    }
}
