<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Indicadores CIM - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
        }
        .logo {
            width: 200px;
            margin-bottom: 20px;
        }
        /* Ajuste da cor dos ícones para cinza claro */
        .input-group-text i {
            color: #888; /* Cor dos ícones em cinza claro */
        }
    </style>
</head>
<body>

<div class="login-container text-center">
    <img src="https://www.cimtoys.com.br/img/logo.png" alt="Logo CIM" class="logo">
    <h2>Sistema de Indicadores CIM</h2>

    <!-- Exibe mensagem de erro de login, se existir -->
    <?php if (isset($_SESSION['erro_login'])): ?>
        <div class="alert alert-danger">
            <?php
            echo $_SESSION['erro_login'];
            unset($_SESSION['erro_login']); // Remove a mensagem após exibir
            ?>
        </div>
    <?php endif; ?>

    <form action="processa_login.php" method="post">
        <div class="form-group mb-3">
            <label for="login" class="form-label">Nome de Login</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span> <!-- Ícone Font Awesome -->
                <input type="text" name="login" id="login" class="form-control" required>
            </div>
        </div>
        <div class="form-group mb-3">
            <label for="senha" class="form-label">Senha</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span> <!-- Ícone Font Awesome -->
                <input type="password" name="senha" id="senha" class="form-control" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>
    <a href="cadastro.php" class="d-block mt-3">Primeiro Acesso? Cadastre-se aqui</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
