<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// Registra o tempo de início da sessão se ainda não existir
if (!isset($_SESSION['session_start_time'])) {
    $_SESSION['session_start_time'] = time();
}

// Função para verificar inatividade
function checkInactivity() {
    $inactive = 1800; // 30 minutos
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
        session_unset();
        session_destroy();
        header("Location: index.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}
checkInactivity();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Indicadores CIM - Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #34495e;
            --secondary-color: #2c3e50;
            --accent-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --light-color: #f5f6fa;
            --dark-color: #2c3e50;
            --transition-speed: 0.3s;
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: var(--light-color);
        }

        /* Navbar Melhorada */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            height: 80px;
            padding: 0 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .navbar-brand img {
            width: 120px;
            transition: transform var(--transition-speed);
        }

        .navbar-brand img:hover {
            transform: scale(1.05);
        }

        .navbar-title {
            font-size: 1.5rem;
            font-weight: 600;
            background: linear-gradient(45deg, #fff, #ecf0f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-text-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Indicadores de Status */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            transition: background var(--transition-speed);
        }

        .status-indicator:hover {
            background: rgba(255,255,255,0.2);
        }

        .status-indicator i {
            font-size: 1.1rem;
        }

        /* Menu Lateral Aprimorado */
        .sidebar {
            position: fixed;
            top: 80px;
            left: 0;
            width: 260px;
            bottom: 40px;
            background: linear-gradient(180deg, var(--secondary-color), var(--dark-color));
            padding: 1.5rem 1rem;
            transition: all var(--transition-speed);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--accent-color) transparent;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: var(--accent-color);
            border-radius: 20px;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .menu-item {
            position: relative;
            color: white;
            padding: 12px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            border-radius: 8px;
            transition: all var(--transition-speed);
            overflow: hidden;
        }

        .menu-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--accent-color);
            transform: scaleY(0);
            transition: transform var(--transition-speed);
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .menu-item:hover:before {
            transform: scaleY(1);
        }

        .menu-item i {
            min-width: 30px;
            font-size: 1.2rem;
            transition: transform var(--transition-speed);
        }

        .menu-item:hover i {
            transform: scale(1.1);
        }

        .menu-text {
            margin-left: 10px;
            font-weight: 500;
            transition: opacity var(--transition-speed);
        }

        .sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
        }

        /* Área de Conteúdo Principal */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            height: calc(100vh - 120px);
            transition: margin var(--transition-speed);
        }

        .main-content.collapsed {
            margin-left: 80px;
        }

        /* Iframe Melhorado */
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: white;
        }

        /* Rodapé Aprimorado */
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            padding: 8px 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
            font-size: 0.9rem;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
        }

        /* Botão de Logout */
        .btn-logout {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            color: white;
            text-decoration: none;
            background: rgba(231, 76, 60, 0.2);
            border-radius: 20px;
            transition: all var(--transition-speed);
        }

        .btn-logout:hover {
            background: rgba(231, 76, 60, 0.4);
            transform: translateY(-2px);
            color: white;
        }

        .btn-logout i {
            margin-right: 8px;
            transition: transform var(--transition-speed);
        }

        .btn-logout:hover i {
            transform: translateX(3px);
        }

        /* Animações e Efeitos */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out;
        }

        /* Notificações */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
/* Loading Spinner */
        .loading-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            margin-bottom: 10px;
            min-width: 300px;
        }
/* Estilos do Modal de Aviso */
.modal-content {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
}

.modal-footer {
    border-top: 1px solid #dee2e6;
}

.list-group-item {
    border: none;
    padding: 0.5rem 0;
}

.fade-scale {
    transform: scale(0.7);
    opacity: 0;
    transition: all .2s linear;
}

.fade-scale.show {
    transform: scale(1);
    opacity: 1;
}

/* Animação do ícone */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.modal .fa-exclamation-triangle {
    animation: pulse 2s infinite;
}

    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Toast Container -->

<!-- Modal de Aviso -->
<div class="modal fade" id="avisoModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Aviso Importante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-warning bg-opacity-25 rounded-circle me-3">
                        <i class="fas fa-code-branch fa-2x text-warning"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Sistema em Fase de Testes</h5>
                        <p class="mb-0 text-muted">Versão Beta 1.0</p>
                    </div>
                </div>
                <p class="mb-3">
                    Este sistema está atualmente em fase de desenvolvimento e testes. Durante este período:
                </p>
                <ul class="list-group list-group-flush mb-3">
                    <li class="list-group-item">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        Podem ocorrer instabilidades temporárias
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-bug text-danger me-2"></i>
                        Bugs podem ser encontrados e serão corrigidos
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-sync text-success me-2"></i>
                        Atualizações frequentes serão realizadas
                    </li>
                </ul>
                <div class="alert alert-warning mb-0">
                    <small>
                        <i class="fas fa-headset me-1"></i>
                        Em caso de problemas, entre em contato com o suporte técnico.
                    </small>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-light">
                <div class="text-muted">
                    <small>
                        <i class="fas fa-clock me-1"></i>
                        Esta mensagem fechará em <span id="countdown">10</span> segundos
                    </small>
                </div>
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal">
                    Entendi
                </button>
            </div>
        </div>
    </div>
