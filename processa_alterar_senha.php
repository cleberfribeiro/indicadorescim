<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Sistema de Indicadores CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <h2>Configurações</h2>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" id="configTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="perfil-tab" data-bs-toggle="tab" data-bs-target="#perfil" type="button" role="tab">Perfil do Usuário</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab">Usuários Cadastrados</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">Logs do Sistema</button>
        </li>
    </ul>

    <!-- Tab content -->
    <div class="tab-content">
        <!-- Aba Perfil do Usuário -->
        <div class="tab-pane fade show active" id="perfil" role="tabpanel">
            <h3>Perfil do Usuário</h3>
            <form action="" method="POST">
                <!-- Coloque aqui os campos do perfil do usuário -->
                <div class="mb-3">
                    <label for="nome_completo" class="form-label">Nome Completo:</label>
                    <input type="text" class="form-control" id="nome_completo" value="<?php echo $_SESSION['usuario']; ?>" readonly>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail:</label>
                    <input type="email" class="form-control" id="email" name="email" value="">
                </div>
                <div class="mb-3">
                    <label for="telefone" class="form-label">Telefone:</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="">
                </div>
                <button type="submit" name="atualizar_perfil" class="btn btn-primary">Atualizar Perfil</button>
            </form>
        </div>

        <!-- Aba Usuários Cadastrados (visível apenas para admins) -->
        <div class="tab-pane fade" id="usuarios" role="tabpanel">
            <h3>Usuários Cadastrados</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome Completo</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Login</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Listar todos os usuários cadastrados -->
                    <?php
                    // Exemplo básico para listar usuários do arquivo usuarios.txt
                    $usuarios = file('dados/usuarios.txt');
                    foreach ($usuarios as $index => $linha) {
                        list($nome, $email, $telefone, $login) = explode(',', trim($linha));
                        echo "<tr>
                                <td>" . ($index + 1) . "</td>
                                <td>$nome</td>
                                <td>$email</td>
                                <td>$telefone</td>
                                <td>$login</td>
                                <td>
                                    <a href='editar_usuario.php?login=$login' class='btn btn-warning'>Editar</a>
                                    <a href='excluir_usuario.php?login=$login' class='btn btn-danger'>Excluir</a>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
            <a href="adicionar_usuario.php" class="btn btn-success">Adicionar Novo Usuário</a>
        </div>

        <!-- Aba Logs do Sistema -->
        <div class="tab-pane fade" id="logs" role="tabpanel">
            <h3>Logs do Sistema</h3>
            <!-- Exibir os logs armazenados -->
            <?php
            if (file_exists('dados/logs.txt')) {
                $logs = file('dados/logs.txt');
                foreach ($logs as $log) {
                    echo "<p>$log</p>";
                }
            } else {
                echo "<p>Nenhum log encontrado.</p>";
            }
            ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
