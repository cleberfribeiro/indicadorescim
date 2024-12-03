<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'master') {
    header("Location: index.php");
    exit();
}

$login_usuario = $_GET['login'];
$nome_completo = '';
$email = '';
$telefone = '';
$senha_hash = ''; // Para garantir que o hash da senha seja mantido
$usuarios = [];

// Carrega os dados do arquivo usuarios.txt
if (file_exists('dados/usuarios.txt')) {
    $arquivo = fopen('dados/usuarios.txt', 'r');
    while (($linha = fgets($arquivo)) !== false) {
        $linha = trim($linha);  // Remove espaços em branco e quebras de linha
        if (!empty($linha)) {
            $usuarios[] = $linha;  // Armazena todas as linhas dos usuários
            $dados_usuario = explode(',', $linha);
            if (count($dados_usuario) === 5) {
                list($nome, $email_lido, $telefone_lido, $login, $senha_hash_lido) = $dados_usuario;
                if ($login === $login_usuario) {
                    $nome_completo = $nome;
                    $email = $email_lido;
                    $telefone = $telefone_lido;
                    $senha_hash = $senha_hash_lido; // Captura o hash da senha
                }
            }
        }
    }
    fclose($arquivo);
}

// Processa a atualização do usuário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_usuario'])) {
    $novo_nome_completo = $_POST['nome_completo'];
    $novo_email = $_POST['email'];
    $novo_telefone = $_POST['telefone'];

    // Atualiza o arquivo usuarios.txt
    $arquivo = fopen('dados/usuarios.txt', 'w');
    foreach ($usuarios as $linha) {
        $dados_usuario = explode(',', $linha);
        if (count($dados_usuario) === 5) {
            list($nome, $email_lido, $telefone_lido, $login, $senha_hash_lido) = $dados_usuario;
            if ($login === $login_usuario) {
                // Atualiza as informações do usuário e mantém o hash da senha
                fwrite($arquivo, "$novo_nome_completo,$novo_email,$novo_telefone,$login,$senha_hash\n");
            } else {
                fwrite($arquivo, $linha . "\n");
            }
        }
    }
    fclose($arquivo);
    
    // Redireciona de volta para a página de usuários
    echo "<script>alert('Usuário editado com sucesso!'); window.location.href='configuracoes.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Sistema de Indicadores CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">
    <h2>Editar Usuário</h2>
    <form action="" method="POST">
        <div class="mb-3">
            <label for="nome_completo" class="form-label">Nome Completo:</label>
            <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?php echo $nome_completo; ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail:</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
        </div>
        <div class="mb-3">
            <label for="telefone" class="form-label">Telefone:</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo $telefone; ?>" required>
        </div>
        <button type="submit" class="btn btn-success" name="salvar_usuario">Salvar Alterações</button>
        <a href="configuracoes.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
