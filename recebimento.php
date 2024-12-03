<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

ini_set('memory_limit', '256M');
set_time_limit(300);

$usuarioLogado = verificarLogin();

$diretorios = ['dados', 'dados/fotos'];
foreach ($diretorios as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Processamento de ações AJAX
if (isset($_POST['action'])) {
    $resultado = false;
    $mensagem = '';

    switch ($_POST['action']) {
        case 'novo_tipo':
            if (isset($_POST['valor'])) {
                $resultado = salvarNovoItem('tipos', $_POST['valor']);
                if ($resultado) {
                    registrar_log("Novo tipo de produto adicionado: {$_POST['valor']} por {$_SESSION['usuario']}");
                    $mensagem = 'Tipo adicionado com sucesso!';
                }
            }
            break;

        case 'nova_origem':
            if (isset($_POST['valor'])) {
                $resultado = salvarNovoItem('origens', $_POST['valor']);
                if ($resultado) {
                    registrar_log("Nova origem adicionada: {$_POST['valor']} por {$_SESSION['usuario']}");
                    $mensagem = 'Origem adicionada com sucesso!';
                }
            }
            break;

        case 'editar_tipo':
            if (isset($_POST['valorAntigo']) && isset($_POST['valorNovo'])) {
                $resultado = editarItem('tipos', $_POST['valorAntigo'], $_POST['valorNovo']);
                $mensagem = $resultado ? 'Tipo editado com sucesso!' : 'Erro ao editar tipo.';
            }
            break;

        case 'editar_origem':
            if (isset($_POST['valorAntigo']) && isset($_POST['valorNovo'])) {
                $resultado = editarItem('origens', $_POST['valorAntigo'], $_POST['valorNovo']);
                $mensagem = $resultado ? 'Origem editada com sucesso!' : 'Erro ao editar origem.';
            }
            break;

        case 'deletar_tipo':
            if (isset($_POST['valor'])) {
                $resultado = deletarItem('tipos', $_POST['valor']);
                $mensagem = $resultado ? 'Tipo excluído com sucesso!' : 'Erro ao excluir tipo.';
            }
            break;

        case 'deletar_origem':
            if (isset($_POST['valor'])) {
                $resultado = deletarItem('origens', $_POST['valor']);
                $mensagem = $resultado ? 'Origem excluída com sucesso!' : 'Erro ao excluir origem.';
            }
            break;
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $resultado, 'message' => $mensagem]);
        exit;
    } else {
        setMensagem($resultado ? 'success' : 'error', $mensagem);
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}
// Processamento do formulário principal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $id = gerarId();
    
    try {
        if (empty($_POST['data_registro'])) {
            throw new Exception('A data de registro é obrigatória.');
        }

        $numeroContainer = validarNumeroContainer($_POST['numero_container']);
        if (!$numeroContainer) {
            throw new Exception('Número do container inválido. Use o formato: ABCD 1234567 0');
        }

        $temPlanInner = isset($_FILES['plan_inner']) && $_FILES['plan_inner']['error'] === UPLOAD_ERR_OK;
        $temJustificativa = !empty($_POST['justificativa_plan_inner']);

        if (!$temPlanInner && !$temJustificativa) {
            throw new Exception('É necessário importar o Plan Inner ou fornecer uma justificativa.');
        }

        $dados = array(
            'id' => $id,
            'data' => $_POST['data_registro'],
            'numero_container' => $numeroContainer,
            'numero_nf' => $_POST['numero_nf'],
            'ordem_descarga' => $_POST['ordem_descarga'],
            'origem' => $_POST['origem'],
            'tipo_produto' => $_POST['tipo_produto'],
            'horario_inicio' => $_POST['horario_inicio'],
            'horario_fim' => $_POST['horario_fim'],
            'qtd_trabalhadores' => $_POST['qtd_trabalhadores'],
            'valor_trabalhador' => $_POST['valor_trabalhador'],
            'usa_esteira' => isset($_POST['usa_esteira']) ? '1' : '0',
            'observacoes' => isset($_POST['sem_observacoes']) ? 'SEM OBSERVAÇÕES' : $_POST['observacoes'],
            'justificativa_plan_inner' => $_POST['justificativa_plan_inner'] ?? '',
            'usuario' => $usuarioLogado['nome']
        );
        
        if ($temPlanInner) {
            $itens = processarPlanilha($_FILES['plan_inner']['tmp_name']);
            if (empty($itens)) {
                throw new Exception('Não foi possível ler os itens do Plan Inner.');
            }
            if (!salvarPlanInner($id, $itens)) {
                throw new Exception('Erro ao salvar os itens do Plan Inner.');
            }
        }

        if (!salvarContainer($dados)) {
            throw new Exception('Erro ao salvar os dados do container.');
        }
        
        if (isset($_FILES['fotos']) && !empty($_FILES['fotos']['tmp_name'][0])) {
            $fotosPermitidas = array('image/jpeg', 'image/jpg', 'image/png');
            
            foreach ($_FILES['fotos']['type'] as $tipo) {
                if (!in_array($tipo, $fotosPermitidas)) {
                    throw new Exception('Use apenas fotos JPG ou PNG.');
                }
            }

            if (!salvarFotos($id, $_FILES['fotos'])) {
                throw new Exception('Erro ao salvar as fotos.');
            }
        }

        registrar_log("Container {$numeroContainer} registrado com sucesso");
        setMensagem('success', 'Container registrado com sucesso!');
        header('Location: recebimento.php');
        exit;

    } catch (Exception $e) {
        setMensagem('error', $e->getMessage());
        header('Location: recebimento.php');
        exit;
    }
}

