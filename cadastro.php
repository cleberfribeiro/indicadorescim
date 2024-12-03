<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuários - Sistema CIM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #34495e;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f1c40f;
        }

        body, html {
            height: 100%;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Roboto', sans-serif;
        }

        .form-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            width: 450px;
            text-align: center;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-logo {
            width: 180px;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .form-logo:hover {
            transform: scale(1.05);
        }

        .form-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-control-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1rem;
            color: #95a5a6;
            transition: color 0.3s ease;
        }

        .form-control {
            padding-left: 45px;
            height: 50px;
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 73, 94, 0.25);
        }

        .form-control:focus + .form-control-icon {
            color: var(--primary-color);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #95a5a6;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .password-strength {
            height: 4px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s ease;
            background: #e0e0e0;
        }

        .strength-weak { width: 33%; background: var(--danger-color); }
        .strength-medium { width: 66%; background: var(--warning-color); }
        .strength-strong { width: 100%; background: var(--success-color); }

        .btn-register {
            height: 50px;
            border-radius: 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 73, 94, 0.3);
        }

        .validation-message {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: none;
            color: var(--danger-color);
        }

        .input-group.is-valid .form-control {
            border-color: var(--success-color);
        }

        .input-group.is-valid .form-control-icon {
            color: var(--success-color);
        }

        .input-group.is-invalid .form-control {
            border-color: var(--danger-color);
        }

        .input-group.is-invalid .form-control-icon {
            color: var(--danger-color);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            display: none;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Alert Messages -->
    <div class="alert" role="alert"></div>

    <div class="form-container">
        <div class="form-header">
            <img src="https://www.cimtoys.com.br/img/logo.png" alt="Logo CIM" class="form-logo">
            <h2 class="form-title">Cadastro de Usuários</h2>
        </div>

        <form id="registrationForm" action="processa_cadastro.php" method="POST" novalidate>
            <div class="input-group">
                <input type="text" class="form-control" name="nome_completo" id="nome_completo" placeholder="Nome Completo" required>
                <span class="form-control-icon"><i class="fas fa-user"></i></span>
                <div class="validation-message" data-field="nome_completo">Nome deve ter pelo menos 3 caracteres</div>
            </div>

            <div class="input-group">
                <input type="email" class="form-control" name="email" id="email" placeholder="E-mail" required>
                <span class="form-control-icon"><i class="fas fa-envelope"></i></span>
                <div class="validation-message" data-field="email">Digite um e-mail válido</div>
            </div>

            <div class="input-group">
                <input type="text" class="form-control" name="telefone" id="telefone" placeholder="Telefone WhatsApp" required>
                <span class="form-control-icon"><i class="fas fa-phone"></i></span>
                <div class="validation-message" data-field="telefone">Digite um telefone válido</div>
            </div>

            <div class="input-group">
                <input type="text" class="form-control" name="login" id="login" placeholder="Nome de Login" required>
                <span class="form-control-icon"><i class="fas fa-user-circle"></i></span>
                <div class="validation-message" data-field="login">Login deve ter pelo menos 4 caracteres</div>
            </div>

            <div class="input-group">
                <input type="password" class="form-control" name="senha" id="senha" placeholder="Digite a Senha" required>
                <span class="form-control-icon"><i class="fas fa-lock"></i></span>
                <span class="password-toggle" onclick="togglePassword('senha')"><i class="fas fa-eye"></i></span>
                <div class="password-strength"></div>
                <div class="validation-message" data-field="senha">
                    Senha deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas e números
                </div>
            </div>

            <div class="input-group">
                <input type="password" class="form-control" name="confirma_senha" id="confirma_senha" placeholder="Confirme a Senha" required>
                <span class="form-control-icon"><i class="fas fa-lock"></i></span>
                <span class="password-toggle" onclick="togglePassword('confirma_senha')"><i class="fas fa-eye"></i></span>
                <div class="validation-message" data-field="confirma_senha">As senhas não conferem</div>
            </div>

            <button type="submit" class="btn btn-primary btn-register w-100">
                <i class="fas fa-user-plus me-2"></i>Cadastrar Usuário
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <script>
        // Máscaras de input
        $(document).ready(function() {
            $('#telefone').mask('(00) 00000-0000');
        });

        // Toggle de visibilidade da senha
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = input.nextElementSibling.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Validações em tempo real
        const validators = {
            nome_completo: (value) => value.length >= 3,
            email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            telefone: (value) => value.replace(/\D/g, '').length >= 11,
            login: (value) => value.length >= 4,
            senha: (value) => {
                const hasUpper = /[A-Z]/.test(value);
                const hasLower = /[a-z]/.test(value);
                const hasNumber = /\d/.test(value);
                const hasLength = value.length >= 8;
                return hasUpper && hasLower && hasNumber && hasLength;
            },
            confirma_senha: (value) => value === document.getElementById('senha').value
        };

        // Atualiza força da senha
        document.getElementById('senha').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.querySelector('.password-strength');
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasLength = password.length >= 8;
            
            const strength = [hasUpper, hasLower, hasNumber, hasLength].filter(Boolean).length;
            
            strengthBar.className = 'password-strength';
            if (strength === 0) strengthBar.style.width = '0';
            else if (strength <= 2) strengthBar.classList.add('strength-weak');
            else if (strength === 3) strengthBar.classList.add('strength-medium');
            else strengthBar.classList.add('strength-strong');
        });

        // Validação de campos
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                const field = this.getAttribute('name');
                const isValid = validators[field](this.value);
                const inputGroup = this.closest('.input-group');
                const validationMessage = inputGroup.querySelector('.validation-message');
                
                inputGroup.classList.remove('is-valid', 'is-invalid');
                inputGroup.classList.add(isValid ? 'is-valid' : 'is-invalid');
                validationMessage.style.display = isValid ? 'none' : 'block';

                // Revalidar confirma_senha quando senha é alterada
                if (field === 'senha') {
                    const confirmaInput = document.getElementById('confirma_senha');
                    if (confirmaInput.value) {
                        const isConfirmaValid = validators.confirma_senha(confirmaInput.value);
                        const confirmaGroup = confirmaInput.closest('.input-group');
                        confirmaGroup.classList.remove('is-valid', 'is-invalid');
                        confirmaGroup.classList.add(isConfirmaValid ? 'is-valid' : 'is-invalid');
                        confirmaGroup.querySelector('.validation-message').style.display = 
                            isConfirmaValid ? 'none' : 'block';
                    }
                }
            });
        });

        // Submissão do formulário
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar todos os campos
            let isValid = true;
            for (const [field, validator] of Object.entries(validators)) {
                const input = document.getElementById(field);
                if (!validator(input.value)) {
                    isValid = false;
                    input.closest('.input-group').classList.add('is-invalid');
                }
            }

            if (!isValid) {
                showAlert('Por favor, corrija os erros no formulário.', 'danger');
                return;
            }

            // Mostrar loading
            document.querySelector('.loading-overlay').style.display = 'flex';

            // Enviar formulário
            fetch(this.action, {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Cadastro realizado com sucesso!', 'success');
                    setTimeout(() => window.location.href = 'login.php', 2000);
               } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Erro ao processar cadastro. Tente novamente.', 'danger');
            })
            .finally(() => {
                document.querySelector('.loading-overlay').style.display = 'none';
            });
        });

        // Função para mostrar alertas
        function showAlert(message, type) {
            const alert = document.querySelector('.alert');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alert.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Função para verificar força da senha
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                numbers: /\d/.test(password),
                special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
            };

            strength = Object.values(requirements).filter(Boolean).length;

            return {
                score: strength,
                requirements,
                text: strength <= 2 ? 'Fraca' : strength <= 3 ? 'Média' : 'Forte',
                class: strength <= 2 ? 'weak' : strength <= 3 ? 'medium' : 'strong'
            };
        }

        // Validação em tempo real do login (verifica se já existe)
        let loginCheckTimeout;
        document.getElementById('login').addEventListener('input', function() {
            clearTimeout(loginCheckTimeout);
            const login = this.value;
            const inputGroup = this.closest('.input-group');
            
            if (login.length >= 4) {
                loginCheckTimeout = setTimeout(() => {
                    fetch('verifica_login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `login=${encodeURIComponent(login)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            inputGroup.classList.add('is-invalid');
                            inputGroup.querySelector('.validation-message').textContent = 'Este login já está em uso';
                            inputGroup.querySelector('.validation-message').style.display = 'block';
                        }
                    });
                }, 500);
            }
        });

        // Máscaras e formatação
        const inputFormatters = {
            telefone: {
                mask: '(00) 00000-0000',
                validator: value => value.replace(/\D/g, '').length === 11
            },
            email: {
                validator: value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
            },
            nome_completo: {
                format: value => value.replace(/[^a-zA-ZÀ-ÿ\s]/g, '').replace(/\s+/g, ' '),
                validator: value => value.trim().split(' ').length >= 2 && value.length >= 3
            }
        };

        // Aplicar formatadores
        Object.entries(inputFormatters).forEach(([field, formatter]) => {
            const input = document.getElementById(field);
            if (input) {
                if (formatter.mask) {
                    $(input).mask(formatter.mask);
                }
                if (formatter.format) {
                    input.addEventListener('input', function() {
                        this.value = formatter.format(this.value);
                    });
                }
            }
        });

        // Prevenção de caracteres especiais no login
        document.getElementById('login').addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '').toLowerCase();
        });

        // Feedback visual para força da senha
        document.getElementById('senha').addEventListener('input', function() {
            const strength = checkPasswordStrength(this.value);
            const strengthBar = this.parentElement.querySelector('.password-strength');
            const validationMessage = this.parentElement.querySelector('.validation-message');
            
            strengthBar.className = `password-strength strength-${strength.class}`;
            
            let requirements = [];
            if (!strength.requirements.length) requirements.push('8 caracteres');
            if (!strength.requirements.uppercase) requirements.push('letra maiúscula');
            if (!strength.requirements.lowercase) requirements.push('letra minúscula');
            if (!strength.requirements.numbers) requirements.push('número');
            if (!strength.requirements.special) requirements.push('caractere especial');
            
            if (requirements.length > 0) {
                validationMessage.textContent = `A senha precisa conter: ${requirements.join(', ')}`;
                validationMessage.style.display = 'block';
            } else {
                validationMessage.style.display = 'none';
            }
        });

        // Tooltip para requisitos da senha
        const senhaTooltip = new bootstrap.Tooltip(document.getElementById('senha'), {
            title: 'A senha deve conter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais',
            placement: 'top',
            trigger: 'focus'
        });
    </script>
</body>
</html>