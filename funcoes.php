<?php

session_start();

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


if (!isset($_SESSION['estoque'])) {
    $_SESSION['estoque'] = [
        ['id' => 1, 'nome' => 'Amoxicilina 500mg', 'codigo' => 'REM-001', 'qtd' => 15, 'validade' => '2026-05-20'],
        ['id' => 2, 'nome' => 'Seringa 5ml', 'codigo' => 'MAT-002', 'qtd' => 500, 'validade' => '2027-10-15'],
        ['id' => 3, 'nome' => 'Luva Procedimento M', 'codigo' => 'EPI-003', 'qtd' => 20, 'validade' => '2026-01-10'],
    ];
}


if (!isset($_SESSION['usuarios_cadastrados'])) {
    $_SESSION['usuarios_cadastrados'] = [
        ['user' => 'admin', 'pass' => password_hash('123', PASSWORD_DEFAULT), 'perfil' => 'admin', 'nome' => 'Administrador'],
        ['user' => 'bh', 'pass' => password_hash('nana', PASSWORD_DEFAULT), 'perfil' => 'usuario', 'nome' => 'Bh Silva']
    ];
}


if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}


function validarLogin($user, $senha) {
    foreach ($_SESSION['usuarios_cadastrados'] as $u) {
        if ($u['user'] === $user && password_verify($senha, $u['pass'])) {
            return $u;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {

    $user = trim($_POST['usuario']);
    $nome = trim($_POST['nome']);
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar_senha'];

   
    if ($senha !== $confirmar) {
        $erro = "As senhas não coincidem!";
    } elseif (strlen($senha) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres!";
    } else {

        foreach ($_SESSION['usuarios_cadastrados'] as $u) {
            if ($u['user'] === $user) {
                $erro = "Usuário já existe!";
                break;
            }
        }
    }

    if (!isset($erro)) {

        $novoUsuario = [
            'user' => $user,
            'pass' => password_hash($senha, PASSWORD_DEFAULT),
            'perfil' => 'usuario',
            'nome' => $nome
        ];

        
        $_SESSION['usuarios_cadastrados'][] = $novoUsuario;

        $_SESSION['user'] = $novoUsuario;

        header("Location: index.php");
        exit;
    }
}

?>
