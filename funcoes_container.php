<?php
require_once 'funcoes_auth.php';

// Verificar se o Composer está instalado corretamente
$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die('Erro: Arquivo vendor/autoload.php não encontrado. Execute "composer install" na pasta do projeto.');
}

require_once $autoloadFile;

use PhpOffice\PhpSpreadsheet\IOFactory;

function contarAvarias($containerId) {
    $diretorio = "dados/avarias/{$containerId}/";
    if (!is_dir($diretorio)) {
        return 0;
    }
    
    return count(glob($diretorio . '*.txt'));
}

function editarItem($tipo, $valorAntigo, $valorNovo) {
    $arquivo = "dados/{$tipo}.txt";
    if (!file_exists($arquivo)) {
        return false;
    }
    
    $itens = array_unique(file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $itens = array_map('trim', $itens);
    
    $chave = array_search(strtoupper($valorAntigo), array_map('strtoupper', $itens));
    if ($chave !== false) {
        $itens[$chave] = strtoupper(trim($valorNovo));
        file_put_contents($arquivo, implode(PHP_EOL, array_unique($itens)) . PHP_EOL);
        registrar_log("Item editado em {$tipo}: {$valorAntigo} -> {$valorNovo}");
        return true;
    }
    return false;
}

function deletarItem($tipo, $valor) {
    $arquivo = "dados/{$tipo}.txt";
    if (!file_exists($arquivo)) {
        return false;
    }
    
    $itens = array_unique(file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $itens = array_map('trim', $itens);
    
    $chave = array_search(strtoupper($valor), array_map('strtoupper', $itens));
    if ($chave !== false) {
        unset($itens[$chave]);
        file_put_contents($arquivo, implode(PHP_EOL, array_unique($itens)) . PHP_EOL);
        registrar_log("Item removido de {$tipo}: {$valor}");
        return true;
    }
    return false;
}

function salvarNovoItem($tipo, $valor) {
    if (!is_dir('dados')) {
        mkdir('dados', 0777, true);
    }
    
    $arquivo = "dados/{$tipo}.txt";
    $valor = strtoupper(trim($valor));
    
    if (!file_exists($arquivo)) {
        touch($arquivo);
        chmod($arquivo, 0666);
    }
    
    $itens = file_exists($arquivo) ? 
        array_unique(file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 
        array();
    
    if (!in_array($valor, array_map('strtoupper', $itens))) {
        $resultado = file_put_contents($arquivo, $valor . PHP_EOL, FILE_APPEND);
        if ($resultado !== false) {
            registrar_log("Novo item adicionado em {$tipo}: {$valor}");
            return true;
        }
    }
    
    return false;
}

function lerItens($tipo) {
    $arquivo = "dados/{$tipo}.txt";
    if (!file_exists($arquivo)) {
        return array();
    }
    $itens = array_unique(file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $itens = array_map('trim', $itens);
    $itens = array_filter($itens);
    return $itens;
}

function lerTiposAvarias() {
    $arquivo = "dados/tipos_avarias.txt";
    if (!file_exists($arquivo)) {
        $tipos_padrao = array(
            "CAIXAS MOLHADAS",
            "CAIXAS RASGADAS",
            "PRODUTOS QUEBRADOS",
            "PRODUTOS COM ETIQUETA ORIGEM ERRADOS",
            "PRODUTO NA COR ERRADA",
            "PRODUTO MOFADO",
            "PRODUTO AMASSADO"
        );
        file_put_contents($arquivo, implode(PHP_EOL, $tipos_padrao));
        return $tipos_padrao;
    }
    return array_unique(file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
}

function salvarTipoAvaria($tipo) {
    $arquivo = "dados/tipos_avarias.txt";
    $tipo = strtoupper(trim($tipo));
    file_put_contents($arquivo, $tipo . PHP_EOL, FILE_APPEND);
}

function buscarProdutoExcel($codigo) {
    try {
        $arquivo = "dados/dados_itens.xls";
        if (!file_exists($arquivo)) {
            return null;
        }

        $spreadsheet = IOFactory::load($arquivo);
        $worksheet = $spreadsheet->getActiveSheet();
        
        foreach ($worksheet->getRowIterator(2) as $row) {
            $codigoProduto = $worksheet->getCell('A' . $row->getRowIndex())->getValue();
            if (strtoupper(trim($codigoProduto)) === strtoupper(trim($codigo))) {
                $produto = array();
                $produto['codigo'] = $codigoProduto;
                $produto['descricao'] = $worksheet->getCell('B' . $row->getRowIndex())->getValue();
                $produto['grupo'] = $worksheet->getCell('D' . $row->getRowIndex())->getValue();
                $produto['subgrupo'] = $worksheet->getCell('E' . $row->getRowIndex())->getValue();
                $produto['linha'] = $worksheet->getCell('F' . $row->getRowIndex())->getValue();
                return $produto;
            }
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

function validarNumeroContainer($numero) {
    $numero = strtoupper(str_replace(array(' ', '-'), '', $numero));
    if (!preg_match('/^[A-Z]{4}\d{7}[0-9A-Z]$/', $numero)) {
        return false;
    }
    return substr($numero, 0, 4) . ' ' . substr($numero, 4, 7) . ' ' . substr($numero, -1);
}

function calcularTempoDescarga($inicio, $fim) {
    $start = new DateTime($inicio);
    $end = new DateTime($fim);
    
    $interval = $start->diff($end);
    $horasTotal = $interval->h + ($interval->days * 24);
    
    $almoco_inicio = new DateTime($start->format('Y-m-d') . ' 12:00:00');
    $almoco_fim = new DateTime($start->format('Y-m-d') . ' 13:00:00');
    
    if ($start <= $almoco_inicio && $end >= $almoco_fim) {
        $horasTotal -= 1; // Desconta 1 hora do almoço
    }
    
    return $horasTotal;
}

function calcularCustoDescarga($qtdTrabalhadores, $valorPorTrabalhador) {
    return $qtdTrabalhadores * $valorPorTrabalhador;
}

function processarPlanilha($arquivo) {
    try {
        if (!is_uploaded_file($arquivo)) {
            throw new Exception('Arquivo não foi enviado corretamente.');
        }

        registrar_log("Iniciando processamento da planilha");

        $spreadsheet = IOFactory::load($arquivo);
        $worksheet = $spreadsheet->getActiveSheet();
        
        $itens = array();
        $row = 2;
        while (true) {
            $codigo = $worksheet->getCell('A' . $row)->getValue();
            
            if (empty($codigo)) {
                break;
            }
            
            $codBarras = $worksheet->getCell('B' . $row)->getValue();
            $descricao = $worksheet->getCell('C' . $row)->getValue();
            $cx = $worksheet->getCell('D' . $row)->getValue();
            
            if (!empty($codigo)) {
                $item = array();
                $item['codigo'] = strtoupper(trim((string)$codigo));
                $item['cod_barras'] = strtoupper(trim((string)$codBarras));
                $item['descricao'] = strtoupper(trim((string)$descricao));
                $item['cx'] = is_numeric($cx) ? (int)$cx : 0;
                $itens[] = $item;
                
                registrar_log("Processada linha $row: Código=$codigo, CX=$cx");
            }
            
            $row++;
        }

        if (empty($itens)) {
            registrar_log("Nenhum item encontrado na planilha");
            throw new Exception('Nenhum item encontrado na planilha.');
        }

        registrar_log("Total de itens processados: " . count($itens));
        return $itens;

    } catch (Exception $e) {
        registrar_log("Erro ao processar planilha: " . $e->getMessage());
        throw new Exception('Não foi possível ler os itens do Plan Inner. Verifique o formato do arquivo.');
    }
}

function gerarId() {
    return 'CNT-' . strtoupper(uniqid());
}

function lerContainers() {
    if (!file_exists("dados/containers.txt")) {
        return array();
    }
    
    $containers = array();
    $linhas = file("dados/containers.txt", FILE_IGNORE_NEW_LINES);
    
    foreach ($linhas as $linha) {
        $dados = explode("|", $linha);
        if (count($dados) >= 15) {
            $container = array();
            $container['id'] = $dados[0];
            $container['data'] = $dados[1];
            $container['numero_container'] = $dados[2];
            $container['numero_nf'] = $dados[3];
            $container['ordem_descarga'] = $dados[4];
            $container['origem'] = $dados[5];
            $container['tipo_produto'] = $dados[6];
            $container['horario_inicio'] = $dados[7];
            $container['horario_fim'] = $dados[8];
            $container['qtd_trabalhadores'] = $dados[9];
            $container['valor_trabalhador'] = $dados[10];
            $container['usa_esteira'] = $dados[11];
            $container['observacoes'] = isset($dados[13]) ? $dados[13] : '';
            $container['justificativa_plan_inner'] = isset($dados[14]) ? $dados[14] : '';
            $container['usuario'] = isset($dados[15]) ? $dados[15] : 'Sistema';
            
            $containers[] = $container;
        }
    }
    
    return array_reverse($containers);
}

function lerContainer($id) {
    $containers = lerContainers();
    foreach ($containers as $container) {
        if ($container['id'] === $id) {
            return $container;
        }
    }
    return null;
}

function salvarContainer($dados) {
    $usuarioLogado = getUsuarioLogado();
    $dados['usuario'] = $usuarioLogado ? $usuarioLogado['nome'] : 'Sistema';
    
    $linha = implode("|", array(
        $dados['id'],
        $dados['data'],
        strtoupper($dados['numero_container']),
        strtoupper($dados['numero_nf']),
        strtoupper($dados['ordem_descarga']),
        strtoupper($dados['origem']),
        strtoupper($dados['tipo_produto']),
        $dados['horario_inicio'],
        $dados['horario_fim'],
        $dados['qtd_trabalhadores'],
        $dados['valor_trabalhador'],
        $dados['usa_esteira'],
        '',  // Campo avarias removido
        strtoupper($dados['observacoes']),
        strtoupper($dados['justificativa_plan_inner'] ?? ''),
        $dados['usuario']
    ));
    
    file_put_contents("dados/containers.txt", $linha . PHP_EOL, FILE_APPEND);
    registrar_log("Container {$dados['numero_container']} registrado por {$dados['usuario']}");
    return true;
}

function salvarPlanInner($containerId, $itens) {
    if (empty($itens)) {
        return false;
    }

    $arquivo = "dados/plan_inner_{$containerId}.txt";
    $conteudo = '';
    
    foreach ($itens as $item) {
        $linha = implode("|", array(
            $item['codigo'],
            $item['cod_barras'],
            $item['descricao'],
            $item['cx']
        )) . PHP_EOL;
        $conteudo .= $linha;
    }
    
    return file_put_contents($arquivo, $conteudo) !== false;
}

function lerPlanInner($containerId) {
    $arquivo = "dados/plan_inner_{$containerId}.txt";
    if (!file_exists($arquivo)) {
        return array();
    }
    
    $itens = array();
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
    foreach ($linhas as $linha) {
        $dados = explode("|", $linha);
        if (count($dados) >= 4) {
            $item = array();
            $item['codigo'] = $dados[0];
            $item['cod_barras'] = $dados[1];
            $item['descricao'] = $dados[2];
            $item['cx'] = $dados[3];
            $itens[] = $item;
        }
    }
    return $itens;
}

function salvarFotos($containerId, $fotos) {
    $diretorio = "dados/fotos/{$containerId}/";
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }
    
    $fotosInfo = array();
    foreach ($fotos['tmp_name'] as $key => $tmp_name) {
        if (is_uploaded_file($tmp_name)) {
            $nomeArquivo = uniqid() . '_' . strtoupper($fotos['name'][$key]);
            $destino = $diretorio . $nomeArquivo;
            
            if (move_uploaded_file($tmp_name, $destino)) {
                $fotosInfo[] = $nomeArquivo;
            }
        }
    }
    
    if (!empty($fotosInfo)) {
        file_put_contents("dados/fotos_{$containerId}.txt", implode(PHP_EOL, $fotosInfo));
        return true;
    }
    
    return false;
}

function salvarAvaria($containerId, $dados) {
    $diretorio = "dados/avarias/{$containerId}/";
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }
    
    $idAvaria = uniqid();
    $arquivo = $diretorio . $idAvaria . '.txt';
    
    // Buscar informações do produto
    $infoProduto = array();
    $produtoInfo = buscarProdutoExcel($dados['codigo_produto']);
    if ($produtoInfo !== null) {
        $infoProduto = $produtoInfo;
    }
    
    $conteudo = implode('|', array(
        date('Y-m-d H:i:s'),
        $dados['codigo_produto'],
        $dados['tipo_avaria'],
        $dados['descricao'],
        json_encode($infoProduto),
        $_SESSION['usuario']
    ));
    
    if (file_put_contents($arquivo, $conteudo)) {
        if (!empty($dados['fotos']['tmp_name'])) {
            $fotosDir = $diretorio . 'fotos/' . $idAvaria . '/';
            if (!is_dir($fotosDir)) {
                mkdir($fotosDir, 0777, true);
            }
            
            foreach ($dados['fotos']['tmp_name'] as $key => $tmp_name) {
                if (is_uploaded_file($tmp_name)) {
                    $nomeArquivo = uniqid() . '_' . $dados['fotos']['name'][$key];
                    move_uploaded_file($tmp_name, $fotosDir . $nomeArquivo);
                }
            }
        }
        return true;
    }
    return false;
}

function lerAvarias($containerId) {
    $diretorio = "dados/avarias/{$containerId}/";
    if (!is_dir($diretorio)) {
        return array();
    }
    
    $avarias = array();
    $arquivos = glob($diretorio . '*.txt');
    
    foreach ($arquivos as $arquivo) {
        $id = basename($arquivo, '.txt');
        $conteudo = file_get_contents($arquivo);
        $dados = explode('|', $conteudo);
        
        if (count($dados) >= 6) {
            $fotosDir = $diretorio . 'fotos/' . $id . '/';
            $fotos = is_dir($fotosDir) ? glob($fotosDir . '*.*') : array();
            
            $avaria = array();
            $avaria['id'] = $id;
            $avaria['data'] = $dados[0];
            $avaria['codigo_produto'] = $dados[1];
            $avaria['tipo_avaria'] = $dados[2];
            $avaria['descricao'] = $dados[3];
            $avaria['info_produto'] = json_decode($dados[4], true);
            $avaria['usuario'] = $dados[5];
            $avaria['fotos'] = array_map(function($foto) {
                return basename($foto);
            }, $fotos);
            
            $avarias[] = $avaria;
        }
    }
    
    usort($avarias, function($a, $b) {
        return strtotime($b['data']) - strtotime($a['data']);
    });
    
    return $avarias;
}

function calcularEstatisticas() {
    if (!file_exists("dados/containers.txt")) {
        return array(
            'total' => 0,
            'tempo_medio' => 0,
            'melhor_tempo' => 0,
            'ranking_tipos' => array()
        );
    }
    
    $containers = lerContainers();
    $total = count($containers);
    $tempos = array();
    $tempos_por_tipo = array();
    
    foreach ($containers as $container) {
        $tempo = calcularTempoDescarga($container['horario_inicio'], $container['horario_fim']);
        $tipo = $container['tipo_produto'];
        
        $tempos[] = $tempo;
        
        if (!isset($tempos_por_tipo[$tipo])) {
            $tempos_por_tipo[$tipo] = array();
        }
        $tempos_por_tipo[$tipo][] = $tempo;
    }
    
    $ranking_tipos = array();
    foreach ($tempos_por_tipo as $tipo => $tempos_tipo) {
        $ranking_tipos[$tipo] = array_sum($tempos_tipo) / count($tempos_tipo);
    }
    
    asort($ranking_tipos);
    
    return array(
        'total' => $total,
        'tempo_medio' => $total > 0 ? array_sum($tempos) / $total : 0,
        'melhor_tempo' => $total > 0 ? min($tempos) : 0,
        'ranking_tipos' => $ranking_tipos
    );
}

function deletarContainer($id) {
    if (!isAdmin()) {
        return false;
    }

    $containers = lerContainers();
    $arquivo = "dados/containers.txt";
    $novoConteudo = "";
    $containerDeletado = null;
    
    foreach ($containers as $container) {
        if ($container['id'] === $id) {
            $containerDeletado = $container;
            continue;
        }
        
        $linha = implode("|", array(
            $container['id'],
            $container['data'],
            $container['numero_container'],
            $container['numero_nf'],
            $container['ordem_descarga'],
            $container['origem'],
            $container['tipo_produto'],
            $container['horario_inicio'],
            $container['horario_fim'],
            $container['qtd_trabalhadores'],
            $container['valor_trabalhador'],
            $container['usa_esteira'],
            '',  // Campo avarias removido
            $container['observacoes'],
            $container['justificativa_plan_inner'],
            $container['usuario']
        )) . PHP_EOL;
        $novoConteudo .= $linha;
    }
    
    // Apaga arquivos relacionados
    @unlink("dados/plan_inner_{$id}.txt");
    @unlink("dados/fotos_{$id}.txt");
    
    // Apagar diretório de fotos e seus conteúdos
    $fotosDir = "dados/fotos/{$id}";
    if (is_dir($fotosDir)) {
        array_map('unlink', glob("$fotosDir/*.*"));
        @rmdir($fotosDir);
    }
    
    // Apagar diretório de avarias e seus conteúdos
    $avariaDir = "dados/avarias/{$id}";
    if (is_dir($avariaDir)) {
        $fotosAvariasDir = "$avariaDir/fotos";
        if (is_dir($fotosAvariasDir)) {
            array_map('unlink', glob("$fotosAvariasDir/*.*"));
            @rmdir($fotosAvariasDir);
        }
        array_map('unlink', glob("$avariaDir/*.*"));
        @rmdir($avariaDir);
    }
    
    $resultado = file_put_contents($arquivo, $novoConteudo) !== false;
    
    if ($resultado && $containerDeletado) {
        registrar_log("Container {$containerDeletado['numero_container']} excluído por " . $_SESSION['usuario']);
        setMensagem('success', 'Container excluído com sucesso!');
    }
    
    return $resultado;
}
?>
