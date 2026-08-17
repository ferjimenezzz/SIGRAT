<?php
/**
 * @file aprobacion_reservas.php
 * @summary Módulo independiente para la gestión de reservas y aprobaciones.
 */

// ============================================================================
// SECCIÓN 1: INICIALIZACIÓN, MIDDLEWARE DE SEGURIDAD Y SESIONES
// ============================================================================

include 'header.php';

$isMaestroDocente = isset($_SESSION['rol']) && (
    strpos(strtoupper($_SESSION['rol']), 'MAESTRO') !== false || 
    strpos(strtoupper($_SESSION['rol']), 'DOCENTE') !== false || 
    strpos(strtoupper($_SESSION['rol']), 'PROFESOR') !== false
);

$userRolCur = isset($_SESSION['rol']) ? strtoupper(trim($_SESSION['rol'])) : '';
$isVisitaUser = ($userRolCur === 'INVITADO' || $userRolCur === 'VISITA' || strpos($userRolCur, 'VISIT') !== false);
$isMaestroOrVisita = ($isMaestroDocente || $isVisitaUser);
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

<div style="display: flex; flex-direction: column; gap: 24px;">

<!-- ============================================================================ -->
<!-- SECCIÓN 2: ESTRUCTURA HTML, ESTILOS CSS Y CABECERAS VISUALES -->
<!-- ============================================================================ -->
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">
                <?php echo $isMaestroOrVisita ? 'Mis Aprobaciones' : 'Aprobaciones de Reservas'; ?>
            </h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">
                <?php echo $isMaestroOrVisita ? 'Consulta el estado de tus solicitudes y gestiona tus cancelaciones' : 'Gestión de solicitudes pendientes de aprobación'; ?>
            </p>
        </div>
    </header>

    <div id="react-approval-app"></div>
</div>

<style>
    /* Reset label styles from header.php so they don't break MUI TextFields */
    .MuiInputLabel-root {
        text-transform: none !important;
        letter-spacing: normal !important;
        margin-bottom: 0 !important;
        font-weight: 400 !important;
    }
    .MuiInputLabel-root.Mui-focused {
        color: #1976d2 !important;
    }
    /* Force Inter font on all elements inside the React approvals app */
    #react-approval-app, 
    #react-approval-app * {
        font-family: 'Inter', sans-serif !important;
    }
    body, html {
        font-family: 'Inter', sans-serif !important;
    }
</style>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script React adaptado -->
<script>
const isMaestro = <?php echo json_encode($isMaestroDocente); ?>;
const isVisita = <?php echo json_encode($isVisitaUser); ?>;
const isMaestroOrVisita = <?php echo json_encode($isMaestroOrVisita); ?>;
const canApprove = <?php echo json_encode(isset($_SESSION["rol"]) ? ($_SESSION["rol"] === "Super Administrador" || $_SESSION["rol"] === "Administrador") : false); ?>;
window.canApprove = canApprove;
window.isMaestro = isMaestro;
window.isVisita = isVisita;

const {
  useState,
  useEffect
} = React;
const {
  Container,
  Typography,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Button,
  Chip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  CircularProgress,
  Alert,
  MenuItem,
  Select,
  InputLabel,
  FormControl,
  Tabs,
  Tab,
  InputAdornment
} = MaterialUI;

const TutorialGuide = window.TutorialGuide;
const tutorialSteps = [
  {
    title: isMaestroOrVisita ? "¡Mis Aprobaciones!" : "¡Bienvenido a Aprobaciones!",
    description: isMaestroOrVisita ? "En este módulo puedes visualizar únicamente tus reservas de espacios, revisar su estado y cancelarlas cuando lo requieras." : "Este recorrido interactivo te guiará en el uso del módulo de aprobación de reservas.",
    position: "center"
  },
  {
    target: "#tutorial-search",
    title: "Buscador e Filtros Avanzados",
    description: isMaestroOrVisita ? "Filtra tus reservas buscando por <b>espacio</b>, fecha específica o por un rango de fechas." : "Filtra reservas al instante buscando por el <b>ID</b>, el nombre del <b>usuario</b>, el <b>espacio</b> solicitado o rango de fechas.",
    position: "bottom"
  },
  {
    target: "#tutorial-tabs",
    title: "Filtros de Estado",
    description: "Cambia entre solicitudes <b>Pendientes</b>, <b>Aprobadas</b> y <b>Canceladas</b> para consultar tus solicitudes.",
    position: "bottom"
  },
  {
    target: "#tutorial-table",
    title: "Listado de Reservas",
    description: isMaestroOrVisita ? "Visualiza el espacio, fecha y hora, estado y el motivo por el cual fue cancelada la reserva." : "En esta tabla se consolida la información detallada de cada solicitud.",
    position: "top"
  }
];

