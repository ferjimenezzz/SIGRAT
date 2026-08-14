<?php
/**
 * @file footer.php
 * @summary Cierre del layout principal.
 * @description Finaliza las etiquetas de cuerpo y HTML e inicializa Lucide Icons para páginas que aún lo usan.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

?>

        </main>
    </div>


<!-- ============================================================================ -->
<!-- SECCIÓN 4: CONTROLADORES JAVASCRIPT, EVENTOS Y FETCH API -->
<!-- ============================================================================ -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        // Inicializar iconos de Lucide (para páginas que aún los usan)
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>

    <!-- Librería para Notificaciones Push (Toastify) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        function showToast(message, type = 'success') {
            let bgColor = type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#3b82f6');
            Toastify({
                text: message,
                duration: 4000,
                close: true,
                gravity: "top", 
                position: "right",
                style: {
                    background: bgColor,
                    borderRadius: '8px',
                    fontFamily: 'Inter, sans-serif',
                    fontSize: '14px',
                    fontWeight: '500',
                    boxShadow: '0 4px 10px rgba(0,0,0,0.1)'
                }
            }).showToast();
        }
    </script>

    <!-- Styles y Modal para Vista Previa de Exportación Excel -->
    <style>
        .excel-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(5px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: excelModalFadeIn 0.2s ease-out;
        }
        @keyframes excelModalFadeIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }
        .excel-modal-container {
            background: #ffffff;
            border-radius: 16px;
            max-width: 920px;
            width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            font-family: inherit;
        }
        .excel-modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
        }
        .excel-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #dcfce7;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .excel-modal-close-btn {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .excel-modal-close-btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .excel-modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            flex: 1;
        }
        .excel-sheet-header-box {
            margin-bottom: 16px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .excel-table-preview-wrapper {
            max-height: 340px;
            overflow: auto;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
        }
        .excel-preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
        }
        .excel-preview-table th {
            background: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            padding: 10px 14px;
            border: 1px solid #3b82f6;
            position: sticky;
            top: 0;
            z-index: 2;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .excel-preview-table td {
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            color: #334155;
            white-space: nowrap;
        }
        .excel-preview-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .excel-preview-table tr:hover {
            background: #e2e8f0;
        }
        .excel-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .excel-btn-cancel {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .excel-btn-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
        .excel-btn-confirm {
            background: #16a34a;
            border: none;
            color: #ffffff;
            padding: 9px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.88rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(22, 163, 74, 0.2);
            transition: all 0.15s ease;
        }
        .excel-btn-confirm:hover {
            background: #15803d;
            box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
        }
    </style>

    <script>
        let currentExcelExportPayload = null;

        function ensureExcelModalExists() {
            if (document.getElementById('excelPreviewModal')) return;

            let modalHTML = `
            <div id="excelPreviewModal" class="excel-modal-overlay" style="display: none;" onclick="handleExcelOverlayClick(event)">
                <div class="excel-modal-container">
                    <div class="excel-modal-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="excel-icon-box">
                                <i data-lucide="file-spreadsheet" style="width:24px; height:24px; color:#16a34a;"></i>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #0f172a;">Vista Previa de Exportación Excel</h3>
                                <span id="excelPreviewSubtitle" style="font-size: 0.85rem; color: #64748b;">Reporte &bull; 0 registros</span>
                            </div>
                        </div>
                        <button type="button" onclick="closeExcelPreviewModal()" class="excel-modal-close-btn">&times;</button>
                    </div>
                    
                    <div class="excel-modal-body">
                        <div class="excel-sheet-header-box">
                            <div style="background: #1e3a8a; color: white; padding: 10px 16px; font-weight: 700; font-size: 0.95rem; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
                                <span>SISTEMA INTEGRAL DE GESTIÓN DE RECURSOS (SIGRAT)</span>
                                <span style="font-size: 0.75rem; background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px;">Formato .xlsx</span>
                            </div>
                            <div style="background: #f8fafc; padding: 12px 16px; border: 1px solid #e2e8f0; border-top: none; font-size: 0.85rem; color: #334155; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                                <div>
                                    <strong id="excelPreviewReportTitle" style="color: #1e3a8a; font-size: 0.95rem; text-transform: uppercase;">REPORTE DE DATOS</strong>
                                </div>
                                <div style="display: flex; gap: 16px; color: #64748b;">
                                    <span><i data-lucide="calendar" style="width:14px; height:14px; display:inline; vertical-align:-2px;"></i> <span id="excelPreviewDate">--</span></span>
                                    <span><i data-lucide="shield-check" style="width:14px; height:14px; display:inline; vertical-align:-2px;"></i> Formato Oficial SIGRAT</span>
                                </div>
                            </div>
                        </div>

                        <div class="excel-table-preview-wrapper">
                            <table id="excelPreviewTable" class="excel-preview-table">
                                <thead id="excelPreviewThead"></thead>
                                <tbody id="excelPreviewTbody"></tbody>
                            </table>
                        </div>
                        <div id="excelPreviewTruncatedNotice" style="font-size: 0.78rem; color: #64748b; margin-top: 8px; font-style: italic; display: none;">
                            * Mostrando las primeras 50 filas en la vista previa. El archivo Excel contendrá el total de registros.
                        </div>
                    </div>

                    <div class="excel-modal-footer">
                        <div style="font-size: 0.82rem; color: #64748b;">
                            <i data-lucide="info" style="width:14px; height:14px; display:inline; vertical-align:-2px; color:#3b82f6;"></i> Verifica que las columnas y datos coincidan antes de confirmar.
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" onclick="closeExcelPreviewModal()" class="excel-btn-cancel">
                                Cancelar
                            </button>
                            <button type="button" onclick="confirmExcelDownload()" class="excel-btn-confirm">
                                <i data-lucide="download" style="width: 16px; height: 16px;"></i> Descargar Excel (.xlsx)
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        function handleExcelOverlayClick(e) {
            if (e.target && e.target.id === 'excelPreviewModal') {
                closeExcelPreviewModal();
            }
        }

        function closeExcelPreviewModal() {
            let modal = document.getElementById('excelPreviewModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function confirmExcelDownload() {
            if (!currentExcelExportPayload) return;

            let form = document.createElement('form');
            form.method = 'POST';
            form.action = '../backend/reports/excel_export.php';
            form.style.display = 'none';

            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'filters';
            // Enviar payload completo con título, encabezados y filas filtradas
            input.value = JSON.stringify(currentExcelExportPayload);
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            closeExcelPreviewModal();

            if (typeof showToast === 'function') {
                showToast("Iniciando descarga de reporte Excel (.xlsx)...", "success");
            }
        }

        function exportTableToExcel(tableID, filename = 'Exportacion_SIGRAT') {
            let table = document.getElementById(tableID);
            if (!table) {
                console.error("Tabla no encontrada: " + tableID);
                if (typeof showToast === 'function') {
                    showToast("Error: No se encontró la tabla de datos.", "error");
                }
                return;
            }

            let headers = [];
            let rows = [];
            let ignoreCols = [];
            
            let thead = table.querySelector('thead');
            if (thead) {
                let ths = thead.querySelectorAll('th');
                ths.forEach((th, index) => {
                    let text = th.innerText.trim().toUpperCase();
                    if (text === 'ACCIONES' || text === 'ACCIÓN' || text === 'OPCIONES') {
                        ignoreCols.push(index);
                    } else if (th.hasAttribute('data-excel-split')) {
                        const splitHeaders = th.getAttribute('data-excel-split').split('|');
                        splitHeaders.forEach(h => headers.push(h.trim()));
                    } else {
                        headers.push(th.innerText.trim());
                    }
                });
            } else {
                let firstRow = table.querySelector('tr');
                if(firstRow) {
                    firstRow.querySelectorAll('th, td').forEach((cell, index) => {
                        let text = cell.innerText.trim().toUpperCase();
                        if (text === 'ACCIONES' || text === 'ACCIÓN' || text === 'OPCIONES') {
                            ignoreCols.push(index);
                        } else if (cell.hasAttribute('data-excel-split')) {
                            const splitHeaders = cell.getAttribute('data-excel-split').split('|');
                            splitHeaders.forEach(h => headers.push(h.trim()));
                        } else {
                            headers.push(cell.innerText.trim());
                        }
                    });
                }
            }

            let tbody = table.querySelector('tbody') || table;
            if (tbody) {
                let trs = tbody.querySelectorAll('tr');
                let isFirst = !table.querySelector('thead');
                
                trs.forEach((tr, trIndex) => {
                    if (isFirst && trIndex === 0) return;
                    if (tr.getAttribute('data-filtered-out') === 'true') return;
                    if (tr.style.display === 'none' && tr.getAttribute('data-filtered-out') !== 'false') return;
                    
                    let rowData = [];
                    let tds = tr.querySelectorAll('td');
                    if (tds.length === 1 && tds[0].colSpan > 1) return;
                    
                    tds.forEach((td, index) => {
                        if (!ignoreCols.includes(index)) {
                            if (td.hasAttribute('data-excel-col1') && td.hasAttribute('data-excel-col2')) {
                                rowData.push(td.getAttribute('data-excel-col1'));
                                rowData.push(td.getAttribute('data-excel-col2'));
                            } else {
                                let text = td.innerText.trim().replace(/\n\s*\n/g, ' ').replace(/\n/g, ' - ');
                                let img = td.querySelector('img');
                                if (!text && img && img.src) {
                                    text = img.src;
                                }
                                rowData.push(text);
                            }
                        }
                    });
                    if (rowData.length > 0) rows.push(rowData);
                });
            }

            if (rows.length === 0) {
                if (typeof showToast === 'function') {
                    showToast("No hay registros disponibles para exportar.", "warning");
                }
                return;
            }

            currentExcelExportPayload = {
                title: filename.replace(/_/g, ' '),
                headers: headers,
                rows: rows
            };

            ensureExcelModalExists();

            // Rellenar meta datos en la vista previa
            document.getElementById('excelPreviewSubtitle').textContent = `${filename}.xlsx • ${rows.length} ${rows.length === 1 ? 'registro' : 'registros'}`;
            document.getElementById('excelPreviewReportTitle').textContent = filename.replace(/_/g, ' ');
            
            let now = new Date();
            let dateStr = now.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + 
                           now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('excelPreviewDate').textContent = dateStr;

            // Rellenar Thead
            let theadEl = document.getElementById('excelPreviewThead');
            theadEl.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;

            // Rellenar Tbody (limitar a 50 filas en la vista previa por performance)
            let tbodyEl = document.getElementById('excelPreviewTbody');
            let previewRows = rows.slice(0, 50);
            tbodyEl.innerHTML = previewRows.map(row => 
                `<tr>${row.map(cell => `<td>${cell || '-'}</td>`).join('')}</tr>`
            ).join('');

            let truncatedNotice = document.getElementById('excelPreviewTruncatedNotice');
            if (rows.length > 50) {
                truncatedNotice.style.display = 'block';
                truncatedNotice.textContent = `* Mostrando las primeras 50 filas de ${rows.length} en la vista previa. El archivo Excel descargado contendrá el total de filas.`;
            } else {
                truncatedNotice.style.display = 'none';
            }

            // Mostrar Modal
            let modal = document.getElementById('excelPreviewModal');
            modal.style.display = 'flex';

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
