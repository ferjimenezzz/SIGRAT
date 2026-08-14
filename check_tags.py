import xml.etree.ElementTree as ET
from collections import Counter
import os

svg_path = r'c:\xampp\htdocs\SIGRAT\Documentacion\mapas\CIC_pa.svg'

if os.path.exists(svg_path):
    print("Leyendo SVG...")
    tree = ET.parse(svg_path)
    root = tree.getroot()
    
    tags = [elem.tag.split('}')[-1] for elem in root.iter()]
    c = Counter(tags)
    print("Conteo de tags:")
    for tag, count in c.most_common():
        print(f"  {tag}: {count}")
else:
    print("No existe el archivo")
