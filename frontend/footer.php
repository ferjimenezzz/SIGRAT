<?php
/**
 * @file footer.php
 * @summary Cierre del layout principal.
 * @description Finaliza las etiquetas de cuerpo y HTML e inicializa Lucide Icons para páginas que aún lo usan.
 */
?>
        </main>
    </div>
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

    <!-- Librería para Exportar a Excel (SheetJS) -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    <script>
        function exportTableToExcel(tableID, filename = 'Exportacion_SIGRAT') {
            let table = document.getElementById(tableID);
            if(!table) {
                console.error("Tabla no encontrada");
                return;
            }
            
            // Clonar tabla
            let cloneTable = table.cloneNode(true);
            
            // Remover última columna (Acciones) y elementos ocultos
            let rows = cloneTable.querySelectorAll('tr');
            rows.forEach(row => {
                if(row.children.length > 0) {
                    row.removeChild(row.lastElementChild);
                }
            });

            // Convertir a libro y descargar
            let wb = XLSX.utils.table_to_book(cloneTable, {sheet: "Hoja 1"});
            XLSX.writeFile(wb, filename + ".xlsx");
            showToast("Archivo Excel generado con éxito", "success");
        }
    </script>
</body>
</html>
