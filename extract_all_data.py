import fitz
import json
import os

pdf_files = {
    'CIC_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA ALTA CIC 4.0.pdf',
    'CIC_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PLANTA BAJA CIC 4.0.pdf',
    'PIDET_pa': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.a.pdf',
    'PIDET_pb': r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\PIDET p.b.pdf'
}

result = {}

for name, path in pdf_files.items():
    if os.path.exists(path):
        doc = fitz.open(path)
        page = doc[0]
        rect = page.rect
        blocks = page.get_text("blocks")
        
        all_texts = []
        for b in blocks:
            text = b[4].strip().replace('\n', ' ')
            if text:
                all_texts.append({
                    'text': text,
                    'x0': round(b[0], 2),
                    'y0': round(b[1], 2),
                    'x1': round(b[2], 2),
                    'y1': round(b[3], 2),
                    'cx': round((b[0]+b[2])/2, 2),
                    'cy': round((b[1]+b[3])/2, 2)
                })
        
        result[name] = {
            'width': rect.width,
            'height': rect.height,
            'texts': all_texts
        }
        print(f"{name}: {rect.width}x{rect.height}, {len(all_texts)} bloques de texto")

# Guardar como JSON
with open('pdf_data.json', 'w', encoding='utf-8') as f:
    json.dump(result, f, ensure_ascii=False, indent=2)
print("Guardado pdf_data.json")
