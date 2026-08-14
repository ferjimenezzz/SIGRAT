"""
Genera el código HTML para los 4 mapas interactivos usando los SVGs extraídos del PDF como fondo
y superponiendo áreas clickeables en las coordenadas correctas de cada sala.

Coordenadas: están en el sistema de coordenadas del PDF (puntos).
Para el overlay, usamos porcentajes del viewBox original del PDF.
"""

# =====================================================================
# COORDENADAS DE SALAS - Basadas en los planos y texto extraído del PDF
# Formato: (id_sala, label_display, x0, y0, x1, y1) en coords PDF
# =====================================================================

# CIC Planta Alta: 2592 x 1728
CIC_PA_ROOMS = [
    # Salas arriba (zona curva con laboratorios)
    # La zona de laboratorios en CIC PA está en la parte inferior del plano
    # Según la imagen: la parte inferior tiene la zona de cubículos/salas
    # Cubiculo 5,6 ~ texto en (858, 1506, 864, 1614) -> sala a la derecha del texto
    # Cubiculo 7,8 ~ texto en (685, 1508, 692, 1615)
    # Sala Video Conferencias ~ texto en (809, 1409, 823, 1460)
    # Sala de Juntas ~ texto en (712, 1788, 726, 1816)
    # CISCO LAB ~ texto en (1140, 1767, 1146, 1802)
    
    # Fila superior del plano (zona recta superior)
    # Los laboratorios IBM, QSM, etc. están en la zona curva superior
    # Según el PNG: parte superior tiene zona curva y parte inferior tiene zona de posgrados/laboratorios
    
    # Zona inferior - Cuadrado recto (cubículos y salas de reuniones)
    ('CUBICULO-5', 'CUBÍCULO 5', 1090, 1450, 1190, 1600),
    ('CUBICULO-6', 'CUBÍCULO 6', 1190, 1450, 1290, 1600),
    ('CUBICULO-7', 'CUBÍCULO 7', 940, 1450, 1040, 1600),
    ('CUBICULO-8', 'CUBÍCULO 8', 1040, 1450, 1090, 1600),
    ('Sala-Video-Conferencias', 'SALA VIDEO CONF.', 900, 1370, 1130, 1500),
    ('Sala-de-Juntas', 'SALA DE JUNTAS', 780, 1700, 1010, 1820),
    # Zona superior curva - Los laboratorios
    # Basado en la imagen del PNG, los labs están en la zona superior derecha
    # Estimando posiciones en el mapa 2592x1728
]

# CIC Planta Baja: 1224 x 792
# Según imagen: FAB Lab en zona central-izquierda, Sala Audiovisual en zona central-derecha
CIC_PB_ROOMS = [
    # Texto FAB Lab: (358.8, 742.2, 361.7, 754.3) - en la zona media
    # Texto Sala Audiovisual: (480.5, 737.7, 487.0, 767.6)
    # Texto Sala Capacitacion: (512.5, 874.5, 519.0, 905.7)
    ('FAB-Lab', 'FAB Lab', 580, 590, 760, 700),
    ('Sala-Audiovisual', 'SALA AUDIOVISUAL', 760, 560, 980, 700),
    ('Sala-Capacitacion-CIC', 'SALA CAPACITACIÓN', 980, 660, 1150, 740),
]

# PIDET Planta Alta: 792 x 612
# Según imagen: zona superior tiene posgrados (cuartos rectangulares), zona inferior tiene aulas magnas/digitales
PIDET_PA_ROOMS = [
    # Zona superior: cuartos rectangulares (Posgrados)
    # Estimando desde la imagen PNG: hay 6 cuartos en fila superior
    ('Posgrado-3', 'POSGRADO 3',  70,  95, 145, 185),
    ('Posgrado-4', 'POSGRADO 4', 145,  95, 220, 185),
    ('Posgrado-5', 'POSGRADO 5', 220,  95, 295, 185),
    ('Posgrado-6', 'POSGRADO 6', 295,  95, 370, 185),
    ('Posgrado-7', 'POSGRADO 7', 370,  95, 445, 185),
    ('Posgrado-8', 'POSGRADO 8', 445,  95, 520, 185),
    # Zona inferior: Aulas magnas y digitales (fila de aulas grande)
    ('Aula-Magna-1', 'MAGNA 1',    28, 330,  75, 450),
    ('Aula-Magna-2', 'MAGNA 2',    75, 330, 122, 450),
    ('Aula-Magna-3', 'MAGNA 3',   122, 330, 169, 450),
    ('Aula-Magna-4', 'MAGNA 4',   169, 330, 216, 450),
    ('Aula-digital-1', 'DIGITAL 1', 276, 330, 358, 450),
    ('Aula-digital-2', 'DIGITAL 2', 358, 330, 440, 450),
    ('Aula-digital-3', 'DIGITAL 3', 440, 330, 522, 450),
    ('Aula-digital-4', 'DIGITAL 4', 522, 330, 604, 450),
    ('Aula-digital-5', 'DIGITAL 5', 604, 330, 660, 450),
]

