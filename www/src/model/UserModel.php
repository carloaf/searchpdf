<?php

namespace Model;

/**
 * Model para gerenciamento de usuários e autenticação
 * 
 * @author Augusto <carloafernandes@gmail.com>
 */
class UserModel
{
    private static function getConnection()
    {
        $dbHost = getenv('DB_HOST') ?: 'db';
        $dbName = getenv('DB_NAME') ?: 'searchpdf_db';
        $dbUser = getenv('DB_USER') ?: 'searchpdf_user';
        $dbPass = getenv('DB_PASS') ?: 'user_password';
        
        $pdo = new \PDO(
            "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        return $pdo;
    }
    
    /**
     * Autentica um usuário
     * 
     * @param string $username
     * @param string $password
     * @return array|false Retorna dados do usuário ou false
     */
    public static function authenticate($username, $password)
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, full_name, email, role, active
                FROM users 
                WHERE username = ? AND active = TRUE
            ");
            
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                error_log("Usuário não encontrado ou inativo: $username");
                return false;
            }
            
            // Verifica a senha
            if (!password_verify($password, $user['password_hash'])) {
                error_log("Senha incorreta para usuário: $username");
                return false;
            }
            
            // Atualiza último login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Remove a senha do retorno
            unset($user['password_hash']);
            
            return $user;
            
        } catch (\PDOException $e) {
            error_log("Erro na autenticação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se o usuário tem permissão para upload
     * 
     * @param string $role
     * @return bool
     */
    public static function canUpload($role)
    {
        return in_array($role, ['admin', 'uploader']);
    }
    
    /**
     * Verifica se o usuário é admin
     * 
     * @param string $role
     * @return bool
     */
    public static function isAdmin($role)
    {
        return $role === 'admin';
    }
    
    /**
     * Busca usuário por ID
     * 
     * @param int $userId
     * @return array|false
     */
    public static function getUserById($userId)
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare("
                SELECT id, username, full_name, email, role, active, created_at, last_login
                FROM users 
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetch();
            
        } catch (\PDOException $e) {
            error_log("Erro ao buscar usuário: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cria novo usuário
     * 
     * @param array $data
     * @return int|false ID do usuário criado ou false
     */
    public static function createUser($data)
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password_hash, full_name, email, role, active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt->execute([
                $data['username'],
                $passwordHash,
                $data['full_name'],
                $data['email'],
                $data['role'] ?? 'viewer',
                $data['active'] ?? true
            ]);
            
            return $pdo->lastInsertId();
            
        } catch (\PDOException $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lista todos os usuários
     * 
     * @return array
     */
    public static function listUsers()
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->query("
                SELECT id, username, full_name, email, role, active, created_at, last_login
                FROM users 
                ORDER BY created_at DESC
            ");
            
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            error_log("Erro ao listar usuários: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registra log de upload
     * 
     * @param array $data
     * @return bool
     */
    public static function logUpload($data)
    {
        try {
            $pdo = self::getConnection();
            
            $stmt = $pdo->prepare("
                INSERT INTO upload_log 
                (user_id, filename, original_filename, file_path, file_size, year, month, status, error_message, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $data['user_id'],
                $data['filename'],
                $data['original_filename'],
                $data['file_path'],
                $data['file_size'],
                $data['year'],
                $data['month'],
                $data['status'] ?? 'success',
                $data['error_message'] ?? null,
                $data['ip_address'] ?? null
            ]);
            
        } catch (\PDOException $e) {
            error_log("Erro ao registrar log de upload: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca histórico de uploads
     * 
     * @param int|null $userId
     * @param int $limit
     * @return array
     */
    public static function getUploadHistory($userId = null, $limit = 50)
    {
        try {
            $pdo = self::getConnection();
            
            $sql = "
                SELECT ul.*, u.username, u.full_name
                FROM upload_log ul
                JOIN users u ON ul.user_id = u.id
            ";
            
            if ($userId) {
                $sql .= " WHERE ul.user_id = ?";
            }
            
            $sql .= " ORDER BY ul.upload_date DESC LIMIT ?";
            
            $stmt = $pdo->prepare($sql);
            
            if ($userId) {
                $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
                $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (\PDOException $e) {
            error_log("Erro ao buscar histórico de uploads: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca informações de um upload específico por ID
     * 
     * @param int $uploadId
     * @return array|null
     */
    public static function getUploadById($uploadId)
    {
        try {
            $pdo = self::getConnection();
            
            $sql = "SELECT * FROM upload_log WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$uploadId]);
            
            return $stmt->fetch() ?: null;
            
        } catch (\PDOException $e) {
            error_log("Erro ao buscar upload por ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Deleta registro de upload do banco de dados
     * 
     * @param int $uploadId
     * @return bool
     */
    public static function deleteUploadRecord($uploadId)
    {
        try {
            $pdo = self::getConnection();
            
            $sql = "DELETE FROM upload_log WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            return $stmt->execute([$uploadId]);
            
        } catch (\PDOException $e) {
            error_log("Erro ao deletar registro de upload: " . $e->getMessage());
            return false;
        }
    }
}
