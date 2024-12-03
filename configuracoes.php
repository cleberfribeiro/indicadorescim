<?php
session_start();

// Proteção CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Token CSRF inválido');
    }
}

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Configurações iniciais
$usuario_logado = $_SESSION['usuario'];
$nome_completo = '';
$email = '';
$telefone = '';
$usuarios = [];
$logs = [];

// Tipos de ações para logs
$tipos_acao = [
    'LOGIN' => 'Login no Sistema',
    'LOGOUT' => 'Logout do Sistema',
    'USUARIO_NOVO' => 'Novo Usuário',
    'USUARIO_EDIT' => 'Edição de Usuário',
    'USUARIO_DELETE' => 'Exclusão de Usuário',
    'PERFIL_UPDATE' => 'Atualização de Perfil',
    'BACKUP' => 'Backup Realizado',
    'SENHA_ALTER' => 'Alteração de Senha'
];

// Função para registrar logs
function registrarLog($acao, $descricao) {
    $data = date('Y-m-d H:i:s');
    $log = "[$data] [$acao] $descricao\n";
    file_put_contents('dados/logs.txt', $log, FILE_APPEND);
}

// Função para realizar backup
function realizarBackup() {
    $data = date('Y-m-d_H-i-s');
    $pasta_backup = 'backups/' . $data;
    
    if (!file_exists('backups')) {
        mkdir('backups', 0777, true);
    }
    
    if (!file_exists($pasta_backup)) {
        mkdir($pasta_backup, 0777, true);
    }

    $arquivos = ['usuarios.txt', 'logs.txt'];
    foreach ($arquivos as $arquivo) {
        if (file_exists('dados/' . $arquivo)) {
            copy('dados/' . $arquivo, $pasta_backup . '/' . $arquivo);
        }
    }

    // Criar arquivo ZIP
    $zip = new ZipArchive();
    $nome_zip = 'backups/backup_' . $data . '.zip';
    
    if ($zip->open($nome_zip, ZipArchive::CREATE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pasta_backup),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $zip->addFile($file->getRealPath(), $file->getFilename());
            }
        }
        
        $zip->close();
        // Limpa os arquivos temporários
        array_map('unlink', glob("$pasta_backup/*.*"));
        rmdir($pasta_backup);
        return $nome_zip;
    }
    return false;
}

// Carrega os dados do usuário
if (file_exists('dados/usuarios.txt')) {
    $arquivo = fopen('dados/usuarios.txt', 'r');
    while (($linha = fgets($arquivo)) !== false) {
        $linha = trim($linha);
        if (!empty($linha)) {
            $usuarios[] = $linha;
            $dados_usuario = explode(',', $linha);
            if (count($dados_usuario) >= 5) {
                list($nome, $email_lido, $telefone_lido, $login, $senha) = $dados_usuario;
                if ($login === $usuario_logado) {
                    $nome_completo = $nome;
                    $email = $email_lido;
                    $telefone = $telefone_lido;
                }
            }
        }
    }
    fclose($arquivo);
}

// Verifica mensagem de sucesso
$mensagem_sucesso = '';
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_sucesso = $_SESSION['mensagem_sucesso'];
    unset($_SESSION['mensagem_sucesso']);
}

// Carrega e filtra logs
if (file_exists('dados/logs.txt')) {
    $logs = file('dados/logs.txt');
}

// Configuração da paginação
$por_pagina = 15;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $por_pagina;

// Filtragem dos logs
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['filtro'])) {
    $filtro_usuario = $_POST['filtro_usuario'] ?? '';
    $filtro_data = $_POST['filtro_data'] ?? '';
    $filtro_acao = $_POST['filtro_acao'] ?? '';
    $filtro_descricao = $_POST['filtro_descricao'] ?? '';

    $logs_filtrados = [];
    foreach ($logs as $log) {
        preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $log, $log_dados);

        if (count($log_dados) == 4) {
            $data = $log_dados[1];
            $acao = $log_dados[2];
            $descricao = $log_dados[3];

            if (
                (!$filtro_usuario || stripos($descricao, $filtro_usuario) !== false) &&
                (!$filtro_data || stripos($data, $filtro_data) !== false) &&
                (!$filtro_acao || stripos($acao, $filtro_acao) !== false) &&
                (!$filtro_descricao || stripos($descricao, $filtro_descricao) !== false)
            ) {
                $logs_filtrados[] = [$data, $acao, $descricao];
            }
        }
    }
    $logs = $logs_filtrados;
}

