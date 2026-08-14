import xml.etree.ElementTree as ET
import os

svg_path = r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\CIC_pa.svg'

if os.path.exists(svg_path):
    print("Leyendo SVG...")
    # Registrar los namespaces para que no se arruine la estructura
    ET.register_namespace('', 'http://www.w3.org/2000/svg')
    
    tree = ET.parse(svg_path)
    root = tree.getroot()
    
    # Encontrar elementos de texto
    texts = []
    # Buscar todos los elementos que contengan texto en sus hijos o atributos
    for elem in root.iter():
        if elem.tag.endswith('text'):
            text_val = "".join(elem.itertext()).strip()
            if text_val:
                x = elem.attrib.get('x', '0')
                y = elem.attrib.get('y', '0')
                texts.append((text_val, x, y))
                
    print(f"Encontrados {len(texts)} elementos de texto:")
    # Mostrar los primeros 30 text elements
    for t, x, y in texts[:30]:
        print(f"Texto: '{t}' en x={x}, y={y}")
else:
    print("No existe el archivo SVG")
