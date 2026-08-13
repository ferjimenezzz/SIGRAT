/**
 * Map Editor JS - SIGRAT
 * Maneja el lienzo SVG, drag & drop de vértices, dibujo, zoom/pan y guardado de datos.
 */

let MAP_DATA = {};
let currentMapKey = '';
let currentTool = 'select'; // 'select' | 'draw'

// Estado del DOM
const zoomContainer = document.getElementById('zoomContainer');
const svg = document.getElementById('drawSvg');
const polygonsGroup = document.getElementById('polygonsGroup');
const handlesGroup = document.getElementById('handlesGroup');
const tempDrawGroup = document.getElementById('tempDrawGroup');
const mapImage = document.getElementById('mapImage');
const spaceAssigner = document.getElementById('spaceAssigner');

// Variables de interacción
let zoomLevel = 1.0;
let panX = 0, panY = 0;
let isPanning = false;
let startPanX = 0, startPanY = 0;

let polygons = []; // { id, db_name, points: [{x, y}] }
let selectedPolyId = null;
let draggingHandle = null; // { polyId, pointIndex }

let texts = []; // { id, x, y, content, size }
let selectedTextId = null;
let draggingText = null;

let isDrawing = false;
let currentDrawingPoints = [];

// Undo Stack
let undoStack = [];

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    populateSpaceAssigner();
    
    // Cargar config actual
    fetch('assets/map_data.json')
        .then(res => res.json())
        .then(data => {
            MAP_DATA = data;
            loadMap(document.getElementById('mapSelector').value);
        })
        .catch(err => alert("Error cargando map_data.json: " + err));
});

function populateSpaceAssigner() {
    spaceAssigner.innerHTML = '<option value="">-- Sin asignar --</option>';
    
    // Obtener edificio y planta del mapa actual
    const config = MAP_DATA[currentMapKey];
    if (!config) return;

    const mapParts = currentMapKey.split('_');
    const mapEdificio = mapParts[0].toLowerCase().trim();
    const mapPlanta = mapParts.length > 1 ? (mapParts[1] === 'alta' ? 'planta alta' : 'planta baja') : '';

    // Filtrado: mismo edificio + (misma planta OR planta null/vacía)
    const filteredSpaces = allSpaces.filter(sp => {
        const dbEdif = (sp.edificio || '').toLowerCase().trim();
        const dbPlan = (sp.planta || '').toLowerCase().trim();
        
        if (dbEdif !== mapEdificio) return false;
        // Incluir espacios de la planta exacta O espacios sin planta asignada (generales del edificio)
        return dbPlan === mapPlanta || dbPlan === '';
    });

    filteredSpaces.forEach(sp => {
        const opt = document.createElement('option');
        opt.value = sp.nombre_numero;
        opt.textContent = `${sp.nombre_numero} (${sp.tipo})`;
        opt.dataset.search = sp.nombre_numero.toLowerCase() + ' ' + sp.tipo.toLowerCase();
        opt.dataset.espid = sp.esp_id;
        spaceAssigner.appendChild(opt);
    });
}

function filterSpaceDropdown() {
    const term = document.getElementById('spaceSearch').value.toLowerCase();
    const options = spaceAssigner.options;
    
    // Saltarse la primera opción ("-- Sin asignar --")
    for (let i = 1; i < options.length; i++) {
        const opt = options[i];
        if (opt.dataset.search.includes(term)) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    }
}

