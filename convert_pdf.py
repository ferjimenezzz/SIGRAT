import fitz
import os

pdf_files = {
    'CIC_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA ALTA CIC 4.0.pdf',
    'CIC_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA BAJA CIC 4.0.pdf',
    'PIDET_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.a.pdf',
    'PIDET_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.b.pdf'
}

output_dir = r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas'

for name, path in pdf_files.items():
    if os.path.exists(path):
        print(f"Abriendo {path}...")
        doc = fitz.open(path)
        page = doc[0]  # Obtener primera página
        svg_content = page.get_svg_image()
        
        output_path = os.path.join(output_dir, f"{name}.svg")
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write(svg_content)
        print(f"Guardado en {output_path}")
    else:
        print(f"No existe: {path}")
