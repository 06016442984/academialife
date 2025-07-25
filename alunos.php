<?php
// api/alunos.php
require_once 'db_connect.php';
require_once 'functions.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lógica para buscar alunos
        if (isset($_GET['id'])) {
            get_aluno_by_id($conn, intval($_GET['id']));
        } else {
            get_all_alunos($conn);
        }
        break;
    
    case 'POST':
        // Lógica para criar ou atualizar um aluno
        $data = json_decode(file_get_contents('php://input'), true);
        save_aluno($conn, $data);
        break;

    case 'DELETE':
        // Lógica para deletar um aluno
        if (isset($_GET['id'])) {
            delete_aluno($conn, intval($_GET['id']));
        } else {
            send_json_response(400, ['error' => 'ID do aluno não fornecido.']);
        }
        break;

    default:
        send_json_response(405, ['error' => 'Método não permitido.']);
        break;
}

function get_all_alunos($conn) {
    $sql = "SELECT id, nome, nascimento, genero FROM alunos ORDER BY nome ASC";
    $result = $conn->query($sql);
    $alunos = [];
    while ($row = $result->fetch_assoc()) {
        // Calcula a idade
        if ($row['nascimento']) {
            $nasc = new DateTime($row['nascimento']);
            $hoje = new DateTime();
            $row['idade'] = $hoje->diff($nasc)->y;
        } else {
            $row['idade'] = null;
        }
        $alunos[] = $row;
    }
    send_json_response(200, $alunos);
}

function get_aluno_by_id($conn, $id) {
    // Busca os dados do perfil do aluno
    $stmt_aluno = $conn->prepare("SELECT * FROM alunos WHERE id = ?");
    $stmt_aluno->bind_param("i", $id);
    $stmt_aluno->execute();
    $result_aluno = $stmt_aluno->get_result();
    $aluno = $result_aluno->fetch_assoc();

    if (!$aluno) {
        send_json_response(404, ['error' => 'Aluno não encontrado.']);
    }

    // Decodifica os campos JSON
    $aluno['objectives'] = json_decode($aluno['objectives'], true);
    $aluno['parq'] = json_decode($aluno['parq'], true);

    // Busca todas as avaliações do aluno
    $stmt_avaliacoes = $conn->prepare("SELECT * FROM avaliacoes WHERE aluno_id = ? ORDER BY data_avaliacao ASC");
    $stmt_avaliacoes->bind_param("i", $id);
    $stmt_avaliacoes->execute();
    $result_avaliacoes = $stmt_avaliacoes->get_result();
    $avaliacoes = [];
    while($row = $result_avaliacoes->fetch_assoc()){
        $row['measurements'] = json_decode($row['measurements'], true);
        $row['calculations'] = json_decode($row['calculations'], true);
        $row['profile_at_time'] = json_decode($row['profile_at_time'], true);
        $avaliacoes[] = $row;
    }

    $aluno['assessments'] = $avaliacoes;
    send_json_response(200, $aluno);
}

function save_aluno($conn, $data) {
    // A função de salvar aluno foi movida para avaliacoes.php
    // pois a criação/atualização do perfil acontece junto com a avaliação.
    send_json_response(400, ['error' => 'Operação movida para o endpoint de avaliações.']);
}

function delete_aluno($conn, $id) {
    // Antes de deletar o aluno, remove os arquivos de fotos associados
    $stmt_fotos = $conn->prepare("SELECT foto_frente, foto_perfil, foto_costas FROM avaliacoes WHERE aluno_id = ?");
    $stmt_fotos->bind_param("i", $id);
    $stmt_fotos->execute();
    $result_fotos = $stmt_fotos->get_result();
    while($row = $result_fotos->fetch_assoc()){
        if ($row['foto_frente'] && file_exists('../' . $row['foto_frente'])) {
            unlink('../' . $row['foto_frente']);
        }
        if ($row['foto_perfil'] && file_exists('../' . $row['foto_perfil'])) {
            unlink('../' . $row['foto_perfil']);
        }
        if ($row['foto_costas'] && file_exists('../' . $row['foto_costas'])) {
            unlink('../' . $row['foto_costas']);
        }
    }
    $stmt_fotos->close();

    // Deleta o aluno (as avaliações serão deletadas em cascata pela chave estrangeira)
    $stmt = $conn->prepare("DELETE FROM alunos WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        send_json_response(200, ['success' => 'Aluno e todo o seu histórico foram deletados com sucesso.']);
    } else {
        send_json_response(500, ['error' => 'Erro ao deletar o aluno: ' . $stmt->error]);
    }
    $stmt->close();
}

$conn->close();
?>
