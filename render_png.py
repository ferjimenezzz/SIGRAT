import fitz
import os

pdf_files = {
    'CIC_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA ALTA CIC 4.0.pdf',
    'CIC_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA BAJA CIC 4.0.pdf',
    'PIDET_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.a.pdf',
    'PIDET_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.b.pdf'
}

artifacts_dir = r'C:\Users\miche\.gemini\antigravity-ide\brain\2b46e283-82ba-4617-b929-26612a7266a5'

for name, path in pdf_files.items():
    if os.path.exists(path):
        print(f"Renderizando {name}...")
        doc = fitz.open(path)
        page = doc[0]
        pix = page.get_pixmap(dpi=150)  # Renderizar con 150 DPI
        
        output_path = os.path.join(artifacts_dir, f"{name}.png")
        pix.save(output_path)
        print(f"Guardado PNG en {output_path}")
    else:
        print(f"No existe: {path}")
