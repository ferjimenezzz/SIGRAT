with open('frontend/inventario.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
for i, line in enumerate(lines):
    if 'premium-table-card' in line:
        print(f'Line {i+1}: {line.strip()}')
        for j in range(1, 15):
            print(f'  {lines[i+j].strip()}')
