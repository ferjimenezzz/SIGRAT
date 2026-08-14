with open('frontend/calendario.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
for i, line in enumerate(lines):
    if 'id="reservationModal"' in line:
        print(f'{i+1}: {line.strip()}')
        for j in range(i, -1, -1):
            if '<div class="main-content"' in lines[j] or '<body' in lines[j]:
                print(f'Found parent at {j+1}: {lines[j].strip()}')
                break
