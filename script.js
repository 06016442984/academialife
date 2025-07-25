// assets/script.js

// URL base da sua API.
const API_BASE_URL = 'api/';

// =================================================================================
// Variáveis Globais
// =================================================================================
let studentsDB = [];
let currentStudent = null;
let selectedAssessmentForPrint = null;
let temporaryPhotoPaths = { frente: null, perfil: null, costas: null };

// =================================================================================
// Inicialização e Login
// =================================================================================
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Inicializando sistema Life Premium Pro...');
    // A senha é apenas uma barreira simples no frontend.
    const CORRECT_PASSWORD = "LIFEPREMIUM";

    document.getElementById('login-form').addEventListener('submit', function(event) {
        event.preventDefault();
        const passwordInput = document.getElementById('password');
        if (passwordInput.value.toUpperCase() === CORRECT_PASSWORD) {
            document.getElementById('login-overlay').style.opacity = '0';
            setTimeout(() => { document.getElementById('login-overlay').style.display = 'none'; }, 500);
            document.getElementById('main-app').style.display = 'block';
        } else {
            const loginError = document.getElementById('login-error');
            loginError.style.display = 'block';
            passwordInput.value = '';
            setTimeout(() => { loginError.style.display = 'none'; }, 3000);
        }
    });

    generatePARQQuestions();
    loadAllStudents();
});

// =================================================================================
// Navegação entre Abas
// =================================================================================
function showTab(tabName) {
    // Esconde todos os conteúdos de abas
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });

    // Remove a classe 'active' de todos os botões de abas
    document.querySelectorAll('.nav-tab').forEach(button => {
        button.classList.remove('active');
    });

    // Mostra a aba e ativa o botão correspondente
    document.getElementById(`tab-${tabName}`).classList.remove('hidden');
    document.querySelector(`.nav-tab[onclick="showTab('${tabName}')"]`).classList.add('active');
}

// =================================================================================
// Funções de Comunicação com a API (Backend)
// =================================================================================
async function apiRequest(endpoint, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    if (body) {
        options.body = JSON.stringify(body);
    }
    try {
        const response = await fetch(API_BASE_URL + endpoint, options);
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || `Erro HTTP: ${response.status}`);
        }
        return response.json();
    } catch (error) {
        console.error('Erro na requisição API:', error);
        showAlert(`Erro de comunicação com o servidor: ${error.message}`, 'error');
        throw error;
    }
}

// =================================================================================
// Gerenciamento de Alunos (Carregar, Selecionar, Novo, Deletar)
// =================================================================================
async function loadAllStudents() {
    try {
        studentsDB = await apiRequest('alunos.php');
        updateStudentsList(studentsDB);
    } catch (error) {
        console.error("Falha ao carregar alunos.");
    }
}

async function loadStudent(studentId) {
    clearForm();
    try {
        currentStudent = await apiRequest(`alunos.php?id=${studentId}`);
        fillFormWithStudentData(currentStudent);
        updateResults(); // Atualiza a aba de resultados com os dados carregados
        // updatePrintAssessmentSelector(); // Adicionar se necessário
        document.getElementById('studentSelect').value = currentStudent.nome;
        showAlert(`Aluno ${currentStudent.nome} carregado.`, 'success');
    } catch (error) {
        console.error("Falha ao carregar dados do aluno.");
    }
}

function newStudent() {
    clearForm();
    currentStudent = null;
    document.getElementById('nome').disabled = false;
    document.getElementById('studentSelect').value = '';
    showTab('avaliacao');
    showAlert('Formulário pronto para cadastrar um novo aluno.', 'info');
}

