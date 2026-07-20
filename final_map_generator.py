"""
Generador FINAL de mapas interactivos para calendario.php
Usa SVGs reales extraídos del PDF como fondo, con overlays clickeables precisos.

Coordenadas basadas en:
- Análisis visual de los PNGs renderizados del PDF
- Texto extraído del PDF con posiciones exactas
- Dimensiones reales de cada PDF

URL de los SVGs en el servidor: ../frontend/img/mapas/
"""

def generate_svg_map(svg_id, svg_href, vw, vh, rooms, facilities=None):
    """Genera un SVG con imagen de fondo y overlays clickeables."""
    lines = []
    lines.append(f'<svg id="{svg_id}" class="interactive-map" viewBox="0 0 {vw} {vh}" xmlns="http://www.w3.org/2000/svg" style="background:#fff; width:100%; height:100%; display:none;">')
    lines.append(f'  <!-- Plano arquitectónico real del PDF -->')
    lines.append(f'  <image href="{svg_href}" x="0" y="0" width="{vw}" height="{vh}" preserveAspectRatio="xMidYMid meet"/>')
    lines.append('')
    
    if facilities:
        lines.append('  <!-- Instalaciones (no reservables) -->')
        for fac in facilities:
            fid, label, x0, y0, x1, y1 = fac
            cx, cy = (x0+x1)/2, (y0+y1)/2
            fs = max(6, int(min(x1-x0, y1-y0) * 0.11))
            lines.append(f'  <rect x="{x0}" y="{y0}" width="{x1-x0}" height="{y1-y0}" class="map-facility"/>')
            lines.append(f'  <text x="{cx:.1f}" y="{cy:.1f}" class="map-label-facility" text-anchor="middle" dominant-baseline="middle" style="font-size:{fs}px; pointer-events:none;">{label}</text>')
        lines.append('')
    
    lines.append('  <!-- Espacios reservables -->')
    for room in rooms:
        rid, label, x0, y0, x1, y1 = room
        cx, cy = (x0+x1)/2, (y0+y1)/2
        w, h = x1-x0, y1-y0
        fs = max(6, int(min(w, h) * 0.12))
        safe_label = label.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
        lines.append(f'  <g id="svg-room-{rid}" class="map-room-group" data-room-id="{rid}">')
        lines.append(f'    <rect x="{x0}" y="{y0}" width="{w}" height="{h}" class="map-room" id="svg-room-rect-{rid}"/>')
        lines.append(f'    <text x="{cx:.1f}" y="{cy:.1f}" class="map-label-title" text-anchor="middle" dominant-baseline="middle" style="font-size:{fs}px; pointer-events:none;">{safe_label}</text>')
        lines.append(f'  </g>')
    
    lines.append('</svg>')
    return '\n'.join(lines)


# ===========================================================
# CIC PLANTA ALTA — 2592 × 1728 pts
# Textos clave del PDF:
#   CUBICULO 5 CUBICULO 6 → cx=861, cy=1560
#   CUBICULO 7 CUBICULO 8 → cx=688, cy=1562
#   ÁREA COWORKING        → cx=777, cy=1563
#   SALA VIDEO CONFER.    → cx=810, cy=1435 (estimado)
#   SALA DE JUNTAS        → cx=719, cy=1802
#   COPIAS                → cx=812, cy=1737
#   PAPELERIA             → cx=727, cy=1669
#   RECEPCION Y ESPERA    → cx=829, cy=1681
#   CISCO LAB             → cx=1143, cy=1785
#   IBM COGNITIVA         → cx=1079, cy=1621
#   DIRECCIÓN             → cx=815, cy=1852
#   ÁREA FORMACION...     → cx=1299, cy=2081
# Zona inferior (y > 1400) es la "planta baja" del plano (zona recta)
# ===========================================================

CIC_PA_ROOMS = [
    # Zona recta inferior del plano CIC PA
    # Los cubículos 5-8 están en la zona derecha, area coworking en centro
    # CUBICULO 7,8 text cx=688, cy=1562 → salas a izquierda del texto (el texto está rotado vertical)
    ('CUBICULO-7', 'CUBÍC. 7',  630, 1450,  700, 1580),
    ('CUBICULO-8', 'CUBÍC. 8',  700, 1450,  770, 1580),
    # CUBICULO 5,6 text cx=861, cy=1560 → salas a la derecha
    ('CUBICULO-5', 'CUBÍC. 5',  800, 1450,  870, 1580),
    ('CUBICULO-6', 'CUBÍC. 6',  870, 1450,  940, 1580),
    # Área coworking text cx=777, cy=1563 → sala amplia en el centro
    ('Sala-Video-Conferencias', 'VIDEO CONF.',  600, 1370,  940, 1460),
    # AREA COWORKING - zona central grande
    # (no está en el sistema como reservable según los IDs del JS, pero lo incluimos)
    # SALA DE JUNTAS text cx=719, cy=1802
    ('Sala-de-Juntas', 'SALA JUNTAS',  630, 1760,  870, 1870),
    # IBM COGNITIVA text cx=1079, cy=1621
    ('IBM-Cognitiva', 'IBM COGNITIVA', 1040, 1570, 1280, 1700),
    # CISCO LAB text cx=1143, cy=1785
    ('CISCO-LAB', 'CISCO LAB',  1060, 1730, 1280, 1870),
    # DIRECCIÓN text cx=815, cy=1852
    ('Direccion', 'DIRECCIÓN',   770, 1820, 870, 1900),
]