function loadMap(mapKey) {
    if (!MAP_DATA[mapKey]) return;
    saveStateToUndo(); // Guardar estado antes de cambiar (opcional)
    
    currentMapKey = mapKey;
    const config = MAP_DATA[mapKey];
    mapImage.onload = () => {
        svg.style.width = mapImage.naturalWidth + 'px';
        svg.style.height = mapImage.naturalHeight + 'px';
        drawGrid(mapImage.naturalWidth, mapImage.naturalHeight);
        resetZoom();
        renderPolygons();
    };
    mapImage.src = config.image;
    
    polygons = config.zones.map((z, i) => {
        const pts = z.points.split(' ').map(p => {
            const [x, y] = p.split(',');
            return { x: parseFloat(x), y: parseFloat(y) };
        });
        return { id: 'poly_' + i + '_' + Date.now(), db_name: z.db_name, esp_id: z.esp_id || null, points: pts };
    });
    
    texts = config.texts ? JSON.parse(JSON.stringify(config.texts)) : [];
    
    populateSpaceAssigner(); // Recargar opciones del dropdown al cambiar de mapa
    document.getElementById('spaceSearch').value = ''; // Limpiar buscador
    selectPolygon(null);
}

// ── SISTEMA DE HISTORIAL (UNDO) ──
function saveStateToUndo() {
    undoStack.push(JSON.stringify({ polygons, texts }));
    if (undoStack.length > 20) undoStack.shift(); // Límite de 20 estados
    document.getElementById('btnUndo').disabled = false;
}

function undo() {
    if (undoStack.length === 0) return;
    const stateStr = undoStack.pop();
    
    // Retrocompatibilidad por si el undo stack viejo solo era polygons
    try {
        const state = JSON.parse(stateStr);
        if (Array.isArray(state)) {
            polygons = state;
            texts = [];
        } else {
            polygons = state.polygons;
            texts = state.texts;
        }
    } catch(e) {}
    
    selectPolygon(null);
    selectText(null);
    renderPolygons();
    renderTexts();
    if (undoStack.length === 0) {
        document.getElementById('btnUndo').disabled = true;
    }
}

document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); }
});

// ── HERRAMIENTAS ──
function setTool(tool) {
    currentTool = tool;
    selectPolygon(null);
    if (typeof selectText === 'function') selectText(null);
    
    if (tool === 'draw') {
        svg.style.cursor = 'crosshair';
        document.getElementById('canvasContainer').style.cursor = 'crosshair';
    } else if (tool === 'text') {
        cancelDrawing();
        svg.style.cursor = 'text';
        document.getElementById('canvasContainer').style.cursor = 'text';
    } else if (tool === 'assign') {
        cancelDrawing();
        svg.style.cursor = 'help';
        document.getElementById('canvasContainer').style.cursor = 'help';
    } else {
        cancelDrawing();
        svg.style.cursor = 'grab';
        document.getElementById('canvasContainer').style.cursor = 'grab';
    }
}

// ── RENDERIZADO ──
function renderTexts() {
    const group = document.getElementById('textsGroup');
    if (!group) return;
    group.innerHTML = '';
    
    texts.forEach(t => {
        const textEl = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        textEl.setAttribute('x', t.x);
        textEl.setAttribute('y', t.y);
        textEl.setAttribute('font-size', t.size || 16);
        textEl.setAttribute('font-family', 'sans-serif');
        textEl.setAttribute('font-weight', 'bold');
        textEl.setAttribute('paint-order', 'stroke fill');
        textEl.setAttribute('stroke', 'rgba(255, 255, 255, 0.9)');
        textEl.setAttribute('stroke-width', '4px');
        textEl.setAttribute('text-anchor', 'middle');
        textEl.setAttribute('dominant-baseline', 'middle');
        textEl.style.cursor = currentTool === 'select' ? 'pointer' : 'default';
        textEl.style.userSelect = 'none';

        if (t.id === selectedTextId) {
            textEl.setAttribute('fill', '#2563eb'); // Azul si está seleccionado
        } else {
            textEl.setAttribute('fill', '#1e293b');
        }

        const lines = (t.content || '').split('\n');
        if (lines.length > 1) {
            lines.forEach((lineText, idx) => {
                const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                tspan.setAttribute('x', t.x);
                if (idx === 0) {
                    const shift = -((lines.length - 1) * 0.6) + "em";
                    tspan.setAttribute('dy', shift);
                } else {
                    tspan.setAttribute('dy', '1.2em');
                }
                tspan.textContent = lineText;
                textEl.appendChild(tspan);
            });
        } else {
            textEl.textContent = t.content;
        }

        // Selección por click
        textEl.addEventListener('mousedown', (e) => {
            if (currentTool === 'select') {
                e.stopPropagation(); // Evitar pan
                selectText(t.id);
                draggingText = t.id;
            }
        });

        group.appendChild(textEl);
    });
}

