-- =========================================================================
-- FL360 — Portal do Aluno
-- Script de instalação completo do banco de dados
-- =========================================================================
-- INSTRUÇÕES:
--   OPÇÃO A — cPanel / phpMyAdmin (produção):
--     1. Crie o banco e o usuário no cPanel → MySQL Databases
--     2. Abra o phpMyAdmin, selecione o banco
--     3. Aba "Import" → escolha este arquivo → Execute
--
--   OPÇÃO B — XAMPP local:
--     1. Abra http://localhost/phpmyadmin
--     2. Crie o banco: CREATE DATABASE plataformafl360 ...  (ver abaixo)
--     3. Selecione o banco e importe este arquivo
--
--   CREDENCIAIS PADRÃO DO ADMIN (criadas automaticamente pelo sistema):
--     E-mail : admin@fl360.local
--     Senha  : 33222   ← troque imediatamente após o primeiro acesso
-- =========================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Criação do banco (descomente se quiser criar pelo script) ────────────
-- Para XAMPP local:
-- CREATE DATABASE IF NOT EXISTS plataformafl360
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE plataformafl360;
--
-- Para cPanel (substitua pelo nome real):
-- CREATE DATABASE IF NOT EXISTS xdigcomb_plataformafl360
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE xdigcomb_plataformafl360;
-- ─────────────────────────────────────────────────────────────────────────

-- ─── TABELA: users ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(150) NOT NULL,
    email       VARCHAR(180) NOT NULL,
    foto_perfil VARCHAR(255) NULL,
    senha       VARCHAR(255) NOT NULL,
    role        ENUM('admin','professor','aluno') NOT NULL DEFAULT 'aluno',
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: modules ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS modules (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo   VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    ordem    INT NOT NULL DEFAULT 0,
    professor_id INT UNSIGNED NULL,
    KEY idx_modules_ordem (ordem),
    KEY idx_modules_professor (professor_id),
    CONSTRAINT fk_modules_professor FOREIGN KEY (professor_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: lessons ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lessons (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    titulo    VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    video_url VARCHAR(255) NOT NULL DEFAULT '',
    ordem     INT NOT NULL DEFAULT 0,
    KEY idx_lessons_module (module_id),
    KEY idx_lessons_module_ordem (module_id, ordem, id),
    CONSTRAINT fk_lessons_module
        FOREIGN KEY (module_id) REFERENCES modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: materials ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS materials (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NULL,
    lesson_id INT UNSIGNED NULL,
    titulo    VARCHAR(180) NOT NULL,
    arquivo   VARCHAR(255) NOT NULL,
    KEY idx_materials_module (module_id),
    KEY idx_materials_lesson (lesson_id),
    CONSTRAINT fk_materials_module
        FOREIGN KEY (module_id) REFERENCES modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_materials_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: progress ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS progress (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    lesson_id  INT UNSIGNED NOT NULL,
    completed  TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_lesson (user_id, lesson_id),
    KEY idx_progress_user (user_id),
    KEY idx_progress_lesson (lesson_id),
    CONSTRAINT fk_progress_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_progress_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: announcements ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS announcements (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo   VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    data     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_announcements_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: notifications ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo       VARCHAR(40)  NOT NULL,
    titulo     VARCHAR(180) NOT NULL,
    mensagem   TEXT NOT NULL,
    url        VARCHAR(255) NULL,
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: notification_reads ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notification_reads (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    lido_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_user (notification_id, user_id),
    KEY idx_notification_reads_user (user_id),
    CONSTRAINT fk_notification_reads_notification
        FOREIGN KEY (notification_id) REFERENCES notifications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notification_reads_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: forum_topics ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS forum_topics (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    titulo       VARCHAR(200) NOT NULL,
    mensagem     TEXT NOT NULL,
    criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_forum_topics_atualizado (atualizado_em),
    CONSTRAINT fk_forum_topics_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: forum_replies ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS forum_replies (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id  INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    mensagem  TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_forum_replies_topic (topic_id),
    CONSTRAINT fk_forum_replies_topic
        FOREIGN KEY (topic_id) REFERENCES forum_topics(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_forum_replies_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: quizzes ──────────────────────────────────────────────────────
-- Um quiz por módulo (UNIQUE em module_id)
CREATE TABLE IF NOT EXISTS quizzes (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    titulo    VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quizzes_module (module_id),
    CONSTRAINT fk_quizzes_module
        FOREIGN KEY (module_id) REFERENCES modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: quiz_questions ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS quiz_questions (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    pergunta TEXT NOT NULL,
    ordem   INT NOT NULL DEFAULT 0,
    KEY idx_quiz_questions_quiz (quiz_id),
    CONSTRAINT fk_quiz_questions_quiz
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: quiz_options ─────────────────────────────────────────────────
-- Sempre 4 opções por pergunta; apenas uma com correta=1
CREATE TABLE IF NOT EXISTS quiz_options (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    texto       VARCHAR(500) NOT NULL,
    correta     TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_quiz_options_question (question_id),
    CONSTRAINT fk_quiz_options_question
        FOREIGN KEY (question_id) REFERENCES quiz_questions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TABELA: quiz_attempts ────────────────────────────────────────────────
-- Histórico de todas as tentativas de cada aluno
-- respostas: JSON {"question_id": option_id, ...}
-- nota: 0-100 (porcentagem de acertos)
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id  INT UNSIGNED NOT NULL,
    quiz_id  INT UNSIGNED NOT NULL,
    acertos  INT UNSIGNED NOT NULL DEFAULT 0,
    total    INT UNSIGNED NOT NULL DEFAULT 0,
    nota     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    respostas TEXT NULL,
    feito_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_quiz_attempts_user (user_id),
    KEY idx_quiz_attempts_quiz (quiz_id),
    CONSTRAINT fk_quiz_attempts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quiz_attempts_quiz
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Dados iniciais de exemplo (opcionais) ────────────────────────────────
-- O usuário admin é criado automaticamente pelo sistema no primeiro acesso.
-- Descomente abaixo para inserir dados de exemplo:

/*
INSERT INTO modules (titulo, descricao, ordem) VALUES
  ('Módulo 1 - Introdução', 'Visão geral do programa FL360 e objetivos da trilha.', 1);

INSERT INTO lessons (module_id, titulo, descricao, video_url, ordem) VALUES
  (1, 'Aula 1 - Boas-vindas', 'Apresentação inicial do curso e metodologia.', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 1);

INSERT INTO announcements (titulo, mensagem) VALUES
  ('Bem-vindo ao FL360', 'Seu portal está pronto. Acompanhe avisos e conclua suas aulas.');
*/
