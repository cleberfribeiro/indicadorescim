<?php
session_start();
require_once 'funcoes_container.php';
require_once 'funcoes_auth.php';

// Verificar se está logado 
$usuarioLogado = verificarLogin();

if (!isset($_GET['id'])) {
    die('ID não fornecido');
}

$id = $_GET['id'];
$container = lerContainer($id);

if (!$container) {
    die('Container não encontrado');
}

$avarias = lerAvarias($id);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avarias do Container - <?php echo htmlspecialchars($container['ordem_descarga']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            .container {
                width: 100% !important;
                max-width: none !important;
                padding: 2cm !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: portrait;
                margin: 2cm;
            }
        }
        .foto-container {
            break-inside: avoid;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Cabeçalho -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold">
                    Avarias do Container: <?php echo htmlspecialchars($container['ordem_descarga']); ?>
                </h1>
                <p class="text-gray-600">
                    Container: <?php echo htmlspecialchars($container['numero_container']); ?><br>
                    Data da Descarga: <?php echo date('d/m/Y', strtotime($container['data'])); ?> às <?php echo substr($container['horario_inicio'], 0, 5); ?><br>
                    Registrado por: <?php echo htmlspecialchars($container['usuario']); ?>
                </p>
            </div>
            <div class="flex gap-4">
                <button onclick="window.print()" 
                        class="px-4 py-2 text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 no-print">
                    Imprimir
                </button>
                <a href="detalhes_container.php?id=<?php echo $id; ?>" 
                   class="text-blue-600 hover:text-blue-800 font-medium no-print">
                    ← Voltar
                </a>
            </div>
        </div>

        <?php if (empty($avarias)): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4">
                Nenhuma avaria registrada para este container.
            </div>
        <?php else: ?>
            <!-- Filtro -->
            <div class="mb-6 no-print">
                <label for="filtroCodigo" class="block text-sm font-medium text-gray-700 mb-2">
                    Filtrar por código do produto
                </label>
                <input type="text" id="filtroCodigo"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                       placeholder="Digite o código do produto para filtrar..."
                       onkeyup="filtrarAvarias(this.value)">
            </div>

            <!-- Lista de Avarias -->
            <div class="space-y-6" id="listaAvarias">
                <?php foreach ($avarias as $avaria): ?>
                    <div class="bg-white rounded-lg shadow-lg p-6 foto-container avaria-item" 
                         data-codigo="<?php echo htmlspecialchars($avaria['codigo_produto']); ?>">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-semibold mb-4">Detalhes da Avaria</h3>
                                <div class="space-y-3">
                                    <div>
                                        <p class="font-medium">Data/Hora do Registro:</p>
                                        <p class="text-gray-700"><?php echo date('d/m/Y H:i', strtotime($avaria['data'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="font-medium">Código do Produto:</p>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($avaria['codigo_produto']); ?></p>
                                    </div>
                                    <?php if (!empty($avaria['info_produto'])): ?>
                                    <div>
                                        <p class="font-medium">Descrição do Produto:</p>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($avaria['info_produto']['descricao']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-medium">Tipo de Avaria:</p>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($avaria['tipo_avaria']); ?></p>
                                    </div>
                                    <div>
                                        <p class="font-medium">Descrição da Avaria:</p>
                                        <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($avaria['descricao'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="font-medium">Registrado por:</p>
                                        <p class="text-gray-700"><?php echo htmlspecialchars($avaria['usuario']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($avaria['fotos'])): ?>
                            <div>
                                <h3 class="text-lg font-semibold mb-4">Fotos da Avaria</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <?php foreach ($avaria['fotos'] as $foto): ?>
                                    <div class="aspect-square">
                                        <img src="dados/avarias/<?php echo $id; ?>/fotos/<?php echo $avaria['id']; ?>/<?php echo htmlspecialchars($foto); ?>"
                                             alt="Foto da avaria"
                                             class="rounded-lg object-cover w-full h-full cursor-pointer"
                                             onclick="abrirFoto(this.src)">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script>
        function abrirFoto(src) {
            document.getElementById('fotoAmpliada').src = src;
            const modal = new Modal(document.getElementById('modalFoto'));
            modal.show();
        }

        function fecharModalFoto() {
            const modal = new Modal(document.getElementById('modalFoto'));
            modal.hide();
        }

        function filtrarAvarias(codigo) {
            codigo = codigo.toUpperCase();
            const itens = document.getElementsByClassName('avaria-item');
            
            Array.from(itens).forEach(item => {
                const codigoProduto = item.getAttribute('data-codigo').toUpperCase();
                if (codigo === '' || codigoProduto.includes(codigo)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>