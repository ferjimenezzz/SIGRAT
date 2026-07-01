<?php
/**
 * @file aprobacion_reservas.php
 * @summary Módulo independiente para la gestión y aprobación de reservas.
 */
// require_once 'seguridad.php';
include 'header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

<div style="display: flex; flex-direction: column; gap: 24px;">
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Aprobaciones de Reservas</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Gestión de solicitudes pendientes de aprobación</p>
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

<!-- React Dependencies for Aprobaciones -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>

<script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script React original adaptado -->
<script>
const canApprove = <?php echo json_encode(isset($_SESSION["rol"]) ? ($_SESSION["rol"] === "Super Administrador" || $_SESSION["rol"] === "Administrador") : false); ?>;
window.canApprove = canApprove;
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
function ReservationApprovalApp() {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [currentTab, setCurrentTab] = useState(0);
  const [searchTerm, setSearchTerm] = useState("");
  const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
  const [selectedReservation, setSelectedReservation] = useState(null);
  const [rejectReason, setRejectReason] = useState("");
  const [rejectSelectReason, setRejectSelectReason] = useState("Espacio no disponible");
  const [actionLoading, setActionLoading] = useState(false);
  const [spaces, setSpaces] = useState([]);
  const [approveDialogOpen, setApproveDialogOpen] = useState(false);
  const [reservationToApprove, setReservationToApprove] = useState(null);
  const [selectedSpaceId, setSelectedSpaceId] = useState("");
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
  const fetchReservations = async (status = "pending") => {
    setLoading(true);
    try {
      const response = await fetch(`../backend/api/index.php/reservations/${status}`, {
        credentials: "same-origin"
      });
      if (!response.ok) throw new Error(`Error del servidor (${response.status})`);
      const data = await response.json();
      setReservations(Array.isArray(data) ? data : []);
      setError(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  useEffect(() => {
    let status = "pending";
    if (currentTab === 1) status = "approved";
    if (currentTab === 2) status = "cancelled";
    fetchReservations(status);
    fetchSpaces();
  }, [currentTab]);
  const handleDirectApprove = async (reservation) => {
    const result = await Swal.fire({
      title: "\xBFDeseas aprobar esta reserva?",
      text: "Se aprobar\xE1 usando el espacio solicitado originalmente.",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#10b981",
      cancelButtonText: "Cancelar",
      confirmButtonText: "S\xED, aprobar"
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
          title: "\xA1Aprobada!",
          text: "Reserva aprobada exitosamente.",
          timer: 2e3,
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
    const { value: reason, isConfirmed } = await Swal.fire({
      title: "Cancelar Reserva",
      html: `
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
      `,
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
            Swal.showValidationMessage('\\xA1Necesitas escribir un motivo!');
            return false;
          }
          return input.value.trim();
        }
        return select.value;
      },
      showCancelButton: true,
      confirmButtonColor: "#ef4444",
      cancelButtonText: "No, regresar",
      confirmButtonText: "S\\xED, cancelar"
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
        await Swal.fire("\xA1Cancelada!", "La reserva ha sido cancelada exitosamente.", "success");
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
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          reason: rejectReason
        })
      });
      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || "Error al rechazar");
      }
      await Swal.fire({
        icon: "success",
        title: "Rechazada",
        text: "La reserva ha sido rechazada.",
        timer: 2e3,
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
        return /* @__PURE__ */ React.createElement("div", { style: { display: "flex", gap: "8px", flexWrap: "nowrap", justifyContent: "center" } }, /* @__PURE__ */ React.createElement(Button, { variant: "contained", size: "small", sx: { fontWeight: 800, borderRadius: 2, bgcolor: "#10b981", boxShadow: "none", whiteSpace: "nowrap" }, onClick: () => handleDirectApprove(row), disabled: actionLoading }, "Aprobar"), /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "error", size: "small", sx: { fontWeight: 800, borderRadius: 2, whiteSpace: "nowrap" }, onClick: () => openRejectDialog(row), disabled: actionLoading }, "Rechazar"));
      } else if (row.status === "approved") {
        return isPast() ? null : /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "warning", size: "small", sx: { fontWeight: 800, borderRadius: 2 }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar");
      } else {
        return /* @__PURE__ */ React.createElement("span", { style: { fontSize: "11px", color: "#94a3b8", fontWeight: 700 } }, "Procesada");
      }
    } else {
      if (row.status === "pending") {
        return null;
      } else if (row.status === "approved") {
        if (isPast()) return null;
        return /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "warning", size: "small", sx: { fontWeight: 800, borderRadius: 2 }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar");
      } else {
        return /* @__PURE__ */ React.createElement("span", { style: { fontSize: "11px", color: "#94a3b8", fontWeight: 700 } }, "Procesada");
      }
    }
  };
  const filteredReservations = reservations.filter(row => {
    if (!searchTerm) return true;
    const term = searchTerm.toLowerCase();
    const idMatch = row.re_id && row.re_id.toString().includes(term);
    const userMatch = row.usuario_nombre && row.usuario_nombre.toLowerCase().includes(term);
    const spaceMatch = row.espacio_nombre && row.espacio_nombre.toLowerCase().includes(term);
    return idMatch || userMatch || spaceMatch;
  });

  return /* @__PURE__ */ React.createElement("div", { style: { marginTop: 10, fontFamily: "Inter, sans-serif", display: "flex", flexDirection: "column", alignItems: "flex-end" } }, error && /* @__PURE__ */ React.createElement(Alert, { severity: "error", sx: { mb: 3, width: "100%" } }, error), /* @__PURE__ */ React.createElement("div", { style: { display: "flex", justifyContent: "space-between", alignItems: "center", width: "100%", marginBottom: "16px" } }, /* @__PURE__ */ React.createElement(TextField, { placeholder: "Buscar ID, usuario o espacio...", variant: "outlined", size: "small", value: searchTerm, onChange: (e) => setSearchTerm(e.target.value), InputProps: { startAdornment: /* @__PURE__ */ React.createElement(InputAdornment, { position: "start" }, /* @__PURE__ */ React.createElement("i", { className: "bi bi-search", style: { fontSize: "15px", color: "#94a3b8" } })) }, sx: { width: "300px", '& .MuiOutlinedInput-root': { borderRadius: "10px", backgroundColor: "#f8fafc", overflow: "hidden", '& fieldset': { borderColor: "#e2e8f0" }, '&:hover fieldset': { borderColor: "#cbd5e1" }, '&.Mui-focused fieldset': { borderColor: "#2563eb" } }, '& .MuiOutlinedInput-input': { backgroundColor: "transparent" } } }), /* @__PURE__ */ React.createElement(Tabs, { value: currentTab, onChange: (e, newValue) => setCurrentTab(newValue), sx: { '& .MuiTabs-flexContainer': { justifyContent: 'flex-end' } } }, /* @__PURE__ */ React.createElement(Tab, { label: "Pendientes", sx: { fontWeight: 800, fontSize: "14px" } }), /* @__PURE__ */ React.createElement(Tab, { label: "Aprobadas", sx: { fontWeight: 800, fontSize: "14px" } }), /* @__PURE__ */ React.createElement(Tab, { label: "Canceladas", sx: { fontWeight: 800, fontSize: "14px" } }))), /* @__PURE__ */ React.createElement(Paper, { elevation: 0, sx: { width: "100%", borderRadius: 3, overflow: "hidden", border: "1px solid #e2e8f0" } }, loading ? /* @__PURE__ */ React.createElement("div", { style: { padding: 60, textAlign: "center" } }, /* @__PURE__ */ React.createElement(CircularProgress, { sx: { color: "#3b82f6" } })) : /* @__PURE__ */ React.createElement(TableContainer, { sx: { maxHeight: 450 } }, /* @__PURE__ */ React.createElement(Table, { stickyHeader: true }, /* @__PURE__ */ React.createElement(TableHead, null, /* @__PURE__ */ React.createElement(TableRow, null, /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "ID"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "USUARIO"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "ESPACIO"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "FECHA Y HORA"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px" } }, "ESTADO"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", textAlign: "center" } }, currentTab === 2 ? "MOTIVO" : "ACCIONES"))), /* @__PURE__ */ React.createElement(TableBody, null, filteredReservations.length === 0 ? /* @__PURE__ */ React.createElement(TableRow, null, /* @__PURE__ */ React.createElement(TableCell, { colSpan: 6, align: "center", sx: { py: 5, color: "#94a3b8" } }, currentTab === 0 ? "No hay solicitudes pendientes o coincidan con la búsqueda" : currentTab === 1 ? "No hay reservas aprobadas o coincidan con la búsqueda" : "No hay reservas canceladas o coincidan con la búsqueda")) : filteredReservations.map((row) => /* @__PURE__ */ React.createElement(TableRow, { key: row.re_id, hover: true, sx: { "&:last-child td, &:last-child th": { border: 0 } } }, /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#94a3b8" } }, typeof row.re_id === 'string' && row.re_id.startsWith('grp_') ? row.re_id.substring(4, 10) : "#" + row.re_id), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 700 } }, row.usuario_nombre || "Desconocido"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 700, color: "#334155" } }, row.espacio_nombre || "Desconocido"), /* @__PURE__ */ React.createElement(TableCell, null, /* @__PURE__ */ React.createElement("div", { style: { fontWeight: 800 } }, row.fecha_uso), /* @__PURE__ */ React.createElement("div", { style: { fontSize: 12, color: "#64748b", fontWeight: 600 } }, row.hora_ent ? row.hora_ent.substring(0, 5) : "", " a ", row.hora_sal ? row.hora_sal.substring(0, 5) : "")), /* @__PURE__ */ React.createElement(TableCell, null, row.status === "pending" && /* @__PURE__ */ React.createElement(Chip, { label: "PENDIENTE", size: "small", sx: { fontWeight: 800, bgcolor: "#fef3c7", color: "#d97706", borderRadius: 2 } }), row.status === "approved" && /* @__PURE__ */ React.createElement(Chip, { label: "APROBADA", size: "small", sx: { fontWeight: 800, bgcolor: "#dcfce3", color: "#10b981", borderRadius: 2 } }), row.status === "cancelled" && /* @__PURE__ */ React.createElement(Chip, { label: "CANCELADA", size: "small", sx: { fontWeight: 800, bgcolor: "#f1f5f9", color: "#64748b", borderRadius: 2 } }), row.status === "rejected" && /* @__PURE__ */ React.createElement(Chip, { label: "RECHAZADA", size: "small", sx: { fontWeight: 800, bgcolor: "#fee2e2", color: "#ef4444", borderRadius: 2 } })), /* @__PURE__ */ React.createElement(TableCell, { align: "center", sx: { fontSize: currentTab === 2 ? "13px" : undefined, color: currentTab === 2 ? "#ef4444" : undefined } }, currentTab === 2 ? (row.cancel_reason || "-") : renderRowAction(row)))))))), /* @__PURE__ */ React.createElement(Dialog, { open: rejectDialogOpen, onClose: closeRejectDialog }, /* @__PURE__ */ React.createElement(DialogTitle, null, "Rechazar Solicitud"), /* @__PURE__ */ React.createElement(DialogContent, { style: { display: "flex", flexDirection: "column", gap: "16px", paddingTop: "10px", width: "400px" } }, /* @__PURE__ */ React.createElement(FormControl, { fullWidth: true, size: "small" }, /* @__PURE__ */ React.createElement(InputLabel, null, "Motivo de rechazo"), /* @__PURE__ */ React.createElement(Select, { value: rejectSelectReason, label: "Motivo de rechazo", onChange: (e) => {
  setRejectSelectReason(e.target.value);
  if (e.target.value !== "Otro") {
    setRejectReason(e.target.value);
  } else {
    setRejectReason("");
  }
} }, /* @__PURE__ */ React.createElement(MenuItem, { value: "Espacio no disponible" }, "Espacio no disponible"), /* @__PURE__ */ React.createElement(MenuItem, { value: "Horario fuera de servicio" }, "Horario fuera de servicio"), /* @__PURE__ */ React.createElement(MenuItem, { value: "Uso indebido del espacio" }, "Uso indebido del espacio"), /* @__PURE__ */ React.createElement(MenuItem, { value: "Otro" }, "Otro (Especificar)"))), rejectSelectReason === "Otro" && /* @__PURE__ */ React.createElement(TextField, { autoFocus: true, margin: "dense", label: "Escribe el motivo...", fullWidth: true, variant: "outlined", value: rejectReason, onChange: (e) => setRejectReason(e.target.value) })), /* @__PURE__ */ React.createElement(DialogActions, null, /* @__PURE__ */ React.createElement(Button, { onClick: closeRejectDialog }, "Cancelar"), /* @__PURE__ */ React.createElement(Button, { onClick: handleReject, color: "error", variant: "contained", disabled: rejectSelectReason === "Otro" && !rejectReason.trim() }, "Confirmar Rechazo"))));
}
const root = ReactDOM.createRoot(document.getElementById("react-approval-app"));
root.render(/* @__PURE__ */ React.createElement(ReservationApprovalApp, null));

</script>



<?php include 'footer.php'; ?>
