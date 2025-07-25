<?php
// api/avaliacoes.php
require_once 'db_connect.php';
require_once 'functions.php';
require_once 'gemini_proxy.php'; // Inclui o novo arquivo

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    save_avaliacao($conn, $data);
} else {
    send_json_response(405, ['error' => 'Método não permitido.']);
}

function save_avaliacao($conn, $data) {
    // ** SUA CHAVE DA API DO GEMINI - INSERIDA **
    $geminiApiKey = 'AIzaSyCMKMiICmRhyYVJH7FfJ7TI2-fItw6zzTw';

    $profile_data = sanitize_input($data['profile']);
    $measurements_data = $data['measurements'];
    $calculations_data = $data['calculations'];
    $photos_data = $data['photos'];

    $conn->begin_transaction();

    try {
        // Bloco para salvar ou atualizar o aluno
        $stmt = $conn->prepare("SELECT id FROM alunos WHERE nome = ?");
        $stmt->bind_param("s", $profile_data['nome']);
        $stmt->execute();
        $result = $stmt->get_result();
        $aluno = $result->fetch_assoc();
        $stmt->close();

        $aluno_id = null;
        $objectives_json = json_encode($profile_data['objectives'] ?? []);
        $parq_json = json_encode($profile_data['parq'] ?? []);

        if ($aluno) {
            $aluno_id = $aluno['id'];
            $stmt = $conn->prepare("UPDATE alunos SET nascimento = ?, genero = ?, avaliador = ?, freq_atual = ?, freq_passada = ?, tipo_exercicio = ?, historico_lesoes = ?, limitacoes = ?, objectives = ?, parq = ? WHERE id = ?");
            $stmt->bind_param("ssssssssssi", 
                $profile_data['nascimento'], $profile_data['genero'], $profile_data['avaliador'],
                $profile_data['freq_atual'], $profile_data['freq_passada'], $profile_data['tipo_exercicio'],
                $profile_data['historico_lesoes'], $profile_data['limitacoes'],
                $objectives_json, $parq_json, $aluno_id
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO alunos (nome, nascimento, genero, avaliador, freq_atual, freq_passada, tipo_exercicio, historico_lesoes, limitacoes, objectives, parq) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", 
                $profile_data['nome'], $profile_data['nascimento'], $profile_data['genero'], $profile_data['avaliador'],
                $profile_data['freq_atual'], $profile_data['freq_passada'], $profile_data['tipo_exercicio'],
                $profile_data['historico_lesoes'], $profile_data['limitacoes'],
                $objectives_json, $parq_json
            );
        }
        $stmt->execute();
        if (!$aluno) {
            $aluno_id = $stmt->insert_id;
        }
        $stmt->close();

        if (!$aluno_id) {
            throw new Exception("Falha ao obter o ID do aluno.");
        }

        // Insere a nova avaliação
        $stmt = $conn->prepare("INSERT INTO avaliacoes (aluno_id, data_avaliacao, method, measurements, calculations, profile_at_time, foto_frente, foto_perfil, foto_costas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $data_avaliacao = $data['date'];
        $method_db = $data['method'];
        $measurements_json = json_encode($measurements_data);
        $calculations_json = json_encode($calculations_data);
        $profile_at_time_json = json_encode($profile_data);

        $stmt->bind_param("issssssss", 
            $aluno_id, $data_avaliacao, $method_db, 
            $measurements_json, $calculations_json, $profile_at_time_json,
            $photos_data['frente'], $photos_data['perfil'], $photos_data['costas']
        );
        
        $stmt->execute();
        $avaliacao_id = $stmt->insert_id;
        $stmt->close();

        $conn->commit(); // Salva os dados básicos primeiro

        // --- CHAMADA PARA O GEMINI ---
        $gemini_analysis_text = "Análise da IA não pôde ser gerada neste momento."; // Mensagem padrão
        try {
            // **MELHORIA: Verifica se o ficheiro existe antes de o ler para evitar erro 500**
            $exercise_file_path = __DIR__ . '/exercicios_sem_duplicatas.csv';
            $exerciseList = ""; // Inicia como string vazia
            if (file_exists($exercise_file_path)) {
                $exerciseList = file_get_contents($exercise_file_path);
            } else {
                // Regista um erro se o ficheiro não for encontrado, para depuração futura
                error_log("Ficheiro de exercícios 'exercicios_sem_duplicatas.csv' não encontrado na pasta api/");
            }
            
            $studentDataForGemini = array_merge($profile_data, $measurements_data, $calculations_data);
            
            $gemini_analysis_text = get_gemini_analysis($geminiApiKey, $studentDataForGemini, $exerciseList);

        } catch (Exception $gemini_e) {
            // Se a chamada ao Gemini falhar, o erro é registrado, mas o script não para.
            error_log("Erro na chamada Gemini: " . $gemini_e->getMessage());
        }

        // Salva a análise do Gemini no banco de dados
        $stmt_update = $conn->prepare("UPDATE avaliacoes SET gemini_analysis = ? WHERE id = ?");
        $stmt_update->bind_param("si", $gemini_analysis_text, $avaliacao_id);
        $stmt_update->execute();
        $stmt_update->close();

        send_json_response(201, ['success' => 'Avaliação salva e análise de IA processada!', 'aluno_id' => $aluno_id, 'avaliacao_id' => $avaliacao_id]);

    } catch (Exception $e) {
        $conn->rollback();
        send_json_response(500, ['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
}

$conn->close();
?>
