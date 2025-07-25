<?php
// api/db_connect.php

// Definições de cabeçalho para permitir requisições de qualquer origem (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Responde a requisições OPTIONS (pre-flight) para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configurações do Banco de Dados - CREDENCIAIS CORRIGIDAS
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'pedroj14_pedrojustus');
define('DB_PASSWORD', 'MinhaSenh@123');
define('DB_NAME', 'pedroj14_lifepremium');

// Tenta estabelecer a conexão com o banco de dados
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Define o charset para UTF-8 para suportar acentos e caracteres especiais
$conn->set_charset("utf8mb4");

// Verifica se a conexão falhou
if ($conn->connect_error) {
    // Se falhar, encerra a execução e retorna um erro em formato JSON
    http_response_code(500); // Internal Server Error
    // Adicionamos mais detalhes ao erro para facilitar a depuração
    echo json_encode([
        'error' => 'Falha na conexão com o banco de dados.',
        'mysql_error' => $conn->connect_error,
        'mysql_errno' => $conn->connect_errno
    ]);
    exit();
}
?>
