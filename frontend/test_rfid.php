<?php
/**
 * @file test_rfid.php
 * @summary Simulador de Hardware RFID para pruebas.
 * @description Permite enviar peticiones al API de forma visual para probar la detección de tags y el registro en bitácora.
 */

include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 32px; max-width: 800px;">
    <header>
        <h1 style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px;">Simulador RFID</h1>
        <p style="font-size: 14px; color: #94a3b8; font-weight: 500;">Use esta herramienta para simular la señal de los lectores físicos.</p>
    </header>

    <div class="card">
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div>
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">ID del Tag (RFID)</label>
                <select id="sim-tag" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 700;">
                    <option value="E200001">E200001 (Usuario Admin)</option>
                    <option value="A100001">A100001 (Activo / Herramienta)</option>
                    <option value="K500001">K500001 (Llave de Espacio)</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Ubicación (Lector/Antena)</label>
                <select id="sim-lec" style="width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 12px; font-weight: 700;">
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

<script>
async function simularEscaneo() {
    const tag = document.getElementById('sim-tag').value;
    const lec = document.getElementById('sim-lec').value;
    const resultDiv = document.getElementById('sim-result');
    
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
            document.getElementById('result-title').innerText = 'ESCANEADO: ' + data.action;
            document.getElementById('result-desc').innerText = 'Identidad: ' + (data.entity_name || 'Tag Detectado') + ' | ' + data.timestamp;
        } else {
            document.getElementById('result-icon').style.background = '#ef4444';
            document.getElementById('result-title').innerText = 'ERROR';
            document.getElementById('result-desc').innerText = data.error;
        }
    } catch (e) {
        alert('Error en la conexión con el API');
    }
}
</script>

<?php include 'footer.php'; ?>
