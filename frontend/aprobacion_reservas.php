<?php
/**
 * @file aprobacion_reservas.php
 * @summary Módulo independiente para la gestión y aprobación de reservas.
 */
// require_once 'seguridad.php';
include 'header.php';
?>

<style>
    @media (max-width: 768px) {
        .approval-header {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 8px;
        }
        .approval-header h1 {
            font-size: 18px !important;
        }
    }
</style>

<div style="display: flex; flex-direction: column; gap: 24px;">
    <header class="approval-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Aprobaciones de Reservas</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Gestión de solicitudes pendientes de aprobación</p>
        </div>
    </header>

    <div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <div id="react-approval-app" style="min-width: 600px;"></div>
    </div>
</div>


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
  FormControl
} = MaterialUI;
function ReservationApprovalApp() {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
  const [selectedReservation, setSelectedReservation] = useState(null);
  const [rejectReason, setRejectReason] = useState("");
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
  const fetchReservations = async () => {
    setLoading(true);
    try {
      const response = await fetch("../backend/api/index.php/reservations/pending", {
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
    fetchReservations();
    fetchSpaces();
  }, []);
  const openApproveDialog = (reservation) => {
    setReservationToApprove(reservation);
    setSelectedSpaceId(reservation.esp_id || "");
    setApproveDialogOpen(true);
  };
  const closeApproveDialog = () => {
    setApproveDialogOpen(false);
    setReservationToApprove(null);
    setSelectedSpaceId("");
  };
  const submitApprove = async () => {
    if (!reservationToApprove) return;
    setActionLoading(true);
    try {
      const response = await fetch(`../backend/api/index.php/reservations/${reservationToApprove.re_id}/approve`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ esp_id: selectedSpaceId })
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
      closeApproveDialog();
      await fetchReservations();
    } catch (err) {
      Swal.fire("Error", err.message, "error");
    } finally {
      setActionLoading(false);
    }
  };
  const handleCancel = async (id) => {
    const { value: reason } = await Swal.fire({
      title: "Cancelar Reserva",
      input: "text",
      inputLabel: "Motivo de la cancelaci\xF3n",
      inputPlaceholder: "Ingresa el motivo aqu\xED...",
      showCancelButton: true,
      confirmButtonColor: "#ef4444",
      cancelButtonText: "No, regresar",
      confirmButtonText: "S\xED, cancelar",
      inputValidator: (value) => {
        if (!value) return "\xA1Necesitas escribir un motivo!";
      }
    });
    if (reason) {
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
        await fetchReservations();
      } catch (err) {
        Swal.fire("Error", err.message, "error");
      } finally {
        setActionLoading(false);
      }
    }
  };
  const openRejectDialog = (reservation) => {
    setSelectedReservation(reservation);
    setRejectReason("");
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
      await fetchReservations();
    } catch (err) {
      Swal.fire("Error", err.message, "error");
    } finally {
      setActionLoading(false);
    }
  };
  const renderRowAction = (row) => {
    if (canApprove) {
      if (row.status === "pending") {
        return /* @__PURE__ */ React.createElement("div", { style: { display: "flex", gap: "8px", flexWrap: "nowrap", justifyContent: "center" } }, /* @__PURE__ */ React.createElement(Button, { variant: "contained", size: "small", sx: { fontWeight: 800, borderRadius: 2, bgcolor: "#10b981", boxShadow: "none", whiteSpace: "nowrap" }, onClick: () => openApproveDialog(row), disabled: actionLoading }, "Aprobar"), /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "error", size: "small", sx: { fontWeight: 800, borderRadius: 2, whiteSpace: "nowrap" }, onClick: () => openRejectDialog(row), disabled: actionLoading }, "Rechazar"), /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "warning", size: "small", sx: { fontWeight: 800, borderRadius: 2, whiteSpace: "nowrap" }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar"));
      } else if (row.status === "approved") {
        return /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "warning", size: "small", sx: { fontWeight: 800, borderRadius: 2 }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar");
      } else {
        return /* @__PURE__ */ React.createElement("span", { style: { fontSize: "11px", color: "#94a3b8", fontWeight: 700 } }, "Procesada");
      }
    } else {
      if (row.status === "pending" || row.status === "approved") {
        return /* @__PURE__ */ React.createElement(Button, { variant: "outlined", color: "warning", size: "small", sx: { fontWeight: 800, borderRadius: 2 }, onClick: () => handleCancel(row.re_id), disabled: actionLoading }, "Cancelar");
      } else {
        return /* @__PURE__ */ React.createElement("span", { style: { fontSize: "11px", color: "#94a3b8", fontWeight: 700 } }, "Procesada");
      }
    }
  };
  const renderApproveDialog = () => {
    return /* @__PURE__ */ React.createElement(Dialog, { open: approveDialogOpen, onClose: closeApproveDialog }, /* @__PURE__ */ React.createElement(DialogTitle, null, "Aprobar y Asignar Espacio"), /* @__PURE__ */ React.createElement(DialogContent, null, /* @__PURE__ */ React.createElement(Typography, { variant: "body2", sx: { mb: 2 } }, "\xBFDeseas aprobar la reserva y asignar un espacio diferente al solicitado?"), /* @__PURE__ */ React.createElement(FormControl, { fullWidth: true, margin: "dense" }, /* @__PURE__ */ React.createElement(InputLabel, null, "Espacio Asignado"), /* @__PURE__ */ React.createElement(Select, { value: selectedSpaceId, label: "Espacio Asignado", onChange: (e) => setSelectedSpaceId(e.target.value) }, spaces.map((sp) => /* @__PURE__ */ React.createElement(MenuItem, { key: sp.esp_id, value: sp.esp_id }, `${sp.edificio} - ${sp.nombre_numero}`))))), /* @__PURE__ */ React.createElement(DialogActions, null, /* @__PURE__ */ React.createElement(Button, { onClick: closeApproveDialog }, "Regresar"), /* @__PURE__ */ React.createElement(Button, { onClick: submitApprove, color: "success", variant: "contained" }, "Confirmar Aprobaci\xF3n")));
  };
  return /* @__PURE__ */ React.createElement("div", { style: { marginTop: 10, fontFamily: "Outfit, sans-serif" } }, error && /* @__PURE__ */ React.createElement(Alert, { severity: "error", sx: { mb: 3 } }, error), /* @__PURE__ */ React.createElement(Paper, { elevation: 0, sx: { borderRadius: 3, overflow: "hidden", border: "1px solid #e2e8f0" } }, loading ? /* @__PURE__ */ React.createElement("div", { style: { padding: 60, textAlign: "center" } }, /* @__PURE__ */ React.createElement(CircularProgress, { sx: { color: "#3b82f6" } })) : /* @__PURE__ */ React.createElement(TableContainer, { sx: { maxHeight: 450 } }, /* @__PURE__ */ React.createElement(Table, { stickyHeader: true, sx: { minWidth: 650 } }, /* @__PURE__ */ React.createElement(TableHead, null, /* @__PURE__ */ React.createElement(TableRow, null, /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", whiteSpace: "nowrap" } }, "ID"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", whiteSpace: "nowrap" } }, "USUARIO"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", whiteSpace: "nowrap" } }, "ESPACIO"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", whiteSpace: "nowrap" } }, "FECHA Y HORA"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", whiteSpace: "nowrap" } }, "ESTADO"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#64748b", fontSize: "12px", textAlign: "center", whiteSpace: "nowrap" } }, "ACCIONES"))), /* @__PURE__ */ React.createElement(TableBody, null, reservations.length === 0 ? /* @__PURE__ */ React.createElement(TableRow, null, /* @__PURE__ */ React.createElement(TableCell, { colSpan: 6, align: "center", sx: { py: 5, color: "#94a3b8" } }, "No hay solicitudes pendientes")) : reservations.map((row) => /* @__PURE__ */ React.createElement(TableRow, { key: row.re_id, hover: true, sx: { "&:last-child td, &:last-child th": { border: 0 } } }, /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 800, color: "#94a3b8", whiteSpace: "nowrap" } }, "#", row.re_id), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 700, whiteSpace: "nowrap" } }, row.usuario_nombre || "Desconocido"), /* @__PURE__ */ React.createElement(TableCell, { sx: { fontWeight: 700, color: "#334155", whiteSpace: "nowrap" } }, row.espacio_nombre || "Desconocido"), /* @__PURE__ */ React.createElement(TableCell, { sx: { whiteSpace: "nowrap" } }, /* @__PURE__ */ React.createElement("div", { style: { fontWeight: 800 } }, row.fecha_uso), /* @__PURE__ */ React.createElement("div", { style: { fontSize: 12, color: "#64748b", fontWeight: 600 } }, row.hora_ent, " a ", row.hora_sal)), /* @__PURE__ */ React.createElement(TableCell, { sx: { whiteSpace: "nowrap" } }, row.status === "pending" && /* @__PURE__ */ React.createElement(Chip, { label: "PENDIENTE", size: "small", sx: { fontWeight: 800, bgcolor: "#fef3c7", color: "#d97706", borderRadius: 2 } }), row.status === "approved" && /* @__PURE__ */ React.createElement(Chip, { label: "APROBADA", size: "small", sx: { fontWeight: 800, bgcolor: "#dcfce3", color: "#10b981", borderRadius: 2 } }), row.status === "cancelled" && /* @__PURE__ */ React.createElement(Chip, { label: "CANCELADA", size: "small", sx: { fontWeight: 800, bgcolor: "#f1f5f9", color: "#64748b", borderRadius: 2 } }), row.status === "rejected" && /* @__PURE__ */ React.createElement(Chip, { label: "RECHAZADA", size: "small", sx: { fontWeight: 800, bgcolor: "#fee2e2", color: "#ef4444", borderRadius: 2 } })), /* @__PURE__ */ React.createElement(TableCell, { align: "center", sx: { whiteSpace: "nowrap" } }, renderRowAction(row)))))))), renderApproveDialog(), /* @__PURE__ */ React.createElement(Dialog, { open: rejectDialogOpen, onClose: closeRejectDialog }, /* @__PURE__ */ React.createElement(DialogTitle, null, "Rechazar Solicitud"), /* @__PURE__ */ React.createElement(DialogContent, null, /* @__PURE__ */ React.createElement(TextField, { autoFocus: true, margin: "dense", label: "Motivo de rechazo (opcional)", fullWidth: true, variant: "outlined", value: rejectReason, onChange: (e) => setRejectReason(e.target.value) })), /* @__PURE__ */ React.createElement(DialogActions, null, /* @__PURE__ */ React.createElement(Button, { onClick: closeRejectDialog }, "Cancelar"), /* @__PURE__ */ React.createElement(Button, { onClick: handleReject, color: "error", variant: "contained" }, "Confirmar Rechazo"))));
}
const root = ReactDOM.createRoot(document.getElementById("react-approval-app"));
root.render(/* @__PURE__ */ React.createElement(ReservationApprovalApp, null));

</script>
<?php include 'footer.php'; ?>