// Carregar dados
$estatisticas = calcularEstatisticas();
$containers = lerContainers();
$origens = lerItens('origens');
$tiposProduto = lerItens('tipos');

// Criar padrões se necessário
if (empty($origens)) {
    $origensDefault = array('CHINA', 'INDIA', 'VIETNAM', 'INDONESIA', 'MALAYSIA');
    foreach ($origensDefault as $origem) {
        salvarNovoItem('origens', $origem);
    }
    $origens = $origensDefault;
}

if (empty($tiposProduto)) {
    $tiposDefault = array('NATAL', 'HALLOWEEN', 'BRINQUEDOS', 'VIDROS');
    foreach ($tiposDefault as $tipo) {
        salvarNovoItem('tipos', $tipo);
    }
    $tiposProduto = $tiposDefault;
}

sort($origens);
sort($tiposProduto);

// Aplicar filtros
$filtro_ordem = isset($_GET['filtro_ordem']) ? $_GET['filtro_ordem'] : '';
$filtro_data = isset($_GET['filtro_data']) ? $_GET['filtro_data'] : '';
$filtro_origem = isset($_GET['filtro_origem']) ? $_GET['filtro_origem'] : '';
$filtro_avarias = isset($_GET['filtro_avarias']) ? $_GET['filtro_avarias'] : 'todos';

