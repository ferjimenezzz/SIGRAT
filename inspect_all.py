"""
Genera el código HTML definitivo para los 4 mapas interactivos de calendario.php

Estrategia:
- Usa los SVGs extraídos del PDF como imagen de fondo (idéntico al plano)
- Superpone rectángulos transparentes clickeables sobre cada sala
- Las coordenadas son en el sistema de puntos del PDF (72dpi)

Basado en análisis visual de los PNGs y los textos extraídos del PDF
"""
import fitz
import os

pdf_files = {
    'PIDET_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.b.pdf',
    'PIDET_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.a.pdf',
    'CIC_pb':   r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA BAJA CIC 4.0.pdf',
    'CIC_pa':   r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA ALTA CIC 4.0.pdf',
}

# Imprimir TODOS los textos de cada PDF con coordenadas, para inspección
for name, path in pdf_files.items():
    if os.path.exists(path):
        doc = fitz.open(path)
        page = doc[0]
        print(f"\n{'='*60}")
        print(f"=== {name}: {page.rect.width:.0f} x {page.rect.height:.0f} ===")
        print(f"{'='*60}")
        blocks = page.get_text("blocks")
        for b in sorted(blocks, key=lambda b: b[1]):  # ordenar por Y (de arriba a abajo)
            text = b[4].strip().replace('\n', ' ')
            if text and len(text) >= 2:
                cx = (b[0]+b[2])/2
                cy = (b[1]+b[3])/2
                print(f"  y={b[1]:6.1f} | '{text:40s}' | x0={b[0]:6.1f} y0={b[1]:6.1f} x1={b[2]:6.1f} y1={b[3]:6.1f} | cx={cx:6.1f} cy={cy:6.1f}")
