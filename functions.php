<?php
// api/functions.php

/**
 * Envia uma resposta JSON padronizada e encerra o script.
 * @param int $statusCode - O código de status HTTP (ex: 200, 404, 500).
 * @param array $data - O array de dados a ser convertido para JSON.
 */
function send_json_response($statusCode, $data) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Valida e sanitiza os dados de entrada do formulário.
 * @param array $data - O array de dados recebido (ex: $_POST).
 * @return array - O array de dados sanitizado.
 */
function sanitize_input($data) {
    $sanitized_data = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            // Se for um array (como 'objectives'), processa cada item
            $sanitized_data[$key] = array_map(function($item) {
                return htmlspecialchars(strip_tags(trim($item)));
            }, $value);
        } elseif (is_string($value)) {
            // Se for uma string, aplica a sanitização padrão
            $sanitized_data[$key] = htmlspecialchars(strip_tags(trim($value)));
        } else {
            // Mantém outros tipos de dados (números, etc.) como estão
            $sanitized_data[$key] = $value;
        }
    }
    return $sanitized_data;
}
?>