async function deleteStudent() {
    if (!currentStudent) {
        showAlert('Nenhum aluno selecionado para excluir.', 'warning');
        return;
    }

    // Usando uma confirmação simples por enquanto. O ideal seria um modal customizado.
    const isConfirmed = window.confirm(`Tem certeza que deseja excluir ${currentStudent.nome} e todo o seu histórico? Esta ação não pode ser desfeita.`);
    
    if (isConfirmed) {
        showLoading('Excluindo aluno...');
        try {
            const result = await apiRequest(`alunos.php?id=${currentStudent.id}`, 'DELETE');
            showAlert(result.success, 'success');
            currentStudent = null;
            clearForm();
            await loadAllStudents(); // Recarrega a lista
        } catch (error) {
            console.error("Falha ao excluir aluno:", error);
        } finally {
            hideLoading();
        }
    }
}

// =================================================================================
// Lógica do Formulário (Preencher, Limpar, Coletar Dados)
// =================================================================================
function fillFormWithStudentData(student) {
    if (!student) return;

    // Preenche dados do perfil
    document.getElementById('nome').value = student.nome;
    document.getElementById('nome').disabled = true; // Bloqueia a edição do nome de aluno existente
    document.getElementById('nascimento').value = student.nascimento;
    calculateAge();
    document.getElementById('genero').value = student.genero;
    document.getElementById('avaliador').value = student.avaliador || '';
    document.getElementById('freq_atual').value = student.freq_atual || '';
    document.getElementById('freq_passada').value = student.freq_passada || '';
    document.getElementById('tipo_exercicio').value = student.tipo_exercicio || '';
    document.getElementById('historico_lesoes').value = student.historico_lesoes || '';
    document.getElementById('limitacoes').value = student.limitacoes || '';

    // Preenche objetivos
    document.querySelectorAll('input[name="objectives"]').forEach(checkbox => {
        checkbox.checked = student.objectives?.includes(checkbox.value) || false;
    });

    // Preenche PAR-Q
    if (student.parq) {
        Object.keys(student.parq).forEach(key => {
            const radio = document.querySelector(`input[name="${key}"][value="${student.parq[key]}"]`);
            if (radio) radio.checked = true;
        });
    }

    // Preenche dados da última avaliação, se houver
    if (student.assessments && student.assessments.length > 0) {
        const lastAssessment = student.assessments[student.assessments.length - 1];
        const m = lastAssessment.measurements;
        
        document.getElementById('peso').value = m.peso || '';
        document.getElementById('altura').value = m.altura || '';
        document.getElementById('fc_repouso').value = m.fc_repouso || '';
        document.getElementById('cintura').value = m.cintura || '';
        document.getElementById('quadril').value = m.quadril || '';
        document.getElementById('triceps').value = m.triceps || '';
        document.getElementById('subscapular').value = m.subscapular || '';
        document.getElementById('peitoral').value = m.peitoral || '';
        document.getElementById('axilar').value = m.axilar || '';
        document.getElementById('suprailiaca').value = m.suprailiaca || '';
        document.getElementById('abdominal').value = m.abdominal || '';
        document.getElementById('coxa').value = m.coxa || '';
        
        // Preenche as imagens e os caminhos temporários
        document.getElementById('preview_frente').src = lastAssessment.foto_frente || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        document.getElementById('preview_perfil').src = lastAssessment.foto_perfil || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        document.getElementById('preview_costas').src = lastAssessment.foto_costas || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        temporaryPhotoPaths.frente = lastAssessment.foto_frente;
        temporaryPhotoPaths.perfil = lastAssessment.foto_perfil;
        temporaryPhotoPaths.costas = lastAssessment.foto_costas;
    }
}

function clearForm() {
    document.getElementById('assessmentForm').reset();
    document.getElementById('nome').disabled = false;
    document.getElementById('idade').value = '';
    document.getElementById('preview_frente').src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
    document.getElementById('preview_perfil').src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
    document.getElementById('preview_costas').src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
    temporaryPhotoPaths = { frente: null, perfil: null, costas: null };
    currentStudent = null;
}