function renderPolygons() {
    polygonsGroup.innerHTML = '';
    handlesGroup.innerHTML = '';

    polygons.forEach(poly => {
        const isSelected = (poly.id === selectedPolyId);
        
        // Crear polígono
        const svgPoly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        svgPoly.setAttribute('points', poly.points.map(p => `${p.x},${p.y}`).join(' '));
        svgPoly.setAttribute('fill', isSelected ? 'rgba(37, 99, 235, 0.4)' : 'rgba(37, 99, 235, 0.1)');
        svgPoly.setAttribute('stroke', isSelected ? '#2563eb' : '#64748b');
        svgPoly.setAttribute('stroke-width', isSelected ? '4' : '2');
        svgPoly.style.cursor = currentTool === 'select' ? 'pointer' : 'default';
        svgPoly.style.pointerEvents = 'all';

        // Etiqueta flotante
        if (poly.db_name) {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            const center = getPolygonCenter(poly.points);
            text.setAttribute('x', center.x);
            text.setAttribute('y', center.y);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dominant-baseline', 'middle');
            text.setAttribute('fill', isSelected ? '#fff' : '#1e293b');
            text.setAttribute('font-size', '16px');
            text.setAttribute('font-weight', 'bold');
            text.style.pointerEvents = 'none';
            text.textContent = poly.db_name;
            // Background opcional para el texto
            polygonsGroup.appendChild(svgPoly);
            polygonsGroup.appendChild(text);
        } else {
            polygonsGroup.appendChild(svgPoly);
        }

        // Evento de selección
        svgPoly.addEventListener('mousedown', (e) => {
            if (currentTool === 'select') {
                e.stopPropagation();
                selectPolygon(poly.id);
            }
        });

        // Evento de inserción de vértice (doble clic en la línea)
        svgPoly.addEventListener('dblclick', (e) => {
            if (currentTool === 'select' && isSelected) {
                e.stopPropagation();
                insertVertex(poly, e);
            }
        });

        // Dibujar handles (vértices) si está seleccionado
        if (isSelected) {
            poly.points.forEach((pt, idx) => {
                const handle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                handle.setAttribute('cx', pt.x);
                handle.setAttribute('cy', pt.y);
                handle.setAttribute('r', '6');
                handle.setAttribute('fill', '#ffffff');
                handle.setAttribute('stroke', '#ef4444');
                handle.setAttribute('stroke-width', '2');
                handle.style.cursor = 'move';
                handle.style.pointerEvents = 'all';

                handle.addEventListener('mousedown', (e) => {
                    e.stopPropagation();
                    saveStateToUndo();
                    draggingHandle = { polyId: poly.id, pointIndex: idx };
                });

                // Eliminar vértice con doble clic en el handle
                handle.addEventListener('dblclick', (e) => {
                    e.stopPropagation();
                    saveStateToUndo();
                    if (poly.points.length > 3) {
                        poly.points.splice(idx, 1);
                        renderPolygons();
                    } else {
                        alert("Un polígono debe tener al menos 3 vértices.");
                    }
                });

                handlesGroup.appendChild(handle);
            });
        }
    });
}

function getPolygonCenter(points) {
    let x = 0, y = 0;
    points.forEach(p => { x += p.x; y += p.y; });
    return { x: x / points.length, y: y / points.length };
}