if (!empty($filtro_ordem) || !empty($filtro_data) || !empty($filtro_origem) || $filtro_avarias !== 'todos') {
    $containers_filtrados = array();
    foreach ($containers as $container) {
        $incluir = true;
        
        if (!empty($filtro_ordem) && stripos($container['ordem_descarga'], $filtro_ordem) === false) {
            $incluir = false;
        }
        
        if (!empty($filtro_data) && $container['data'] !== $filtro_data) {
            $incluir = false;
        }
        
        if (!empty($filtro_origem) && $container['origem'] !== $filtro_origem) {
            $incluir = false;
        }
        
        $num_avarias = contarAvarias($container['id']);
        if ($filtro_avarias === 'com_avarias' && $num_avarias === 0) {
            $incluir = false;
        }
        if ($filtro_avarias === 'sem_avarias' && $num_avarias > 0) {
            $incluir = false;
        }
        
        if ($incluir) {
            $containers_filtrados[] = $container;
        }
    }
    $containers = $containers_filtrados;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recebimento de Containers - CIM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet">
    <style>
        .compact-form .form-group { margin-bottom: 0.75rem; }
        .compact-form label { margin-bottom: 0.25rem; }
        .btn-action {
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .btn-action:hover { background-color: #f3f4f6; }
        input:not([type="file"]):not([type="password"]):not([type="time"]):not([type="number"]),
        textarea,
        select { text-transform: uppercase !important; }
        .success-message {
            background-color: #d1fae5;
            border-color: #059669;
            color: #065f46;
        }
        .error-message {
            background-color: #fee2e2;
            border-color: #dc2626;
            color: #991b1b;
        }
        .edit-button {
            cursor: pointer;
            padding: 0.25rem;
            transition: all 0.2s;
            color: #6b7280;
        }
        .edit-button:hover { color: #1d4ed8; }
        input[type="date"] {
            width: auto;
            min-width: 120px;
            max-width: 150px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Cabeçalho -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Recebimento de Containers</h1>
                <p class="text-gray-600">
                    Bem-vindo, <?php echo explode(' ', $usuarioLogado['nome'])[0]; ?>
                    <?php if (isAdmin()): ?>
                        <span class="text-blue-600">(Administrador)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php 
        $mensagem = getMensagem();
        if ($mensagem): 
        ?>
        <div class="p-4 mb-4 text-sm rounded-lg border <?php echo $mensagem['tipo'] === 'success' ? 'success-message' : 'error-message'; ?>" role="alert">
            <?php echo $mensagem['texto']; ?>
        </div>
        <?php endif; ?>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold text-gray-700">Total de Containers</h3>
                <p class="text-2xl font-bold text-blue-600"><?php echo $estatisticas['total']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold text-gray-700">Tempo Médio</h3>
                <p class="text-2xl font-bold text-green-600"><?php echo number_format($estatisticas['tempo_medio'], 1); ?> hrs</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold text-gray-700">Melhor Tempo</h3>
                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($estatisticas['melhor_tempo'], 1); ?> hrs</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-lg font-semibold text-gray-700">Tipo Mais Rápido</h3>
                <p class="text-2xl font-bold text-orange-600">
                    <?php 
                    $tipos = array_keys($estatisticas['ranking_tipos']);
                    echo !empty($tipos) ? htmlspecialchars($tipos[0]) : 'N/A';
                    ?>
                </p>
            </div>
        </div>

        <!-- Formulário de Cadastro -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Novo Recebimento</h2>
            
            <form action="" method="POST" enctype="multipart/form-data" class="compact-form">
                <!-- Data de Registro -->
                <div class="form-group mb-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Data de Registro
                    </label>
                    <input type="date" name="data_registro" required
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Número do Container -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Número do Container
                            <span class="text-xs text-gray-500">(ABCD 1234567 0)</span>
                        </label>
                        <input type="text" name="numero_container" required
                               pattern="[A-Z]{4}\s?\d{7}\s?[0-9A-Z]"
                               placeholder="ABCD 1234567 0"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>

                    <!-- Ordem de Descarga -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Ordem de Descarga
                        </label>
                        <input type="text" name="ordem_descarga" required
                               placeholder="Ex: W505"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>

                    <!-- Número da NF -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Número da Nota Fiscal
                        </label>
                        <input type="text" name="numero_nf" required
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <!-- Origem -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">Origem</label>
                        <div class="flex gap-2">
                            <select name="origem" required
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">SELECIONE A ORIGEM</option>
                                <?php foreach ($origens as $origem): ?>
                                    <option value="<?php echo htmlspecialchars($origem); ?>">
                                        <?php echo htmlspecialchars($origem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="novaOrigem()"
                                    class="px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                +
                            </button>
                            <button type="button" onclick="editarOrigem()"
                                    class="edit-button" title="Editar Origens">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Tipo de Produto -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">Tipo de Produto</label>
                        <div class="flex gap-2">
                            <select name="tipo_produto" required
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">SELECIONE O TIPO</option>
                                <?php foreach ($tiposProduto as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>">
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="novoTipo()"
                                    class="px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                +
                            </button>
                            <button type="button" onclick="editarTipo()"
                                    class="edit-button" title="Editar Tipos">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Plan Inner e Justificativa -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Plan Inner (Excel)
                        </label>
                        <input type="file" name="plan_inner" accept=".xlsx"
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2.5"
                               onchange="toggleJustificativa(this)">
                        <p class="mt-1 text-xs text-gray-500">Arquivo Excel (.xlsx) com as colunas necessárias</p>
                    </div>
                    
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Justificativa (caso não tenha Plan Inner)
                        </label>
                        <textarea name="justificativa_plan_inner" id="justificativa_plan_inner" rows="2"
                                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                    <!-- Horário de Início -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Horário de Início
                        </label>
                        <input type="time" name="horario_inicio" required
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>

                    <!-- Horário de Término -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Horário de Término
                        </label>
                        <input type="time" name="horario_fim" required
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>

                    <!-- Quantidade de Trabalhadores -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Quantidade de Trabalhadores
                        </label>
                        <input type="number" name="qtd_trabalhadores" required min="1"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>

                    <!-- Valor por Trabalhador -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700">
                            Valor por Trabalhador (R$)
                        </label>
                        <input type="number" name="valor_trabalhador" required min="0" step="0.01"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>

                <!-- Uso de Esteira -->
                <div class="flex items-center mt-4">
                    <input type="checkbox" name="usa_esteira" value="1"
                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                    <label class="ml-2 text-sm font-medium text-gray-700">
                        Utilização de Esteira
                    </label>
                </div>

                <!-- Observações -->
                <div class="mt-4">
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="sem_observacoes" name="sem_observacoes"
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                               onchange="toggleObservacoes(this)">
                        <label class="ml-2 text-sm font-medium text-gray-700">
                            Sem observações
                        </label>
                    </div>
                    <textarea name="observacoes" id="observacoes" rows="3"
                              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                </div>

                <!-- Fotos -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">
                        Fotos do Container
                    </label>
                    <input type="file" name="fotos[]" multiple accept="image/jpeg,image/jpg,image/png"
                           class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2.5">
                </div>

                <button type="submit"
                        class="mt-6 text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center w-full">
                    Registrar Container
                </button>
            </form>
        </div>
        <!-- Lista de Containers -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Containers Registrados</h2>
            </div>

            <!-- Filtros em linha única -->
            <div class="flex flex-wrap gap-4 items-center mb-6 bg-gray-50 p-4 rounded-lg">
                <div class="w-48">
                    <input type="text" id="filtro_ordem" placeholder="Ordem Container" 
                           value="<?php echo htmlspecialchars($filtro_ordem); ?>"
                           class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full p-2">
                </div>

                <div class="w-40">
                    <input type="date" id="filtro_data"
                           value="<?php echo htmlspecialchars($filtro_data); ?>"
                           class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full p-2">
                </div>

                <div class="w-48">
                    <select id="filtro_origem"
                            class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full p-2">
                        <option value="">TODAS AS ORIGENS</option>
                        <?php foreach ($origens as $origem): ?>
                            <option value="<?php echo htmlspecialchars($origem); ?>"
                                    <?php if ($filtro_origem === $origem) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($origem); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="filtro_avarias" value="todos" 
                               <?php if ($filtro_avarias === 'todos') echo 'checked'; ?>
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300">
                        <span class="ml-2 text-sm">Todos</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="filtro_avarias" value="com_avarias"
                               <?php if ($filtro_avarias === 'com_avarias') echo 'checked'; ?>
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300">
                        <span class="ml-2 text-sm">Com Avarias</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="filtro_avarias" value="sem_avarias"
                               <?php if ($filtro_avarias === 'sem_avarias') echo 'checked'; ?>
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300">
                        <span class="ml-2 text-sm">Sem Avarias</span>
                    </label>
                </div>

                <div class="flex gap-2">
                    <button onclick="aplicarFiltros()"
                            class="px-4 py-2 text-white bg-blue-700 text-sm font-medium rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                        Aplicar Filtros
                    </button>
                    <button onclick="limparFiltros()"
                            class="px-4 py-2 text-gray-700 bg-white text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-100 focus:ring-4 focus:ring-gray-200">
                        Limpar
                    </button>
                </div>
            </div>
            <!-- Tabela de Containers -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Ordem</th>
                            <th scope="col" class="px-6 py-3">Data</th>
                            <th scope="col" class="px-6 py-3">Origem</th>
                            <th scope="col" class="px-6 py-3">Tipo</th>
                            <th scope="col" class="px-6 py-3">Tempo</th>
                            <th scope="col" class="px-6 py-3">Custo</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($containers as $container): ?>
                            <?php 
                                $tempoDescarga = calcularTempoDescarga($container['horario_inicio'], $container['horario_fim']);
                                $custoTotal = calcularCustoDescarga($container['qtd_trabalhadores'], $container['valor_trabalhador']);
                                $numAvarias = contarAvarias($container['id']);
                            ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($container['ordem_descarga']); ?></td>
                                <td class="px-6 py-4"><?php echo date('d/m/Y', strtotime($container['data'])); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($container['origem']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($container['tipo_produto']); ?></td>
                                <td class="px-6 py-4"><?php echo number_format($tempoDescarga, 1); ?> hrs</td>
                                <td class="px-6 py-4">R$ <?php echo number_format($custoTotal, 2, ',', '.'); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($numAvarias > 0): ?>
                                        <span class="text-red-600 font-medium">
                                            COM AVARIAS (<?php echo $numAvarias; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-green-600 font-medium">
                                            SEM AVARIAS
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button type="button" 
                                                onclick="window.location.href='detalhes_container.php?id=<?php echo $container['id']; ?>'"
                                                class="btn-action text-blue-600 hover:text-blue-900"
                                                title="Visualizar Detalhes">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>

                                        <?php if (isAdmin()): ?>
                                        <button type="button"
                                                onclick="confirmarExclusao('<?php echo $container['id']; ?>', '<?php echo $container['ordem_descarga']; ?>')"
                                                class="btn-action text-red-600 hover:text-red-900"
                                                title="Excluir Container">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Modal Novo Item -->
    <div id="modalNovoItem" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-md max-h-full">
            <div class="relative bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900" id="modalTitulo">
                        Novo Item
                    </h3>
                    <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" data-modal-hide="modalNovoItem">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 md:p-5">
                    <input type="text" id="novoItemValor" 
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                           placeholder="Digite o valor">
                    <input type="hidden" id="novoItemTipo" value="">
                    <button onclick="salvarNovoItem()" 
                            class="mt-4 text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center w-full">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Itens -->
    <div id="modalEditarItens" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-4xl max-h-full">
            <div class="relative bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900" id="modalEditarTitulo">
                        Editar Itens
                    </h3>
                    <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center" onclick="fecharModalEditar()">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 md:p-5">
                    <input type="hidden" id="editarTipo" value="">
                    <div class="overflow-y-auto max-h-96">
                        <table class="w-full text-sm text-left text-gray-500" id="tabelaEditarItens">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Item</th>
                                    <th scope="col" class="px-6 py-3">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Preenchido via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script>
        function toggleJustificativa(input) {
            const justificativaField = document.getElementById('justificativa_plan_inner');
            if (input.files.length > 0) {
                justificativaField.value = '';
                justificativaField.disabled = true;
            } else {
                justificativaField.disabled = false;
            }
        }

        function toggleObservacoes(checkbox) {
            const observacoesField = document.getElementById('observacoes');
            if (checkbox.checked) {
                observacoesField.value = 'SEM OBSERVAÇÕES';
                observacoesField.disabled = true;
            } else {
                observacoesField.value = '';
                observacoesField.disabled = false;
            }
        }

        function novaOrigem() {
            document.getElementById('modalTitulo').textContent = 'Nova Origem';
            document.getElementById('novoItemTipo').value = 'origens';
            document.getElementById('novoItemValor').value = '';
            const modal = new Modal(document.getElementById('modalNovoItem'));
            modal.show();
        }

        function novoTipo() {
            document.getElementById('modalTitulo').textContent = 'Novo Tipo de Produto';
            document.getElementById('novoItemTipo').value = 'tipos';
            document.getElementById('novoItemValor').value = '';
            const modal = new Modal(document.getElementById('modalNovoItem'));
            modal.show();
        }

        function editarOrigem() {
            document.getElementById('modalEditarTitulo').textContent = 'Editar Origens';
            document.getElementById('editarTipo').value = 'origens';
            preencherTabelaEdicao(<?php echo json_encode($origens); ?>);
            const modal = new Modal(document.getElementById('modalEditarItens'));
            modal.show();
        }

        function editarTipo() {
            document.getElementById('modalEditarTitulo').textContent = 'Editar Tipos de Produto';
            document.getElementById('editarTipo').value = 'tipos';
            preencherTabelaEdicao(<?php echo json_encode($tiposProduto); ?>);
            const modal = new Modal(document.getElementById('modalEditarItens'));
            modal.show();
        }

        function preencherTabelaEdicao(itens) {
            const tbody = document.querySelector('#tabelaEditarItens tbody');
            tbody.innerHTML = '';
            
            itens.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'bg-white border-b';
                tr.innerHTML = `
                    <td class="px-6 py-4">
                        <input type="text" value="${item}" 
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2">
                    </td>
                    <td class="px-6 py-4">
                        <button onclick="salvarEdicao(this)" class="text-blue-600 hover:text-blue-900 mr-3">
                            Salvar
                        </button>
                        <button onclick="deletarItem(this)" class="text-red-600 hover:text-red-900">
                            Excluir
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function salvarEdicao(button) {
            const row = button.closest('tr');
            const input = row.querySelector('input');
            const valorAntigo = input.defaultValue;
            const valorNovo = input.value.trim();
            const tipo = document.getElementById('editarTipo').value;

            if (!valorNovo) return;

            const formData = new FormData();
            formData.append('action', 'editar_' + tipo);
            formData.append('valorAntigo', valorAntigo);
            formData.append('valorNovo', valorNovo);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Erro ao salvar alteração');
                }
            });
        }

        function deletarItem(button) {
            const row = button.closest('tr');
            const input = row.querySelector('input');
            const valor = input.value;
            const tipo = document.getElementById('editarTipo').value;

            if (confirm(`Tem certeza que deseja excluir "${valor}"?`)) {
                const formData = new FormData();
                formData.append('action', 'deletar_' + tipo);
                formData.append('valor', valor);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Erro ao excluir item');
                    }
                });
            }
        }

        function fecharModalEditar() {
            const modal = new Modal(document.getElementById('modalEditarItens'));
            modal.hide();
        }

        function salvarNovoItem() {
            const tipo = document.getElementById('novoItemTipo').value;
            const valor = document.getElementById('novoItemValor').value.trim();
            
            if (!valor) return;

            const formData = new FormData();
            formData.append('action', tipo === 'origens' ? 'nova_origem' : 'novo_tipo');
            formData.append('valor', valor);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Erro ao adicionar item');
                }
            });
        }

        function confirmarExclusao(id, ordem) {
            if (confirm(`Tem certeza que deseja excluir o container com ordem ${ordem}?`)) {
                window.location.href = 'deletar_container.php?id=' + id;
            }
        }

        function aplicarFiltros() {
            const ordem = document.getElementById('filtro_ordem').value;
            const data = document.getElementById('filtro_data').value;
            const origem = document.getElementById('filtro_origem').value;
            const avarias = document.querySelector('input[name="filtro_avarias"]:checked').value;

            window.location.href = `recebimento.php?filtro_ordem=${ordem}&filtro_data=${data}&filtro_origem=${origem}&filtro_avarias=${avarias}`;
        }

        function limparFiltros() {
            window.location.href = 'recebimento.php';
        }

        // Converter inputs para maiúsculas
        document.querySelectorAll('input:not([type="file"]):not([type="password"]):not([type="time"]):not([type="number"]), textarea')
            .forEach(function(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            });
    </script>
</body>
</html>