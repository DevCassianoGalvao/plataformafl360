<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();
flash('success', 'Sessao encerrada com sucesso.');
redirect('login.php');