// ── SELECCIÓN Y PROPIEDADES ──
function selectPolygon(polyId) {
    selectedPolyId = polyId;
    renderPolygons();

    const panel = document.getElementById('selectionPanel');
    if (polyId) {
        panel.style.display = 'block';
        const poly = polygons.find(p => p.id === polyId);
        spaceAssigner.value = poly.db_name || '';
    } else {
        panel.style.display = 'none';
        spaceAssigner.value = '';
    }
}

function assignSpaceToSelected(val) {
    if (!selectedPolyId) return;
    saveStateToUndo();
    const poly = polygons.find(p => p.id === selectedPolyId);
    if (poly) {
        poly.db_name = val;
        // Buscar esp_id desde el option seleccionado (que tiene data-espid)
        if (val) {
            const selectedOpt = Array.from(spaceAssigner.options).find(o => o.value === val);
            if (selectedOpt && selectedOpt.dataset.espid) {
                poly.esp_id = parseInt(selectedOpt.dataset.espid);
            } else {
                // Fallback: buscar en allSpaces
                const mapParts = currentMapKey.split('_');
                const mapEdificio = mapParts[0].toLowerCase().trim();
                const sp = allSpaces.find(s => s.nombre_numero === val && 
                                          (s.edificio || '').toLowerCase().trim() === mapEdificio);
                poly.esp_id = sp ? sp.esp_id : null;
            }
        } else {
            poly.esp_id = null;
        }
        renderPolygons();
    }
}

window.clearSelectedLabel = function() {
    if (!selectedPolyId) return;
    saveStateToUndo();
    const poly = polygons.find(p => p.id === selectedPolyId);
    if (poly) {
        poly.db_name = '';
        poly.esp_id = null;
        document.getElementById('spaceAssigner').value = '';
        renderPolygons();
    }
};


function deleteSelected() {
    if (!selectedPolyId) return;
    if (confirm("¿Eliminar este polígono?")) {
        saveStateToUndo();
        polygons = polygons.filter(p => p.id !== selectedPolyId);
        selectPolygon(null);
    }
}

function duplicateSelected() {
    if (!selectedPolyId) return;
    saveStateToUndo();
    const poly = polygons.find(p => p.id === selectedPolyId);
    if (poly) {
        // Clonar y desplazar ligeramente
        const newPoly = {
            id: 'poly_' + Date.now(),
            db_name: poly.db_name,
            points: poly.points.map(p => ({ x: p.x + 20, y: p.y + 20 }))
        };
        polygons.push(newPoly);
        selectPolygon(newPoly.id);
    }
}

function insertVertex(poly, e) {
    saveStateToUndo();
    const pt = getMouseCoords(e);
    // Encontrar la arista más cercana
    let minIdx = 0;
    let minDist = Infinity;
    
    for(let i=0; i<poly.points.length; i++) {
        let p1 = poly.points[i];
        let p2 = poly.points[(i+1) % poly.points.length];
        let d = distToSegment(pt, p1, p2);
        if (d < minDist) {
            minDist = d;
            minIdx = i;
        }
    }
    // Insertar después de minIdx
    poly.points.splice(minIdx + 1, 0, pt);
    renderPolygons();
}

// Utilidad geométrica
function sqr(x) { return x * x }
function dist2(v, w) { return sqr(v.x - w.x) + sqr(v.y - w.y) }
function distToSegmentSquared(p, v, w) {
  let l2 = dist2(v, w);
  if (l2 === 0) return dist2(p, v);
  let t = ((p.x - v.x) * (w.x - v.x) + (p.y - v.y) * (w.y - v.y)) / l2;
  t = Math.max(0, Math.min(1, t));
  return dist2(p, { x: v.x + t * (w.x - v.x), y: v.y + t * (w.y - v.y) });
}
function distToSegment(p, v, w) { return Math.sqrt(distToSegmentSquared(p, v, w)); }


