-- =============================================
-- Script de criação de tabelas para autenticação
-- Sistema SearchPDF
-- =============================================

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'uploader', 'viewer') DEFAULT 'viewer',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de log de uploads
CREATE TABLE IF NOT EXISTS upload_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    year INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    status ENUM('success', 'failed', 'indexed') DEFAULT 'success',
    error_message TEXT NULL,
    ip_address VARCHAR(45) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_upload_date (upload_date),
    INDEX idx_status (status),
    INDEX idx_year_month (year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de sessões (opcional, para maior controle)
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Inserir usuário admin padrão
-- Senha padrão: admin123 (ALTERAR EM PRODUÇÃO!)
-- =============================================
INSERT INTO users (username, password_hash, full_name, email, role, active) 
VALUES (
    'admin',
    '$2y$10$EQF4F0eLtGwNQ1boJI5xv./w/3wAFe3VpHdKa5nP9Ngo0BQV7qkpi', -- admin123
    'Administrador',
    'admin@searchpdf.local',
    'admin',
    TRUE
);

-- Usuário de teste para uploader
-- Senha padrão: uploader123
INSERT INTO users (username, password_hash, full_name, email, role, active) 
VALUES (
    'uploader',
    '$2y$10$i7FVV39kDREG7zM3K1PcCOGKki1GNz.l.qBNUGaJnQ8dqqNxH5e1K', -- uploader123
    'Usuário Upload',
    'uploader@searchpdf.local',
    'uploader',
    TRUE
);

-- =============================================
-- Procedure para limpar sessões expiradas
-- =============================================
DELIMITER //
CREATE PROCEDURE clean_expired_sessions()
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END//
DELIMITER ;

-- =============================================
-- Event para limpar sessões a cada hora
-- =============================================
CREATE EVENT IF NOT EXISTS clean_sessions_event
ON SCHEDULE EVERY 1 HOUR
DO CALL clean_expired_sessions();
