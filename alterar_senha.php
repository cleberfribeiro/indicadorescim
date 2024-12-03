<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$usuario_logado = $_SESSION['usuario'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Carrega os dados dos usuários
    $usuarios = file('dados/usuarios.txt');
    $usuario_encontrado = false;

    foreach ($usuarios as $index => $linha) {
        $dados_usuario = explode(',', $linha);
        if (count($dados_usuario) >= 5 && trim($dados_usuario[3]) === $usuario_logado) {
            $senha_hash = trim($dados_usuario[4]);
            // Verifica se a senha atual está correta
            if (password_verify($senha_atual, $senha_hash)) {
                // Verifica se as novas senhas coincidem
                if ($nova_senha === $confirmar_senha) {
                    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $dados_usuario[4] = $nova_senha_hash;
                    $usuarios[$index] = implode(',', $dados_usuario) . "\n";
                    file_put_contents('dados/usuarios.txt', implode('', $usuarios));
                    $_SESSION['mensagem_sucesso'] = "Senha alterada com sucesso!";
                    header("Location: configuracoes.php");
                    exit();
                } else {
                    $erro = "As novas senhas não coincidem!";
                }
            } else {
                $erro = "A senha atual está incorreta!";
            }
            $usuario_encontrado = true;
            break;
        }
    }

    if (!$usuario_encontrado) {
        $erro = "Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha - Sistema de Indicadores CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    <h2>Alterar Senha</h2>

    <!-- Exibe uma mensagem de erro, se houver -->
    <?php if (isset($erro)): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>

    <form action="alterar_senha.php" method="POST">
        <div class="mb-3">
            <label for="senha_atual" class="form-label">Senha Atual:</label>
            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
        </div>
        <div class="mb-3">
            <label for="nova_senha" class="form-label">Nova Senha:</label>
            <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
        </div>
        <div class="mb-3">
            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha:</label>
            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
        </div>
        <button type="submit" class="btn btn-primary" name="alterar_senha">Alterar Senha</button>
    </form>

    <a href="configuracoes.php" class="btn btn-secondary mt-3">Cancelar</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