</div>

    <div class="toast-container" id="toastContainer"></div>

    <!-- Navbar -->
    <nav class="navbar navbar-dark">
        <div class="navbar-brand">
            <img src="https://www.cimtoys.com.br/img/logo.png" alt="Logo CIM" id="logo">
            <span class="navbar-title">Sistema de Indicadores CIM</span>
        </div>
        <div class="navbar-text-right">
            <!-- Status do Usuário -->
            <div class="status-indicator">
                <i class="fas fa-user-circle"></i>
                <span class="username"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            </div>
            
            <!-- Tempo de Sessão -->
            <div class="status-indicator">
                <i class="fas fa-hourglass-half"></i>
                <span id="session-time">00:00:00</span>
            </div>
            
            <!-- Relógio -->
            <div class="status-indicator">
                <i class="fas fa-clock"></i>
                <span id="clock"></span>
            </div>

            <!-- Notificações -->
            <div class="status-indicator" id="notificationButton">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount">0</span>
            </div>
            
            <!-- Logout -->
            <a href="logout.php" class="btn-logout" id="logoutBtn">
                <i class="fas fa-sign-out-alt"></i>
                Sair
            </a>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="#" class="menu-item" id="toggleMenu">
            <i class="fas fa-bars"></i>
            <span class="menu-text">Menu</span>
        </a>
        
        <a href="dashboard.php" target="content-frame" class="menu-item" data-module="dashboard">
            <i class="fas fa-tachometer-alt"></i>
            <span class="menu-text">Dashboard</span>
        </a>
        
        <a href="gestao_estoque.php" target="content-frame" class="menu-item" data-module="estoque">
            <i class="fas fa-box"></i>
            <span class="menu-text">Gestão de Estoque</span>
            <span class="notification-badge">3</span>
        </a>
        
        <a href="recebimento.php" target="content-frame" class="menu-item" data-module="recebimento">
            <i class="fas fa-truck"></i>
            <span class="menu-text">Recebimento</span>
        </a>
        
        <a href="separacao.php" target="content-frame" class="menu-item" data-module="separacao">
            <i class="fas fa-clipboard-list"></i>
            <span class="menu-text">Separação</span>
        </a>
        
        <a href="expedicao.php" target="content-frame" class="menu-item" data-module="expedicao">
            <i class="fas fa-shipping-fast"></i>
            <span class="menu-text">Expedição</span>
        </a>
        
        <a href="avarias.php" target="content-frame" class="menu-item" data-module="avarias">
            <i class="fas fa-exclamation-triangle"></i>
            <span class="menu-text">Avarias</span>
        </a>
        
        <a href="mao_obra.php" target="content-frame" class="menu-item" data-module="mao_obra">
            <i class="fas fa-users"></i>
            <span class="menu-text">Mão de Obra</span>
        </a>
        
        <a href="custo_frete.php" target="content-frame" class="menu-item" data-module="custo_frete">
            <i class="fas fa-dollar-sign"></i>
            <span class="menu-text">Custo Frete</span>
        </a>
        
        <a href="configuracoes.php" target="content-frame" class="menu-item" data-module="configuracoes">
            <i class="fas fa-cogs"></i>
            <span class="menu-text">Configurações</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <iframe name="content-frame" id="content-frame" src="dashboard.php" onload="hideLoading()"></iframe>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>© <?php echo date('Y'); ?> CFR-Logística. Todos os direitos reservados.</p>
    </div>

    <!-- Modal de Confirmação de Logout -->
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Saída</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Deseja realmente sair do sistema?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="logout.php" class="btn btn-danger">Sair</a>
                </div>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>

<script>
// Sistema de Cache
const PageCache = {
    cache: new Map(),
    maxAge: 5 * 60 * 1000, // 5 minutos

    set(key, value) {
        this.cache.set(key, {
            value,
            timestamp: Date.now()
        });
    },

    get(key) {
        const item = this.cache.get(key);
        if (!item) return null;
        if (Date.now() - item.timestamp > this.maxAge) {
            this.cache.delete(key);
            return null;
        }
        return item.value;
    },

    clear() {
        this.cache.clear();
    }
};

// Sistema de Notificações
class NotificationSystem {
    constructor() {
        this.container = document.getElementById('toastContainer');
        this.count = 0;
        this.badge = document.getElementById('notificationCount');
    }

    show(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        const id = `toast-${Date.now()}`;
        toast.className = `toast show animate-fadeIn`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="toast-header bg-${type}">
                <strong class="me-auto text-white">${this.getIconForType(type)} ${this.getTitleForType(type)}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;

        this.container.appendChild(toast);
        this.updateCount(1);

        setTimeout(() => {
            toast.remove();
            this.updateCount(-1);
        }, duration);
    }

