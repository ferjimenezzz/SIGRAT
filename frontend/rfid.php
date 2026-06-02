<?php
/**
 * @file rfid.php
 * @summary Módulo de Gestión RFID.
 * @description Permite el enrolamiento manual de tags y la simulación de hardware.
 */

require_once '../backend/config/Database.php';
require_once '../backend/controllers/TagController.php';

$db = Config\Database::getConnection();
$tagController = new Controllers\TagController();

// Manejar enrolamiento masivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_tags') {
    $tagsToEnroll = [];
    $mode = $_POST['enroll_mode'] ?? 'list';
    
    if ($mode === 'single' && !empty($_POST['single_tag'])) {
        $tagsToEnroll[] = trim($_POST['single_tag']);
    } elseif ($mode === 'range') {
        $prefixForm = trim($_POST['range_prefix'] ?? '');
        $startStrRaw = trim($_POST['range_start'] ?? '');
        $endStrRaw = trim($_POST['range_end'] ?? '');
        
        preg_match('/^(.*?)(\d+)$/', $startStrRaw, $startMatches);
        preg_match('/^(.*?)(\d+)$/', $endStrRaw, $endMatches);
        
        $startPrefix = $startMatches[1] ?? '';
        $startNumStr = $startMatches[2] ?? $startStrRaw;
        $endNumStr = $endMatches[2] ?? $endStrRaw;
        
        $finalPrefix = $prefixForm . $startPrefix;
        $start = (int)$startNumStr;
        $end = (int)$endNumStr;
        $padLength = strlen($startNumStr);
        
        if ($start > 0 && $end >= $start) {
            $limit = min($end, $start + 100000);
            for ($i = $start; $i <= $limit; $i++) {
                $tagsToEnroll[] = $finalPrefix . str_pad((string)$i, $padLength, '0', STR_PAD_LEFT);
            }
        }
    } elseif ($mode === 'list' && !empty($_POST['tags_text'])) {
        $tagsToEnroll = explode("\n", $_POST['tags_text']);
    }

    if (empty($tagsToEnroll)) {
        header("Location: rfid.php?tab=enrolamiento&error=" . urlencode("Datos inválidos para enrolar."));
        exit();
    }

    $res = $tagController->enrollManualBatch($tagsToEnroll);
    if ($res['success']) {
        header("Location: rfid.php?tab=enrolamiento&success=" . $res['enrolled']);
    } else {
        header("Location: rfid.php?tab=enrolamiento&error=" . urlencode($res['error']));
    }
    exit();
}

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px;">
    <header style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Gestión RFID</h1>
            <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Enrolamiento de TAGs y simulador de hardware.</p>
        </div>
        <div style="display: flex; gap: 8px; background: #f1f5f9; padding: 4px; border-radius: 12px;">
            <button onclick="switchTab('enrolamiento')" id="tab-enrolamiento" class="btn-tab active">ENROLAMIENTO</button>
            <button onclick="switchTab('simulador')" id="tab-simulador" class="btn-tab">LECTOR USB / SIMULADOR</button>
        </div>
    </header>

    <!-- Sección: Enrolamiento -->
    <div id="section-enrolamiento" style="display: grid; grid-template-columns: 1fr; gap: 32px;">
        <main class="card" style="max-width: 800px; margin: 0 auto; width: 100%;">
            <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 24px;">Enrolamiento Manual de TAGs</h3>
            
            <?php if(isset($_GET['success']) && $_GET['tab'] === 'enrolamiento'): ?>
                <div style="background: #dcfce3; color: #166534; padding: 16px; border-radius: 8px; margin-bottom: 16px; font-weight: bold;">
                    Se enrolaron exitosamente <?php echo htmlspecialchars($_GET['success']); ?> TAG(s).
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && $_GET['tab'] === 'enrolamiento'): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 16px; border-radius: 8px; margin-bottom: 16px; font-weight: bold;">
                    Error: <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <p style="font-size: 14px; color: #64748b; margin-bottom: 24px; line-height: 1.6;">
                Selecciona la modalidad para dar de alta los identificadores. Estos quedarán como "Disponibles" listos para asociarse.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="enroll_tags">
                
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Modo de Captura</label>
                    <select name="enroll_mode" id="enroll_mode" class="form-control" onchange="toggleEnrollMode()" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; width: 100%; font-weight: 700; color: #334155;">
                        <option value="range">Lote Secuencial (Rango Automático)</option>
                        <option value="single">Unidad Única (Captura Manual o Escáner)</option>
                        <option value="list">Lista Manual (Copiar/Pegar Lote)</option>
                    </select>
                </div>

                <!-- MODO RANGE -->
                <div id="mode-range" style="display: block; background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #334155; margin-bottom: 16px;">Generación Cíclica</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 6px;">Prefijo</label>
                            <input type="text" name="range_prefix" class="form-control" placeholder="Ej: TAG-" style="font-family: 'JetBrains Mono', monospace;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 6px;">Inicial</label>
                            <input type="text" name="range_start" class="form-control" placeholder="Ej: 001" style="font-family: 'JetBrains Mono', monospace;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 10px; font-weight: 800; color: #64748b; margin-bottom: 6px;">Final</label>
                            <input type="text" name="range_end" class="form-control" placeholder="Ej: 100" style="font-family: 'JetBrains Mono', monospace;">
                        </div>
                    </div>
                </div>

                <!-- MODO SINGLE -->
                <div id="mode-single" style="display: none; background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 8px;">UID de la Tarjeta</label>
                    <input type="text" name="single_tag" class="form-control" placeholder="Haz clic aquí y pasa la tarjeta..." style="font-family: 'JetBrains Mono', monospace; font-size: 16px; padding: 16px; color: var(--active-blue);">
                </div>

                <!-- MODO LIST -->
                <div id="mode-list" style="display: none; background: #f8fafc; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 8px;">Pegar Lista de Códigos</label>
                    <textarea name="tags_text" rows="8" placeholder="E200001B&#10;A100001B" class="form-control" style="width: 100%; font-family: 'JetBrains Mono', monospace; padding: 16px; resize: vertical;"></textarea>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-primary" style="padding: 12px 32px; font-size: 14px;">EJECUTAR ENROLAMIENTO</button>
                </div>
            </form>
        </main>
    </div>

    <!-- Sección: Simulador -->
    <div id="section-simulador" style="display: none; flex-direction: column; gap: 32px; max-width: 800px; margin: 0 auto; width: 100%;">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-weight: 800; color: #1e293b; margin: 0;">Lector Físico y Simulador (HW)</h3>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span id="serial-status" style="font-size: 11px; font-weight: 900; color: #ef4444; text-transform: uppercase;">Desconectado</span>
                    <button type="button" onclick="connectSerial()" class="btn-secondary" style="padding: 6px 12px; font-size: 10px; cursor: pointer; text-decoration: none;">CONECTAR ARDUINO (USB)</button>
                </div>
            </div>
            
            <p style="font-size: 13px; color: #64748b; margin-bottom: 24px;">Conecta tu placa Arduino por USB, presiona el botón "Conectar" y acerca un TAG al lector RC522. Alternativamente, escribe un UID manual para simularlo.</p>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">ID del Tag (RFID)</label>
                    <input type="text" id="sim-tag" class="form-control" placeholder="E200001" style="font-family: 'JetBrains Mono', monospace;">
                </div>

                <div>
                    <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Ubicación (Lector/Antena)</label>
                    <select id="sim-lec" class="form-control">
                        <option value="1">Antena 1 - Entrada CIC</option>
                        <option value="2">Antena 2 - Entrada PIDET</option>
                    </select>
                </div>

                <button onclick="simularEscaneo()" class="btn-primary" style="width: 100%; justify-content: center; height: 48px;">
                    <i data-lucide="zap"></i> ENVIAR SEÑAL DE HARDWARE
                </button>
            </div>
        </div>

        <div id="sim-result" style="display: none;" class="card">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div id="result-icon" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="check" style="color: white;"></i>
                </div>
                <div>
                    <p id="result-title" style="font-size: 16px; font-weight: 800; color: #1e293b;"></p>
                    <p id="result-desc" style="font-size: 12px; color: #94a3b8;"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-tab {
        border: none; background: none; padding: 8px 16px; border-radius: 10px;
        font-size: 11px; font-weight: 900; color: #94a3b8; cursor: pointer; transition: all 0.2s;
    }
    .btn-tab.active { background: white; color: var(--active-blue); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
</style>

<script>
    function toggleEnrollMode() {
        const mode = document.getElementById('enroll_mode').value;
        document.getElementById('mode-single').style.display = mode === 'single' ? 'block' : 'none';
        document.getElementById('mode-range').style.display = mode === 'range' ? 'block' : 'none';
        document.getElementById('mode-list').style.display = mode === 'list' ? 'block' : 'none';
    }

    function switchTab(tab) {
        document.getElementById('section-enrolamiento').style.display = tab === 'enrolamiento' ? 'grid' : 'none';
        document.getElementById('section-simulador').style.display = tab === 'simulador' ? 'flex' : 'none';
        
        document.querySelectorAll('.btn-tab').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
    }

    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'enrolamiento';
    switchTab(activeTab);

    let serialPort;
    let serialReader;

    async function connectSerial() {
        if (!('serial' in navigator)) {
            alert("Tu navegador no soporta Web Serial API. Usa Google Chrome o Microsoft Edge en tu PC.");
            return;
        }
        
        try {
            serialPort = await navigator.serial.requestPort();
            await serialPort.open({ baudRate: 9600 });
            
            const decoder = new TextDecoderStream();
            serialPort.readable.pipeTo(decoder.writable);
            const inputStream = decoder.readable;
            serialReader = inputStream.getReader();
            
            document.getElementById('serial-status').innerText = "CONECTADO AL COM";
            document.getElementById('serial-status').style.color = "#10b981";
            
            readSerialLoop();
        } catch (e) {
            console.error(e);
            alert("Error al conectar con el lector USB: " + e.message);
        }
    }

    async function readSerialLoop() {
        let buffer = "";
        while (true) {
            const { value, done } = await serialReader.read();
            if (done) {
                serialReader.releaseLock();
                break;
            }
            buffer += value;
            if (buffer.includes("\n")) {
                let lines = buffer.split("\n");
                buffer = lines.pop(); // Guarda la línea incompleta
                for (let line of lines) {
                    line = line.trim();
                    // Filtra la cadena "Card UID:" que emite el nuevo script de Arduino
                    if (line.includes("Card UID:")) {
                        let uid = line.split("Card UID:")[1].trim().replace(/\s/g, "").toUpperCase();
                        
                        // Llenar el input del formulario y enviar la petición REST
                        document.getElementById('sim-tag').value = uid;
                        simularEscaneo();
                    }
                }
            }
        }
    }

    async function simularEscaneo() {
        const tag = document.getElementById('sim-tag').value;
        const lec = document.getElementById('sim-lec').value;
        const resultDiv = document.getElementById('sim-result');
        
        if(!tag) return alert('Ingresa un TAG');

        try {
            const response = await fetch('../backend/api/index.php/hardware/rfid-scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tag_id: tag, lec_id: lec })
            });
            
            const data = await response.json();
            
            resultDiv.style.display = 'block';
            if (data.success) {
                document.getElementById('result-icon').style.background = '#10b981';
                document.getElementById('result-icon').innerHTML = '<i data-lucide="check" style="color: white;"></i>';
                document.getElementById('result-title').innerText = 'ESCANEADO: ' + data.action;
                document.getElementById('result-desc').innerText = 'Identidad: ' + (data.entity_name || 'Tag Detectado') + ' | ' + data.timestamp;
            } else {
                document.getElementById('result-icon').style.background = '#ef4444';
                document.getElementById('result-icon').innerHTML = '<i data-lucide="x" style="color: white;"></i>';
                document.getElementById('result-title').innerText = 'ERROR';
                document.getElementById('result-desc').innerText = data.error;
            }
            lucide.createIcons();
        } catch (e) {
            alert('Error en la conexión con el API');
        }
    }
</script>

<?php include 'footer.php'; ?>