CIC_PA_FACILITIES = [
    # PAPELERÍA, COPIAS, RECEPCION, BAÑO, HOMBRES, MUJERES
    ('fac-papeleria', 'PAPELERÍA', 700, 1630, 770, 1720),
    ('fac-copias',    'COPIAS',    780, 1700, 850, 1770),
    ('fac-recepcion', 'RECEPCIÓN', 780, 1630, 870, 1700),
    ('fac-bano',      'BAÑO',      830, 1840, 900, 1900),
    ('fac-wc-h',      'H',         630, 1640, 700, 1720),
    ('fac-wc-m',      'M',         630, 1560, 700, 1640),
]

# ===========================================================
# CIC PLANTA BAJA — 1224 × 792 pts
# Textos clave del PDF:
#   FAB Lab              → cx=360, cy=748 (texto muy pequeño, rotado)
#   SALA AUDIOVISUAL...  → cx=483, cy=752 (texto rotado)
#   SALA CAPACITACION... → cx=515, cy=889 (fuera del área visible?)
#   DESARROLLO DE TALENTO (texto largo) → parece zona grande
#   SITE                 → cx=372, cy=520
# Notar: el PDF de CIC_pb es 1224x792 (landscape)
# Mirando el PNG: zona rectangular superior izquierda + zona curva derecha
# ===========================================================

CIC_PB_ROOMS = [
    # FAB Lab - zona centro del plano rectangular
    ('FAB-Lab', 'FAB Lab',  220, 490, 430, 640),
    # Sala Audiovisual - zona centro-derecha
    ('Sala-Audiovisual', 'SALA AUDIOVISUAL',  400, 490, 650, 640),
    # Sala Capacitación - zona derecha curva
    ('Sala-Capacitacion-CIC', 'SALA CAPACIT.', 650, 530, 820, 640),
]

CIC_PB_FACILITIES = [
    ('fac-site-cic', 'SITE', 340, 490, 420, 560),
    ('fac-desarrollo', 'DESARROLLO', 80, 490, 220, 640),
]

# ===========================================================
# PIDET PLANTA ALTA — 792 × 612 pts
# Mirando el PNG: 
#   Fila superior: cuartos rectangulares alineados (Posgrados 3-8)
#   Fila inferior izquierda: cuartos curvos (Magnas 1-4)
#   Fila inferior derecha: más cuartos curvos (Digitales 1-5)
# Dimensiones aproximadas basadas en el análisis visual del PNG
# El PNG tiene 1198x924 px para 792x612 pts → escala ~1.51x
# Posgrados visibles en y≈90-185 del PDF
# Aulas grandes en y≈330-450 del PDF
# ===========================================================

PIDET_PA_ROOMS = [
    # Posgrados fila superior (zona recta)
    ('Posgrado-3', 'POSGRADO 3',   72,  90, 147, 185),
    ('Posgrado-4', 'POSGRADO 4',  147,  90, 222, 185),
    ('Posgrado-5', 'POSGRADO 5',  222,  90, 297, 185),
    ('Posgrado-6', 'POSGRADO 6',  297,  90, 372, 185),
    ('Posgrado-7', 'POSGRADO 7',  372,  90, 447, 185),
    ('Posgrado-8', 'POSGRADO 8',  447,  90, 522, 185),
    # Aulas magnas fila inferior izquierda (zona curva)
    ('Aula-Magna-1', 'MAGNA 1',    28, 330,  75, 455),
    ('Aula-Magna-2', 'MAGNA 2',    75, 330, 122, 455),
    ('Aula-Magna-3', 'MAGNA 3',   122, 330, 169, 455),
    ('Aula-Magna-4', 'MAGNA 4',   169, 330, 216, 455),
    # Aulas digitales fila inferior derecha
    ('Aula-digital-1', 'DIGITAL 1', 276, 330, 358, 455),
    ('Aula-digital-2', 'DIGITAL 2', 358, 330, 440, 455),
    ('Aula-digital-3', 'DIGITAL 3', 440, 330, 522, 455),
    ('Aula-digital-4', 'DIGITAL 4', 522, 330, 604, 455),
    ('Aula-digital-5', 'DIGITAL 5', 604, 330, 665, 455),
]

PIDET_PA_FACILITIES = [
    ('fac-escaleras-pa', 'ESCALERAS', 522, 185, 590, 330),
    ('fac-wc-pa', 'WC', 590, 185, 650, 330),
    ('fac-sala-tec', 'SALA TÉC.', 650, 185, 720, 330),
]

