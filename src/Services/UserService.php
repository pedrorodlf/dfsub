<?php

namespace App\Services;

use PDO;

class UserService
{
    private DatabaseService $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
        $this->updateExpiredUsers();
    }

    public function updateExpiredUsers(): void
    {
        try {
            $sql = "UPDATE users SET status = 'Vencido' WHERE data_vencimento IS NOT NULL AND data_vencimento < CURDATE() AND status = 'Ativo'";
            $this->databaseService->getConnection()->exec($sql);
        } catch (\Throwable $e) {
            // Ignora falhas silenciosas na atualização automática
        }
    }

    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->databaseService->getConnection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->databaseService->getConnection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getUserByToken(string $token): ?array
    {
        $stmt = $this->databaseService->getConnection()->prepare('SELECT * FROM users WHERE qr_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public function getUserByMatricula(string $matricula): ?array
    {
        $stmt = $this->databaseService->getConnection()->prepare('SELECT * FROM users WHERE matricula = :matricula LIMIT 1');
        $stmt->execute(['matricula' => $matricula]);
        return $stmt->fetch() ?: null;
    }

    public function listAllUsers(): array
    {
        $stmt = $this->databaseService->getConnection()->query('SELECT * FROM users ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function createUser(array $data): int
    {
        $stmt = $this->databaseService->getConnection()->prepare(
            'INSERT INTO users (nome, data_nascimento, email, matricula, senha_hash, status, data_emissao, data_vencimento, qr_token, role, created_at, updated_at)
             VALUES (:nome, :data_nascimento, :email, :matricula, :senha_hash, :status, :data_emissao, :data_vencimento, :qr_token, :role, NOW(), NOW())'
        );

        $senhaHash = null;
        if (!empty($data['senha'])) {
            $senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        $stmt->execute([
            'nome' => $data['nome'],
            'data_nascimento' => $data['data_nascimento'],
            'email' => $data['email'],
            'matricula' => $data['matricula'],
            'senha_hash' => $senhaHash,
            'status' => $data['status'] ?? 'Aguardando verificação',
            'data_emissao' => empty($data['data_emissao']) ? null : $data['data_emissao'],
            'data_vencimento' => empty($data['data_vencimento']) ? null : $data['data_vencimento'],
            'qr_token' => $data['qr_token'] ?? $this->generateQrToken(),
            'role' => $data['role'] ?? 'associado',
        ]);

        return (int)$this->databaseService->getConnection()->lastInsertId();
    }

    public function updateUser(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['nome', 'data_nascimento', 'email', 'matricula', 'status', 'data_emissao', 'data_vencimento', 'role'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = sprintf('%s = :%s', $field, $field);
                $params[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }

        if (array_key_exists('senha', $data) && $data['senha'] !== '') {
            $fields[] = 'senha_hash = :senha_hash';
            $params['senha_hash'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $params['updated_at'] = date('Y-m-d H:i:s');
        $fields[] = 'updated_at = :updated_at';

        $sql = sprintf('UPDATE users SET %s WHERE id = :id', implode(', ', $fields));
        $stmt = $this->databaseService->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteUser(int $id): bool
    {
        $stmt = $this->databaseService->getConnection()->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function verifyPassword(string $password, ?string $hash): bool
    {
        if (empty($hash)) {
            return false;
        }
        return password_verify($password, $hash);
    }

    public function generateQrToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
