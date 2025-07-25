<?php
// api/upload.php
require_once 'functions.php';

// Diretório de destino para os uploads
$target_dir = "../uploads/";

// Verifica se o diretório de uploads existe, senão, tenta criar
if (!file_exists($target_dir)) {
    if (!mkdir($target_dir, 0755, true)) {
        send_json_response(500, ['error' => 'Falha ao criar o diretório de uploads. Verifique as permissões.']);
    }
}

// Verifica se um arquivo foi enviado
if (!isset($_FILES['photo'])) {
    send_json_response(400, ['error' => 'Nenhum arquivo de foto enviado.']);
}

$file = $_FILES['photo'];

// Verifica se houve erros no upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    send_json_response(500, ['error' => 'Erro durante o upload do arquivo. Código: ' . $file['error']]);
}

// Valida o tipo de arquivo (MIME type)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($mime_type, $allowed_mime_types)) {
    send_json_response(415, ['error' => 'Tipo de arquivo não suportado. Apenas JPG, PNG, GIF e WEBP são permitidos.']);
}

// Gera um nome de arquivo único para evitar sobreposições
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = uniqid('foto_', true) . '.' . strtolower($file_extension);
$target_file = $target_dir . $unique_filename;

// Move o arquivo temporário para o destino final
if (move_uploaded_file($file['tmp_name'], $target_file)) {
    // Retorna o caminho relativo do arquivo para ser salvo no banco de dados
    $relative_path = 'uploads/' . $unique_filename;
    send_json_response(200, ['success' => 'Upload bem-sucedido!', 'filePath' => $relative_path]);
} else {
    send_json_response(500, ['error' => 'Falha ao mover o arquivo enviado.']);
}
?>