# ===========================================================
# PIDET PLANTA BAJA — 792 × 612 pts
# Textos extraídos del PDF (con coordenadas):
#   AULA 01 → cx=216, cy=175 (texto rotado vertical) → sala en zona inferior izquierda
#   AULA 02 → cx=257, cy=250 → sala mediana inferior
#   AUDITORIO/ESTRADO → texto en y=219-400 → zona superior izquierda
#   AULA 03 → cx=352, cy=448 → zona superior derecha inicio curva
#   AULA 04 → cx=357, cy=510 → más a la derecha
#   AULA 05 → cx=378, cy=576 → más a la derecha
#   AULA 06 → cx=415, cy=637 → extremo derecho
#   SITE → cx=270, cy=334 → pequeño cuarto
#   OFICINA → cx=271, cy=354 → al lado de SITE
#   VESTIBULO → cx=257, cy=396 → zona central
# 
# Mirando el PNG de PIDET_pb:
#   - ESTRADO (zona negra/rayada) → parte izquierda del bloque superior
#   - AUDITORIO → zona amplia del bloque superior
#   - AULA 03-06 → en la zona CURVA superior derecha (trapezoides)
#   - AULA 01 → zona inferior izquierda curva (rectángulo grande)
#   - AULA 02 → al lado de AULA 01
# ===========================================================

PIDET_PB_ROOMS = [
    # Zona superior recta: ESTRADO + AUDITORIO
    # El "ESTRADO AUDITORIO" text está en y=219-400 (rotado vertical), cx=352
    # Estrado (zona rayada) está a la izquierda del auditorio
    ('Auditorio-PIDET', 'AUDITORIO PIDET',  288, 195, 495, 295),
    # Aulas zona curva superior derecha
    # AULA 03 texto cx=352, cy=448 → la sala está en la zona curva superior derecha
    # Mirando el PNG: las aulas 03-06 están en trapezoides curvos
    ('Aula-03-PIDET', 'AULA 03',   490, 195, 575, 290),
    ('Aula-04-PIDET', 'AULA 04',   565, 185, 647, 270),
    ('Aula-05-PIDET', 'AULA 05',   638, 175, 717, 255),
    ('Aula-06-PIDET', 'AULA 06',   706, 158, 782, 235),
    # Zona inferior izquierda curva: AULA 01, AULA 02
    # AULA 01 texto: cx=216, cy=175 (ROTADO! el texto está rotado 90°)
    # En el PNG, AULA 01 es la sala grande de la parte inferior izquierda
    ('Aula-01-PIDET', 'AULA 01',    30, 345, 192, 450),
    ('Aula-02-PIDET', 'AULA 02',   192, 340, 305, 415),
]

PIDET_PB_FACILITIES = [
    ('fac-site-pidet', 'SITE',      258, 315, 295, 355),
    ('fac-oficina-pidet', 'OFICINA', 258, 335, 295, 375),
    ('fac-vestibulo', 'VESTÍBULO',  230, 375, 295, 420),
    ('fac-escaleras', 'ESCALERAS',  290, 295, 345, 420),
    ('fac-ss-h', 'SS HOM.',         425, 300, 490, 420),
    ('fac-ss-m', 'SS MUJ.',         490, 300, 540, 420),
    ('fac-estrado', 'ESTRADO',      125, 195, 290, 295),
]

# ===========================================================
# GENERAR EL HTML
# ===========================================================

SVG_BASE_URL = '../frontend/img/mapas/'

maps_config = [
    ('svgMap-CIC-pa',   'CIC_pa.svg',   2592, 1728, CIC_PA_ROOMS,   CIC_PA_FACILITIES),
    ('svgMap-CIC-pb',   'CIC_pb.svg',   1224,  792, CIC_PB_ROOMS,   CIC_PB_FACILITIES),
    ('svgMap-PIDET-pa', 'PIDET_pa.svg',  792,  612, PIDET_PA_ROOMS, PIDET_PA_FACILITIES),
    ('svgMap-PIDET-pb', 'PIDET_pb.svg',  792,  612, PIDET_PB_ROOMS, PIDET_PB_FACILITIES),
]

all_html = []
for svg_id, svg_file, vw, vh, rooms, facilities in maps_config:
    href = SVG_BASE_URL + svg_file
    html = generate_svg_map(svg_id, href, vw, vh, rooms, facilities)
    all_html.append(f'                        <!-- {svg_id} -->')
    # Añadir indentación
    indented = '\n'.join('                        ' + line if line.strip() else '' for line in html.split('\n'))
    all_html.append(indented)

output = '\n'.join(all_html)

with open('final_maps.html', 'w', encoding='utf-8') as f:
    f.write(output)

print("OK Generado: final_maps.html")
print(f"Total de caracteres: {len(output)}")

# Verificar conteo de salas
total_rooms = len(CIC_PA_ROOMS) + len(CIC_PB_ROOMS) + len(PIDET_PA_ROOMS) + len(PIDET_PB_ROOMS)
print(f"\nTotal de salas reservables: {total_rooms}")
print(f"  CIC PA:   {len(CIC_PA_ROOMS)} salas")
print(f"  CIC PB:   {len(CIC_PB_ROOMS)} salas")
print(f"  PIDET PA: {len(PIDET_PA_ROOMS)} salas")
print(f"  PIDET PB: {len(PIDET_PB_ROOMS)} salas")
