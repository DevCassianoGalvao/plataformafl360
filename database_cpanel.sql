-- =====================================================
-- Portal do Aluno - FL360
-- SQL para importar no cPanel/phpMyAdmin
-- Selecione antes o banco xdigcomb_plataformafl360
-- =====================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    foto_perfil VARCHAR(255) NULL,
    senha VARCHAR(255) NOT NULL,
    role ENUM('admin', 'professor', 'aluno') NOT NULL DEFAULT 'aluno',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    ordem INT NOT NULL DEFAULT 0,
    professor_id INT UNSIGNED NULL,
    KEY idx_modules_ordem (ordem),
    KEY idx_modules_professor (professor_id),
    CONSTRAINT fk_modules_professor FOREIGN KEY (professor_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    video_url VARCHAR(255) NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    KEY idx_lessons_module (module_id),
    KEY idx_lessons_module_ordem (module_id, ordem, id),
    CONSTRAINT fk_lessons_module
        FOREIGN KEY (module_id) REFERENCES modules(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NULL,
    lesson_id INT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    KEY idx_materials_module (module_id),
    KEY idx_materials_lesson (lesson_id),
    CONSTRAINT fk_materials_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_materials_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    lesson_id INT UNSIGNED NOT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_lesson (user_id, lesson_id),
    KEY idx_progress_user (user_id),
    KEY idx_progress_lesson (lesson_id),
    CONSTRAINT fk_progress_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_progress_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_announcements_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(40) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    mensagem TEXT NOT NULL,
    url VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_reads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_user (notification_id, user_id),
    KEY idx_notification_reads_user (user_id),
    CONSTRAINT fk_notification_reads_notification
        FOREIGN KEY (notification_id) REFERENCES notifications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notification_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forum_topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_forum_topics_atualizado (atualizado_em),
    CONSTRAINT fk_forum_topics_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forum_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_forum_replies_topic (topic_id),
    CONSTRAINT fk_forum_replies_topic
        FOREIGN KEY (topic_id) REFERENCES forum_topics(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_forum_replies_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO modules (id, titulo, descricao, ordem)
SELECT 1, 'Módulo 1 - Introdução', 'Visão geral do programa FL360 e objetivos da trilha.', 1
WHERE NOT EXISTS (SELECT 1 FROM modules WHERE id = 1);

INSERT INTO lessons (id, module_id, titulo, descricao, video_url, ordem)
SELECT 1, 1, 'Aula 1 - Boas-vindas', 'Apresentação inicial do curso e metodologia.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 1
WHERE NOT EXISTS (SELECT 1 FROM lessons WHERE id = 1);

INSERT INTO announcements (id, titulo, mensagem, data)
SELECT 1, 'Bem-vindo ao FL360', 'Seu portal está pronto. Acompanhe avisos e conclua suas aulas.', NOW()
WHERE NOT EXISTS (SELECT 1 FROM announcements WHERE id = 1);

SET FOREIGN_KEY_CHECKS = 1;
