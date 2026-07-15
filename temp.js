
// DATOS ESTÁTICOS COMPARTIDOS DESDE PHP
    const allSpaces = null;
    const allAssets = null;
    const sessionUserId = null;
    const isUserAdmin = null;

    // SISTEMA DE COLORES POR ESPACIO
    function getColorForSpace(esp_id) {
        const colors = [
            'event-color-blue', 'event-color-purple', 'event-color-orange', 
            'event-color-pink', 'event-color-green', 'event-color-teal', 'event-color-red'
        ];
        let hash = 0;
        const str = String(esp_id);
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        const index = Math.abs(hash) % colors.length;
        return colors[index];
    }

    // ESTADO DE LA APLICACIÓN DE CALENDARIO
    const state = {
        currentView: 'month', // 'month', 'week'
        currentDate: new Date(), // Fecha de referencia de la vista
        events: [], // Eventos cargados desde la API
        filters: {
            edificio: [],
            tipo: [],
            esp_id: '',
            status: 'Todos',
            fecha_inicio: '',
            fecha_fin: '',
            hora_inicio: '08:00',
            hora_fin: '20:00',
            capacidad: 5,
            us_id: ''
        },
        searchQuery: '',
        resMode: 'single' // 'single', 'multiple'
    };

    document.addEventListener('DOMContentLoaded', () => {
        initYearNav();
        initUIElements();
        syncFiltersAndFetch();

        // Escucha de caracteres en el textarea del modal
        const resMotivo = document.getElementById('resMotivo');
        const charCount = document.getElementById('charCount');
        if (resMotivo && charCount) {
            resMotivo.addEventListener('input', () => {
                charCount.textContent = resMotivo.value.length;
            });
        }
    });

    // ----------------------------------------------------
    // INICIALIZACIÓN DEL SELECTOR DE AÑO
    // ----------------------------------------------------
    function initYearNav() {
        const yearSelect = document.getElementById('selectYearNav');
        const currentYear = new Date().getFullYear();
        
        let opts = '';
        for (let y = currentYear - 2; y <= currentYear + 2; y++) {
            opts += `<option value="${y}">${y}</option>`;
        }
        yearSelect.innerHTML = opts;
        yearSelect.value = currentYear;
    }

    // ----------------------------------------------------
    // INICIALIZACIÓN DE COMPONENTES DE INTERFAZ
    // ----------------------------------------------------
    function initUIElements() {
        // Dropdowns de mes y año
        const monthSelect = document.getElementById('selectMonthNav');
        const yearSelect = document.getElementById('selectYearNav');
        
        monthSelect.value = state.currentDate.getMonth();
        yearSelect.value = state.currentDate.getFullYear();

        const handleSelectNavChange = () => {
            state.currentDate = new Date(parseInt(yearSelect.value), parseInt(monthSelect.value), 1);
            renderActiveCalendar();
        };

        monthSelect.addEventListener('change', handleSelectNavChange);
        yearSelect.addEventListener('change', handleSelectNavChange);

        // Switchers de vista (Mes/Semana)
        document.querySelectorAll('.btn-switch-view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.btn-switch-view').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.currentView = btn.dataset.view;
                
                // Ajustar UI según la vista
                document.getElementById('monthViewGrid').style.display = state.currentView === 'month' ? 'grid' : 'none';
                document.getElementById('weekViewGrid').style.display = state.currentView === 'week' ? 'block' : 'none';

                renderActiveCalendar();

                renderActiveCalendar();
            });
        });

        // Navegación de flechas
        document.getElementById('btnPrev').addEventListener('click', () => adjustDate(-1));
        document.getElementById('btnNext').addEventListener('click', () => adjustDate(1));
        document.getElementById('btnToday').addEventListener('click', () => {
            state.currentDate = new Date();
            monthSelect.value = state.currentDate.getMonth();
            yearSelect.value = state.currentDate.getFullYear();
            renderActiveCalendar();
        });

        // Filtro sidebar toggle
        const filtersOverlay = document.getElementById('filtersOverlay');
        const filtersSidebar = document.getElementById('filtersSidebar');
        const btnToggleFilters = document.getElementById('btnToggleFilters');
        const btnExitFilters = document.getElementById('btnExitFilters');

        function openFilters() {
            filtersOverlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                filtersOverlay.style.opacity = '1';
                filtersSidebar.classList.add('show');
            }, 10);
        }

        function closeFilters() {
            filtersSidebar.classList.remove('show');
            filtersOverlay.style.opacity = '0';
            setTimeout(() => {
                filtersOverlay.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }

        if(btnToggleFilters) btnToggleFilters.addEventListener('click', openFilters);
        if(btnExitFilters) btnExitFilters.addEventListener('click', closeFilters);
        if(filtersOverlay) filtersOverlay.addEventListener('click', closeFilters);

        // Capacidad slider listener en sidebar
        const filterCapacidad = document.getElementById('filterCapacidad');
        const capacidadSliderLabel = document.getElementById('capacidadSliderLabel');
        if (filterCapacidad && capacidadSliderLabel) {
            filterCapacidad.addEventListener('input', (e) => {
                capacidadSliderLabel.textContent = `Mínimo: ${e.target.value} personas`;
            });
        }

        // Aplicar y Limpiar filtros del Panel Lateral
        document.getElementById('btnApplyFilters').addEventListener('click', () => {
            applySidebarFilters();
            closeFilters();
        });

        document.getElementById('btnClearFilters').addEventListener('click', () => {
            clearSidebarFilters();
            closeFilters();
        });

        // ----------------------------------------------------
        // FILTROS RÁPIDOS INLINE (EVENT LISTENERS)
        // ----------------------------------------------------
        const quickFilterEdificio = document.getElementById('quickFilterEdificio');
        const quickFilterTipo = document.getElementById('quickFilterTipo');
        const quickFilterEspacio = document.getElementById('quickFilterEspacio');
        const quickFilterStatus = document.getElementById('quickFilterStatus');
        const quickFilterSoloMisReservas = document.getElementById('quickFilterSoloMisReservas');

        // Poblar selector de espacios rápido
        function populateQuickSpaces() {
            const edifVal = quickFilterEdificio.value;
            const tipoVal = quickFilterTipo.value;
            
            let filtered = allSpaces;
            if(edifVal) filtered = filtered.filter(s => s.edificio === edifVal);
            if(tipoVal) filtered = filtered.filter(s => s.tipo === tipoVal);
            
            let opts = '<option value="">Todos</option>';
            filtered.forEach(s => {
                opts += `<option value="${s.esp_id}">${s.edificio} - ${s.nombre_numero}</option>`;
            });
            quickFilterEspacio.innerHTML = opts;
            quickFilterEspacio.value = state.filters.esp_id || "";
        }
        
        populateQuickSpaces();

        // Al cambiar cualquier filtro rápido, actualizamos el estado e interactuamos al instante
        quickFilterEdificio.addEventListener('change', () => {
            state.filters.edificio = quickFilterEdificio.value ? [quickFilterEdificio.value] : [];
            state.filters.esp_id = ""; // Reset espacio al cambiar edificio
            populateQuickSpaces();
            
            // Sincronizar sidebar
            document.querySelectorAll('input[name="filter_edificio"]').forEach(c => {
                c.checked = state.filters.edificio.includes(c.value);
            });
            document.getElementById('filterEspacioSelect').value = "";
            
            renderActiveCalendar();
        });

        quickFilterTipo.addEventListener('change', () => {
            state.filters.tipo = quickFilterTipo.value ? [quickFilterTipo.value] : [];
            state.filters.esp_id = ""; // Reset espacio al cambiar tipo
            populateQuickSpaces();

            // Sincronizar sidebar
            document.querySelectorAll('input[name="filter_tipo"]').forEach(c => {
                c.checked = state.filters.tipo.includes(c.value);
            });
            document.getElementById('filterEspacioSelect').value = "";

            renderActiveCalendar();
        });

        quickFilterEspacio.addEventListener('change', () => {
            state.filters.esp_id = quickFilterEspacio.value;
            
            // Sincronizar sidebar
            document.getElementById('filterEspacioSelect').value = quickFilterEspacio.value;
            
            renderActiveCalendar();
        });

        quickFilterStatus.addEventListener('change', () => {
            state.filters.status = quickFilterStatus.value;
            
            // Sincronizar sidebar
            document.querySelector(`input[name="filter_status"][value="${quickFilterStatus.value}"]`).checked = true;
            
            renderActiveCalendar();
        });

        quickFilterSoloMisReservas.addEventListener('change', () => {
            state.filters.us_id = quickFilterSoloMisReservas.checked ? sessionUserId : '';
            
            // Sincronizar sidebar
            document.getElementById('filterSoloMisReservas').checked = quickFilterSoloMisReservas.checked;
            
            renderActiveCalendar();
        });

        // Barra de búsqueda en tiempo real
        const searchInput = document.getElementById('searchInput');
        if(searchInput) {
            searchInput.addEventListener('input', (e) => {
                state.searchQuery = e.target.value.toLowerCase().trim();
                renderActiveCalendar();
            });
        }

        // MODAL DE RESERVACIÓN
        const reservationModal = document.getElementById('reservationModal');
        const btnNewReservation = document.getElementById('btnNewReservation');
        const btnExitResModal = document.getElementById('btnExitResModal');
        const btnCancelReserva = document.getElementById('btnCancelReserva');
        const resEdificio = document.getElementById('resEdificio');
        const resEspacio = document.getElementById('resEspacio');
        
        // SWITCHER DE MODO EN MODAL (DÍA ÚNICO VS MULTI-DÍA)
        const btnResModeSingle = document.getElementById('btnResModeSingle');
        const btnResModeMultiple = document.getElementById('btnResModeMultiple');
        const resSingleDayFields = document.getElementById('resSingleDayFields');
        const resMultiDayFields = document.getElementById('resMultiDayFields');
        
        btnResModeSingle.addEventListener('click', () => {
            btnResModeSingle.classList.add('active');
            btnResModeMultiple.classList.remove('active');
            resSingleDayFields.style.display = 'block';
            resMultiDayFields.style.display = 'none';
            state.resMode = 'single';
            
            // Requiere fecha única obligatoria
            document.getElementById('resFecha').required = true;
            document.getElementById('resFechaInicio').required = false;
            document.getElementById('resFechaFin').required = false;
        });

        btnResModeMultiple.addEventListener('click', () => {
            btnResModeMultiple.classList.add('active');
            btnResModeSingle.classList.remove('active');
            resSingleDayFields.style.display = 'none';
            resMultiDayFields.style.display = 'flex';
            state.resMode = 'multiple';

            // Requiere fechas múltiples obligatorias
            document.getElementById('resFecha').required = false;
            document.getElementById('resFechaInicio').required = true;
            document.getElementById('resFechaFin').required = true;
        });

        function openResModal(defaultDate = null) {
            // Rellenar fecha seleccionada
            const todayStr = defaultDate || new Date().toISOString().split('T')[0];
            document.getElementById('resFecha').value = todayStr;
            document.getElementById('resFechaInicio').value = todayStr;
            
            // Calcular fecha fin (hoy + 7 días por defecto para facilidad)
            const dFin = new Date(todayStr + 'T00:00:00');
            dFin.setDate(dFin.getDate() + 7);
            document.getElementById('resFechaFin').value = dFin.toISOString().split('T')[0];
            
            // Vaciar y resetear campos
            document.getElementById('resEdificio').value = "PIDET";
            document.getElementById('resEdificio').dispatchEvent(new Event('change'));
            document.getElementById('resEspacio').innerHTML = '<option value="">Seleccione espacio...</option>';
            document.getElementById('resCapacidadLabel').value = "0 personas";
            const eqCont = document.getElementById('resEquipamientoContainer');
            if (eqCont) eqCont.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
            document.getElementById('resMotivo').value = "";
            document.getElementById('charCount').textContent = "0";
            document.getElementById('resWarningLong').style.display = 'none';
            document.getElementById('resDuracion').value = "2";

            // Forzar volver a Día Único al abrir
            btnResModeSingle.click();

            reservationModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Inicializar o resetear mapa
            setTimeout(() => {
                if (typeof initModalMap === 'function') initModalMap();
            }, 100);
        }

        window.closeResModal = function() {
            reservationModal.style.display = 'none';
            document.body.style.overflow = '';
        }

        if(btnNewReservation) btnNewReservation.addEventListener('click', () => openResModal());
        if(btnExitResModal) btnExitResModal.addEventListener('click', window.closeResModal);
        if(btnCancelReserva) btnCancelReserva.addEventListener('click', window.closeResModal);

        // Al cambiar edificio en la reserva
        if(resEdificio) {
            resEdificio.addEventListener('change', (e) => {
                const edif = e.target.value;
                const filtered = allSpaces.filter(sp => sp.edificio === edif);
                
                let opts = '<option value="">Seleccione espacio...</option>';
                filtered.forEach(sp => {
                    opts += `<option value="${sp.esp_id}">${sp.nombre_numero} (${sp.tipo})</option>`;
                });
                resEspacio.innerHTML = opts;
                document.getElementById('resCapacidadLabel').value = "0 personas";
                const eqCont2 = document.getElementById('resEquipamientoContainer');
                if (eqCont2) eqCont2.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
            });
        }

        // Al cambiar espacio en la reserva
        if(resEspacio) {
            resEspacio.addEventListener('change', (e) => {
                const espId = parseInt(e.target.value);
                const spObj = allSpaces.find(sp => sp.esp_id === espId);
                const eqContainer = document.getElementById('resEquipamientoContainer');
                if (spObj) {
                    document.getElementById('resCapacidadLabel').value = `${spObj.capacidad} personas`;
                    
                    // Buscar equipamiento asignado a este espacio o edificio
                    const spAssets = allAssets.filter(as => as.esp_asignado == espId || (as.edificio === spObj.edificio && !as.esp_asignado));
                    if(spAssets.length > 0) {
                        let html = '';
                        spAssets.forEach(as => {
                            html += `<label style='display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-primary); cursor: pointer;'>
                                <input type='checkbox' class='equipamiento-checkbox' value='${as.act_id}'>
                                ${as.tipo} ${as.marca} ${as.modelo || ''}
                            </label>`;
                        });
                        eqContainer.innerHTML = html;
                    } else {
                        eqContainer.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Sin equipamiento específico disponible.</div>';
                    }
                } else {
                    eqContainer.innerHTML = '<div style="font-size: 12px; color: var(--text-secondary);">Selecciona un espacio primero...</div>';
                }
                checkAvailability();
            });
        }

        const resFecha = document.getElementById('resFecha');
        if (resFecha) {
            resFecha.addEventListener('change', checkAvailability);
        }

        const resDuracionSelect = document.getElementById('resDuracion');
        const resWarningLong = document.getElementById('resWarningLong');
        if (resDuracionSelect && resWarningLong) {
            resDuracionSelect.addEventListener('change', (e) => {
                if (parseInt(e.target.value) > 2) {
                    resWarningLong.style.display = 'block';
                } else {
                    resWarningLong.style.display = 'none';
                }
                checkAvailability();
            });
        }

        function checkAvailability() {
            if (state.resMode !== 'single') return; // En multi-día es más complejo, lo dejamos al backend
            const espId = resEspacio.value;
            const fecha = document.getElementById('resFecha').value;
            if (!espId || !fecha) return;
            
            const now = new Date();
            const tzOffset = now.getTimezoneOffset() * 60000;
            const localISOTime = (new Date(now.getTime() - tzOffset)).toISOString().slice(0, 10);
            const isTodayExact = fecha === localISOTime;
            const currentHour = now.getHours();

            // Habilitar todos primero
            Array.from(document.getElementById('resHoraEnt').options).forEach(opt => {
                opt.disabled = false;
                const h = parseInt(opt.value);
                opt.text = opt.value + (h < 12 ? ' AM' : ' PM');
                
                if (isTodayExact && h <= currentHour) {
                    opt.disabled = true;
                    opt.text = opt.value + ' (Pasada)';
                }
            });
            
            fetch(`../backend/api/index.php/reservations?esp_id=${espId}&date=${fecha}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const selectHora = document.getElementById('resHoraEnt');
                        Array.from(selectHora.options).forEach(opt => {
                            const optHour = parseInt(opt.value);
                            data.forEach(res => {
                                const startH = parseInt(res.hora_ent);
                                const endH = parseInt(res.hora_sal);
                                if (optHour >= startH && optHour < endH) {
                                    opt.disabled = true;
                                    opt.text = opt.value + ' (Ocupado)';
                                }
                            });
                        });
                        // Si la seleccionada está ocupada, cambiar
                        if (selectHora.options[selectHora.selectedIndex].disabled) {
                            for (let i=0; i<selectHora.options.length; i++) {
                                if (!selectHora.options[i].disabled) {
                                    selectHora.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                    }
                })
                .catch(err => console.error("Error check availability", err));
        }

        // Envío del formulario de reserva
        const resForm = document.getElementById('reservationForm');
        if (resForm) {
            resForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitReservation();
            });
        }

        // ----------------------------------------------------
        // LOGICA DE SELECTOR DE FECHA PERSONALIZADO
        // ----------------------------------------------------
        const monthPickerTrigger = document.getElementById('monthPickerTrigger');
        const monthPickerDropdown = document.getElementById('monthPickerDropdown');
        const monthPickerChevron = document.getElementById('monthPickerChevron');
        const currentMonthYearLabel = document.getElementById('currentMonthYearLabel');
        const pickerYearLabel = document.getElementById('pickerYearLabel');
        const prevYearBtn = document.getElementById('prevYearBtn');
        const nextYearBtn = document.getElementById('nextYearBtn');
        
        let pickerCurrentYear = state.currentDate.getFullYear();
        
        window.updateCustomPickerUI = function() {
            const currentMonth = state.currentDate.getMonth();
            const currentYear = state.currentDate.getFullYear();
            
            const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            if (currentMonthYearLabel) {
                currentMonthYearLabel.textContent = `${monthNames[currentMonth]} ${currentYear}`;
            }
            
            if (pickerYearLabel) {
                pickerYearLabel.textContent = pickerCurrentYear;
            }
            
            document.querySelectorAll('.picker-month-btn').forEach(btn => {
                const btnMonth = parseInt(btn.dataset.month);
                if (btnMonth === currentMonth && pickerCurrentYear === currentYear) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        };
        
        if (monthPickerTrigger) {
            monthPickerTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = monthPickerDropdown.style.display === 'block';
                if (isOpen) {
                    closeCustomPicker();
                } else {
                    openCustomPicker();
                }
            });
        }
        
        function openCustomPicker() {
            if (monthPickerDropdown) monthPickerDropdown.style.display = 'block';
            if (monthPickerChevron) monthPickerChevron.style.transform = 'rotate(180deg)';
            pickerCurrentYear = state.currentDate.getFullYear();
            window.updateCustomPickerUI();
        }
        
        function closeCustomPicker() {
            if (monthPickerDropdown) monthPickerDropdown.style.display = 'none';
            if (monthPickerChevron) monthPickerChevron.style.transform = 'rotate(0deg)';
        }
        
        document.addEventListener('click', (e) => {
            if (monthPickerDropdown && !monthPickerDropdown.contains(e.target) && e.target !== monthPickerTrigger) {
                closeCustomPicker();
            }
        });
        
        if (prevYearBtn) {
            prevYearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                pickerCurrentYear--;
                window.updateCustomPickerUI();
            });
        }
        
        if (nextYearBtn) {
            nextYearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                pickerCurrentYear++;
                window.updateCustomPickerUI();
            });
        }
        
        document.querySelectorAll('.picker-month-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const selectedMonth = parseInt(btn.dataset.month);
                
                monthSelect.value = selectedMonth;
                
                let yearOptionExists = false;
                for (let i = 0; i < yearSelect.options.length; i++) {
                    if (parseInt(yearSelect.options[i].value) === pickerCurrentYear) {
                        yearOptionExists = true;
                        break;
                    }
                }
                
                if (!yearOptionExists) {
                    const opt = document.createElement('option');
                    opt.value = pickerCurrentYear;
                    opt.textContent = pickerCurrentYear;
                    yearSelect.appendChild(opt);
                }
                
                yearSelect.value = pickerCurrentYear;
                monthSelect.dispatchEvent(new Event('change'));
                closeCustomPicker();
            });
        });

        // ----------------------------------------------------
        // LOGICA DE MODAL DE DETALLES
        // ----------------------------------------------------
        const resDetailsModal = document.getElementById('resDetailsModal');
        const btnExitDetailsModal = document.getElementById('btnExitDetailsModal');
        const btnCloseDetailsModal = document.getElementById('btnCloseDetailsModal');
        
        if (btnExitDetailsModal) btnExitDetailsModal.addEventListener('click', () => { resDetailsModal.style.display = 'none'; document.body.style.overflow = ''; });
        if (btnCloseDetailsModal) btnCloseDetailsModal.addEventListener('click', () => { resDetailsModal.style.display = 'none'; document.body.style.overflow = ''; });
        if (resDetailsModal) {
            resDetailsModal.addEventListener('click', (e) => {
                if (e.target === resDetailsModal) {
                    resDetailsModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    }

    // ----------------------------------------------------
    // SINCRO FILTROS RAPIDOS & FETCH INICIAL
    // ----------------------------------------------------
    function syncFiltersAndFetch() {
        fetchEvents();
    }

    // ----------------------------------------------------
    // AJUSTAR FECHA DE NAVEGACIÓN (FLECHAS)
    // ----------------------------------------------------
    function adjustDate(direction) {
        const d = state.currentDate;
        if (state.currentView === 'month') {
            d.setMonth(d.getMonth() + direction);
        } else if (state.currentView === 'week') {
            d.setDate(d.getDate() + (direction * 7));
        }
        
        // Sincronizar selectores
        document.getElementById('selectMonthNav').value = d.getMonth();
        document.getElementById('selectYearNav').value = d.getFullYear();

        renderActiveCalendar();
    }

    // ----------------------------------------------------
    // OBTENER RESERVACIONES DESDE LA API
    // ----------------------------------------------------
    window.fetchEvents = function() {
        let url = '../backend/api/index.php/calendar/events';
        
        fetch(url, { credentials: 'same-origin' })
            .then(res => {
                if(!res.ok) throw new Error("Error en la petición: " + res.statusText);
                return res.json();
            })
            .then(data => {
                state.events = Array.isArray(data) ? data : [];
                renderActiveCalendar();
            })
            .catch(err => console.error("Error al cargar reservaciones del calendario:", err));
    }

    // ----------------------------------------------------
    // RENDERIZAR CALENDARIO SELECCIONADO
    // ----------------------------------------------------
    function renderActiveCalendar() {
        // Actualizar dropdowns de mes/año nav
        document.getElementById('selectMonthNav').value = state.currentDate.getMonth();
        document.getElementById('selectYearNav').value = state.currentDate.getFullYear();
        
        // Sincronizar UI del seleccionador de fecha personalizado
        if (typeof window.updateCustomPickerUI === 'function') {
            window.updateCustomPickerUI();
        }
        
        // Renderizar filtros tags
        renderActiveFiltersTags();

        // Renderizar según vista activa
        if (state.currentView === 'month') {
            renderMonthView();
        } else if (state.currentView === 'week') {
            renderWeekView();
        }

        // Actualizar sidebar e indicadores de resumen
        updateSidebarStats();
    }

    // ----------------------------------------------------
    // RENDER DE FILTROS ACTIVOS (TAGS)
    // ----------------------------------------------------
    const mesesEsp = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    function renderActiveFiltersTags() {
        const container = document.getElementById('activeFiltersContainer');
        const highlightBar = document.getElementById('highlightBar');
        container.innerHTML = '';
        
        const tags = [];
        const highlightTexts = [];

        if (state.filters.edificio.length > 0) {
            tags.push({ key: 'edificio', label: `Edificio: ${state.filters.edificio.join(', ')}` });
            highlightTexts.push(`Edificio: ${state.filters.edificio.join(', ')}`);
        }
        if (state.filters.tipo.length > 0) {
            tags.push({ key: 'tipo', label: `Tipo: ${state.filters.tipo.join(', ')}` });
            highlightTexts.push(`Tipo: ${state.filters.tipo.join(', ')}`);
        }
        if (state.filters.esp_id) {
            const sp = allSpaces.find(s => s.esp_id == state.filters.esp_id);
            if(sp) {
                tags.push({ key: 'esp_id', label: `Espacio: ${sp.edificio} - ${sp.nombre_numero}` });
                highlightTexts.push(`Espacio: ${sp.edificio} - ${sp.nombre_numero}`);
            }
        }
        if (state.filters.status !== 'Todos') {
            tags.push({ key: 'status', label: `Estatus: ${state.filters.status}` });
            highlightTexts.push(`Estatus: ${state.filters.status}`);
        }
        if (state.filters.hora_inicio !== '08:00' || state.filters.hora_fin !== '20:00') {
            tags.push({ key: 'hours', label: `Horario: ${state.filters.hora_inicio} a ${state.filters.hora_fin}` });
            highlightTexts.push(`Horario: ${state.filters.hora_inicio} a ${state.filters.hora_fin}`);
        }
        if (state.filters.capacidad > 5) {
            tags.push({ key: 'capacidad', label: `Capacidad: ≥${state.filters.capacidad} pers.` });
            highlightTexts.push(`Capacidad: ≥${state.filters.capacidad} pers.`);
        }
        if (state.filters.us_id) {
            tags.push({ key: 'us_id', label: 'Solo mis reservas' });
            highlightTexts.push('Mis reservaciones');
        }

        if (tags.length > 0) {
            container.style.display = 'flex';
            tags.forEach(t => {
                const tagEl = document.createElement('div');
                tagEl.className = 'filter-tag';
                tagEl.innerHTML = `
                    <span>${t.label}</span>
                    <i class="bi bi-x" onclick="removeActiveFilter('${t.key}')"></i>
                `;
                container.appendChild(tagEl);
            });
            
            // Botón Limpiar Todo
            const btnClear = document.createElement('button');
            btnClear.className = 'btn-clear-all-filters';
            btnClear.textContent = 'Limpiar todo';
            btnClear.onclick = () => clearSidebarFilters();
            container.appendChild(btnClear);

            // Highlight bar
            highlightBar.style.display = 'block';
            highlightBar.textContent = `Mostrando: ${highlightTexts.join(' · ')}`;
        } else {
            container.style.display = 'none';
            highlightBar.style.display = 'none';
        }
    }

    window.removeActiveFilter = function(key) {
        if (key === 'edificio') {
            state.filters.edificio = [];
            document.querySelectorAll('input[name="filter_edificio"]').forEach(c => c.checked = false);
            document.getElementById('quickFilterEdificio').value = "";
        } else if (key === 'tipo') {
            state.filters.tipo = [];
            document.querySelectorAll('input[name="filter_tipo"]').forEach(c => c.checked = false);
            document.getElementById('quickFilterTipo').value = "";
        } else if (key === 'esp_id') {
            state.filters.esp_id = '';
            document.getElementById('filterEspacioSelect').value = '';
            document.getElementById('quickFilterEspacio').value = '';
        } else if (key === 'status') {
            state.filters.status = 'Todos';
            document.querySelector('input[name="filter_status"][value="Todos"]').checked = true;
            document.getElementById('quickFilterStatus').value = 'Todos';
        } else if (key === 'hours') {
            state.filters.hora_inicio = '08:00';
            state.filters.hora_fin = '20:00';
            document.getElementById('filterHoraInicio').value = '08:00';
            document.getElementById('filterHoraFin').value = '20:00';
        } else if (key === 'capacidad') {
            state.filters.capacidad = 5;
            document.getElementById('filterCapacidad').value = 5;
            document.getElementById('capacidadSliderLabel').textContent = 'Mínimo: 5 personas';
        } else if (key === 'us_id') {
            state.filters.us_id = '';
            document.getElementById('filterSoloMisReservas').checked = false;
            document.getElementById('quickFilterSoloMisReservas').checked = false;
        }
        renderActiveCalendar();
    };

    // APLICAR FILTROS DESDE SIDEBAR
    function applySidebarFilters() {
        // Edificio
        const edificios = [];
        document.querySelectorAll('input[name="filter_edificio"]:checked').forEach(c => edificios.push(c.value));
        state.filters.edificio = edificios;

        // Tipo
        const tipos = [];
        document.querySelectorAll('input[name="filter_tipo"]:checked').forEach(c => tipos.push(c.value));
        state.filters.tipo = tipos;

        // Espacio
        state.filters.esp_id = document.getElementById('filterEspacioSelect').value;

        // Estatus
        state.filters.status = document.querySelector('input[name="filter_status"]:checked').value;

        // Rango de fechas
        state.filters.fecha_inicio = document.getElementById('filterFechaDesde').value;
        state.filters.fecha_fin = document.getElementById('filterFechaHasta').value;

        // Horas
        state.filters.hora_inicio = document.getElementById('filterHoraInicio').value;
        state.filters.hora_fin = document.getElementById('filterHoraFin').value;

        // Capacidad
        state.filters.capacidad = parseInt(document.getElementById('filterCapacidad').value);

        // Solo mis reservaciones
        state.filters.us_id = document.getElementById('filterSoloMisReservas').checked ? sessionUserId : '';

        // Sincronizar filtros rápidos
        document.getElementById('quickFilterEdificio').value = edificios.length === 1 ? edificios[0] : "";
        document.getElementById('quickFilterTipo').value = tipos.length === 1 ? tipos[0] : "";
        
        // Recargar selector de espacios rápido
        const quickEsp = document.getElementById('quickFilterEspacio');
        let quickOpts = '<option value="">Todos</option>';
        allSpaces.forEach(s => {
            quickOpts += `<option value="${s.esp_id}">${s.edificio} - ${s.nombre_numero}</option>`;
        });
        quickEsp.innerHTML = quickOpts;
        quickEsp.value = state.filters.esp_id;

        document.getElementById('quickFilterStatus').value = state.filters.status;
        document.getElementById('quickFilterSoloMisReservas').checked = !!state.filters.us_id;

        renderActiveCalendar();
    }

    // LIMPIAR FILTROS
    function clearSidebarFilters() {
        state.filters = {
            edificio: [],
            tipo: [],
            esp_id: '',
            status: 'Todos',
            fecha_inicio: '',
            fecha_fin: '',
            hora_inicio: '08:00',
            hora_fin: '20:00',
            capacidad: 5,
            us_id: ''
        };

        // Reset Inputs Sidebar
        document.querySelectorAll('input[name="filter_edificio"]').forEach(c => c.checked = false);
        document.querySelectorAll('input[name="filter_tipo"]').forEach(c => c.checked = false);
        document.getElementById('filterEspacioSelect').value = '';
        document.querySelector('input[name="filter_status"][value="Todos"]').checked = true;
        document.getElementById('filterFechaDesde').value = '';
        document.getElementById('filterFechaHasta').value = '';
        document.getElementById('filterHoraInicio').value = '08:00';
        document.getElementById('filterHoraFin').value = '20:00';
        document.getElementById('filterCapacidad').value = 5;
        document.getElementById('capacidadSliderLabel').textContent = 'Mínimo: 5 personas';
        document.getElementById('filterSoloMisReservas').checked = false;

        // Reset Inputs Inline Rápidos
        document.getElementById('quickFilterEdificio').value = "";
        document.getElementById('quickFilterTipo').value = "";
        document.getElementById('quickFilterEspacio').value = "";
        document.getElementById('quickFilterStatus').value = "Todos";
        document.getElementById('quickFilterSoloMisReservas').checked = false;

        renderActiveCalendar();
    }

    // ----------------------------------------------------
    // FUNCIÓN DE FILTRADO LOCAL DE EVENTOS Y ESPACIOS
    // ----------------------------------------------------
    function getFilteredEvents() {
        return state.events.filter(ev => {
            // Filtro por búsqueda de texto
            if (state.searchQuery) {
                const sName = ev.nombre_numero.toLowerCase();
                const uName = (ev.usuario_nombre || '').toLowerCase();
                if (!sName.includes(state.searchQuery) && !uName.includes(state.searchQuery)) {
                    return false;
                }
            }

            // Filtro de Edificio
            if (state.filters.edificio.length > 0 && !state.filters.edificio.includes(ev.edificio)) {
                return false;
            }

            // Filtro de Tipo de espacio
            if (state.filters.tipo.length > 0 && !state.filters.tipo.includes(ev.espacio_tipo)) {
                return false;
            }

            // Filtro de Espacio específico
            if (state.filters.esp_id && ev.esp_id != state.filters.esp_id) {
                return false;
            }

            // Filtro de Estatus (Aprobada / Pendiente / Rechazada)
            if (state.filters.status !== 'Todos') {
                const evStatus = ev.estatus || ev.status;
                if (state.filters.status === 'Aprobada' && evStatus !== 'Aprobada' && evStatus !== 'approved') return false;
                if (state.filters.status === 'Pendiente' && evStatus !== 'Pendiente' && evStatus !== 'pending') return false;
            }

            // Filtro de Horario
            if (ev.hora_ent < state.filters.hora_inicio || ev.hora_sal > state.filters.hora_fin) {
                return false;
            }

            // Filtro de Capacidad mínima
            if (ev.espacio_capacidad && ev.espacio_capacidad < state.filters.capacidad) {
                return false;
            }

            // Solo mis reservaciones
            if (state.filters.us_id && ev.us_id != state.filters.us_id) {
                return false;
            }

            return true;
        });
    }

    function getFilteredSpaces() {
        return allSpaces.filter(sp => {
            // Filtro por búsqueda
            if (state.searchQuery) {
                const sName = sp.nombre_numero.toLowerCase();
                if (!sName.includes(state.searchQuery)) return false;
            }

            // Edificio
            if (state.filters.edificio.length > 0 && !state.filters.edificio.includes(sp.edificio)) {
                return false;
            }

            // Tipo
            if (state.filters.tipo.length > 0 && !state.filters.tipo.includes(sp.tipo)) {
                return false;
            }

            // Espacio específico
            if (state.filters.esp_id && sp.esp_id != state.filters.esp_id) {
                return false;
            }

            // Capacidad
            if (sp.capacidad < state.filters.capacidad) {
                return false;
            }

            return true;
        });
    }

    // ----------------------------------------------------
    // VISTA MENSUAL: CÁLCULOS Y RENDER
    // ----------------------------------------------------
    function renderMonthView() {
        const monthBody = document.getElementById('monthGridBody');
        monthBody.innerHTML = '';

        const d = state.currentDate;
        const year = d.getFullYear();
        const month = d.getMonth();

        // Primer día del mes y total de días
        const firstDay = new Date(year, month, 1);
        const startDayIndex = firstDay.getDay(); // 0 (Dom) a 6 (Sáb)
        const totalDays = new Date(year, month + 1, 0).getDate();
        const prevTotalDays = new Date(year, month, 0).getDate();

        const cells = [];

        // Rellenar días del mes anterior
        for (let i = startDayIndex - 1; i >= 0; i--) {
            cells.push({
                date: new Date(year, month - 1, prevTotalDays - i),
                currentMonth: false
            });
        }

        // Rellenar días del mes actual
        for (let i = 1; i <= totalDays; i++) {
            cells.push({
                date: new Date(year, month, i),
                currentMonth: true
            });
        }

        // Rellenar días del mes siguiente para completar la cuadrícula de 6 filas (42 celdas)
        const nextMonthPadding = 42 - cells.length;
        for (let i = 1; i <= nextMonthPadding; i++) {
            cells.push({
                date: new Date(year, month + 1, i),
                currentMonth: false
            });
        }

        const filteredEvents = getFilteredEvents();
        const todayStr = new Date().toISOString().split('T')[0];

        // Crear elementos HTML
        cells.forEach(cell => {
            const cellEl = document.createElement('div');
            cellEl.className = 'month-day-cell';
            if (!cell.currentMonth) cellEl.classList.add('other-month');
            
            const cellDateStr = cell.date.toISOString().split('T')[0];
            if (cellDateStr === todayStr) cellEl.classList.add('today');

            // Número de día
            const numEl = document.createElement('div');
            numEl.className = 'day-number';
            numEl.textContent = cell.date.getDate();
            cellEl.appendChild(numEl);

            // Contenedor de eventos
            const eventsCont = document.createElement('div');
            eventsCont.className = 'month-events-container';

            // Filtrar eventos para este día
            const dayEvents = filteredEvents.filter(ev => ev.fecha_uso === cellDateStr);
            dayEvents.forEach(ev => {
                const evEl = document.createElement('div');
                
                // Formatear color dinámico por espacio
                let statClass = getColorForSpace(ev.esp_id);

                evEl.className = `event-capsule ${statClass}`;
                evEl.textContent = `${ev.hora_ent.substring(0,5)} ${ev.nombre_numero}`;
                
                // Tooltip events
                evEl.addEventListener('mouseenter', (e) => showResTooltip(e, ev));
                evEl.addEventListener('mousemove', (e) => positionResTooltip(e));
                evEl.addEventListener('mouseleave', () => hideResTooltip());
                
                // Click details modal event
                evEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    hideResTooltip();
                    openDetailsModal(ev);
                });

                eventsCont.appendChild(evEl);
            });

            cellEl.appendChild(eventsCont);

            // Al dar click en una celda
            cellEl.addEventListener('click', (e) => {
                if(!e.target.classList.contains('event-capsule')) {
                    openResModal(cellDateStr);
                }
            });

            monthBody.appendChild(cellEl);
        });
    }

    // ----------------------------------------------------
    // VISTA SEMANAL: CÁLCULOS Y RENDER
    // ----------------------------------------------------
    function renderWeekView() {
        const tableHeader = document.getElementById('weekTableHeader');
        const tableBody = document.getElementById('weekTableBody');
        
        // Calcular los días de la semana (Lunes a Domingo)
        const d = state.currentDate;
        const dayOfWeek = d.getDay(); 
        const distanceToMon = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        
        const weekDates = [];
        for (let i = 0; i < 7; i++) {
            const temp = new Date(d);
            temp.setDate(d.getDate() + distanceToMon + i);
            weekDates.push(temp);
        }

        // Render headers
        const diasSemanaNombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        let headerHtml = '<th class="col-space-header">Espacio</th>';
        weekDates.forEach((wDate, idx) => {
            const dayNum = wDate.getDate();
            const monthShort = mesesEsp[wDate.getMonth()].substring(0,3);
            const isToday = wDate.toISOString().split('T')[0] === new Date().toISOString().split('T')[0];
            const activeCircle = isToday ? 'style="background:var(--active-blue); color:white; border-radius:50%; width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center;"' : '';
            
            headerHtml += `<th>
                <div>${diasSemanaNombres[idx]}</div>
                <div style="font-size:14px; font-weight:800; margin-top:4px; color:var(--text-primary);">
                    <span ${activeCircle}>${dayNum}</span>
                </div>
            </th>`;
        });
        tableHeader.innerHTML = headerHtml;

        // Render body
        tableBody.innerHTML = '';
        const filteredSpaces = getFilteredSpaces();
        const filteredEvents = getFilteredEvents();

        if (filteredSpaces.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" style="padding: 48px; text-align: center; color: var(--text-secondary); font-weight: 600;">No hay espacios que coincidan con los filtros.</td></tr>`;
            return;
        }

        filteredSpaces.forEach(sp => {
            const row = document.createElement('tr');
            
            // Columna de espacio
            const spaceTd = document.createElement('td');
            spaceTd.className = 'col-space-info';
            spaceTd.innerHTML = `
                <div class="week-space-title">${sp.nombre_numero}</div>
                <div class="week-space-subtitle">${sp.edificio} · Cap: ${sp.capacidad}</div>
            `;
            row.appendChild(spaceTd);

            // Columnas de días
            weekDates.forEach(wDate => {
                const dayTd = document.createElement('td');
                const dateStr = wDate.toISOString().split('T')[0];

                const cellContainer = document.createElement('div');
                cellContainer.className = 'week-cell-slots-container';

                // Obtener reservaciones para este espacio y este día
                const resEvents = filteredEvents.filter(ev => ev.esp_id == sp.esp_id && ev.fecha_uso === dateStr);
                
                resEvents.forEach(ev => {
                    const evCard = document.createElement('div');
                    
                    // Elegir color dinámico según espacio
                    let colorClass = getColorForSpace(sp.esp_id);

                    evCard.className = `week-event-card ${colorClass}`;
                    
                    const userName = ev.usuario_nombre || 'Visita';
                    evCard.innerHTML = `
                        <div style="font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${userName}</div>
                        <div class="week-event-time">${ev.hora_ent.substring(0,5)} - ${ev.hora_sal.substring(0,5)}</div>
                    `;
                    
                    // Tooltip events
                    evCard.addEventListener('mouseenter', (e) => showResTooltip(e, ev));
                    evCard.addEventListener('mousemove', (e) => positionResTooltip(e));
                    evCard.addEventListener('mouseleave', () => hideResTooltip());
                    
                    evCard.addEventListener('click', (e) => {
                        e.stopPropagation();
                        hideResTooltip();
                        openDetailsModal(ev);
                    });

                    cellContainer.appendChild(evCard);
                });

                dayTd.appendChild(cellContainer);
                
                // Clic en la celda vacía para crear reservación
                dayTd.addEventListener('click', () => {
                    openResModal(dateStr);
                    // Prefiltar edificio y espacio si aplica
                    document.getElementById('resEdificio').value = sp.edificio;
                    document.getElementById('resEdificio').dispatchEvent(new Event('change'));
                    document.getElementById('resEspacio').value = sp.esp_id;
                    document.getElementById('resEspacio').dispatchEvent(new Event('change'));
                });

                row.appendChild(dayTd);
            });

            tableBody.appendChild(row);
        });
    }

    // ----------------------------------------------------
    // ACTUALIZAR ESTADÍSTICAS LATERALES Y RESUMEN
    // ----------------------------------------------------
    function updateSidebarStats() {
        const filteredEvents = getFilteredEvents();
        const filteredSpaces = getFilteredSpaces();
        
        const d = state.currentDate;
        const dateStr = d.toISOString().split('T')[0];

        // Reservas de hoy (de la fecha actual de navegación)
        const todayEvents = filteredEvents.filter(ev => ev.fecha_uso === dateStr);
        
        // 1. Resumen del Día Contadores
        const totalHoyCount = todayEvents.length;
        const pendientesCount = todayEvents.filter(ev => {
            const est = ev.estatus || ev.status;
            return est === 'Pendiente' || est === 'pending';
        }).length;
        
        // Espacios Libres (total espacios menos los que tienen al menos una reserva aprobada hoy)
        const occupiedSpaceIds = todayEvents.filter(ev => {
            const est = ev.estatus || ev.status;
            return est === 'Aprobada' || est === 'approved';
        }).map(ev => ev.esp_id);
        const uniqueOccupied = [...new Set(occupiedSpaceIds)];
        const libresCount = Math.max(0, filteredSpaces.length - uniqueOccupied.length);

        document.getElementById('statReservasHoy').textContent = totalHoyCount;
        document.getElementById('statDisponibles').textContent = libresCount;
        document.getElementById('statPendientes').textContent = pendientesCount;

        // 2. Próximas Reservaciones Sidebar (Top 3 ordenados por hora de entrada)
        const upcomingList = document.getElementById('upcomingReservationsList');
        upcomingList.innerHTML = '';

        const sortedTodayEvents = [...todayEvents].sort((a,b) => a.hora_ent.localeCompare(b.hora_ent)).slice(0, 3);
        
        if (sortedTodayEvents.length === 0) {
            upcomingList.innerHTML = '<div style="font-size:12px; color:var(--text-secondary); font-style:italic; text-align:center; padding: 12px 0;">Sin reservaciones agendadas hoy.</div>';
        } else {
            sortedTodayEvents.forEach(ev => {
                const item = document.createElement('div');
                item.className = 'upcoming-res-item';
                
                let iconClass = 'icon-blue';
                let iconType = 'bi-journal-check';
                if(ev.espacio_tipo === 'Laboratorio') { iconClass = 'icon-orange'; iconType = 'bi-laptop'; }
                if(ev.espacio_tipo === 'Auditorio') { iconClass = 'icon-green'; iconType = 'bi-megaphone'; }

                let badgeText = 'Confirmada';
                let badgeClass = 'badge-confirmada';
                const est = ev.estatus || ev.status;
                if(est === 'Pendiente' || est === 'pending') { badgeText = 'Pendiente'; badgeClass = 'badge-pendiente'; }
                if(est === 'Rechazada' || est === 'rejected') { badgeText = 'Rechazada'; badgeClass = 'badge-rechazada'; }

                item.innerHTML = `
                    <div class="res-item-icon ${iconClass}"><i class="bi ${iconType}"></i></div>
                    <div class="res-item-info">
                        <div class="res-item-name">${ev.nombre_numero}</div>
                        <div class="res-item-time">${ev.hora_ent.substring(0,5)} - ${ev.hora_sal.substring(0,5)}</div>
                    </div>
                    <span class="status-badge ${badgeClass}">${badgeText}</span>
                `;
                upcomingList.appendChild(item);
            });
        }

        // 3. Espacios Disponibles Sidebar (Top 5 listado)
        const spacesList = document.getElementById('availableSpacesList');
        spacesList.innerHTML = '';

        const topSpaces = filteredSpaces.slice(0, 5);
        if (topSpaces.length === 0) {
            spacesList.innerHTML = '<div style="font-size:12px; color:var(--text-secondary); font-style:italic; text-align:center; padding: 12px 0;">No hay espacios registrados.</div>';
        } else {
            topSpaces.forEach(sp => {
                const isOccupiedToday = uniqueOccupied.includes(sp.esp_id);
                const stateText = isOccupiedToday ? 'Ocupado hoy' : 'Disponible';
                const stateClass = isOccupiedToday ? 'state-ocupado' : 'state-libre';
                const stateIcon = isOccupiedToday ? 'bi-lock' : 'bi-check-circle';

                const sEl = document.createElement('div');
                sEl.className = 'space-status-item';
                sEl.innerHTML = `
                    <div class="space-status-left">
                        <i class="bi ${stateIcon} space-status-icon"></i>
                        <span class="space-status-name">${sp.nombre_numero}</span>
                    </div>
                    <span class="space-status-state ${stateClass}">${stateText}</span>
                `;
                
                sEl.addEventListener('click', () => {
                    openResModal(dateStr);
                    document.getElementById('resEdificio').value = sp.edificio;
                    document.getElementById('resEdificio').dispatchEvent(new Event('change'));
                    document.getElementById('resEspacio').value = sp.esp_id;
                    document.getElementById('resEspacio').dispatchEvent(new Event('change'));
                });

                spacesList.appendChild(sEl);
            });
        }
    }
    // ENVIAR SOLICITUD DE RESERVACIÓN (DÍA ÚNICO O RECURRENTE)
    // ----------------------------------------------------
    function submitReservation() {
        const espId = document.getElementById('resEspacio').value;
        const horaEnt = document.getElementById('resHoraEnt').value;
        const duracionHoras = parseInt(document.getElementById('resDuracion').value);
        const numAlumnos = parseInt(document.getElementById('resNumAlumnos').value);
        const motivo = document.getElementById('resMotivo').value;

        if (!espId || !horaEnt) {
            Swal.fire('Atención', 'Por favor, complete todos los campos obligatorios.', 'warning');
            return;
        }

        const now = new Date();
        const currentHour = now.getHours();
        const selectedHour = parseInt(horaEnt.split(':')[0]);
        const tzOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = (new Date(now.getTime() - tzOffset)).toISOString().slice(0, 10);

        // Calcular hora de salida
        const entParts = horaEnt.split(':').map(Number);
        let salHour = entParts[0] + duracionHoras;
        let salMin = entParts[1];
        
        const salHourStr = salHour < 10 ? `0${salHour}` : `${salHour}`;
        const salMinStr = salMin < 10 ? `0${salMin}` : `${salMin}`;
        const horaSal = `${salHourStr}:${salMinStr}`;

        const requestData = {
            esp_id: parseInt(espId),
            hora_ent: `${horaEnt}:00`,
            hora_sal: `${horaSal}:00`,
            num_alumnos: numAlumnos,
            motivo: motivo,
            vis_id: null
        };

        // Procesar por modo: Día Único vs. Múltiples Días
        if (state.resMode === 'single') {
            const fecha = document.getElementById('resFecha').value;
            if(!fecha) {
                Swal.fire('Atención', 'Por favor, selecciona una fecha.', 'warning');
                return;
            }
            if (fecha === localISOTime && selectedHour <= currentHour) {
                Swal.fire('Atención', 'No puedes reservar en una hora que ya pasó el día de hoy.', 'warning');
                return;
            }
            requestData.fecha_uso = fecha;
        } else {
            // Múltiples días
            const startStr = document.getElementById('resFechaInicio').value;
            const endStr = document.getElementById('resFechaFin').value;
            if(!startStr || !endStr) {
                Swal.fire('Atención', 'Por favor, selecciona el rango de fechas.', 'warning');
                return;
            }
            if (startStr === localISOTime && selectedHour <= currentHour) {
                 Swal.fire('Atención', 'No puedes reservar en una hora que ya pasó para el día de hoy (fecha de inicio).', 'warning');
                 return;
            }

            const startDate = new Date(startStr + 'T00:00:00');
            const endDate = new Date(endStr + 'T00:00:00');
            if (endDate < startDate) {
                Swal.fire('Atención', 'La fecha de fin no puede ser menor que la de inicio.', 'warning');
                return;
            }

            // Obtener días de la semana seleccionados
            const checkedWeekdays = [];
            document.querySelectorAll('.weekday-checkbox:checked').forEach(cb => {
                checkedWeekdays.push(parseInt(cb.value));
            });

            if (checkedWeekdays.length === 0) {
                Swal.fire('Atención', 'Por favor, selecciona al menos un día de la semana.', 'warning');
                return;
            }

            // Generar lista de fechas hábiles dentro del rango
            const fechas = [];
            let curr = new Date(startDate);
            while (curr <= endDate) {
                if (checkedWeekdays.includes(curr.getDay())) {
                    const y = curr.getFullYear();
                    const m = String(curr.getMonth() + 1).padStart(2, '0');
                    const d = String(curr.getDate()).padStart(2, '0');
                    fechas.push(`${y}-${m}-${d}`);
                }
                curr.setDate(curr.getDate() + 1);
            }
            
            console.log("Multi-day array generated:", fechas);

            if (fechas.length === 0) {
                alert("No hay días hábiles que coincidan en el rango seleccionado.");
                return;
            }

            // Adjuntar arreglo al payload
            requestData.fechas_uso = fechas;
        }

        // Obtener equipamientos seleccionados
        const eqCheckboxes = document.querySelectorAll('.equipamiento-checkbox:checked');
        if (eqCheckboxes.length > 0) {
            requestData.equipamiento_ids = Array.from(eqCheckboxes).map(cb => parseInt(cb.value));
        }

        const btnConfirm = document.getElementById('btnConfirmReserva');
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';

        const performFetch = (dataToSubmit) => {
            fetch('../backend/api/index.php/reservations', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataToSubmit)
            })
            .then(async res => {
                const status = res.status;
                const data = await res.json().catch(() => ({}));
                
                if (status === 409 && data.conflicts) {
                    const result = await Swal.fire({
                        icon: 'warning',
                        title: 'Fechas Ocupadas',
                        text: `Los siguientes días ya están ocupados: ${data.conflicts.join(', ')}. ¿Deseas reservar de todos modos omitiendo estos días?`,
                        showCancelButton: true,
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Sí, omitir ocupados',
                        cancelButtonText: 'Cancelar reserva'
                    });
                    
                    if (result.isConfirmed) {
                        dataToSubmit.skip_conflicts = true;
                        performFetch(dataToSubmit);
                    } else {
                        btnConfirm.disabled = false;
                        btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
                    }
                } else if (data.success || data.id || data.ids) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Reservación Solicitada!',
                        text: 'Tu reservación ha sido programada con éxito. Revisa tu correo para más detalles.',
                        confirmButtonColor: '#10b981'
                    });
                    window.closeResModal();
                    window.fetchEvents();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al agendar reserva',
                        text: data.error || 'Conflicto de horario o espacio no disponible.',
                        confirmButtonColor: '#ef4444'
                    });
                    btnConfirm.disabled = false;
                    btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
                }
            })
            .catch(err => {
                console.error("Error submitting reservation:", err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'Ocurrió un error al procesar la reservación: ' + err.message,
                    confirmButtonColor: '#ef4444'
                });
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-calendar-check"></i> Confirmar reserva';
            });
        };
        
        performFetch(requestData);
    }

    // ----------------------------------------------------
    // DETALLES Y TOOLTIP DE RESERVACIONES (DIT)
    // ----------------------------------------------------
    window.openDetailsModal = function(ev) {
        document.getElementById('detEspacioNombre').textContent = ev.nombre_numero;
        document.getElementById('detEdificioTipo').textContent = `${ev.edificio} · ${ev.espacio_tipo || 'Espacio'}`;
        
        const est = ev.estatus || ev.status || 'Pendiente';
        const badge = document.getElementById('detEstatusBadge');
        badge.textContent = est;
        badge.className = 'status-badge';
        
        if (est === 'Aprobada' || est === 'approved' || est === 'Aprobado') {
            badge.style.background = '#dcfce7';
            badge.style.color = '#15803d';
        } else if (est === 'Pendiente' || est === 'pending') {
            badge.style.background = '#fef3c7';
            badge.style.color = '#b45309';
        } else {
            badge.style.background = '#fee2e2';
            badge.style.color = '#b91c1c';
        }
        
        // Formatear fecha
        try {
            const dateParts = ev.fecha_uso.split('-');
            const dateObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('es-ES', options);
            document.getElementById('detFecha').textContent = formattedDate.charAt(0).toUpperCase() + formattedDate.slice(1);
        } catch (err) {
            document.getElementById('detFecha').textContent = ev.fecha_uso;
        }
        
        // Formatear horario
        const horaEnt = ev.hora_ent ? ev.hora_ent.substring(0, 5) : '00:00';
        const horaSal = ev.hora_sal ? ev.hora_sal.substring(0, 5) : '00:00';
        document.getElementById('detHorario').textContent = `${horaEnt} - ${horaSal}`;
        
        // Solicitante
        document.getElementById('detSolicitante').textContent = ev.usuario_nombre || 'Visita / Externo';
        document.getElementById('detCorreo').textContent = ev.usuario_correo || 'No disponible';
        document.getElementById('detAsistentes').textContent = `${ev.num_alumnos || 0} alumnos`;
        
        document.getElementById('resDetailsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.showResTooltip = function(e, ev) {
        const tooltip = document.getElementById('calendarTooltip');
        if (!tooltip) return;
        
        const est = ev.estatus || ev.status || 'Pendiente';
        let statusColor = '#10b981';
        if (est === 'Pendiente' || est === 'pending') statusColor = '#f59e0b';
        if (est === 'Rechazada' || est === 'rejected') statusColor = '#ef4444';
        
        const horaEnt = ev.hora_ent ? ev.hora_ent.substring(0, 5) : '00:00';
        const horaSal = ev.hora_sal ? ev.hora_sal.substring(0, 5) : '00:00';
        
        tooltip.innerHTML = `
            <div style="font-weight: 800; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <span>${ev.nombre_numero} (${ev.edificio})</span>
                <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: ${statusColor}; color: white; text-transform: uppercase;">${est}</span>
            </div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-clock" style="margin-right: 6px;"></i><strong>Horario:</strong> ${horaEnt} - ${horaSal}</div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-person" style="margin-right: 6px;"></i><strong>Solicitante:</strong> ${ev.usuario_nombre || 'Visita'}</div>
            <div style="margin-bottom: 4px; color: #cbd5e1;"><i class="bi bi-envelope" style="margin-right: 6px;"></i><strong>Correo:</strong> ${ev.usuario_correo || 'N/A'}</div>
            <div style="color: #cbd5e1;"><i class="bi bi-people" style="margin-right: 6px;"></i><strong>Asistentes:</strong> ${ev.num_alumnos || 0}</div>
        `;
        tooltip.style.display = 'block';
        window.positionResTooltip(e);
    };

    window.positionResTooltip = function(e) {
        const tooltip = document.getElementById('calendarTooltip');
        if (!tooltip || tooltip.style.display === 'none') return;
        
        const tooltipWidth = tooltip.offsetWidth;
        const tooltipHeight = tooltip.offsetHeight;
        
        let x = e.clientX + 15;
        let y = e.clientY + 15;
        
        if (x + tooltipWidth > window.innerWidth) {
            x = e.clientX - tooltipWidth - 15;
        }
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    };

    window.hideResTooltip = function() {
        const tooltip = document.getElementById('calendarTooltip');
        if (tooltip) tooltip.style.display = 'none';
    };

    // ============================================================================
    // MAPA INTERACTIVO INTEGRADO EN EL MODAL DE RESERVAS
    // ============================================================================

    let MAP_DATA = {};
    let modalCurrentMapKey = 'PIDET_alta';
    let modalSelectedPolygon = null;

    // Estado del Pan y Zoom
    let modalZoom = 1;
    let modalPanX = 0;
    let modalPanY = 0;
    let modalIsDragging = false;
    let modalStartX = 0;
    let modalStartY = 0;

    // Inicializar Motor
    function initModalMap() {
        if (Object.keys(MAP_DATA).length === 0) {
            fetch('assets/map_data.json')
                .then(r => r.json())
                .then(data => {
                    MAP_DATA = data;
                    updateModalMapImage();
                })
                .catch(e => console.error('Error cargando map_data.json:', e));
        } else {
            updateModalMapImage();
        }
    }

    function updateModalMapImage() {
        const edif = document.getElementById('resEdificio').value || 'PIDET';
        const planta = document.getElementById('resPlanta').value || 'alta';
        modalCurrentMapKey = `${edif}_${planta}`;

        const config = MAP_DATA[modalCurrentMapKey];
        if (!config) return;

        modalDeselectZone();
        const img = document.getElementById('modalMapImage');
        img.src = config.image;
        
        // Reset pan & zoom al cambiar de mapa
        modalMapZoomReset();
    }

    // Callbacks del modal selectores
    document.getElementById('resEdificio').addEventListener('change', () => {
        updateModalMapImage();
    });
    
    document.getElementById('resPlanta').addEventListener('change', () => {
        updateModalMapImage();
        // Borrar selección de espacio si cambió de planta
        document.getElementById('resEspacio').value = "";
        document.getElementById('resEspacio').dispatchEvent(new Event('change'));
    });

    window.onModalMapImageLoad = function() {
        const img = document.getElementById('modalMapImage');
        if (!img.naturalWidth) return;

        const svg = document.getElementById('modalMapOverlay');
        svg.setAttribute('viewBox', `0 0 ${img.naturalWidth} ${img.naturalHeight}`);
        renderModalMap();

        // Ejecutar Fit to View inicial
        if (typeof window.calculateFitToView === 'function') {
            window.calculateFitToView();
        }
    };

    function renderModalMap() {
        const svg = document.getElementById('modalMapOverlay');
        svg.innerHTML = '';
        const config = MAP_DATA[modalCurrentMapKey];
        if (!config || !config.zones) return;

        config.zones.forEach(zone => {
            const spaceData = findSpaceInDB(zone.db_name, config.edificio);
            const estatus = spaceData ? spaceData.estatus : 'Disponible';
            const label = spaceData ? spaceData.nombre_numero : zone.db_name;

            let esReservable = true;
            if (spaceData && spaceData.hasOwnProperty('es_reservable')) {
                esReservable = (spaceData.es_reservable === true || spaceData.es_reservable === 't' || spaceData.es_reservable == 1);
            } else if (spaceData) {
                esReservable = ['Aula', 'Laboratorio', 'Auditorio', 'Sala de juntas'].includes(spaceData.tipo);
            }

            const baseStyle = getBaseStyle(estatus, esReservable);
            const poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            poly.setAttribute('points', zone.points);
            poly.dataset.dbname = zone.db_name;
            poly.dataset.espid = spaceData ? spaceData.esp_id : '';
            poly.dataset.estatus = estatus;
            poly.dataset.reservable = esReservable;
            
            poly.setAttribute('fill', baseStyle.fill);
            poly.setAttribute('stroke', baseStyle.stroke);
            poly.setAttribute('stroke-width', '3');
            poly.setAttribute('stroke-opacity', '0.4');
            poly.style.pointerEvents = 'all';
            poly.style.cursor = esReservable ? 'pointer' : 'not-allowed';
            poly.style.transition = 'all 0.15s ease';

            // Tooltip events
            poly.addEventListener('mouseenter', (e) => {
                if (modalIsDragging) return;
                if (poly !== modalSelectedPolygon) {
                    poly.setAttribute('stroke-width', '4');
                    poly.setAttribute('stroke-opacity', '0.9');
                    poly.setAttribute('fill', baseStyle.fill.replace('0.15', '0.25').replace('0.08', '0.2'));
                }
                showModalTooltip(e, label, spaceData, estatus, esReservable);
            });

            poly.addEventListener('mousemove', (e) => {
                if (modalIsDragging) { hideModalTooltip(); return; }
                moveModalTooltip(e);
            });

            poly.addEventListener('mouseleave', () => {
                if (poly !== modalSelectedPolygon) {
                    poly.setAttribute('stroke-width', '3');
                    poly.setAttribute('stroke-opacity', '0.4');
                    poly.setAttribute('fill', baseStyle.fill);
                }
                hideModalTooltip();
            });

            // Click event (seleccionar)
            poly.addEventListener('click', (e) => {
                if (modalIsDragging) return; // evitar click accidental al arrastrar
                e.stopPropagation();
                if (!esReservable) return;
                modalSelectZone(poly, zone, spaceData);
            });

            svg.appendChild(poly);
        });

        // Sincronizar selección si ya había un espacio seleccionado en el form
        syncMapFromForm();
    }

    function modalSelectZone(poly, zone, spaceData) {
        if (modalSelectedPolygon && modalSelectedPolygon !== poly) {
            resetPolygonStyle(modalSelectedPolygon);
        }

        modalSelectedPolygon = poly;

        // Estilo seleccionado MUCHO más evidente (Azul fuerte, borde grueso)
        poly.setAttribute('fill', 'rgba(37, 99, 235, 0.4)');
        poly.setAttribute('stroke', '#1d4ed8');
        poly.setAttribute('stroke-opacity', '1');
        poly.setAttribute('stroke-width', '6');

        // Auto-centrar en el salón seleccionado
        centerMapOnPolygon(poly);

        // Sincronizar hacia el formulario
        if (spaceData) {
            const resEspacio = document.getElementById('resEspacio');
            if (resEspacio) {
                resEspacio.value = spaceData.esp_id;
                resEspacio.dispatchEvent(new Event('change'));
            }
        }
    }

    function modalDeselectZone() {
        if (modalSelectedPolygon) {
            resetPolygonStyle(modalSelectedPolygon);
            modalSelectedPolygon = null;
        }
    }

    function resetPolygonStyle(poly) {
        const estatus = poly.dataset.estatus || 'Disponible';
        const res = poly.dataset.reservable === 'true';
        const style = getBaseStyle(estatus, res);
        poly.setAttribute('fill', style.fill);
        poly.setAttribute('stroke', style.stroke);
        poly.setAttribute('stroke-opacity', '0.4');
        poly.setAttribute('stroke-width', '3');
    }

    function getBaseStyle(estatus, isReservable) {
        if (!isReservable) return { fill: 'rgba(100, 116, 139, 0.15)', stroke: '#64748b' };
        switch (estatus) {
            case 'Ocupado':       return { fill: 'rgba(239, 68, 68, 0.15)', stroke: '#ef4444' };
            case 'Mantenimiento': return { fill: 'rgba(234, 179, 8, 0.15)',  stroke: '#eab308' };
            default:              return { fill: 'rgba(34, 197, 94, 0.08)',  stroke: '#22c55e' };
        }
    }

    function findSpaceInDB(dbName, edificio) {
        if (!dbName || !allSpaces) return null;
        const needle = dbName.toLowerCase().trim();
        return allSpaces.find(sp => {
            if (sp.edificio !== edificio) return false;
            const hay = sp.nombre_numero.toLowerCase().trim();
            return hay.includes(needle) || needle.includes(hay);
        }) || null;
    }

    // Sincronización Form -> Map
    function syncMapFromForm() {
        const espId = document.getElementById('resEspacio').value;
        if (!espId) {
            modalDeselectZone();
            return;
        }
        const svg = document.getElementById('modalMapOverlay');
        const polys = svg.querySelectorAll('polygon');
        let found = false;
        polys.forEach(p => {
            if (p.dataset.espid == espId) {
                modalSelectZone(p, null, null); // spaceData not strictly needed here for styling
                found = true;
            }
        });
        if(!found) modalDeselectZone();
    }

    document.getElementById('resEspacio').addEventListener('change', () => {
        syncMapFromForm();
        
        // Show info box below select
        const espId = document.getElementById('resEspacio').value;
        const infoBox = document.getElementById('resSelectedSpaceInfo');
        if (espId) {
            const spObj = allSpaces.find(sp => sp.esp_id == espId);
            if(spObj) {
                infoBox.style.display = 'block';
                infoBox.innerHTML = `<i class="bi bi-check-circle-fill"></i> Seleccionado: ${spObj.nombre_numero} (${spObj.tipo}) - Capacidad: ${spObj.capacidad}`;
            }
        } else {
            infoBox.style.display = 'none';
        }
    });

    // Tooltip
    const tooltip = document.getElementById('modalMapTooltip');
    function showModalTooltip(e, label, data, estatus, isReservable) {
        let typeHtml = data ? data.tipo : 'Espacio genérico';
        let capHtml = data ? `Capacidad: ${data.capacidad} personas` : '';
        let statusClass = estatus === 'Disponible' ? 'libre' : (estatus === 'Ocupado' ? 'ocupado' : '');
        if(!isReservable) statusClass = 'nores';
        
        let estatusStr = isReservable ? estatus : 'No reservable';

        tooltip.innerHTML = `
            <div class="tt-name">${label}</div>
            <div class="tt-type">${typeHtml}</div>
            ${capHtml ? `<div class="tt-cap"><i class="bi bi-people"></i> ${capHtml}</div>` : ''}
            <div class="tt-status ${statusClass}">${estatusStr}</div>
        `;
        tooltip.style.display = 'block';
        moveModalTooltip(e);
    }
    function moveModalTooltip(e) {
        if(tooltip.style.display === 'none') return;
        let x = e.clientX + 15;
        let y = e.clientY + 15;
        if (x + tooltip.offsetWidth > window.innerWidth) x = e.clientX - tooltip.offsetWidth - 15;
        if (y + tooltip.offsetHeight > window.innerHeight) y = e.clientY - tooltip.offsetHeight - 15;
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }
    function hideModalTooltip() {
        tooltip.style.display = 'none';
    }

    // Buscador local en el mapa con auto-foco
    window.modalHighlightMapSpace = function(query) {
        const polys = document.getElementById('modalMapOverlay').querySelectorAll('polygon');
        const q = query.toLowerCase().trim();
        let exactMatch = null;
        let partialMatches = [];

        polys.forEach(p => {
            const dbname = (p.dataset.dbname || '').toLowerCase();
            if (!q) {
                p.style.opacity = '1';
                if (p !== modalSelectedPolygon) { resetPolygonStyle(p); }
            } else if (dbname.includes(q)) {
                p.style.opacity = '1';
                if(p !== modalSelectedPolygon) {
                    p.setAttribute('stroke','#2563eb'); 
                    p.setAttribute('stroke-opacity','0.8');
                    p.setAttribute('stroke-width','4');
                }
                if (dbname === q) exactMatch = p;
                partialMatches.push(p);
            } else {
                p.style.opacity = '0.2';
                if (p !== modalSelectedPolygon) { resetPolygonStyle(p); }
            }
        });

        if (q && exactMatch) {
            centerMapOnPolygon(exactMatch);
        } else if (q && partialMatches.length === 1) {
            centerMapOnPolygon(partialMatches[0]);
        }
    };

    // Zoom & Pan (Matemáticas Avanzadas)
    const mapViewport = document.getElementById('modalMapViewport');
    const mapInner = document.getElementById('modalMapInner');

    function applyTransform(withTransition = false) {
        if (!mapInner) return;
        if (withTransition) {
            mapInner.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        } else {
            mapInner.style.transition = 'none';
        }
        mapInner.style.transform = `translate(${modalPanX}px, ${modalPanY}px) scale(${modalZoom})`;
        
        // Remove transition after it's done so drag remains responsive
        if (withTransition) {
            setTimeout(() => { mapInner.style.transition = 'none'; }, 300);
        }
    }

    // Auto-foco matemático usando BoundingBox del SVG
    window.centerMapOnPolygon = function(poly) {
        if (!mapViewport || !poly) return;
        const bbox = poly.getBBox();
        const polyCenterX = bbox.x + (bbox.width / 2);
        const polyCenterY = bbox.y + (bbox.height / 2);
        
        // Se mantiene el zoom actual, solo se centra la cámara
        const vW = mapViewport.clientWidth;
        const vH = mapViewport.clientHeight;
        
        modalPanX = (vW / 2) - (polyCenterX * modalZoom);
        modalPanY = (vH / 2) - (polyCenterY * modalZoom);
        
        applyTransform(true);
    };

    function zoomToCenter(delta) {
        const newZoom = Math.min(Math.max(modalZoom + delta, 0.5), 4);
        if (!mapViewport) return;
        const rect = mapViewport.getBoundingClientRect();
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        
        const imgX = (centerX - modalPanX) / modalZoom;
        const imgY = (centerY - modalPanY) / modalZoom;
        
        modalZoom = newZoom;
        modalPanX = centerX - (imgX * modalZoom);
        modalPanY = centerY - (imgY * modalZoom);
        
        applyTransform(true);
    }

    window.calculateFitToView = function() {
        if (!mapViewport) return;
        const img = document.getElementById('modalMapImage');
        if (!img || !img.naturalWidth) return;

        const vW = mapViewport.clientWidth;
        const vH = mapViewport.clientHeight;
        
        // Márgenes de seguridad
        const margin = 30;
        const availableW = vW - (margin * 2);
        const availableH = vH - (margin * 2);

        // Calcular escalas para que quepa completo
        const scaleX = availableW / img.naturalWidth;
        const scaleY = availableH / img.naturalHeight;

        // Tomar la escala mínima para asegurar que quepa sin recortarse
        const fitScale = Math.min(scaleX, scaleY);
        
        modalZoom = Math.min(Math.max(fitScale, 0.1), 4);

        // Calcular el centrado absoluto
        const scaledW = img.naturalWidth * modalZoom;
        const scaledH = img.naturalHeight * modalZoom;

        modalPanX = (vW - scaledW) / 2;
        modalPanY = (vH - scaledH) / 2;

        applyTransform(true);
    };

    window.modalMapZoomIn = function() { zoomToCenter(0.4); };
    window.modalMapZoomOut = function() { zoomToCenter(-0.4); };
    window.modalMapZoomReset = function() { 
        window.calculateFitToView();
    };

    // Recalcular Fit to View al cambiar el tamaño de ventana (debounce)
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // Solo si el modal está abierto (si no, no tiene clientWidth válido)
            const modal = document.getElementById('reservationModalWide');
            if (modal && modal.style.display === 'flex') {
                window.calculateFitToView();
            }
        }, 150);
    });

    if (mapViewport) {
        // Scroll wheel zoom (Centrado al puntero)
        mapViewport.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.15 : 0.15;
            const newZoom = Math.min(Math.max(modalZoom + delta, 0.5), 4);
            
            const rect = mapViewport.getBoundingClientRect();
            const cursorX = e.clientX - rect.left;
            const cursorY = e.clientY - rect.top;
            
            const imgX = (cursorX - modalPanX) / modalZoom;
            const imgY = (cursorY - modalPanY) / modalZoom;
            
            modalZoom = newZoom;
            modalPanX = cursorX - (imgX * modalZoom);
            modalPanY = cursorY - (imgY * modalZoom);
            
            applyTransform(false);
        }, {passive: false});

        // Drag to pan
        mapViewport.addEventListener('mousedown', (e) => {
            if(e.button !== 0) return; // solo click izquierdo
            modalIsDragging = true;
            modalStartX = e.clientX - modalPanX;
            modalStartY = e.clientY - modalPanY;
            mapViewport.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!modalIsDragging) return;
            modalPanX = e.clientX - modalStartX;
            modalPanY = e.clientY - modalStartY;
            applyTransform(false);
        });

        window.addEventListener('mouseup', () => {
            modalIsDragging = false;
            mapViewport.style.cursor = 'grab';
        });
        
        window.addEventListener('mouseleave', () => {
            modalIsDragging = false;
            mapViewport.style.cursor = 'grab';
        });
    }