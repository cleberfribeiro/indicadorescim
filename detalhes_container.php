<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

// Verifica se está logado
$usuarioLogado = verificarLogin();

if (!isset($_GET['id'])) {
    die('ID não fornecido');
}

$id = $_GET['id'];
$container = lerContainer($id);

if (!$container) {
    die('Container não encontrado');
}

// Carregar itens do Plan Inner
$planInner = lerPlanInner($id);

// Carregar avarias
$avarias = lerAvarias($id);

// Carregar tipos de avarias
$tiposAvarias = lerTiposAvarias();

// Calcular métricas
$tempoDescarga = calcularTempoDescarga($container['horario_inicio'], $container['horario_fim']);
$custoTotal = calcularCustoDescarga($container['qtd_trabalhadores'], $container['valor_trabalhador']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Container - <?php echo htmlspecialchars($container['ordem_descarga']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet">
    <style>
        .btn-action {
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .btn-action:hover {
            background-color: #f3f4f6;
        }
        .modal-content {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .table-header {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
        }
        .status-avaria {
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        input:not([type="file"]):not([type="password"]):not([type="time"]):not([type="number"]),
        textarea,
        select {
            text-transform: uppercase !important;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 1cm;
            }
            @page {
                size: portrait;
                margin: 2cm;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Cabeçalho -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold">
                    Container: <?php echo htmlspecialchars($container['ordem_descarga']); ?>
                </h1>
                <p class="text-gray-600">
                    Registrado por <?php echo htmlspecialchars($container['usuario']); ?> 
                    em <?php echo date('d/m/Y', strtotime($container['data'])); ?>
                    às <?php echo substr($container['horario_inicio'], 0, 5); ?>
                </p>
            </div>
            <div class="flex gap-4">
                <button onclick="exportarPDF()" 
                        class="px-4 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 no-print">
                    Exportar PDF
                </button>
                <a href="recebimento.php" class="text-blue-600 hover:text-blue-800 font-medium no-print">← Voltar</a>
            </div>
        </div>

        <?php if (!empty($avarias)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 flex justify-between items-center">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2 status-avaria" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="font-bold">Container recebido com <?php echo count($avarias); ?> avaria(s)</span>
            </div>
            <a href="visualizar_avarias.php?id=<?php echo $id; ?>" 
               class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded no-print">
                Visualizar Avarias
            </a>
        </div>
        <?php endif; ?>

        <!-- Cards com métricas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-blue-700">Tempo de Descarga</h3>
                <p class="text-2xl font-bold text-blue-800"><?php echo number_format($tempoDescarga, 1); ?> horas</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-green-700">Custo Total</h3>
                <p class="text-2xl font-bold text-green-800">R$ <?php echo number_format($custoTotal, 2, ',', '.'); ?></p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-purple-700">Trabalhadores</h3>
                <p class="text-2xl font-bold text-purple-800"><?php echo $container['qtd_trabalhadores']; ?></p>
            </div>
        </div>

        <!-- Informações do Container -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-lg font-semibold">Informações do Container</h2>
                    <?php if (!empty($planInner)): ?>
                    <button type="button"
                            onclick="mostrarItensContainer()"
                            class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 no-print">
                        Mostrar Produtos do Container
                    </button>
                    <?php endif; ?>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <div>
                        <span class="font-medium">Número do Container:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($container['numero_container']); ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Nota Fiscal:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($container['numero_nf']); ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Origem:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($container['origem']); ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Tipo de Produto:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($container['tipo_produto']); ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-semibold mb-3">Informações da Descarga</h2>
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <div>
                        <span class="font-medium">Horário de Início:</span>
                        <span class="ml-2"><?php echo substr($container['horario_inicio'], 0, 5); ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Horário de Término:</span>
                        <span class="ml-2"><?php echo substr($container['horario_fim'], 0, 5); ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Valor por Trabalhador:</span>
                        <span class="ml-2">R$ <?php echo number_format($container['valor_trabalhador'], 2, ',', '.'); ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Uso de Esteira:</span>
                        <span class="ml-2"><?php echo $container['usa_esteira'] == '1' ? 'SIM' : 'NÃO'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção para registro de avarias -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6 no-print">
            <h2 class="text-lg font-semibold mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Registrar Nova Avaria
            </h2>
            <form id="formAvaria" class="space-y-4" onsubmit="registrarAvaria(event)">
                <input type="hidden" name="container_id" value="<?php echo $id; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Código do Produto
                        </label>
                        <input type="text" name="codigo_produto" required
                               onchange="buscarProduto(this.value)"
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5">
                        <p id="descricaoProduto" class="mt-1 text-sm text-gray-500"></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de Avaria
                        </label>
                        <div class="flex gap-2">
                            <select name="tipo_avaria" required
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5">
                                <option value="">SELECIONE O TIPO DE AVARIA</option>
                                <?php foreach ($tiposAvarias as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo); ?>">
                                    <?php echo htmlspecialchars($tipo); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="novoTipoAvaria()"
                                    class="px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300">
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Fotos da Avaria
                    </label>
                    <input type="file" name="fotos[]" multiple required
                           accept="image/jpeg,image/jpg,image/png"
                           class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2.5">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Descrição da Avaria
                    </label>
                    <textarea name="descricao" required rows="3"
                              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5"></textarea>
                </div>

                <button type="submit"
                        class="text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                    Registrar Avaria
                </button>
            </form>
        </div>

        <?php if ($container['observacoes']): ?>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-lg font-semibold mb-3">Observações</h2>
            <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-gray-700 whitespace-pre-line">
                    <?php echo nl2br(htmlspecialchars($container['observacoes'])); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Fotos -->
        <?php
        $arquivoFotos = "dados/fotos_{$id}.txt";
        if (file_exists($arquivoFotos)):
            $fotos = file($arquivoFotos, FILE_IGNORE_NEW_LINES);
            if (!empty($fotos)):
        ?>
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-lg font-semibold mb-3">Fotos do Container</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($fotos as $foto): ?>
                <div class="relative aspect-square">
                    <img src="dados/fotos/<?php echo $id; ?>/<?php echo htmlspecialchars($foto); ?>"
                         alt="Foto do container"
                         class="rounded-lg object-cover w-full h-full cursor-pointer"
                         onclick="abrirFoto(this.src)">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        endif; 
        ?>
    </div>

    <!-- Modal para visualização dos itens do container -->
    <div id="modalItensContainer" tabindex="-1" aria-hidden="true" 
         class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-7xl max-h-full">
            <div class="relative bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Produtos do Container <?php echo htmlspecialchars($container['ordem_descarga']); ?>
                    </h3>
                    <button type="button"
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
                            onclick="fecharModalItens()">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 md:p-5">
                    <div class="mb-4">
                        <input type="text" id="filtroTabela" 
                               placeholder="Filtrar produtos..."
                               class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                               onkeyup="filtrarTabela()">
                    </div>
                    <div class="modal-content">
                        <table class="w-full text-sm text-left text-gray-500" id="tabelaProdutos">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 table-header">
                                <tr>
                                    <th class="px-4 py-3">Código</th>
                                    <th class="px-4 py-3">Código de Barras</th>
                                    <th class="px-4 py-3">Descrição</th>
                                    <th class="px-4 py-3 text-center">CX</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planInner as $item): ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['codigo']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['cod_barras']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($item['descricao']); ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo htmlspecialchars($item['cx']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 font-medium">
                                    <td colspan="3" class="px-4 py-3 text-right">Total de Caixas:</td>
                                    <td class="px-4 py-3 text-center">
                                        <?php echo array_sum(array_column($planInner, 'cx')); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para visualização de fotos -->
    <div id="modalFoto" tabindex="-1" aria-hidden="true" 
         class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-4xl max-h-full">
            <div class="relative bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Visualização da Foto
                    </h3>
                    <button type="button" 
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
                            onclick="fecharModalFoto()">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 md:p-5">
                    <img id="fotoAmpliada" src="" alt="Foto ampliada" class="w-full rounded-lg">
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para novo tipo de avaria -->
    <div id="modalNovoTipoAvaria" tabindex="-1" aria-hidden="true" 
         class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-md max-h-full">
            <div class="relative bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Novo Tipo de Avaria
                    </h3>
                    <button type="button"
                            class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
                            data-modal-hide="modalNovoTipoAvaria">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 md:p-5">
                    <input type="text" id="novoTipoAvariaValor"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5"
                           placeholder="Digite o tipo de avaria">
                    <button onclick="salvarNovoTipoAvaria()"
                            class="mt-4 text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center w-full">
                        Adicionar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de produtos para impressão (inicialmente oculta) -->
    <div id="tabelaProdutosImpressao" class="hidden">
        <div class="container mx-auto p-8">
            <h1 class="text-2xl font-bold mb-6">
                Container: <?php echo htmlspecialchars($container['ordem_descarga']); ?>
                <br>
                <span class="text-base font-normal">
                    Registrado por <?php echo htmlspecialchars($container['usuario']); ?> 
                    em <?php echo date('d/m/Y', strtotime($container['data'])); ?>
                    às <?php echo substr($container['horario_inicio'], 0, 5); ?>
                </span>
            </h1>

            <!-- Informações principais -->
            <div class="mb-8 grid grid-cols-2 gap-4">
                <div>
                    <h2 class="text-lg font-semibold mb-2">Informações do Container</h2>
                    <div class="space-y-1">
                        <p><strong>Número:</strong> <?php echo htmlspecialchars($container['numero_container']); ?></p>
                        <p><strong>Nota Fiscal:</strong> <?php echo htmlspecialchars($container['numero_nf']); ?></p>
                        <p><strong>Origem:</strong> <?php echo htmlspecialchars($container['origem']); ?></p>
                        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($container['tipo_produto']); ?></p>
                    </div>
                </div>
                <div>
                    <h2 class="text-lg font-semibold mb-2">Informações da Descarga</h2>
                    <div class="space-y-1">
                        <p><strong>Início:</strong> <?php echo substr($container['horario_inicio'], 0, 5); ?></p>
                        <p><strong>Término:</strong> <?php echo substr($container['horario_fim'], 0, 5); ?></p>
                        <p><strong>Tempo Total:</strong> <?php echo number_format($tempoDescarga, 1); ?> horas</p>
                        <p><strong>Trabalhadores:</strong> <?php echo $container['qtd_trabalhadores']; ?></p>
                    </div>
                </div>
            </div>

            <!-- Produtos -->
            <h2 class="text-xl font-semibold mb-4">Produtos do Container</h2>
            <table class="w-full text-sm border-collapse mb-8">
                <thead>
                    <tr>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-50">Código</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-50">Código de Barras</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-50">Descrição</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-50">CX</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planInner as $item): ?>
                    <tr>
                        <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($item['codigo']); ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($item['cod_barras']); ?></td>
                        <td class="border border-gray-300 px-4 py-2"><?php echo htmlspecialchars($item['descricao']); ?></td>
                        <td class="border border-gray-300 px-4 py-2 text-center"><?php echo htmlspecialchars($item['cx']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-gray-50 font-medium">
                        <td colspan="3" class="border border-gray-300 px-4 py-2 text-right">Total de Caixas:</td>
                        <td class="border border-gray-300 px-4 py-2 text-center">
                            <?php echo array_sum(array_column($planInner, 'cx')); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script>
        function mostrarItensContainer() {
            const modal = new Modal(document.getElementById('modalItensContainer'));
            modal.show();
        }

        function fecharModalItens() {
            const modal = new Modal(document.getElementById('modalItensContainer'));
            modal.hide();
        }

        function abrirFoto(src) {
            document.getElementById('fotoAmpliada').src = src;
            const modal = new Modal(document.getElementById('modalFoto'));
            modal.show();
        }

        function fecharModalFoto() {
            const modal = new Modal(document.getElementById('modalFoto'));
            modal.hide();
        }

        function novoTipoAvaria() {
            const modal = new Modal(document.getElementById('modalNovoTipoAvaria'));
            modal.show();
        }

        function salvarNovoTipoAvaria() {
            const valor = document.getElementById('novoTipoAvariaValor').value.trim().toUpperCase();
            if (!valor) return;

            const formData = new FormData();
            formData.append('action', 'novo_tipo_avaria');
            formData.append('valor', valor);

            fetch('salvar_tipo_avaria.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.querySelector('select[name="tipo_avaria"]');
                    const option = new Option(valor, valor);
                    select.add(option);
                    select.value = valor;
                    
                    const modal = new Modal(document.getElementById('modalNovoTipoAvaria'));
                    modal.hide();
                    
                    document.getElementById('novoTipoAvariaValor').value = '';
                }
            });
        }

        function buscarProduto(codigo) {
            if (!codigo) return;
            
            fetch(`buscar_produto.php?codigo=${encodeURIComponent(codigo)}`)
            .then(response => response.json())
            .then(data => {
                const descricaoElement = document.getElementById('descricaoProduto');
                if (data.success && data.data) {
                    descricaoElement.textContent = data.data.descricao;
                    descricaoElement.classList.remove('text-red-500');
                    descricaoElement.classList.add('text-green-500');
                } else {
                    descricaoElement.textContent = 'Produto não encontrado';
                    descricaoElement.classList.remove('text-green-500');
                    descricaoElement.classList.add('text-red-500');
                }
            });
        }

        function exportarPDF() {
            // Mostrar tabela de produtos para impressão
            document.getElementById('tabelaProdutosImpressao').classList.remove('hidden');
            
            // Remover temporariamente os botões e elementos não imprimíveis
            const elementosNoPrint = document.querySelectorAll('.no-print');
            elementosNoPrint.forEach(el => el.style.display = 'none');
            
            // Imprimir
            window.print();
            
            // Restaurar elementos
            elementosNoPrint.forEach(el => el.style.display = '');
            
            // Ocultar tabela de produtos novamente
            document.getElementById('tabelaProdutosImpressao').classList.add('hidden');
        }

        function filtrarTabela() {
            const input = document.getElementById('filtroTabela');
            const filtro = input.value.toUpperCase();
            const tabela = document.getElementById('tabelaProdutos');
            const linhas = tabela.getElementsByTagName('tr');

            for (let i = 1; i < linhas.length - 1; i++) {
                const linha = linhas[i];
                const colunas = linha.getElementsByTagName('td');
                let mostrar = false;
                
                for (let j = 0; j < colunas.length; j++) {
                    const texto = colunas[j].textContent || colunas[j].innerText;
                    if (texto.toUpperCase().indexOf(filtro) > -1) {
                        mostrar = true;
                        break;
                    }
                }
                
                linha.style.display = mostrar ? '' : 'none';
            }
        }

        function registrarAvaria(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            fetch('registrar_avaria.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Avaria registrada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao registrar avaria: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao registrar avaria: ' + error);
            });
        }

        // Adicionar navegação por ENTER nos campos
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input:not([type="file"]), select, textarea');
            
            inputs.forEach((input, index) => {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const nextInput = inputs[index + 1];
                        if (nextInput) {
                            nextInput.focus();
                        }
                    }
                });
            });
        });

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