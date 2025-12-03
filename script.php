<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root';
$db_name = 'artesanal';
$aes_key = 'Cqkc8SChPPeJ91w8X/3IRkVj0+seeb8D9Rlj6bfUQEg=';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) die("Erro de conexão: " . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

if (isset($_POST['acao']) && $_POST['acao'] === 'converter') {

    header('Content-Type: text/plain; charset=utf-8');
    ob_start();

    $result = $mysqli->query("SHOW TABLES");
    if (!$result) die("Erro SHOW TABLES: " . $mysqli->error);

    while ($row = $result->fetch_array()) {
        $table = $row[0];
        echo "\nTabela: $table\n";

        // verifica se existe coluna cpf
        $res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'cpf'");
        if (!$res || $res->num_rows == 0) {
            echo "Ignorada (sem coluna CPF)\n";
            continue;
        }

        // adiciona matricula
        $res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'matricula'");
        if ($res && $res->num_rows == 0) {
            if (!$mysqli->query("ALTER TABLE `$table` ADD COLUMN `matricula` VARCHAR(50) AFTER `id`")) {
                echo "Erro ao adicionar matricula: " . $mysqli->error . "\n";
            } else echo "Coluna matricula adicionada.\n";
        }

        // adiciona cpf_enc
        $res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'cpf_enc'");
        if ($res && $res->num_rows == 0) {
            if (!$mysqli->query("ALTER TABLE `$table` ADD COLUMN `cpf_enc` TEXT DEFAULT NULL")) {
                echo "Erro ao adicionar cpf_enc: " . $mysqli->error . "\n";
            } else echo "Coluna cpf_enc adicionada.\n";
        }

        // verifica se existe datanascimento
        $temData = false;
        $res = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'datanascimento'");
        if ($res && $res->num_rows > 0) $temData = true;

        // gera matricula
        if ($temData) {
            $sqlMat = "
            UPDATE `$table`
            SET matricula = CONCAT(
              RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''),3),
              LEFT(REPLACE(REPLACE(REPLACE(datanascimento,'/',''),'-',''),'.',''),2)
            )
            WHERE cpf IS NOT NULL AND datanascimento IS NOT NULL";
        } else {
            $sqlMat = "
            UPDATE `$table`
            SET matricula = RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''),3)
            WHERE cpf IS NOT NULL";
        }
        if ($mysqli->query($sqlMat)) echo "Matrículas geradas.\n";
        else echo "Erro matricula: " . $mysqli->error . "\n";

        // criptografa cpf
        $sqlEnc = "
        UPDATE `$table`
        SET cpf_enc = TO_BASE64(AES_ENCRYPT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''), '".$mysqli->real_escape_string($aes_key)."'))
        WHERE cpf IS NOT NULL";
        if ($mysqli->query($sqlEnc)) echo "CPF criptografado.\n";
        else echo "Erro criptografia: " . $mysqli->error . "\n";

        // mascara cpf
        $sqlMask = "
        UPDATE `$table`
        SET cpf = CONCAT('***.***.', RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''),' ',''),3))
        WHERE cpf IS NOT NULL";
        if ($mysqli->query($sqlMask)) echo "CPF mascarado.\n";
        else echo "Erro máscara: " . $mysqli->error . "\n";
    }

    echo ob_get_clean();
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Conversão LGPD - CPF → Matrícula</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="max-w-2xl mx-auto mt-12 p-6 bg-white rounded-2xl shadow">
  <h1 class="text-xl font-bold mb-4 text-gray-800">Conversão de CPF e Geração de Matrícula</h1>
  <p class="text-gray-600 mb-4">Executa a conversão em todas as tabelas do banco configurado.</p>
  <button id="btn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Iniciar Conversão</button>
  <pre id="log" class="mt-6 bg-gray-900 text-green-300 text-sm p-4 rounded hidden"></pre>
</div>
<script>
document.getElementById('btn').addEventListener('click', async () => {
  const btn = document.getElementById('btn');
  const log = document.getElementById('log');
  btn.disabled = true;
  btn.textContent = 'Processando...';
  log.classList.remove('hidden');
  log.textContent = 'Executando conversão nas tabelas...\n';

  try {
    const response = await fetch('', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'acao=converter'
    });
    const text = await response.text();
    log.textContent += '\n' + text;
  } catch (e) {
    log.textContent += '\nErro: ' + e.message;
  }

  btn.disabled = false;
  btn.textContent = 'Executar Novamente';
});
</script>
</body>
</html>
