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
        print(f"\n=== {name} ===")
        doc = fitz.open(path)
        page = doc[0]
        blocks = page.get_text("blocks")
        
        # Filtrar bloques con palabras de más de 3 letras o números
        for b in blocks:
            text = b[4].strip().replace('\n', ' ')
            if len(text) >= 3:
                # Mostrar texto y coordenadas
                print(f"  '{text}' -> rect({b[0]:.1f}, {b[1]:.1f}, {b[2]:.1f}, {b[3]:.1f})")
