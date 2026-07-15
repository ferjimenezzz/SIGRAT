const fs = require('fs');
const path = require('path');

const mapDataPath = path.join(__dirname, 'frontend', 'assets', 'map_data.json');
const mapData = JSON.parse(fs.readFileSync(mapDataPath, 'utf8'));

// Tabla de asignación definitiva: db_name → esp_id
// Basada en la auditoría de BD (40 registros, IDs confirmados)
const DB_NAME_TO_ID = {
    // PIDET Alta
    'Sala Magna 1':             1,
    'Sala Magna 2':             2,
    'Sala Magna 3':             3,
    'Sala Magna 4':             4,
    'Posgrado 1':               5,
    'Posgrado 2':               6,
    'Salón 1':                  7,
    'Cubículos':                8,
    'Oficina Lety':             9,
    'Aula Digital 1':           10,
    'Aula Digital 2':           11,
    'Aula Digital 3':           12,
    'Aula Digital 4':           13,
    'Aula 5 Digital':           14,
    // PIDET Baja
    'Auditorio':                null, // Hay dos Auditorios, se resuelve por mapa
    'Maker Space':              16,
    'Talentos':                 17,
    'Aula 03':                  18,
    'Aula 04':                  19,
    'Aula 05':                  20,
    'Aula 06':                  21,
    'Posgrado 1 Baja':          22,
    'Posgrado 2 Baja':          23,
    // CIC Alta
    'UNAM':                     24,
    'Lab. Siemens':             25,
    'Aula Siemens':             26,
    'Laboratorio Intel':        27,
    'Sala IBM':                 28,
    'Laboratorio CISCO':        29,
    'Aula CIC Alta':            30,
    'Sala de Videoconferencias':31,
    'Sala de Juntas':           32,
    'Aula Proyectos':           40,
    // CIC Baja
    'Embebidos':                33,
    'Aula Huawei':              34,
    'GE Vernova':               35,
    'Gevernova':                35,  // fallback por si quedó el nombre viejo
    'CEPRODI':                  37,
    'Sala Capacitación':        38,
    'Centro de Innovación Siemens': 39,
};

// Auditorios por mapa específico
const AUDITORIO_BY_MAP = {
    'PIDET_baja': 15,
    'CIC_baja':   36,
};

let totalAssigned = 0;
let totalSkipped = 0;

Object.keys(mapData).forEach(mapKey => {
    const config = mapData[mapKey];
    console.log(`\n=== ${mapKey} ===`);

    config.zones.forEach((zone, idx) => {
        const dbName = zone.db_name || '';
        
        let espId = null;
        if (dbName === 'Auditorio') {
            espId = AUDITORIO_BY_MAP[mapKey] || null;
        } else {
            espId = DB_NAME_TO_ID[dbName] ?? null;
        }

        if (espId !== null) {
            zone.esp_id = espId;
            console.log(`  ✅ Zona ${idx}: "${dbName}" → esp_id: ${espId}`);
            totalAssigned++;
        } else {
            console.log(`  ⚠️  Zona ${idx}: "${dbName}" → sin esp_id (verificar)`);
            totalSkipped++;
        }
    });
});

fs.writeFileSync(mapDataPath, JSON.stringify(mapData, null, 4));
console.log(`\n✅ map_data.json guardado. Asignados: ${totalAssigned}, Sin asignar: ${totalSkipped}`);

// Verificación final
console.log('\n=== VERIFICACIÓN FINAL ===');
const fresh = JSON.parse(fs.readFileSync(mapDataPath, 'utf8'));
Object.keys(fresh).forEach(k => {
    const withId = fresh[k].zones.filter(z => z.esp_id).length;
    const total  = fresh[k].zones.length;
    const icon   = withId === total ? '✅' : '⚠️';
    console.log(`  ${icon} ${k}: ${withId}/${total} con esp_id`);
});
