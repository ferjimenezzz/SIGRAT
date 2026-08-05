<?php
// Autenticación manejada por header.php

require_once '../backend/config/Database.php';
$db = Config\Database::getConnection();

// Obtener todos los espacios para poder asignarlos a los polígonos
$stmt = $db->query("SELECT esp_id, edificio, planta, nombre_numero, tipo FROM espacio ORDER BY edificio, planta, nombre_numero");
$spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Map Builder - SIGRAT";
include 'header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold" style="color: var(--text-dark);">Map Builder</h2>
            <p class="text-muted mb-0">Traza y edita los polígonos interactivos de los planos arquitectónicos.</p>
        </div>
        <div class="d-flex gap-2">
            <button id="btnUndo" class="btn btn-outline-secondary" onclick="undo()" disabled title="Deshacer (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i> Deshacer</button>
            <button id="btnGrid" class="btn btn-outline-primary" onclick="toggleGrid()" title="Mostrar/Ocultar Cuadrícula"><i class="bi bi-grid-3x3"></i> Grid</button>
            <button id="btnSave" class="btn btn-primary" onclick="saveMapData()"><i class="bi bi-save"></i> Guardar Cambios</button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Panel lateral de herramientas -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">1. Seleccionar Plano</h6>
                    <select id="mapSelector" class="form-select mb-4" onchange="loadMap(this.value)">
                        <option value="PIDET_alta">PIDET - Planta Alta</option>
                        <option value="PIDET_baja">PIDET - Planta Baja</option>
                        <option value="CIC_alta">CIC - Planta Alta</option>
                        <option value="CIC_baja">CIC - Planta Baja</option>
                    </select>

                    <h6 class="fw-bold mb-3">2. Herramientas</h6>
                    <div class="btn-group-vertical w-100 mb-4" role="group">
                        <input type="radio" class="btn-check" name="tools" id="toolSelect" autocomplete="off" value="select" checked onchange="setTool(this.value)">
                        <label class="btn btn-outline-primary text-start" for="toolSelect"><i class="bi bi-cursor"></i> Seleccionar / Mover</label>

                        <input type="radio" class="btn-check" name="tools" id="toolDraw" autocomplete="off" value="draw" onchange="setTool(this.value)">
                        <label class="btn btn-outline-primary text-start" for="toolDraw"><i class="bi bi-pen"></i> Dibujar Polígono</label>
                        
                        <input type="radio" class="btn-check" name="tools" id="toolText" autocomplete="off" value="text" onchange="setTool(this.value)">
                        <label class="btn btn-outline-primary text-start" for="toolText"><i class="bi bi-fonts"></i> Añadir Texto libre</label>

                        <input type="radio" class="btn-check" name="tools" id="toolAssign" autocomplete="off" value="assign" onchange="setTool(this.value)">
                        <label class="btn btn-outline-primary text-start" for="toolAssign"><i class="bi bi-link"></i> Asignar espacio existente</label>
                    </div>

                    <div id="selectionPanel" style="display: none;">
                        <h6 class="fw-bold mb-3 text-primary">Propiedades del Polígono</h6>
                        <label class="form-label text-muted small">Espacio asociado:</label>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" id="spaceSearch" class="form-control" placeholder="🔍 Buscar espacio..." onkeyup="filterSpaceDropdown()">
                            <button class="btn btn-outline-danger" onclick="clearSelectedLabel()" title="Quitar Etiqueta"><i class="bi bi-trash"></i></button>
                        </div>
                        <select id="spaceAssigner" class="form-select mb-3" onchange="assignSpaceToSelected(this.value)">
                            <option value="">-- Sin asignar --</option>
                        </select>

                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="duplicateSelected()"><i class="bi bi-copy"></i> Duplicar Polígono</button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteSelected()"><i class="bi bi-trash"></i> Eliminar Polígono</button>
                        </div>
                    </div>

                    <div id="textPropertiesPanel" style="display: none;">
                        <h6 class="fw-bold mb-3 text-primary">Propiedades del Texto</h6>
                        <label class="form-label text-muted small">Contenido:</label>
                        <input type="text" id="textContentInput" class="form-control form-control-sm mb-2" onkeyup="updateSelectedTextContent(this.value)">
                        
                        <label class="form-label text-muted small">Tamaño de letra:</label>
                        <input type="range" id="textSizeInput" class="form-range mb-3" min="10" max="60" value="16" oninput="updateSelectedTextSize(this.value)">
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteSelectedText()"><i class="bi bi-trash"></i> Eliminar Texto</button>
                        </div>
                    </div>

                    <hr>
                    <div class="alert alert-info py-2 small mb-0">
                        <strong>Atajos:</strong><br>
                        - <b>Clic:</b> Agregar vértice (Dibujar)<br>
                        - <b>Enter / Doble Clic:</b> Cerrar polígono<br>
                        - <b>Arrastrar:</b> Mover vértice o plano<br>
                        - <b>Rueda Mouse:</b> Zoom<br>
                        - <b>Supr / Backspace:</b> Eliminar seleccionado<br>
                        - <b>Ctrl+Z:</b> Deshacer
                    </div>
                </div>
            </div>
        </div>

        <!-- Área de Dibujo -->
        <div class="col-md-9">
            <div class="card border-0 shadow-sm rounded-4" style="height: 75vh; background: #e2e8f0; cursor: grab; overflow: hidden; position: relative;" id="canvasContainer">
                
                <!-- Contenedor con zoom/pan -->
                <div id="zoomContainer" style="transform-origin: 0 0; position: relative; display: inline-block;">
                    <img id="mapImage" src="" style="display: block; pointer-events: none;" alt="Plano" draggable="false">
                    <svg id="drawSvg" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden;">
                        <!-- Polígonos generados dinámicamente -->
                        <g id="gridGroup" style="display: none; pointer-events: none;"></g>
                        <g id="polygonsGroup"></g>
                        <g id="textsGroup"></g>
                        <g id="handlesGroup"></g>
                        <g id="tempDrawGroup"></g>
                    </svg>
                </div>

                <!-- Controles de Zoom -->
                <div class="position-absolute bottom-0 end-0 m-3 d-flex flex-column gap-2">
                    <button class="btn btn-light shadow-sm" onclick="setZoom(zoomLevel + 0.2)"><i class="bi bi-zoom-in"></i></button>
                    <button class="btn btn-light shadow-sm" onclick="setZoom(zoomLevel - 0.2)"><i class="bi bi-zoom-out"></i></button>
                    <button class="btn btn-light shadow-sm" onclick="resetZoom()"><i class="bi bi-arrows-fullscreen"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const allSpaces = <?php echo json_encode($spaces); ?>;
</script>
<script src="assets/js/map_editor.js"></script>

<?php include 'footer.php'; ?>
