<?php
// api/gemini_proxy.php -> AGORA � UM PROXY PARA OPENAI (ChatGPT)

/**
 * Obt�m a an�lise de um assistente da OpenAI.
 * @param string $apiKey - Sua chave da API da OpenAI.
 * @param string $assistantId - O ID do seu assistente da OpenAI.
 * @param array $studentData - Dados do aluno.
 * @param string $exerciseList - Lista de exerc�cios dispon�veis.
 * @return string - A resposta do assistente ou uma mensagem de erro.
 */
function get_openai_analysis($apiKey, $assistantId, $studentData, $exerciseList) {
    // Verifica se a extens�o cURL do PHP est� instalada
    if (!function_exists('curl_init')) {
        return "Erro Cr�tico de Servidor: A extens�o cURL do PHP n�o est� instalada ou ativada. Esta extens�o � necess�ria para comunicar com a API da OpenAI.";
    }

    // Prompt simplificado, enviando apenas os dados.
    // As instru��es detalhadas devem estar configuradas no pr�prio Assistente na plataforma da OpenAI.
    $prompt = "Por favor, gere a an�lise e prescri��o de treino com base nos seguintes dados:\n\n";
    $prompt .= "DADOS DA AVALIA��O DO ALUNO:\n" . json_encode($studentData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    $prompt .= "LISTA DE EXERC�CIOS/EQUIPAMENTOS DISPON�VEIS:\n" . $exerciseList;

    // Cabe�alhos padr�o para a API da OpenAI
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'OpenAI-Beta: assistants=v2' // Necess�rio para a API de Assistentes v2
    ];

    // --- PASSO 1: Criar uma "Thread" (uma conversa) ---
    $thread_ch = curl_init('https://api.openai.com/v1/threads');
    curl_setopt($thread_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($thread_ch, CURLOPT_POST, true);
    curl_setopt($thread_ch, CURLOPT_HTTPHEADER, $headers);
    $thread_response = curl_exec($thread_ch);
    curl_close($thread_ch);
    $thread_data = json_decode($thread_response, true);
    if (!isset($thread_data['id'])) {
        return "Erro ao criar a conversa com a OpenAI: " . $thread_response;
    }
    $thread_id = $thread_data['id'];

    // --- PASSO 2: Adicionar a mensagem � Thread ---
    $message_payload = json_encode(['role' => 'user', 'content' => $prompt]);
    $message_ch = curl_init("https://api.openai.com/v1/threads/{$thread_id}/messages");
    curl_setopt($message_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($message_ch, CURLOPT_POST, true);
    curl_setopt($message_ch, CURLOPT_POSTFIELDS, $message_payload);
    curl_setopt($message_ch, CURLOPT_HTTPHEADER, $headers);
    curl_exec($message_ch); // Apenas envia a mensagem
    curl_close($message_ch);

    // --- PASSO 3: Executar o Assistente na Thread ---
    $run_payload = json_encode(['assistant_id' => $assistantId]);
    $run_ch = curl_init("https://api.openai.com/v1/threads/{$thread_id}/runs");
    curl_setopt($run_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($run_ch, CURLOPT_POST, true);
    curl_setopt($run_ch, CURLOPT_POSTFIELDS, $run_payload);
    curl_setopt($run_ch, CURLOPT_HTTPHEADER, $headers);
    $run_response = curl_exec($run_ch);
    curl_close($run_ch);
    $run_data = json_decode($run_response, true);
    if (!isset($run_data['id'])) {
        return "Erro ao executar o assistente da OpenAI: " . $run_response;
    }
    $run_id = $run_data['id'];

    // --- PASSO 4: Aguardar a conclus�o da execu��o ---
    $runStatus = '';
    $startTime = time();
    $timeout = 80; // Timeout de 80 segundos
    $runStatusUrl = "https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}";

    while ($runStatus !== 'completed') {
        if (time() - $startTime > $timeout) {
            return "Erro: A execu��o da IA demorou mais de {$timeout} segundos.";
        }
        sleep(2); // Pausa para n�o sobrecarregar a API

        $status_ch = curl_init($runStatusUrl);
        curl_setopt($status_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($status_ch, CURLOPT_HTTPHEADER, $headers);
        $status_response = curl_exec($status_ch);
        curl_close($status_ch);
        $status_data = json_decode($status_response, true);
        $runStatus = $status_data['status'] ?? 'failed';

        if (in_array($runStatus, ['failed', 'cancelled', 'expired'])) {
            return "Erro: A execu��o da IA falhou com o status: {$runStatus}. Detalhes: " . ($status_data['last_error']['message'] ?? 'Nenhum detalhe fornecido.');
        }
    }

    // --- PASSO 5: Obter a resposta do assistente ---
    $list_ch = curl_init("https://api.openai.com/v1/threads/{$thread_id}/messages");
    curl_setopt($list_ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($list_ch, CURLOPT_HTTPHEADER, $headers);
    $list_response = curl_exec($list_ch);
    curl_close($list_ch);
    $list_data = json_decode($list_response, true);

    // A resposta do assistente � a primeira mensagem na lista
    if (isset($list_data['data'][0]['content'][0]['text']['value'])) {
        return $list_data['data'][0]['content'][0]['text']['value'];
    }

    return "Erro: N�o foi poss�vel obter a resposta do assistente. Resposta completa: " . $list_response;
}
?>
