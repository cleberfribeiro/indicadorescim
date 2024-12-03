<?php
session_start();

// Configurações de segurança da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Classe para gerenciar autenticação
class AuthManager {
    private const MAX_LOGIN_ATTEMPTS = 3;
    private const LOCKOUT_TIME = 900; // 15 minutos
    private $logFile = 'dados/logs.txt';
    private $userFile = 'dados/usuarios.txt';
    private $attemptFile = 'dados/login_attempts.txt';

    /**
     * Registra tentativas de login
     */
    private function registerLoginAttempt($ip, $login, $success = false) {
        $attempts = $this->getLoginAttempts($ip);
        $attempts[] = [
            'time' => time(),
            'login' => $login,
            'success' => $success
        ];
        
        file_put_contents(
            $this->attemptFile,
            "$ip|" . serialize($attempts) . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Obtém tentativas de login
     */
    private function getLoginAttempts($ip) {
        if (!file_exists($this->attemptFile)) {
            return [];
        }

        $attempts = [];
        $lines = file($this->attemptFile);
        foreach ($lines as $line) {
            list($stored_ip, $data) = explode('|', trim($line));
            if ($stored_ip === $ip) {
                $attempts = unserialize($data);
                break;
            }
        }

        // Limpa tentativas antigas
        return array_filter($attempts, function($attempt) {
            return $attempt['time'] > (time() - self::LOCKOUT_TIME);
        });
    }

    /**
     * Verifica se o IP está bloqueado
     */
    private function isIpBlocked($ip) {
        $attempts = $this->getLoginAttempts($ip);
        $failed_attempts = array_filter($attempts, function($attempt) {
            return !$attempt['success'] && $attempt['time'] > (time() - self::LOCKOUT_TIME);
        });

        return count($failed_attempts) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Registra log
     */
    private function log($message, $level = 'INFO', $context = []) {
        $date = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user = $_SESSION['usuario'] ?? 'System';
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        
        $logMessage = "[$date] [$level] [User: $user] [IP: $ip] $message$contextStr" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Tenta realizar o login
     */
    public function attemptLogin($login, $senha) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Verifica bloqueio
        if ($this->isIpBlocked($ip)) {
            $this->log('Tentativa de login bloqueada - IP bloqueado', 'WARNING', [
                'ip' => $ip,
                'login' => $login
            ]);
            return [
                'success' => false,
                'message' => 'Muitas tentativas de login. Tente novamente em 15 minutos.',
                'code' => 'blocked'
            ];
        }

        // Validação básica
        if (empty($login) || empty($senha)) {
            return [
                'success' => false,
                'message' => 'Preencha todos os campos.',
                'code' => 'empty_fields'
            ];
        }

        // Proteção contra injeção
        $login = htmlspecialchars(trim($login));

        try {
            if (!file_exists($this->userFile)) {
                throw new Exception('Arquivo de usuários não encontrado.');
            }

            $usuarios = file($this->userFile);
            foreach ($usuarios as $linha) {
                list($nome_completo, $email, $telefone, $login_salvo, $senha_hash) = explode(',', trim($linha));
                
                if ($login === $login_salvo) {
                    if (password_verify($senha, $senha_hash)) {
                        // Login bem-sucedido
                        $_SESSION['usuario'] = $login;
                        $_SESSION['nome_completo'] = $nome_completo;
                        $_SESSION['email'] = $email;
                        $_SESSION['last_activity'] = time();
                        
                        $this->registerLoginAttempt($ip, $login, true);
                        $this->log('Login bem-sucedido', 'INFO', ['user' => $login]);
                        
                        return [
                            'success' => true,
                            'message' => 'Login realizado com sucesso!',
                            'code' => 'success'
                        ];
                    }
                    
                    // Senha incorreta
                    $this->registerLoginAttempt($ip, $login, false);
                    $this->log('Tentativa de login falhou - senha incorreta', 'WARNING', [
                        'user' => $login
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Senha incorreta.',
                        'code' => 'invalid_password'
                    ];
                }
            }
            
            // Usuário não encontrado
            $this->registerLoginAttempt($ip, $login, false);
            $this->log('Tentativa de login falhou - usuário não encontrado', 'WARNING', [
                'attempted_user' => $login
            ]);
            
            return [
                'success' => false,
                'message' => 'Usuário não encontrado.',
                'code' => 'user_not_found'
            ];
            
        } catch (Exception $e) {
            $this->log('Erro no sistema de login', 'ERROR', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro interno do sistema. Tente novamente mais tarde.',
                'code' => 'system_error'
            ];
        }
    }
}

// Tratamento da requisição
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $auth = new AuthManager();
    $result = $auth->attemptLogin($_POST['login'], $_POST['senha']);
    
    header('Content-Type: application/json');
    
    if ($result['success']) {
        // Redireciona em caso de sucesso
        echo json_encode([
            'success' => true,
            'redirect' => 'principal.php'
        ]);
    } else {
        // Retorna erro
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'code' => $result['code']
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Indicadores CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header img {
            max-width: 200px;
            margin-bottom: 1rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .btn-login {
            width: 100%;
            padding: 0.8rem;
            font-weight: 500;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }
        .alert {
            display: none;
            margin-bottom: 1rem;
        }
        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
        }
        .loading-spinner {
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 0.5rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="https://www.cimtoys.com.br/img/logo.png" alt="Logo CIM">
            <h4>Sistema de Indicadores</h4>
        </div>

        <div class="alert" role="alert"></div>

        <form id="loginForm" method="POST">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="login" name="login" placeholder="Login" required>
                <label for="login">Login</label>
            </div>

            <div class="form-floating mb-4 position-relative">
                <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" required>
                <label for="senha">Senha</label>
                <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
            </div>

            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Entrar
            </button>
        </form>

        <div class="loading">
            <div class="loading-spinner"></div>
            <span>Verificando credenciais...</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const senhaInput = document.getElementById('senha');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                senhaInput.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const alert = document.querySelector('.alert');
            const loading = document.querySelector('.loading');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // Reset visual state
            alert.style.display = 'none';
            loading.style.display = 'block';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert.textContent = data.message;
                    alert.className = 'alert alert-danger';
                    alert.style.display = 'block';
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                alert.textContent = 'Erro ao conectar com o servidor. Tente novamente.';
                alert.className = 'alert alert-danger';
                alert.style.display = 'block';
                submitBtn.disabled = false;
            })
            .finally(() => {
                loading.style.display = 'none';
            });
        });
    </script>
</body>
</html>