// ── DIBUJAR NUEVO POLÍGONO ──
function cancelDrawing() {
    isDrawing = false;
    currentDrawingPoints = [];
    tempDrawGroup.innerHTML = '';
}

function renderTempDrawing(mousePt) {
    tempDrawGroup.innerHTML = '';
    if (currentDrawingPoints.length === 0) return;

    const allPts = [...currentDrawingPoints, mousePt];
    
    // Líneas
    const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
    polyline.setAttribute('points', allPts.map(p => `${p.x},${p.y}`).join(' '));
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', '#f59e0b');
    polyline.setAttribute('stroke-width', '2');
    polyline.setAttribute('stroke-dasharray', '5,5');
    tempDrawGroup.appendChild(polyline);

    // Handles
    currentDrawingPoints.forEach((pt, idx) => {
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', pt.x);
        circle.setAttribute('cy', pt.y);
        circle.setAttribute('r', idx === 0 ? '8' : '5');
        circle.setAttribute('fill', idx === 0 ? '#10b981' : '#f59e0b');
        tempDrawGroup.appendChild(circle);
    });
}

function completeDrawing() {
    if (currentDrawingPoints.length >= 3) {
        saveStateToUndo();
        const newPoly = {
            id: 'poly_' + Date.now(),
            db_name: '',
            points: [...currentDrawingPoints]
        };
        polygons.push(newPoly);
        selectPolygon(newPoly.id);
        // Quitado el setTool('select') para permitir dibujar varios seguidos sin interrupción
    } else {
        alert("El polígono debe tener al menos 3 puntos.");
    }
    cancelDrawing();
}

// ── EVENTOS DEL RATÓN EN EL LIENZO ──
const container = document.getElementById('canvasContainer');

function getMouseCoords(e) {
    const rect = zoomContainer.getBoundingClientRect();
    return {
        x: (e.clientX - rect.left) / zoomLevel,
        y: (e.clientY - rect.top) / zoomLevel
    };
}

container.addEventListener('mousedown', (e) => {
    // Rueda del ratón o si estamos en select pero arrastrando fondo
    if (e.button === 1 || (e.button === 0 && currentTool === 'select' && e.target === container)) {
        isPanning = true;
        startPanX = e.clientX - panX;
        startPanY = e.clientY - panY;
        container.style.cursor = 'grabbing';
        return;
    }

    if (e.button !== 0) return;

    if (currentTool === 'draw') {
        const pt = getMouseCoords(e);
        // Si hace clic cerca del primer punto, cerrar
        if (currentDrawingPoints.length >= 3) {
            const first = currentDrawingPoints[0];
            const dist = Math.hypot(pt.x - first.x, pt.y - first.y);
            if (dist < 15 / zoomLevel) {
                completeDrawing();
                return;
            }
        }
        currentDrawingPoints.push(pt);
        renderTempDrawing(pt);
    }
    
    if (currentTool === 'text') {
        const pt = getMouseCoords(e);
        const textContent = prompt("Ingrese el texto:", "Etiqueta");
        if (textContent && textContent.trim() !== '') {
            saveStateToUndo();
            const newText = {
                id: 'txt_' + Date.now(),
                x: pt.x,
                y: pt.y,
                content: textContent.trim(),
                size: 16
            };
            texts.push(newText);
            selectText(newText.id);
        }
    }
});

document.addEventListener('mousemove', (e) => {
    if (isPanning) {
        panX = e.clientX - startPanX;
        panY = e.clientY - startPanY;
        applyTransform();
        return;
    }

    if (draggingHandle) {
        const pt = getMouseCoords(e);
        const poly = polygons.find(p => p.id === draggingHandle.polyId);
        poly.points[draggingHandle.pointIndex] = pt;
        renderPolygons();
        return;
    }

    if (draggingText) {
        const pt = getMouseCoords(e);
        const t = texts.find(x => x.id === draggingText);
        if (t) {
            t.x = pt.x;
            t.y = pt.y;
            renderTexts();
        }
        return;
    }

    if (currentTool === 'draw' && currentDrawingPoints.length > 0) {
        renderTempDrawing(getMouseCoords(e));
    }
});

