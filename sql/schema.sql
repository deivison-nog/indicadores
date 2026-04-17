-- Indicadores APS - Database Schema
-- Run this on your MySQL/MariaDB server

CREATE DATABASE IF NOT EXISTS indicadores CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE indicadores;

-- administrators table
CREATE TABLE IF NOT EXISTS administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    public_token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- users table (read-only viewers, linked to an admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES administrators(id)
);

-- competency imports tracking
CREATE TABLE IF NOT EXISTS competency_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    indicator_key VARCHAR(100) NOT NULL,
    competency VARCHAR(7) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES administrators(id),
    UNIQUE KEY unique_competency (admin_id, indicator_key, competency)
);

-- mais acesso data rows
CREATE TABLE IF NOT EXISTS mais_acesso_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_id INT NOT NULL,
    admin_id INT NOT NULL,
    competency VARCHAR(7) NOT NULL,
    equipe VARCHAR(200) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    atendimento_urgencia INT DEFAULT 0,
    consulta_agendada INT DEFAULT 0,
    consulta_agendada_programada INT DEFAULT 0,
    consulta_no_dia INT DEFAULT 0,
    total INT DEFAULT 0,
    FOREIGN KEY (import_id) REFERENCES competency_imports(id),
    FOREIGN KEY (admin_id) REFERENCES administrators(id)
);

-- seed: default admin account (password: admin123)
-- !! IMPORTANT: Change this password immediately after first login in production !!
INSERT IGNORE INTO administrators (name, email, password_hash, public_token)
VALUES (
    'Administrador',
    'admin@indicadores.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'default_token_abc123def456'
);
