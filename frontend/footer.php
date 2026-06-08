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
</body>
</html>