document.addEventListener('mouseup', () => {
    isPanning = false;
    draggingHandle = null;
    draggingText = null;
    if (currentTool === 'select') container.style.cursor = 'grab';
});

// Completar con doble clic o Enter
document.addEventListener('dblclick', (e) => {
    if (currentTool === 'draw') {
        // Quitar el último punto que se añadió por el primer clic del dblclick
        currentDrawingPoints.pop();
        completeDrawing();
    }
});
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); }
    
    // Evitar interferir con inputs (buscador, contenido de texto, etc.)
    const isInput = e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA';
    
    if (!isInput && (e.key === 'Delete' || e.key === 'Backspace')) {
        if (selectedTextId) {
            e.preventDefault();
            deleteSelectedText();
        } else if (selectedPolyId) {
            e.preventDefault();
            deleteSelected();
        }
    }

    if (e.key === 'Enter' && currentTool === 'draw') completeDrawing();
    if (e.key === 'Escape' && currentTool === 'draw') cancelDrawing();
});

// ── ZOOM Y PAN ──
container.addEventListener('wheel', (e) => {
    e.preventDefault();
    const zoomDelta = e.deltaY > 0 ? -0.15 : 0.15;
    const newZoom = Math.max(0.2, Math.min(zoomLevel + zoomDelta, 5.0));
    
    // Zoom hacia el puntero usando el contenedor FIJO
    const rect = container.getBoundingClientRect();
    const cursorX = e.clientX - rect.left;
    const cursorY = e.clientY - rect.top;
    
    // Calcular coordenadas nativas en el plano original antes del zoom
    const imgX = (cursorX - panX) / zoomLevel;
    const imgY = (cursorY - panY) / zoomLevel;
    
    zoomLevel = newZoom;
    
    // Calcular el nuevo pan para que la coordenada nativa quede bajo el puntero
    panX = cursorX - (imgX * zoomLevel);
    panY = cursorY - (imgY * zoomLevel);
    
    applyTransform();
}, { passive: false });

function setZoom(level) {
    const newZoom = Math.max(0.2, Math.min(level, 5.0));
    const rect = container.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    
    const imgX = (centerX - panX) / zoomLevel;
    const imgY = (centerY - panY) / zoomLevel;
    
    zoomLevel = newZoom;
    panX = centerX - (imgX * zoomLevel);
    panY = centerY - (imgY * zoomLevel);
    
    applyTransform();
}

function resetZoom() {
    if (!mapImage || !mapImage.naturalWidth) return;
    
    // Matemáticas de Fit to View Responsivo
    const vW = container.clientWidth;
    const vH = container.clientHeight;
    
    const margin = 30;
    const availableW = vW - (margin * 2);
    const availableH = vH - (margin * 2);

    const scaleX = availableW / mapImage.naturalWidth;
    const scaleY = availableH / mapImage.naturalHeight;

    const fitScale = Math.min(scaleX, scaleY);
    zoomLevel = Math.max(0.1, Math.min(fitScale, 5.0));

    const scaledW = mapImage.naturalWidth * zoomLevel;
    const scaledH = mapImage.naturalHeight * zoomLevel;

    panX = (vW - scaledW) / 2;
    panY = (vH - scaledH) / 2;
    
    applyTransform();
}

// Escuchar cambios de tamaño de ventana para recalcular Fit to View
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        resetZoom();
    }, 150);
});

function applyTransform() {
    zoomContainer.style.transform = `translate(${panX}px, ${panY}px) scale(${zoomLevel})`;
    // Mantener tamaño de vértices independientemente del zoom
    const strokeW = 2 / zoomLevel;
    const rad = 6 / zoomLevel;
    handlesGroup.querySelectorAll('circle').forEach(c => {
        c.setAttribute('r', rad);
        c.setAttribute('stroke-width', strokeW);
    });
}