# PIDET Planta Baja: 792 x 612
# Según imagen y texto extraído:
# AULA 01 ~ (214.1, 167.3, 218.0, 183.7) -> el texto está rotado, la sala es grande inferior izquierda
# AULA 02 ~ (255.2, 241.9, 259.1, 258.3) -> sala mediana
# AULA 03 ~ (350.2, 440.1, 354.1, 456.4) -> sala superior derecha zona curva
# AULA 04 ~ (355.6, 501.9, 359.5, 518.3)
# AULA 05 ~ (376.3, 568.5, 380.3, 584.9)
# AULA 06 ~ (413.1, 629.8, 417.1, 646.1) -> nota: la página es 612 alto, puede estar fuera

# Las coordenadas del texto están en el PDF space, que para PIDET es 792x612
# El texto "AULA 01" está en x=214-218, y=167-183 (coordenadas PDF rotadas en el plano)
# Mirando el PNG: AULA 01 y AULA 02 están en la parte INFERIOR IZQUIERDA (zona curva)
# AUDITORIO/ESTRADO en la parte SUPERIOR IZQUIERDA (zona recta)
# AULA 03, 04, 05, 06 en la zona superior derecha (curvada)
PIDET_PB_ROOMS = [
    # Zona superior recta: Auditorio, Estrado
    ('Auditorio-PIDET', 'AUDITORIO PIDET',  285, 200, 490, 295),
    # Nota: texto "NPT+ 2.25 ESTRADO AUDITORIO" en (349.6, 219.2, 354.7, 400.0)
    # Zona superior curva derecha: Aulas 03-06
    ('Aula-03-PIDET', 'AULA 03',  490, 195, 580, 290),
    ('Aula-04-PIDET', 'AULA 04',  568, 195, 655, 265),
    ('Aula-05-PIDET', 'AULA 05',  643, 185, 725, 255),
    ('Aula-06-PIDET', 'AULA 06',  710, 165, 785, 230),
    # Zona inferior: Aula 01, 02 (zona curva)
    ('Aula-01-PIDET', 'AULA 01',   28, 345, 190, 450),
    ('Aula-02-PIDET', 'AULA 02',  190, 345, 310, 415),
]

print("Configuración de salas cargada.")
print(f"CIC PA: {len(CIC_PA_ROOMS)} salas")
print(f"CIC PB: {len(CIC_PB_ROOMS)} salas")
print(f"PIDET PA: {len(PIDET_PA_ROOMS)} salas")
print(f"PIDET PB: {len(PIDET_PB_ROOMS)} salas")


def generate_svg_map(svg_id, svg_path_href, view_width, view_height, rooms, is_rotated=False):
    """
    Genera código SVG con la imagen del plano como fondo y overlays clickeables.
    svg_path_href: ruta URL del SVG del plano
    view_width, view_height: dimensiones originales del PDF (el viewBox del SVG del plano)
    rooms: lista de (id, label, x0, y0, x1, y1) en coords del PDF
    """
    lines = []
    lines.append(f'<svg id="{svg_id}" class="interactive-map" viewBox="0 0 {view_width} {view_height}" style="background:#fff;">')
    
    # Imagen de fondo (el SVG del plano)
    lines.append(f'  <image href="{svg_path_href}" x="0" y="0" width="{view_width}" height="{view_height}" preserveAspectRatio="xMidYMid meet"/>')
    
    # Overlays clickeables
    for room in rooms:
        room_id, label, x0, y0, x1, y1 = room
        cx = (x0 + x1) / 2
        cy = (y0 + y1) / 2
        w = x1 - x0
        h = y1 - y0
        safe_label = label.replace("'", "&apos;").replace('"', '&quot;')
        lines.append(f'  <rect x="{x0}" y="{y0}" width="{w}" height="{h}" class="map-room" id="svg-room-{room_id}" data-label="{safe_label}"/>')
        lines.append(f'  <text x="{cx}" y="{cy}" class="map-label-title" text-anchor="middle" dominant-baseline="middle" style="font-size:{max(8, int(h*0.12))}px; pointer-events:none;">{label}</text>')
    
    lines.append('</svg>')
    return '\n'.join(lines)


# Generar los 4 mapas
maps = {
    'CIC_pa':   ('svgMap-CIC-pa',   '../frontend/assets/mapas/CIC_pa.svg',   2592, 1728, CIC_PA_ROOMS),
    'CIC_pb':   ('svgMap-CIC-pb',   '../frontend/assets/mapas/CIC_pb.svg',   1224,  792, CIC_PB_ROOMS),
    'PIDET_pa': ('svgMap-PIDET-pa', '../frontend/assets/mapas/PIDET_pa.svg',  792,  612, PIDET_PA_ROOMS),
    'PIDET_pb': ('svgMap-PIDET-pb', '../frontend/assets/mapas/PIDET_pb.svg',  792,  612, PIDET_PB_ROOMS),
}

output = []
for key, (svg_id, href, vw, vh, rooms) in maps.items():
    output.append(f'\n<!-- === {key} === -->')
    output.append(generate_svg_map(svg_id, href, vw, vh, rooms))

html_output = '\n'.join(output)

with open('generated_maps.html', 'w', encoding='utf-8') as f:
    f.write(html_output)
print("\nGuardado: generated_maps.html")
print(html_output[:500])