function ReservationApprovalApp() {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [currentTab, setCurrentTab] = useState(0);
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedSpaceFilter, setSelectedSpaceFilter] = useState("ALL");
  const [startDateFilter, setStartDateFilter] = useState("");
  const [endDateFilter, setEndDateFilter] = useState("");
  const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
  const [selectedReservation, setSelectedReservation] = useState(null);
  const [rejectReason, setRejectReason] = useState("");
  const [rejectSelectReason, setRejectSelectReason] = useState("Espacio no disponible");
  const [actionLoading, setActionLoading] = useState(false);
  const [spaces, setSpaces] = useState([]);

  const fetchSpaces = async () => {
    try {
      const response = await fetch("../backend/api/index.php/spaces", { credentials: "same-origin" });
      if (response.ok) {
        const data = await response.json();
        setSpaces(Array.isArray(data) ? data : []);
      }
    } catch (e) {
      console.error(e);
    }
  };

  const fetchReservations = async (status = "pending", isSilent = false) => {
    if (!isSilent) setLoading(true);
    try {
      const response = await fetch(`../backend/api/index.php/reservations/${status}?t=${Date.now()}`, {
        credentials: "same-origin",
        headers: { "Cache-Control": "no-cache" }
      });
      if (!response.ok) throw new Error(`Error del servidor (${response.status})`);
      const data = await response.json();
      setReservations(Array.isArray(data) ? data : []);
      setError(null);
    } catch (err) {
      if (!isSilent) setError(err.message);
    } finally {
      if (!isSilent) setLoading(false);
    }
  };

  useEffect(() => {
    let status = "pending";
    if (currentTab === 1) status = "approved";
    if (currentTab === 2) status = "cancelled";
    
    // Carga inicial
    fetchReservations(status);
    fetchSpaces();

    // Auto-actualización periódica en segundo plano cada 6 segundos
    const intervalId = setInterval(() => {
      fetchReservations(status, true);
    }, 6000);

    // Recargar al enfocar la ventana del navegador
    const handleFocus = () => {
      fetchReservations(status, true);
    };
    window.addEventListener("focus", handleFocus);

    return () => {
      clearInterval(intervalId);
      window.removeEventListener("focus", handleFocus);
    };
  }, [currentTab]);

  const handleDirectApprove = async (reservation) => {
    const result = await Swal.fire({
      title: "¿Deseas aprobar esta reserva?",
      text: "Se aprobará usando el espacio solicitado originalmente.",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#10b981",
      cancelButtonText: "Cancelar",
      confirmButtonText: "Sí, aprobar"
    });
    if (result.isConfirmed) {
      setActionLoading(true);
      try {
        const response = await fetch(`../backend/api/index.php/reservations/${reservation.re_id}/approve`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ esp_id: reservation.esp_id })
        });
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          throw new Error(errorData.error || "Error al aprobar");
        }
        await Swal.fire({
          icon: "success",
          title: "¡Aprobada!",
          text: "Reserva aprobada exitosamente.",
          timer: 2000,
          showConfirmButton: false
        });
        await fetchReservations(currentTab === 0 ? "pending" : currentTab === 1 ? "approved" : "cancelled");
      } catch (err) {
        Swal.fire("Error", err.message, "error");
      } finally {
        setActionLoading(false);
      }
    }
  };

  const handleCancel = async (id) => {
    const swalTitle = isMaestroOrVisita ? "Cancelar Mi Reserva" : "Cancelar Reserva";
    const swalOptionsHtml = isMaestroOrVisita ? `
        <div style="text-align: left; margin-top: 10px;">
          <label style="font-size: 14px; font-weight: 500; margin-bottom: 8px; display: block; color: #334155;">Selecciona el motivo de cancelación:</label>
          <select id="swal-select" class="swal2-select" style="display: flex; width: 100%; margin: 0; padding: 10px; font-size: 15px;">
            <option value="Cambio de horario o clase">Cambio de horario o actividad</option>
            <option value="Imprevisto personal / Ausencia">Imprevisto personal / Ausencia</option>
            <option value="Actividad reprogramada o suspendida">Actividad reprogramada o suspendida</option>
            <option value="Ya no requiero el espacio reservado">Ya no requiero el espacio reservado</option>
            <option value="Otro">Otro (Especificar)</option>
          </select>
          <input id="swal-input" class="swal2-input" style="display: none; width: 100%; margin: 10px 0 0 0; box-sizing: border-box; font-size: 15px;" placeholder="Escribe el motivo aquí...">
        </div>
    ` : `
        <div style="text-align: left; margin-top: 10px;">
          <label style="font-size: 14px; font-weight: 500; margin-bottom: 8px; display: block; color: #334155;">Selecciona el motivo:</label>
          <select id="swal-select" class="swal2-select" style="display: flex; width: 100%; margin: 0; padding: 10px; font-size: 15px;">
            <option value="Cancelado por el usuario">Cancelado por el usuario</option>
            <option value="Espacio en mantenimiento">Espacio en mantenimiento</option>
            <option value="Falta de personal / profesor ausente">Falta de personal / profesor ausente</option>
            <option value="Cambio de horario/fecha">Cambio de horario/fecha</option>
            <option value="Otro">Otro (Especificar)</option>
          </select>
          <input id="swal-input" class="swal2-input" style="display: none; width: 100%; margin: 10px 0 0 0; box-sizing: border-box; font-size: 15px;" placeholder="Escribe el motivo aquí...">
        </div>
    `;

    const { value: reason, isConfirmed } = await Swal.fire({
      title: swalTitle,
      html: swalOptionsHtml,
      didOpen: () => {
        const select = Swal.getPopup().querySelector('#swal-select');
        const input = Swal.getPopup().querySelector('#swal-input');
        select.addEventListener('change', () => {
          if (select.value === 'Otro') {
            input.style.display = 'flex';
            input.focus();
          } else {
            input.style.display = 'none';
          }
        });
      },
      preConfirm: () => {
        const select = Swal.getPopup().querySelector('#swal-select');
        const input = Swal.getPopup().querySelector('#swal-input');
        if (select.value === 'Otro') {
          if (!input.value.trim()) {
            Swal.showValidationMessage('¡Necesitas escribir un motivo!');
            return false;
          }
          return input.value.trim();
        }
        return select.value;
      },
      showCancelButton: true,
      confirmButtonColor: "#ef4444",
      cancelButtonText: "No, regresar",
      confirmButtonText: "Confirmar Cancelación"
    });

    if (isConfirmed && reason) {
      setActionLoading(true);
      try {
        const response = await fetch(`../backend/api/index.php/reservations/${id}/cancel`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ reason })
        });
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          throw new Error(errorData.error || "Error al cancelar");
        }
        await Swal.fire("¡Cancelada!", "Tu reserva ha sido cancelada exitosamente.", "success");
        await fetchReservations(currentTab === 0 ? "pending" : currentTab === 1 ? "approved" : "cancelled");
      } catch (err) {
        Swal.fire("Error", err.message, "error");
      } finally {
        setActionLoading(false);
      }
    }
  };

  const openRejectDialog = (reservation) => {
    setSelectedReservation(reservation);
    setRejectSelectReason("Espacio no disponible");
    setRejectReason("Espacio no disponible");
    setRejectDialogOpen(true);
  };

  const closeRejectDialog = () => {
    setRejectDialogOpen(false);
    setSelectedReservation(null);
  };

  const handleReject = async () => {
    if (!selectedReservation) return;
    setActionLoading(true);
    try {
      const response = await fetch(`../backend/api/index.php/reservations/${selectedReservation.re_id}/reject`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ reason: rejectReason })
      });
      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || "Error al rechazar");
      }
      await Swal.fire({
        icon: "success",
        title: "Rechazada",
        text: "La reserva ha sido rechazada.",
        timer: 2000,
        showConfirmButton: false
      });
      closeRejectDialog();
      await fetchReservations(currentTab === 0 ? "pending" : currentTab === 1 ? "approved" : "cancelled");
    } catch (err) {
      Swal.fire("Error", err.message, "error");
    } finally {
      setActionLoading(false);
    }
  };

  const renderRowAction = (row) => {
    const isPast = () => {
      const rowDateTime = new Date(`${row.fecha_uso}T${row.hora_ent}`);
      return new Date() > rowDateTime;
    };
    if (canApprove) {
      if (row.status === "pending") {
        return React.createElement("div", { className: "tutorial-row-actions", style: { display: "flex", gap: "8px", flexWrap: "nowrap", justifyContent: "center" } }, 
          React.createElement(Button, { variant: "contained", size: "small", sx: { fontWeight: 800, borderRadius: 2, bgcolor: "#10b981", boxShadow: "none", whiteSpace: "nowrap" }, onClick: () => handleDirectApprove(row), disabled: actionLoading }, "Aprobar"), 
          React.createElement(Button, { variant: "outlined", color: "error", size: "small", sx: { fontWeight: 800, borderRadius: 2, whiteSpace: "nowrap" }, onClick: () => openRejectDialog(row), disabled: actionLoading }, "Rechazar")
        );
      } else if (row.status === "approved") {
        return isPast() ? null : React.createElement(Button, { variant: "outlined", color: "warning", size: "small", sx: { fontWeight: 800, borderRadius: 2 }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar");
      } else {
        return React.createElement("span", { style: { fontSize: "11px", color: "#94a3b8", fontWeight: 700 } }, "Procesada");
      }
    } else {
      if (row.status === "pending" || row.status === "approved") {
        if (isPast()) return null;
        return React.createElement(Button, { variant: "outlined", color: "error", size: "small", sx: { fontWeight: 800, borderRadius: 2 }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar Reserva");
      } else {
        return React.createElement("span", { style: { fontSize: "11px", color: "#94a3b8", fontWeight: 700 } }, "Procesada");
      }
    }
  };

  const [sortOrder, setSortOrder] = useState("DESC"); // "DESC": Recientes primero, "ASC": Antiguas primero

  const handleClearFilters = () => {
    setSearchTerm("");
    setSelectedSpaceFilter("ALL");
    setStartDateFilter("");
    setEndDateFilter("");
    setSortOrder("DESC");
  };

  const filteredReservations = reservations
    .filter(row => {
      // Filter text search
      if (searchTerm) {
        const term = searchTerm.toLowerCase();
        const idMatch = !isMaestroOrVisita && row.re_id && row.re_id.toString().includes(term);
        const userMatch = !isMaestroOrVisita && row.usuario_nombre && row.usuario_nombre.toLowerCase().includes(term);
        const spaceMatch = row.espacio_nombre && row.espacio_nombre.toLowerCase().includes(term);
        if (!(idMatch || userMatch || spaceMatch)) return false;
      }

      // Filter space
      if (selectedSpaceFilter !== "ALL") {
        const selectedSpace = spaces.find(s => s.esp_id == selectedSpaceFilter);
        const spaceNameMatch = selectedSpace && row.espacio_nombre && row.espacio_nombre.toLowerCase().includes(selectedSpace.nombre_numero.toLowerCase());
        const spaceIdMatch = row.esp_id != null && row.esp_id == selectedSpaceFilter;
        if (!spaceIdMatch && !spaceNameMatch) return false;
      }

      // Filter fecha (Start & End Date range or single date)
      if (startDateFilter) {
        if (row.fecha_uso < startDateFilter) return false;
      }
      if (endDateFilter) {
        if (row.fecha_uso > endDateFilter) return false;
      }

      return true;
    })
    .sort((a, b) => {
      const dateTimeA = new Date(`${a.fecha_uso}T${a.hora_ent || '00:00:00'}`).getTime();
      const dateTimeB = new Date(`${b.fecha_uso}T${b.hora_ent || '00:00:00'}`).getTime();
      return sortOrder === "DESC" ? dateTimeB - dateTimeA : dateTimeA - dateTimeB;
    });

  return React.createElement("div", { style: { marginTop: 10, fontFamily: "Inter, sans-serif", display: "flex", flexDirection: "column", alignItems: "flex-end" } }, 
    error && React.createElement(Alert, { severity: "error", sx: { mb: 3, width: "100%" } }, error), 

    // BARRA DE FILTROS SUPERIOR
    React.createElement("div", { style: { display: "flex", flexDirection: "column", gap: "16px", width: "100%", marginBottom: "16px" } }, 
      React.createElement("div", { style: { display: "flex", justifyContent: "space-between", alignItems: "center", width: "100%", flexWrap: "wrap", gap: "12px" } }, 
        React.createElement("div", { style: { display: "flex", alignItems: "center", gap: "10px", flexWrap: "wrap", flex: 1 } },
          React.createElement(TextField, { 
            id: "tutorial-search", 
            placeholder: isMaestroOrVisita ? "Buscar espacio..." : "Buscar ID, usuario o espacio...", 
            variant: "outlined", 
            size: "small", 
            value: searchTerm, 
            onChange: (e) => setSearchTerm(e.target.value), 
            InputProps: { startAdornment: React.createElement(InputAdornment, { position: "start" }, React.createElement("i", { className: "bi bi-search", style: { fontSize: "15px", color: "#94a3b8" } })) }, 
            sx: { minWidth: "180px", maxWidth: "220px", '& .MuiOutlinedInput-root': { borderRadius: "10px", backgroundColor: "#f8fafc", overflow: "hidden", '& fieldset': { borderColor: "#e2e8f0" }, '&:hover fieldset': { borderColor: "#cbd5e1" }, '&.Mui-focused fieldset': { borderColor: "#2563eb" } }, '& .MuiOutlinedInput-input': { backgroundColor: "transparent" } } 
          }),

          // Filtro por Espacio
          React.createElement(FormControl, { size: "small", sx: { minWidth: "170px", maxWidth: "200px" } },
            React.createElement(Select, {
              value: selectedSpaceFilter,
              onChange: (e) => setSelectedSpaceFilter(e.target.value),
              displayEmpty: true,
              sx: { borderRadius: "10px", backgroundColor: "#f8fafc", fontSize: "13px", color: "#334155", '& fieldset': { borderColor: "#e2e8f0" } }
            },
              React.createElement(MenuItem, { value: "ALL" }, "Todos los Espacios"),
              spaces.map(s => React.createElement(MenuItem, { key: s.esp_id, value: s.esp_id }, `${s.nombre_numero} (${s.edificio})`))
            )
          ),

          // Ordenamiento por Fecha/Tiempo
          React.createElement(FormControl, { size: "small", sx: { minWidth: "180px", maxWidth: "210px" } },
            React.createElement(Select, {
              value: sortOrder,
              onChange: (e) => setSortOrder(e.target.value),
              sx: { borderRadius: "10px", backgroundColor: "#f8fafc", fontSize: "13px", color: "#334155", '& fieldset': { borderColor: "#e2e8f0" } }
            },
              React.createElement(MenuItem, { value: "DESC" }, "Más recientes primero"),
              React.createElement(MenuItem, { value: "ASC" }, "Más antiguas primero")
            )
          ),

          // Filtro Fecha Inicio / Fecha Específica
          React.createElement(TextField, {
            type: "date",
            size: "small",
            value: startDateFilter,
            onChange: (e) => setStartDateFilter(e.target.value),
            InputLabelProps: { shrink: true },
            sx: { width: "140px", '& .MuiOutlinedInput-root': { borderRadius: "10px", backgroundColor: "#f8fafc", '& fieldset': { borderColor: "#e2e8f0" } } }
          }),

          // Filtro Fecha Fin (Rango)
          React.createElement(TextField, {
            type: "date",
            size: "small",
            value: endDateFilter,
            onChange: (e) => setEndDateFilter(e.target.value),
            InputLabelProps: { shrink: true },
            sx: { width: "140px", '& .MuiOutlinedInput-root': { borderRadius: "10px", backgroundColor: "#f8fafc", '& fieldset': { borderColor: "#e2e8f0" } } }
          }),

          // Botón Limpiar Filtros
          (searchTerm || selectedSpaceFilter !== "ALL" || startDateFilter || endDateFilter || sortOrder !== "DESC") && React.createElement(Button, {
            variant: "text",
            size: "small",
            onClick: handleClearFilters,
            sx: { color: "#ef4444", fontWeight: 700, textTransform: "none", fontSize: "12px", whiteSpace: "nowrap" }
          }, React.createElement("i", { className: "bi bi-x-circle", style: { marginRight: "4px" } }), "Limpiar Filtros"),

          React.createElement(Button, {
            variant: "outlined",
            size: "small",
            onClick: () => fetchReservations(currentTab === 0 ? "pending" : currentTab === 1 ? "approved" : "cancelled"),
            sx: { borderRadius: "10px", height: "40px", borderColor: "#e2e8f0", color: "#475569", fontWeight: 700, textTransform: "none", flexShrink: 0, '&:hover': { borderColor: "#cbd5e1", backgroundColor: "#f8fafc" } }
          }, React.createElement("i", { className: "bi bi-arrow-clockwise", style: { marginRight: "6px", fontSize: "14px" } }), "Actualizar")
        ), 
        React.createElement(Tabs, { id: "tutorial-tabs", value: currentTab, onChange: (e, newValue) => setCurrentTab(newValue), sx: { flexShrink: 0, '& .MuiTabs-flexContainer': { justifyContent: 'flex-end' } } }, 
          React.createElement(Tab, { label: "Pendientes", sx: { fontWeight: 800, fontSize: "14px", minWidth: "auto", px: 2 } }), 
          React.createElement(Tab, { label: "Aprobadas", sx: { fontWeight: 800, fontSize: "14px", minWidth: "auto", px: 2 } }), 
          React.createElement(Tab, { label: "Canceladas", sx: { fontWeight: 800, fontSize: "14px", minWidth: "auto", px: 2 } })
        )
      )
    ), 
    React.createElement(Paper, { id: "tutorial-table", elevation: 0, sx: { width: "100%", borderRadius: 3, overflow: "hidden", border: "1px solid #e2e8f0" } }, 
      loading ? React.createElement("div", { style: { padding: 60, textAlign: "center" } }, React.createElement(CircularProgress, { sx: { color: "#3b82f6" } })) : React.createElement(TableContainer, { sx: { maxHeight: 450 } }, 
        React.createElement(Table, { stickyHeader: true }, 
          React.createElement(TableHead, null, 
            React.createElement(TableRow, null, 
              !isMaestroOrVisita && React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "ID"), 
              !isMaestroOrVisita && React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "USUARIO"), 
              React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "ESPACIO"), 
              React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "FECHA Y HORA"), 
              React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "ESTADO"), 
              React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", textAlign: currentTab === 2 ? "left" : "center" } }, currentTab === 2 ? "MOTIVO DE CANCELACIÓN" : "ACCIONES")
            )
          ), 
          React.createElement(TableBody, null, 
            filteredReservations.length === 0 ? React.createElement(TableRow, null, 
              React.createElement(TableCell, { colSpan: isMaestroOrVisita ? 4 : 6, align: "center", sx: { py: 5, color: "#94a3b8" } }, 
                currentTab === 0 ? "No hay solicitudes pendientes" : currentTab === 1 ? "No hay reservas aprobadas" : "No hay reservas canceladas"
              )
            ) : filteredReservations.map((row) => 
              React.createElement(TableRow, { key: row.re_id, hover: true, sx: { "&:last-child td, &:last-child th": { border: 0 } } }, 
                !isMaestroOrVisita && React.createElement(TableCell, { sx: { fontWeight: 800, color: "#94a3b8" } }, typeof row.re_id === 'string' && row.re_id.startsWith('grp_') ? row.re_id.substring(4, 10) : "#" + row.re_id), 
                !isMaestroOrVisita && React.createElement(TableCell, { sx: { fontWeight: 700 } }, row.usuario_nombre || "Desconocido"), 
                React.createElement(TableCell, { sx: { fontWeight: 700, color: "#334155" } }, row.espacio_nombre || "Desconocido"), 
                React.createElement(TableCell, null, 
                  React.createElement("div", { style: { fontWeight: 800 } }, row.fecha_uso), 
                  React.createElement("div", { style: { fontSize: 12, color: "#64748b", fontWeight: 600 } }, row.hora_ent ? row.hora_ent.substring(0, 5) : "", " a ", row.hora_sal ? row.hora_sal.substring(0, 5) : "")
                ), 
                React.createElement(TableCell, null, 
                  row.status === "pending" && React.createElement(Chip, { label: "PENDIENTE", size: "small", sx: { fontWeight: 800, bgcolor: "#fef3c7", color: "#d97706", borderRadius: 2 } }), 
                  row.status === "approved" && React.createElement(Chip, { label: "APROBADA", size: "small", sx: { fontWeight: 800, bgcolor: "#dcfce3", color: "#10b981", borderRadius: 2 } }), 
                  row.status === "cancelled" && React.createElement(Chip, { label: "CANCELADA", size: "small", sx: { fontWeight: 800, bgcolor: "#f1f5f9", color: "#64748b", borderRadius: 2 } }), 
                  row.status === "rejected" && React.createElement(Chip, { label: "RECHAZADA", size: "small", sx: { fontWeight: 800, bgcolor: "#fee2e2", color: "#ef4444", borderRadius: 2 } })
                ), 
                React.createElement(TableCell, { align: currentTab === 2 ? "left" : "center", sx: { fontSize: currentTab === 2 ? "13px" : undefined } }, 
                  currentTab === 2 ? React.createElement("div", { style: { background: '#f8fafc', padding: '6px 12px', borderRadius: '8px', border: '1px solid #e2e8f0', color: '#334155', display: 'inline-block', maxWidth: '360px', whiteSpace: 'normal', wordBreak: 'break-word' } }, 
                    React.createElement("span", { style: { fontWeight: 700, color: row.status === 'rejected' ? '#dc2626' : '#475569', fontSize: '11px', display: 'block', textTransform: 'uppercase', marginBottom: '2px' } }, row.status === 'rejected' ? "Rechazada por Administrador:" : "Motivo de Cancelación:"), 
                    row.cancel_reason || "Sin motivo especificado"
                  ) : renderRowAction(row)
                )
              )
            )
          )
        )
      )
    ), 
    React.createElement(Dialog, { open: rejectDialogOpen, onClose: closeRejectDialog }, 
      React.createElement(DialogTitle, null, "Rechazar Solicitud"), 
      React.createElement(DialogContent, { style: { display: "flex", flexDirection: "column", gap: "16px", paddingTop: "10px", width: "400px" } }, 
        React.createElement(FormControl, { fullWidth: true, size: "small" }, 
          React.createElement(InputLabel, null, "Motivo de rechazo"), 
          React.createElement(Select, { value: rejectSelectReason, label: "Motivo de rechazo", onChange: (e) => {
            setRejectSelectReason(e.target.value);
            if (e.target.value !== "Otro") {
              setRejectReason(e.target.value);
            } else {
              setRejectReason("");
            }
          } }, 
            React.createElement(MenuItem, { value: "Espacio no disponible" }, "Espacio no disponible"), 
            React.createElement(MenuItem, { value: "Horario fuera de servicio" }, "Horario fuera de servicio"), 
            React.createElement(MenuItem, { value: "Uso indebido del espacio" }, "Uso indebido del espacio"), 
            React.createElement(MenuItem, { value: "Otro" }, "Otro (Especificar)")
          )
        ), 
        rejectSelectReason === "Otro" && React.createElement(TextField, { autoFocus: true, margin: "dense", label: "Escribe el motivo...", fullWidth: true, variant: "outlined", value: rejectReason, onChange: (e) => setRejectReason(e.target.value) })
      ), 
      React.createElement(DialogActions, null, 
        React.createElement(Button, { onClick: closeRejectDialog }, "Cancelar"), 
        React.createElement(Button, { onClick: handleReject, color: "error", variant: "contained", disabled: rejectSelectReason === "Otro" && !rejectReason.trim() }, "Confirmar Rechazo")
      )
    ), 
    React.createElement(TutorialGuide, { steps: tutorialSteps, moduleId: "aprobaciones" })
  );
}

const root = ReactDOM.createRoot(document.getElementById("react-approval-app"));
root.render(React.createElement(ReservationApprovalApp, null));
</script>

<?php include 'footer.php'; ?>