// ── GRID (Cuadrícula) ──
let gridVisible = false;
function drawGrid(w, h) {
    const group = document.getElementById('gridGroup');
    group.innerHTML = '';
    const step = 20; // 20px
    for (let x = 0; x < w; x += step) {
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', x); line.setAttribute('y1', 0);
        line.setAttribute('x2', x); line.setAttribute('y2', h);
        line.setAttribute('stroke', '#cbd5e1'); line.setAttribute('stroke-width', '1');
        group.appendChild(line);
    }
    for (let y = 0; y < h; y += step) {
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', 0); line.setAttribute('y1', y);
        line.setAttribute('x2', w); line.setAttribute('y2', y);
        line.setAttribute('stroke', '#cbd5e1'); line.setAttribute('stroke-width', '1');
        group.appendChild(line);
    }
}
function toggleGrid() {
    gridVisible = !gridVisible;
    document.getElementById('gridGroup').style.display = gridVisible ? 'block' : 'none';
    const btn = document.getElementById('btnGrid');
    if (gridVisible) {
        btn.classList.replace('btn-outline-primary', 'btn-primary');
    } else {
        btn.classList.replace('btn-primary', 'btn-outline-primary');
    }
}

// ── MANEJO DE TEXTOS ──
window.selectText = function(id) {
    selectedTextId = id;
    renderTexts();
    
    const textPropsPanel = document.getElementById('textPropertiesPanel');
    const inputContent = document.getElementById('textContentInput');
    const inputSize = document.getElementById('textSizeInput');
    
    if (id) {
        if (typeof selectPolygon === 'function') selectPolygon(null); // Deseleccionar polígonos
        const t = texts.find(x => x.id === id);
        if (t) {
            inputContent.value = t.content;
            inputSize.value = t.size || 16;
            textPropsPanel.style.display = 'block';
        }
    } else {
        textPropsPanel.style.display = 'none';
        inputContent.value = '';
    }
};

window.updateSelectedTextContent = function(val) {
    if (!selectedTextId) return;
    saveStateToUndo();
    const t = texts.find(x => x.id === selectedTextId);
    if (t) t.content = val;
    renderTexts();
};

window.updateSelectedTextSize = function(val) {
    if (!selectedTextId) return;
    saveStateToUndo();
    const t = texts.find(x => x.id === selectedTextId);
    if (t) t.size = parseInt(val, 10);
    renderTexts();
};

window.deleteSelectedText = function() {
    if (!selectedTextId) return;
    if (confirm("¿Eliminar este texto?")) {
        saveStateToUndo();
        texts = texts.filter(t => t.id !== selectedTextId);
        selectText(null);
        renderTexts();
    }
};

// ── GUARDAR ──
function saveMapData() {
    // Actualizar MAP_DATA actual
    MAP_DATA[currentMapKey].zones = polygons.map(p => ({
        db_name: p.db_name || '',
        esp_id: p.esp_id || null,
        points: p.points.map(pt => `${Math.round(pt.x)},${Math.round(pt.y)}`).join(' ')
    }));
    
    MAP_DATA[currentMapKey].texts = texts.map(t => ({
        id: t.id,
        x: Math.round(t.x),
        y: Math.round(t.y),
        content: t.content,
        size: t.size
    }));

    const btn = document.getElementById('btnSave');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
    btn.disabled = true;

    fetch('../backend/api/save_map.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(MAP_DATA)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert('¡Configuración guardada exitosamente! El mapa interactivo ha sido actualizado.');
        } else {
            alert('Error guardando: ' + data.message);
        }
    })
    .catch(err => alert('Error de red: ' + err))
    .finally(() => {
        btn.innerHTML = oldHtml;
        btn.disabled = false;
    });
}
