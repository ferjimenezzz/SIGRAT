import fitz
import os

pdf_files = {
    'CIC_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA ALTA CIC 4.0.pdf',
    'CIC_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA BAJA CIC 4.0.pdf',
    'PIDET_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.a.pdf',
    'PIDET_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.b.pdf'
}

for name, path in pdf_files.items():
    if os.path.exists(path):
        print(f"\n=== {name} (Ancho: {fitz.open(path)[0].rect.width}, Alto: {fitz.open(path)[0].rect.height}) ===")
        doc = fitz.open(path)
        page = doc[0]
        blocks = page.get_text("blocks")
        
        # Filtrar bloques que tengan texto
        valid_blocks = []
        for b in blocks:
            text = b[4].strip().replace('\n', ' ')
            if text:
                valid_blocks.append((text, b[0], b[1], b[2], b[3]))
                
        # Mostrar los bloques ordenados
        for text, x0, y0, x1, y1 in valid_blocks:
            # Solo mostrar si contiene palabras clave del sistema (aula, lab, posgrado, magna, digital, etc.)
            text_upper = text.upper()
            keywords = ["AULA", "LABORATORIO", "POSGRADO", "MAGNA", "DIGITAL", "INTEL", "CISCO", "SIEMENS", "AUDITORIO", "CEPRODI", "IBM", "QSM", "MIRAI", "PLM", "EMBEBIDOS", "REALIDAD"]
            if any(k in text_upper for k in keywords) or len(text.strip()) < 10:
                print(f"  '{text}' -> rect({x0:.1f}, {y0:.1f}, {x1:.1f}, {y1:.1f})")
