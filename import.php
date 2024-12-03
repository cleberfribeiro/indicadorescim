<!DOCTYPE html>
<html>
<head>
  <title>Custo de Mão de Obra Terceiro</title>
  <style>
    body {
      font-family: Arial, sans-serif;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 8px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    .card {
      display: inline-block;
      padding: 10px;
      margin: 10px;
      background-color: #f1f1f1;
      border-radius: 5px;
    }
  </style>
</head>
<body>
  <h1>Custo de Mão de Obra Terceiro</h1>

  <form id="uploadForm">
    <input type="file" id="excelFile" accept=".xlsx">
    <button type="submit">Enviar</button>
  </form>

  <div id="summaryCards"></div>

  <table id="dataGrid"></table>

  <script>
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
      e.preventDefault();

      var fileInput = document.getElementById('excelFile');
      var file = fileInput.files[0];

      var formData = new FormData();
      formData.append('file', file);

      fetch('/upload', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        displaySummaryCards(data.summary);
        displayDataGrid(data.grid);
      })
      .catch(error => {
        console.error('Error:', error);
      });
    });

    function displaySummaryCards(summary) {
      var summaryCardsDiv = document.getElementById('summaryCards');
      summaryCardsDiv.innerHTML = '';

      for (var category in summary) {
        var card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `<h3>${category}</h3><p>R$ ${summary[category]}</p>`;
        summaryCardsDiv.appendChild(card);
      }
    }

    function displayDataGrid(gridData) {
      var table = document.getElementById('dataGrid');
      table.innerHTML = '';

      // Cabeçalho da tabela
      var headerRow = table.insertRow();
      var headers = ['Data Serviço', 'Processo', 'Nome Serviço', 'Operação MDO', 'Valor Total'];
      headers.forEach(function(headerText) {
        var th = document.createElement('th');
        th.textContent = headerText;
        headerRow.appendChild(th);
      });

      // Linhas de dados
      gridData.forEach(function(rowData) {
        var row = table.insertRow();
        Object.values(rowData).forEach(function(cellData) {
          var cell = row.insertCell();
          cell.textContent = cellData;
        });
      });
    }
  </script>
</body>
</html>