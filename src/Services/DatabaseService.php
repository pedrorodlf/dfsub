<?php

namespace App\Services;

use PDO;
use PDOException;

class DatabaseService
{
    private ConfigService $configService;
    private ?PDO $connection = null;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->configService->getDbHost(),
            $this->configService->getDbPort(),
            $this->configService->getDbName()
        );

        try {
            $this->connection = new PDO($dsn, $this->configService->getDbUser(), $this->configService->getDbPassword(), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Não foi possível conectar ao banco de dados: ' . $exception->getMessage());
        }

        return $this->connection;
    }
}