function getFormData() {
    const profile = {
        nome: document.getElementById('nome').value,
        nascimento: document.getElementById('nascimento').value,
        idade: parseInt(document.getElementById('idade').value),
        genero: document.getElementById('genero').value,
        avaliador: document.getElementById('avaliador').value,
        freq_atual: document.getElementById('freq_atual').value,
        freq_passada: document.getElementById('freq_passada').value,
        tipo_exercicio: document.getElementById('tipo_exercicio').value,
        historico_lesoes: document.getElementById('historico_lesoes').value,
        limitacoes: document.getElementById('limitacoes').value,
        objectives: Array.from(document.querySelectorAll('input[name="objectives"]:checked')).map(cb => cb.value),
        parq: {}
    };

    document.querySelectorAll('#parqQuestions .parq-question').forEach((q, i) => {
        const questionKey = `parq_q${i+1}`;
        const selected = q.querySelector(`input[name="${questionKey}"]:checked`);
        profile.parq[questionKey] = selected ? selected.value : null;
    });

    const measurements = {
        peso: parseFloat(document.getElementById('peso').value),
        altura: parseFloat(document.getElementById('altura').value),
        fc_repouso: parseInt(document.getElementById('fc_repouso').value),
        cintura: parseFloat(document.getElementById('cintura').value),
        quadril: parseFloat(document.getElementById('quadril').value),
        triceps: parseFloat(document.getElementById('triceps').value),
        subscapular: parseFloat(document.getElementById('subscapular').value),
        peitoral: parseFloat(document.getElementById('peitoral').value),
        axilar: parseFloat(document.getElementById('axilar').value),
        suprailiaca: parseFloat(document.getElementById('suprailiaca').value),
        abdominal: parseFloat(document.getElementById('abdominal').value),
        coxa: parseFloat(document.getElementById('coxa').value)
    };
    
    // Validação simples
    if (!profile.nome || !profile.nascimento || !measurements.peso || !measurements.altura) {
        showAlert('Por favor, preencha todos os campos obrigatórios (*).', 'error');
        return null;
    }

    return { profile, measurements };
}

