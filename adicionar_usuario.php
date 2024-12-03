<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Função para registrar logs
function registrarLog($acao, $descricao) {
    $data = date('Y-m-d H:i:s');
    $log = "[$data] [$acao] $descricao\n";
    file_put_contents('dados/logs.txt', $log, FILE_APPEND);
}

// Função para validar e-mail
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para validar telefone
function validarTelefone($telefone) {
    return preg_match('/^\(\d{2}\)\s\d{4,5}-\d{4}$/', $telefone);
}

// Função para validar força da senha
function validarForcaSenha($senha) {
    if (strlen($senha) < 8) return false;
    if (!preg_match("/[A-Z]/", $senha)) return false;
    if (!preg_match("/[a-z]/", $senha)) return false;
    if (!preg_match("/[0-9]/", $senha)) return false;
    if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $senha)) return false;
    return true;
}

$mensagem = ["tipo" => "", "texto" => ""];
$dados_form = ["nome" => "", "email" => "", "telefone" => "", "login" => ""];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome_completo']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $login = trim($_POST['login']);
    $senha = $_POST['senha'];
    
    // Guarda os dados do formulário para repreenchimento em caso de erro
    $dados_form = [
        "nome" => $nome,
        "email" => $email,
        "telefone" => $telefone,
        "login" => $login
    ];

    $erro = false;

    // Validações
    if (strlen($nome) < 3) {
        $mensagem = ["tipo" => "danger", "texto" => "O nome deve ter pelo menos 3 caracteres."];
        $erro = true;
    }
    elseif (!validarEmail($email)) {
        $mensagem = ["tipo" => "danger", "texto" => "E-mail inválido."];
        $erro = true;
    }
    elseif (!validarTelefone($telefone)) {
        $mensagem = ["tipo" => "danger", "texto" => "Telefone inválido. Use o formato (99) 99999-9999"];
        $erro = true;
    }
    elseif (strlen($login) < 4) {
        $mensagem = ["tipo" => "danger", "texto" => "O login deve ter pelo menos 4 caracteres."];
        $erro = true;
    }
    elseif (!validarForcaSenha($senha)) {
        $mensagem = ["tipo" => "danger", "texto" => "A senha deve ter pelo menos 8 caracteres, incluir maiúsculas, minúsculas, números e caracteres especiais."];
        $erro = true;
    }

    if (!$erro) {
        // Verifica se o login já existe
        if (file_exists('dados/usuarios.txt')) {
            $usuarios = file('dados/usuarios.txt');
            foreach ($usuarios as $usuario) {
                $dados_usuario = explode(',', trim($usuario));
                if ($dados_usuario[3] == $login) {
                    $mensagem = ["tipo" => "warning", "texto" => "Este login já está em uso. Escolha outro."];
                    $erro = true;
                    break;
                }
            }
        }

        if (!$erro) {
            try {
                // Adiciona o novo usuário
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $novo_usuario = "$nome,$email,$telefone,$login,$senha_hash\n";
                file_put_contents('dados/usuarios.txt', $novo_usuario, FILE_APPEND);
                
                // Registra o log
                registrarLog("USUARIO_NOVO", "Usuário '$login' adicionado por {$_SESSION['usuario']}");
                
                $mensagem = ["tipo" => "success", "texto" => "Usuário cadastrado com sucesso! Redirecionando..."];
                
                // Limpa os dados do formulário após sucesso
                $dados_form = ["nome" => "", "email" => "", "telefone" => "", "login" => ""];
                
                // Redireciona após 3 segundos
                header("refresh:3;url=configuracoes.php#usuarios");
            } catch (Exception $e) {
                $mensagem = ["tipo" => "danger", "texto" => "Erro ao salvar usuário. Tente novamente."];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Novo Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .password-strength {
            height: 5px;
            transition: all 0.3s ease;
            margin-top: 5px;
        }
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-medium { background-color: #ffc107; width: 50%; }
        .strength-strong { background-color: #28a745; width: 100%; }
        .form-floating {
            margin-bottom: 1rem;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .input-group-text {
            background-color: #f8f9fa;
        }
        .btn-toggle-password {
            cursor: pointer;
        }
        .form-label {
            font-weight: 500;
        }
        .alert {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .btn-submit {
            padding: 0.75rem 2rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="form-container">
            <h2 class="mb-4 text-center">Cadastrar Novo Usuário</h2>
            
            <?php if ($mensagem["texto"]): ?>
                <div class="alert alert-<?php echo $mensagem["tipo"]; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $mensagem["tipo"] == "success" ? "check-circle" : "exclamation-circle"; ?> me-2"></i>
                    <?php echo $mensagem["texto"]; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="needs-validation" novalidate>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nome_completo" name="nome_completo" 
                           placeholder="Nome Completo" value="<?php echo htmlspecialchars($dados_form["nome"]); ?>" required>
                    <label for="nome_completo">Nome Completo</label>
                    <div class="invalid-feedback">
                        Por favor, insira um nome válido.
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="E-mail" value="<?php echo htmlspecialchars($dados_form["email"]); ?>" required>
                    <label for="email">E-mail</label>
                    <div class="invalid-feedback">
                        Por favor, insira um e-mail válido.
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="telefone" name="telefone" 
                           placeholder="Telefone" value="<?php echo htmlspecialchars($dados_form["telefone"]); ?>" required>
                    <label for="telefone">Telefone</label>
                    <div class="invalid-feedback">
                        Por favor, insira um telefone válido.
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="login" name="login" 
                           placeholder="Login" value="<?php echo htmlspecialchars($dados_form["login"]); ?>" required>
                    <label for="login">Login</label>
                    <div class="invalid-feedback">
                        O login deve ter pelo menos 4 caracteres.
                    </div>
                </div>

                <div class="form-floating mb-3">
                    <div class="input-group">
                        <input type="password" class="form-control" id="senha" name="senha" 
                               placeholder="Senha" required>
                        <span class="input-group-text btn-toggle-password">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-strength"></div>
                    <small class="form-text text-muted">
                        A senha deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.
                    </small>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="configuracoes.php#usuarios" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary btn-submit">
                        <i class="fas fa-user-plus me-2"></i>Cadastrar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Máscara para telefone
            $('#telefone').mask('(00) 00000-0000');

            // Toggle de visibilidade da senha
            $('.btn-toggle-password').click(function() {
                const input = $('#senha');
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Verificador de força da senha
            $('#senha').keyup(function() {
                const password = $(this).val();
                const strengthBar = $('.password-strength');
                
                // Critérios de força
                const hasLower = /[a-z]/.test(password);
                const hasUpper = /[A-Z]/.test(password);
                const hasNumber = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
                const isLongEnough = password.length >= 8;

                const strength = [hasLower, hasUpper, hasNumber, hasSpecial, isLongEnough]
                    .filter(Boolean).length;

                strengthBar.removeClass('strength-weak strength-medium strength-strong');
                
                if (strength <= 2) {
                    strengthBar.addClass('strength-weak');
                } else if (strength <= 4) {
                    strengthBar.addClass('strength-medium');
                } else {
                    strengthBar.addClass('strength-strong');
                }
            });

            // Validação do formulário
            $('form').submit(function(event) {
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                $(this).addClass('was-validated');
            });
        });
    </script>
</body>
</html>