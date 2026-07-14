import fitz  # PyMuPDF
import os

pdf_dir = r"c:\xampp\htdocs\Estadias\SIGRAT\Documentacion\Mapas"
out_dir = r"c:\xampp\htdocs\Estadias\SIGRAT\frontend\assets\img\mapas"

os.makedirs(out_dir, exist_ok=True)

for filename in os.listdir(pdf_dir):
    if filename.lower().endswith(".pdf"):
        pdf_path = os.path.join(pdf_dir, filename)
        # Nombre de salida, reemplazando espacios con guiones bajos y .pdf con .png
        out_name = filename[:-4].replace(" ", "_").replace(".", "_") + ".png"
        out_path = os.path.join(out_dir, out_name)
        
        # Abrir PDF
        doc = fitz.open(pdf_path)
        # Extraer la primera página
        page = doc.load_page(0)
        # Renderizar la página como imagen (matriz de zoom para mejor resolución)
        zoom = 2.0  # aumentar resolución
        mat = fitz.Matrix(zoom, zoom)
        pix = page.get_pixmap(matrix=mat, alpha=True)
        # Guardar como PNG
        pix.save(out_path)
        print(f"Convertido: {filename} -> {out_name}")

print("Conversión completada.")
