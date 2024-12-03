<?php
// Inicia a sessão
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Verifica se o login foi recebido via POST ou GET
if (isset($_POST['login'])) {
    $login = $_POST['login'];
} elseif (isset($_GET['login'])) {
    $login = $_GET['login'];
} else {
    echo "Erro: Login não recebido!";
    exit();
}

// Caminho para o arquivo de usuários
$arquivo_usuarios = 'dados/usuarios.txt';

// Verifica se o arquivo de usuários existe
if (!file_exists($arquivo_usuarios)) {
    echo "Erro: Arquivo de usuários não encontrado!";
    exit();
}

// Se o formulário de confirmação for submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_exclusao'])) {
    $usuarios = file($arquivo_usuarios);
    $usuarios_atualizados = [];
    $usuario_encontrado = false;

    // Procura pelo usuário a ser excluído e remove-o
    foreach ($usuarios as $linha) {
        $dados_usuario = explode(',', $linha);
        if (trim($dados_usuario[3]) !== $login) { // Comparando o login (4º campo)
            $usuarios_atualizados[] = $linha; // Mantém os outros usuários
        } else {
            $usuario_encontrado = true;
        }
    }

    if ($usuario_encontrado) {
        // Atualiza o arquivo de usuários sem o usuário excluído
        file_put_contents($arquivo_usuarios, implode("", $usuarios_atualizados));

        // Exibe a mensagem de sucesso com contador de 5 segundos
        echo "
        <!DOCTYPE html>
        <html lang='pt-br'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Usuário Excluído</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <script>
                let count = 5;
                function updateCountdown() {
                    document.getElementById('contador').innerText = count;
                    count--;
                    if (count < 0) {
                        window.location.href = 'configuracoes.php';
                    }
                }
                setInterval(updateCountdown, 1000);
            </script>
        </head>
        <body>
        <div class='container mt-4'>
            <div class='alert alert-success'>
                Usuário excluído com sucesso... Retornando em <span id='contador'>5</span> segundos.
            </div>
        </div>
        </body>
        </html>
        ";
        exit();
    } else {
        echo "Usuário não encontrado!";
        exit();
    }
}

// Se o login foi recebido, exibe a página de confirmação de exclusão
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Confirmação de Exclusão</h2>
    <p>Você deseja realmente excluir o usuário <strong><?php echo htmlspecialchars($login); ?></strong>? Esta ação é irreversível.</p>
    
    <form action="excluir_usuario.php" method="post">
        <input type="hidden" name="login" value="<?php echo htmlspecialchars($login); ?>">
        <button type="submit" name="confirmar_exclusao" class="btn btn-danger">Excluir</button>
        <a href="configuracoes.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
</body>
</html>
