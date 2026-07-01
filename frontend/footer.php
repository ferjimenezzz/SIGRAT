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

    <script>
        function exportTableToExcel(tableID, filename = 'Exportacion_SIGRAT') {
            let table = document.getElementById(tableID);
            if (!table) {
                console.error("Tabla no encontrada");
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
                    } else {
                        headers.push(th.innerText.trim());
                    }
                });
            } else {
                // Caso tablas sin thead (por si acaso)
                let firstRow = table.querySelector('tr');
                if(firstRow) {
                    firstRow.querySelectorAll('th, td').forEach((cell, index) => {
                        let text = cell.innerText.trim().toUpperCase();
                        if (text === 'ACCIONES' || text === 'ACCIÓN' || text === 'OPCIONES') {
                            ignoreCols.push(index);
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
                    if (isFirst && trIndex === 0) return; // Si no hay thead, la primera es header
                    if (tr.style.display === 'none') return;
                    
                    let rowData = [];
                    let tds = tr.querySelectorAll('td');
                    if (tds.length === 1 && tds[0].colSpan > 1) return; // Mensajes vacíos
                    
                    tds.forEach((td, index) => {
                        if (!ignoreCols.includes(index)) {
                            let text = td.innerText.trim().replace(/\n\s*\n/g, ' ');
                            text = text.replace(/\n/g, ' - ');
                            rowData.push(text);
                        }
                    });
                    if (rowData.length > 0) rows.push(rowData);
                });
            }

            let payload = {
                title: filename.replace(/_/g, ' '),
                headers: headers,
                rows: rows
            };

            let form = document.createElement('form');
            form.method = 'POST';
            form.action = '../backend/reports/excel_export.php';
            form.style.display = 'none';

            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'exportData';
            input.value = JSON.stringify(payload);

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            if (typeof showToast === 'function') {
                showToast("Iniciando descarga de Excel...", "success");
            }
        }
    </script>
</body>
</html>