// =================================================================================
// Lógica Principal (Salvar Avaliação, Cálculos)
// =================================================================================
document.getElementById('assessmentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = getFormData();
    if (!formData) return;

    const calculations = performCalculations(formData);
    if (!calculations) return;

    const payload = {
        profile: formData.profile,
        measurements: formData.measurements,
        calculations: calculations,
        photos: temporaryPhotoPaths,
        date: new Date().toISOString(),
        method: 'folds_7' // Método de 7 dobras
    };

    showLoading('Salvando avaliação e gerando análise com IA... Isso pode levar um momento.');

    try {
        const result = await apiRequest('avaliacoes.php', 'POST', payload);
        showAlert(result.success, 'success');
        await loadAllStudents(); 
        await loadStudent(result.aluno_id);
        showTab('resultados');
    } catch (error) {
        console.error("Falha ao salvar avaliação:", error);
        showAlert(`Falha ao salvar avaliação: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
});

function performCalculations(formData) {
    const { profile, measurements } = formData;
    const { idade, genero } = profile;
    const { peso, altura, triceps, subscapular, peitoral, axilar, suprailiaca, abdominal, coxa } = measurements;

    if (!peso || !altura || !idade || !genero || !triceps || !subscapular || !peitoral || !axilar || !suprailiaca || !abdominal || !coxa) {
        showAlert('Preencha todas as medidas de dobras cutâneas, peso, altura e idade para calcular.', 'warning');
        return null;
    }

    const alturaM = altura / 100;
    const imc = peso / (alturaM * alturaM);
    
    // Fórmula de Densidade Corporal de Jackson & Pollock (7 Dobras)
    const somaDobras = triceps + subscapular + peitoral + axilar + suprailiaca + abdominal + coxa;
    let densidade;

    if (genero === 'masculino') {
        densidade = 1.112 - (0.00043499 * somaDobras) + (0.00000055 * Math.pow(somaDobras, 2)) - (0.00028826 * idade);
    } else { // feminino
        densidade = 1.097 - (0.00046971 * somaDobras) + (0.00000056 * Math.pow(somaDobras, 2)) - (0.00012828 * idade);
    }

    // Fórmula de Siri para % de Gordura
    const percGordura = ((4.95 / densidade) - 4.5) * 100;
    const gorduraKg = peso * (percGordura / 100);
    const massaMagraKg = peso - gorduraKg;

    return {
        imc: imc.toFixed(2),
        somaDobras: somaDobras.toFixed(2),
        densidadeCorporal: densidade.toFixed(4),
        percentualGordura: percGordura.toFixed(2),
        gorduraAbsolutaKg: gorduraKg.toFixed(2),
        massaMagraKg: massaMagraKg.toFixed(2),
        pesoIdeal: (massaMagraKg / (genero === 'masculino' ? 0.85 : 0.75)).toFixed(2) // Exemplo de meta
    };
}

// =================================================================================
// Upload de Fotos
// =================================================================================
async function handlePhotoUpload(event, position) {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);

    showLoading(`Enviando foto de ${position}...`);
    try {
        const response = await fetch(API_BASE_URL + 'upload.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Erro desconhecido no upload');
        }

        const fullPath = result.filePath; // O PHP já retorna o caminho relativo
        document.getElementById(`preview_${position}`).src = fullPath;
        temporaryPhotoPaths[position] = fullPath;
        showAlert(`Foto de ${position} enviada com sucesso.`, 'success');
    } catch (error) {
        showAlert(`Falha no upload da foto: ${error.message}`, 'error');
    } finally {
        hideLoading();
    }
}

// =================================================================================
// UI e Funções Auxiliares
// =================================================================================
function updateStudentsList(students) {
    const dropdown = document.getElementById('studentDropdown');
    dropdown.innerHTML = ''; // Limpa a lista
    if (students.length === 0) {
        dropdown.innerHTML = '<div class="dropdown-item">Nenhum aluno encontrado.</div>';
        return;
    }
    students.forEach(student => {
        const item = document.createElement('div');
        item.className = 'dropdown-item';
        item.textContent = student.nome;
        item.onmousedown = () => loadStudent(student.id); // onmousedown para executar antes do onblur
        dropdown.appendChild(item);
    });
}

function searchStudents() {
    const filter = document.getElementById('studentSelect').value.toUpperCase();
    const filteredStudents = studentsDB.filter(s => s.nome.toUpperCase().includes(filter));
    updateStudentsList(filteredStudents);
}

function showDropdown() { document.getElementById('studentDropdown').style.display = 'block'; }
function hideDropdown() { setTimeout(() => { document.getElementById('studentDropdown').style.display = 'none'; }, 200); }

function calculateAge() {
    const birthDate = document.getElementById('nascimento').value;
    if (birthDate) {
        const age = new Date().getFullYear() - new Date(birthDate).getFullYear();
        document.getElementById('idade').value = age;
    }
}

function generatePARQQuestions() {
    const questions = [
        "O seu médico já lhe disse que você tem um problema cardíaco e que só deveria fazer atividade física sob supervisão médica?",
        "Você sente dor no peito quando faz atividade física?",
        "No último mês, você sentiu dor no peito quando não estava a fazer atividade física?",
        "Você perde o equilíbrio devido a tonturas ou já perdeu a consciência alguma vez?",
        "Você tem algum problema ósseo ou articular (por exemplo, nas costas, joelho ou anca) que poderia ser agravado por uma alteração na sua atividade física?",
        "O seu médico está atualmente a prescrever-lhe medicamentos (por exemplo, diuréticos) para a sua pressão arterial ou problema cardíaco?",
        "Você conhece alguma outra razão pela qual não deveria fazer atividade física?"
    ];
    const container = document.getElementById('parqQuestions');
    container.innerHTML = questions.map((q, i) => `
        <div class="parq-question">
            <p>${i+1}. ${q}</p>
            <div class="parq-options">
                <label><input type="radio" name="parq_q${i+1}" value="sim"> Sim</label>
                <label><input type="radio" name="parq_q${i+1}" value="nao" checked> Não</label>
            </div>
        </div>
    `).join('');
}

function updateResults() {
    const resultsContainer = document.getElementById('resultsContent');
    const geminiContainer = document.getElementById('geminiAnalysisContent');

    if (!currentStudent || !currentStudent.assessments || currentStudent.assessments.length === 0) {
        resultsContainer.innerHTML = '<div class="alert alert-info"><span>ℹ️</span><span>Selecione um aluno e realize uma avaliação para ver os resultados aqui.</span></div>';
        geminiContainer.innerHTML = '';
        return;
    }

    const lastAssessment = currentStudent.assessments[currentStudent.assessments.length - 1];
    const c = lastAssessment.calculations;

    // Renderiza os resultados numéricos
    resultsContainer.innerHTML = `
        <h3>Resultados da Avaliação de ${new Date(lastAssessment.data_avaliacao).toLocaleDateString('pt-BR')}</h3>
        <div class="results-grid">
            <div class="result-item"><span>IMC</span><strong>${c.imc}</strong></div>
            <div class="result-item"><span>% Gordura</span><strong>${c.percentualGordura}%</strong></div>
            <div class="result-item"><span>Massa Magra</span><strong>${c.massaMagraKg} kg</strong></div>
            <div class="result-item"><span>Gordura Absoluta</span><strong>${c.gorduraAbsolutaKg} kg</strong></div>
            <div class="result-item"><span>Soma das Dobras</span><strong>${c.somaDobras} mm</strong></div>
            <div class="result-item"><span>Peso Ideal (Est.)</span><strong>${c.pesoIdeal} kg</strong></div>
        </div>
    `;

    // Renderiza a análise do Gemini
    if (lastAssessment.gemini_analysis) {
        geminiContainer.innerHTML = parseSimpleMarkdown(lastAssessment.gemini_analysis);
    } else {
        geminiContainer.innerHTML = '<div class="alert alert-warning"><span>⚠️</span><span>Análise de IA não encontrada ou ainda não gerada para esta avaliação.</span></div>';
    }
}

function parseSimpleMarkdown(text) {
    if (!text) return '';
    // Converte títulos com emojis em <h3> e quebras de linha em <br>
    return text
        .replace(/🔍\s*(.*?):/g, '<h3><span class="card-icon">🔍</span> $1</h3>')
        .replace(/🧬\s*(.*?):/g, '<h3><span class="card-icon">🧬</span> $1</h3>')
        .replace(/🏋️\s*(.*?):/g, '<h3><span class="card-icon">🏋️</span> $1</h3>')
        .replace(/🔥\s*(.*?):/g, '<h3><span class="card-icon">🔥</span> $1</h3>')
        .replace(/🎯\s*(.*?):/g, '<h3><span class="card-icon">🎯</span> $1</h3>')
        .replace(/🗣️\s*(.*?):/g, '<h3><span class="card-icon">🗣️</span> $1</h3>')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Negrito
        .replace(/\n/g, '<br>');
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alertBox = document.createElement('div');
    alertBox.className = `alert alert-${type}`;
    alertBox.textContent = message;
    container.appendChild(alertBox);
    setTimeout(() => {
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
    }, 4000);
}

function showLoading(message) {
    let loadingOverlay = document.getElementById('loading-overlay');
    if (!loadingOverlay) {
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loading-overlay';
        document.body.appendChild(loadingOverlay);
    }
    loadingOverlay.innerHTML = `
        <div class="loading-box">
            <div class="spinner"></div>
            <p>${message}</p>
        </div>
    `;
    loadingOverlay.style.display = 'flex';
}

function hideLoading() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}