    getIconForType(type) {
        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-exclamation-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };
        return icons[type] || icons.info;
    }

    getTitleForType(type) {
        const titles = {
            success: 'Sucesso',
            error: 'Erro',
            warning: 'Atenção',
            info: 'Informação'
        };
        return titles[type] || titles.info;
    }

    updateCount(delta) {
        this.count = Math.max(0, this.count + delta);
        this.badge.textContent = this.count;
        this.badge.style.display = this.count > 0 ? 'flex' : 'none';
    }
}

const notifications = new NotificationSystem();

// Controle de Loading
function showLoading() {
    document.getElementById('loadingSpinner').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingSpinner').style.display = 'none';
}

// Gerenciamento de Sessão
class SessionManager {
    constructor() {
        this.startTime = Date.now();
        this.warningTime = 25 * 60 * 1000; // 25 minutos
        this.logoutTime = 30 * 60 * 1000; // 30 minutos
        this.warningShown = false;
        this.setupEventListeners();
    }

    setupEventListeners() {
        ['mousemove', 'keypress', 'click'].forEach(event => {
            document.addEventListener(event, () => this.resetTimer());
        });
        this.updateTimer();
        setInterval(() => this.checkSession(), 1000);
    }

    resetTimer() {
        this.startTime = Date.now();
        this.warningShown = false;
    }

    updateTimer() {
        const sessionTime = document.getElementById('session-time');
        setInterval(() => {
            const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
            const hours = Math.floor(elapsed / 3600).toString().padStart(2, '0');
            const minutes = Math.floor((elapsed % 3600) / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            sessionTime.textContent = `${hours}:${minutes}:${seconds}`;
        }, 1000);
    }

    checkSession() {
        const elapsed = Date.now() - this.startTime;
        if (elapsed > this.warningTime && !this.warningShown) {
            this.warningShown = true;
            notifications.show('Sua sessão irá expirar em 5 minutos!', 'warning');
        }
        if (elapsed > this.logoutTime) {
            window.location.href = 'logout.php?timeout=1';
        }
    }
}

// Menu Lateral
class SidebarManager {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.mainContent = document.getElementById('main-content');
        this.toggleBtn = document.getElementById('toggleMenu');
        this.activeModule = null;
        this.setupEventListeners();
    }

    setupEventListeners() {
        this.toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleSidebar();
        });

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', () => this.handleMenuClick(item));
        });
    }

    toggleSidebar() {
        this.sidebar.classList.toggle('collapsed');
        this.mainContent.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', this.sidebar.classList.contains('collapsed'));
    }

    handleMenuClick(item) {
        const module = item.dataset.module;
        if (module) {
            this.setActiveModule(module);
            showLoading();
        }
    }

    setActiveModule(module) {
        document.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove('active');
        });
        const activeItem = document.querySelector(`[data-module="${module}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
            this.activeModule = module;
        }
    }
}

// Relógio em Tempo Real
class Clock {
    constructor() {
        this.element = document.getElementById('clock');
        this.update();
        setInterval(() => this.update(), 1000);
    }

    update() {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        this.element.textContent = `${hours}:${minutes}:${seconds}`;
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    // Instancia todas as classes
    const sessionManager = new SessionManager();
    const sidebarManager = new SidebarManager();
    const clock = new Clock();

    // Setup do Modal de Logout
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    
    logoutBtn.addEventListener('click', (e) => {
        e.preventDefault();
        logoutModal.show();
    });

    // Restaura estado do sidebar
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebarManager.toggleSidebar();
    }

    // Loader para o iframe
    const contentFrame = document.getElementById('content-frame');
    contentFrame.addEventListener('load', hideLoading);
    contentFrame.addEventListener('error', () => {
        hideLoading();
        notifications.show('Erro ao carregar a página', 'error');
    });

    // Exemplo de notificação de boas-vindas
    setTimeout(() => {
        notifications.show(`Bem-vindo(a), ${document.querySelector('.username').textContent}!`, 'info');
    }, 1000);
});

// Prevenção de perda de dados
window.addEventListener('beforeunload', (e) => {
    const contentFrame = document.getElementById('content-frame');
    if (contentFrame.contentWindow.hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Atalhos de teclado
document.addEventListener('keydown', (e) => {
    // Ctrl + M: Toggle Menu
    if (e.ctrlKey && e.key === 'm') {
        e.preventDefault();
        document.getElementById('toggleMenu').click();
    }
    
    // Ctrl + H: Ir para Dashboard
    if (e.ctrlKey && e.key === 'h') {
        e.preventDefault();
        document.querySelector('[data-module="dashboard"]').click();
    }
});

// Modal de Aviso com Timer
document.addEventListener('DOMContentLoaded', () => {
    const avisoModal = new bootstrap.Modal(document.getElementById('avisoModal'), {
        keyboard: false
    });
    
    // Mostra o modal
    avisoModal.show();
    
    // Timer para fechar o modal
    let countdown = 60;
    const countdownElement = document.getElementById('countdown');
    
    const timer = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(timer);
            avisoModal.hide();
        }
    }, 1000);
    
    // Se o modal for fechado manualmente, limpa o timer
    document.getElementById('avisoModal').addEventListener('hidden.bs.modal', () => {
        clearInterval(timer);
    });
});

</script>
</body>
</html>