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
        doc = fitz.open(path)
        page = doc[0]
        rect = page.rect
        print(f"{name}: width={rect.width:.2f}, height={rect.height:.2f}")
        
        # Also extract all text blocks with room names
        blocks = page.get_text("blocks")
        print(f"  Total text blocks: {len(blocks)}")
        for b in blocks:
            text = b[4].strip().replace('\n', ' ')
            text_upper = text.upper()
            keywords = ["AULA", "LAB", "AUDITORIO", "POSGRADO", "MAGNA", "DIGITAL", 
                       "SALA", "CUBÍCULO", "COCINA", "VESTÍBULO", "SITE", "OFICINA",
                       "FAB", "AREA", "RECEPCIÓN", "CUBICUL", "CUBICL", "CUBICULO"]
            if any(k in text_upper for k in keywords):
                print(f"  SALA: '{text}' -> ({b[0]:.1f}, {b[1]:.1f}, {b[2]:.1f}, {b[3]:.1f})")