// Preparar logs para paginação
$total_logs = count($logs);
$total_paginas = ceil($total_logs / $por_pagina);
$logs_paginados = array_slice($logs, $offset, $por_pagina);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Sistema de Indicadores CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary-color);
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-bottom: 2px solid rgba(13, 110, 253, 0.5);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            font-weight: bold;
        }

        .table {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .filter-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .fade-out {
            animation: fadeOut 3s forwards;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }

        @keyframes fadeOut {
            0% { opacity: 1; transform: translateY(-20px); }
            70% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); display: none; }
        }

        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .pagination {
            margin-top: 20px;
            justify-content: center;
        }

        .page-link {
            padding: 8px 16px;
            color: var(--primary-color);
            border-radius: 5px;
            margin: 0 2px;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-icon {
            margin-right: 5px;
        }

        .profile-section {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .section-title {
            color: var(--secondary-color);
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--secondary-color);
            font-size: 14px;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="section-title">Configurações do Sistema</h2>

    <?php if (!empty($mensagem_sucesso)): ?>
        <div id="mensagem-sucesso" class="alert alert-success fade-out">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $mensagem_sucesso; ?>
        </div>
    <?php endif; ?>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" id="configTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="perfil-tab" data-bs-toggle="tab" data-bs-target="#perfil" type="button" role="tab">
                <i class="fas fa-user me-2"></i>Perfil do Usuário
            </button>
        </li>
        <?php if ($usuario_logado === 'master'): ?>
            <li class="nav-item">
                <button class="nav-link" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>Usuários Cadastrados
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                    <i class="fas fa-history me-2"></i>Logs do Sistema
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                    <i class="fas fa-database me-2"></i>Backup Manual
                </button>
            </li>
        <?php endif; ?>
    </ul>
<!-- Tab content -->
    <div class="tab-content">
        <!-- Aba Perfil do Usuário -->
        <div class="tab-pane fade show active" id="perfil" role="tabpanel">
            <div class="profile-section">
                <h3 class="mb-4">Perfil do Usuário</h3>
                <form action="atualiza_perfil.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nome_completo" class="form-label">
                                <i class="fas fa-user me-2"></i>Nome Completo:
                            </label>
                            <input type="text" class="form-control" id="nome_completo" name="nome_completo" 
                                   value="<?php echo htmlspecialchars($nome_completo); ?>" readonly required>
                            <div class="invalid-feedback">
                                Por favor, insira seu nome completo.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>E-mail:
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>" readonly required>
                            <div class="invalid-feedback">
                                Por favor, insira um e-mail válido.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="telefone" class="form-label">
                                <i class="fas fa-phone me-2"></i>Telefone:
                            </label>
                            <input type="text" class="form-control" id="telefone" name="telefone" 
                                   value="<?php echo htmlspecialchars($telefone); ?>" readonly required>
                            <div class="invalid-feedback">
                                Por favor, insira um telefone válido.
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-primary" id="btnEditar" onclick="toggleEditMode(event)">
                            <i class="fas fa-edit me-2"></i>Editar Perfil
                        </button>
                        <button type="submit" class="btn btn-success" id="btnSalvar" name="salvar_edicao" style="display: none;">
                            <i class="fas fa-save me-2"></i>Salvar Edição
                        </button>
                        <a href="alterar_senha.php" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Alterar Senha
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($usuario_logado === 'master'): ?>
        <!-- Aba Usuários Cadastrados -->
        <div class="tab-pane fade" id="usuarios" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3>Usuários Cadastrados</h3>
                        <a href="adicionar_usuario.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Adicionar Novo Usuário
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>#</th>
                                    <th><i class="fas fa-user me-2"></i>Nome Completo</th>
                                    <th><i class="fas fa-envelope me-2"></i>E-mail</th>
                                    <th><i class="fas fa-phone me-2"></i>Telefone</th>
                                    <th><i class="fas fa-user-tag me-2"></i>Login</th>
                                    <th><i class="fas fa-cog me-2"></i>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($usuarios as $index => $linha) {
                                    $linha = trim($linha);
                                    if (empty($linha)) continue;

                                    $dados_usuario = explode(',', $linha);
                                    if (count($dados_usuario) >= 5):
                                        list($nome, $email, $telefone, $login, $senha_texto_simples) = $dados_usuario;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($nome); ?></td>
                                    <td><?php echo htmlspecialchars($email); ?></td>
                                    <td><?php echo htmlspecialchars($telefone); ?></td>
                                    <td><?php echo htmlspecialchars($login); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="editar_usuario.php?login=<?php echo urlencode($login); ?>" 
                                               class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="excluir_usuario.php?login=<?php echo urlencode($login); ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Tem certeza que deseja excluir este usuário?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="mailto:<?php echo $email; ?>?subject=Credenciais de Acesso - Sistema CIM&body=Olá <?php echo $nome; ?>, suas credenciais de acesso são:%0A%0ALogin: <?php echo $login; ?>%0ASenha: <?php echo $senha_texto_simples; ?>%0A%0APor segurança, recomendamos alterar a senha após o primeiro login." 
                                               class="btn btn-info btn-sm">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endif;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba Logs do Sistema -->
        <div class="tab-pane fade" id="logs" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h3 class="mb-4">Logs do Sistema</h3>
                    
                    <!-- Filtros -->
                    <div class="filter-section">
                        <form action="" method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" name="filtro_data" class="form-control" 
                                           value="<?php echo isset($_POST['filtro_data']) ? $_POST['filtro_data'] : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                    <select name="filtro_acao" class="form-control">
                                        <option value="">Todas as Ações</option>
                                        <?php foreach ($tipos_acao as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" 
                                                <?php echo isset($_POST['filtro_acao']) && $_POST['filtro_acao'] === $key ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" name="filtro_descricao" class="form-control" 
                                           placeholder="Buscar na descrição" 
                                           value="<?php echo isset($_POST['filtro_descricao']) ? $_POST['filtro_descricao'] : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="filtro" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filtrar
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="mb-3">
                        <a href="exportar_logs.php" class="btn btn-success">
                            <i class="fas fa-file-export me-2"></i>Exportar CSV
                        </a>
                    </div>

                    <!-- Tabela de Logs -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-calendar me-2"></i>Data</th>
                                    <th><i class="fas fa-tag me-2"></i>Ação</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs_paginados as $log): ?>
                                    <?php
                                    preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $log, $log_dados);
                                    if (count($log_dados) == 4):
                                        $data = date('d/m/Y H:i:s', strtotime($log_dados[1]));
                                        $acao = $log_dados[2];
                                        $descricao = $log_dados[3];
                                    ?>
                                    <tr>
                                        <td><?php echo $data; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $acao; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($descricao); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Navegação de páginas">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aba Backup Manual -->
        <div class="tab-pane fade" id="backup" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <h3 class="mb-4">Backup Manual</h3>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Você pode realizar o backup manual dos arquivos de usuários e logs clicando no botão abaixo.
                    </div>
                    <form action="backup.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i>Fazer Backup
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Máscara para telefone
function mascararTelefone(telefone) {
    const texto = telefone.value;
    const textoApenasNumeros = texto.replace(/\D/g, '').substring(0, 11);
    
    let telefoneFormatado = textoApenasNumeros.replace(/^(\d{2})(\d)/g, '($1) $2');
    telefoneFormatado = telefoneFormatado.replace(/(\d)(\d{4})$/, '$1-$2');
    
    telefone.value = telefoneFormatado;
}

// Função para validar e-mail
function validarEmail(email) {
    const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

// Função para habilitar/desabilitar modo de edição
function toggleEditMode(event) {
    event.preventDefault();
    const inputs = document.querySelectorAll('#perfil input[type="text"], #perfil input[type="email"]');
    const btnEditar = document.getElementById('btnEditar');
    const btnSalvar = document.getElementById('btnSalvar');

    inputs.forEach(input => {
        input.readOnly = !input.readOnly;
        if (!input.readOnly) {
            input.classList.add('border-primary');
        } else {
            input.classList.remove('border-primary');
        }
    });

    btnEditar.style.display = 'none';
    btnSalvar.style.display = 'inline';

    // Adiciona máscara ao telefone quando estiver editando
    const inputTelefone = document.getElementById('telefone');
    if (!inputTelefone.readOnly) {
        inputTelefone.addEventListener('input', function() {
            mascararTelefone(this);
        });
    }
}

// Validação do formulário antes de enviar
document.querySelector('form').addEventListener('submit', function(event) {
    const form = event.target;
    let isValid = true;

    // Validar nome
    const nome = form.querySelector('#nome_completo');
    if (nome.value.trim().length < 3) {
        nome.classList.add('is-invalid');
        isValid = false;
    } else {
        nome.classList.remove('is-invalid');
    }

    // Validar email
    const email = form.querySelector('#email');
    if (!validarEmail(email.value)) {
        email.classList.add('is-invalid');
        isValid = false;
    } else {
        email.classList.remove('is-invalid');
    }

    // Validar telefone
    const telefone = form.querySelector('#telefone');
    if (telefone.value.replace(/\D/g, '').length < 10) {
        telefone.classList.add('is-invalid');
        isValid = false;
    } else {
        telefone.classList.remove('is-invalid');
    }

    if (!isValid) {
        event.preventDefault();
    }
});

// Sistema de notificações
class NotificationSystem {
    static show(message, type = 'success', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} fade-out`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, duration);
    }
}

// Confirmações de exclusão
document.querySelectorAll('[data-confirm]').forEach(element => {
    element.addEventListener('click', function(event) {
        if (!confirm(this.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });
});

// Inicialização de tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Filtros dinâmicos para tabelas
document.querySelectorAll('.table-filter').forEach(filter => {
    filter.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        const table = this.closest('.table-responsive').querySelector('table');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });
});

// Ordenação de tabelas
document.querySelectorAll('.sortable').forEach(header => {
    header.addEventListener('click', function() {
        const table = this.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const index = Array.from(header.parentElement.children).indexOf(header);
        const direction = header.classList.contains('asc') ? -1 : 1;
        
        rows.sort((a, b) => {
            const aText = a.children[index].textContent;
            const bText = b.children[index].textContent;
            return aText.localeCompare(bText) * direction;
        });
        
        rows.forEach(row => tbody.appendChild(row));
        header.classList.toggle('asc');
    });
});

// Mensagem de sucesso automática
const mensagemSucesso = document.getElementById('mensagem-sucesso');
if (mensagemSucesso) {
    setTimeout(() => {
        mensagemSucesso.style.display = 'none';
    }, 5000);
}

// Exportação de dados
document.querySelectorAll('.export-data').forEach(button => {
    button.addEventListener('click', function() {
        const table = this.closest('.card').querySelector('table');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        rows.forEach(row => {
            let rowData = [];
            row.querySelectorAll('td, th').forEach(cell => {
                rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', 'exported_data.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});

// Inicialização do sistema
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa todos os componentes Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Adiciona handlers para os filtros
    const filterInputs = document.querySelectorAll('.filter-input');
    filterInputs.forEach(input => {
        input.addEventListener('keyup', debounce(function() {
            applyFilters();
        }, 300));
    });
});

// Função de debounce para otimização de performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>
</body>
</html>