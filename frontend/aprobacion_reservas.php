<?php
/**
 * @file aprobacion_reservas.php
 * @summary Módulo independiente para la gestión y aprobación de reservas.
 */
require_once 'seguridad.php';
include 'header.php';
?>

<div style="display: flex; flex-direction: column; gap: 24px;">
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; margin-bottom: 4px;">Aprobaciones de Reservas</h1>
            <p style="font-size: 13px; color: #64748b; font-weight: 500;">Gestión de solicitudes pendientes de aprobación</p>
        </div>
    </header>

    <div id="react-approval-app"></div>
</div>

<!-- React Dependencies for Aprobaciones -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>

<script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script React original adaptado -->
<script>
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
  Alert
} = MaterialUI;
function ReservationApprovalApp() {
  const [reservations, setReservations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
  const [selectedReservation, setSelectedReservation] = useState(null);
  const [rejectReason, setRejectReason] = useState("");
  const [actionLoading, setActionLoading] = useState(false);
  const fetchReservations = async () => {
    setLoading(true);
    try {
      const response = await fetch('../backend/api/index.php/reservations/pending', {
        credentials: 'same-origin'
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
  }, []);
  const handleApprove = async id => {
    const result = await Swal.fire({
      title: 'Confirmar Aprobación',
      text: 'Se enviará una notificación al usuario.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10b981',
      cancelButtonColor: '#94a3b8',
      confirmButtonText: 'Sí, aprobar',
      cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return;
    setActionLoading(true);
    try {
      const response = await fetch(`../backend/api/index.php/reservations/${id}/approve`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        }
      });
      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || "Error al aprobar");
      }
      await Swal.fire({
        icon: 'success',
        title: '¡Aprobada!',
        text: 'Reserva aprobada exitosamente.',
        timer: 2000,
        showConfirmButton: false
      });
      await fetchReservations();
    } catch (err) {
      Swal.fire('Error', err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };
  const openRejectDialog = reservation => {
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
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
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
        icon: 'success',
        title: 'Rechazada',
        text: 'Reserva rechazada exitosamente.',
        timer: 2000,
        showConfirmButton: false
      });
      closeRejectDialog();
      await fetchReservations();
    } catch (err) {
      Swal.fire('Error', err.message, 'error');
    } finally {
      setActionLoading(false);
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 10,
      fontFamily: 'Outfit, sans-serif'
    }
  }, error && /*#__PURE__*/React.createElement(Alert, {
    severity: "error",
    sx: {
      mb: 3
    }
  }, error), /*#__PURE__*/React.createElement(Paper, {
    elevation: 0,
    sx: {
      borderRadius: 3,
      overflow: "hidden",
      border: '1px solid #e2e8f0'
    }
  }, loading ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 60,
      textAlign: "center"
    }
  }, /*#__PURE__*/React.createElement(CircularProgress, {
    sx: {
      color: '#3b82f6'
    }
  })) : /*#__PURE__*/React.createElement(TableContainer, null, /*#__PURE__*/React.createElement(Table, null, /*#__PURE__*/React.createElement(TableHead, {
    sx: {
      bgcolor: "#f8fafc"
    }
  }, /*#__PURE__*/React.createElement(TableRow, null, /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 800,
      color: "#475569",
      fontSize: '0.85rem'
    }
  }, "ID"), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 800,
      color: "#475569",
      fontSize: '0.85rem'
    }
  }, "Solicitante"), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 800,
      color: "#475569",
      fontSize: '0.85rem'
    }
  }, "Espacio"), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 800,
      color: "#475569",
      fontSize: '0.85rem'
    }
  }, "Horario"), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 800,
      color: "#475569",
      fontSize: '0.85rem'
    }
  }, "Estado"), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 800,
      color: "#475569",
      fontSize: '0.85rem'
    },
    align: "center"
  }, "Acciones"))), /*#__PURE__*/React.createElement(TableBody, null, reservations.length === 0 ? /*#__PURE__*/React.createElement(TableRow, null, /*#__PURE__*/React.createElement(TableCell, {
    colSpan: 6,
    align: "center",
    sx: {
      py: 6,
      color: "#94a3b8",
      fontWeight: 700
    }
  }, "No hay reservas registradas en este momento.")) : reservations.map(row => /*#__PURE__*/React.createElement(TableRow, {
    key: row.re_id,
    hover: true
  }, /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 700,
      color: '#64748b'
    }
  }, "#", row.re_id), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 700
    }
  }, row.usuario_nombre || 'Desconocido'), /*#__PURE__*/React.createElement(TableCell, {
    sx: {
      fontWeight: 700,
      color: '#334155'
    }
  }, row.espacio_nombre || 'Desconocido'), /*#__PURE__*/React.createElement(TableCell, null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 800
    }
  }, row.fecha_uso), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 12,
      color: "#64748b",
      fontWeight: 600
    }
  }, row.hora_ent, " a ", row.hora_sal)), /*#__PURE__*/React.createElement(TableCell, null, row.status === 'pending' && /*#__PURE__*/React.createElement(Chip, {
    label: "PENDIENTE",
    size: "small",
    sx: {
      fontWeight: 800,
      bgcolor: '#fef3c7',
      color: '#d97706',
      borderRadius: 2
    }
  }), row.status === 'approved' && /*#__PURE__*/React.createElement(Chip, {
    label: "APROBADA",
    size: "small",
    sx: {
      fontWeight: 800,
      bgcolor: '#dcfce3',
      color: '#10b981',
      borderRadius: 2
    }
  }), row.status === 'rejected' && /*#__PURE__*/React.createElement(Chip, {
    label: "RECHAZADA",
    size: "small",
    sx: {
      fontWeight: 800,
      bgcolor: '#fee2e2',
      color: '#ef4444',
      borderRadius: 2
    }
  })), /*#__PURE__*/React.createElement(TableCell, {
    align: "center"
  }, row.status === 'pending' ? /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(Button, {
    variant: "contained",
    size: "small",
    sx: {
      mr: 1,
      fontWeight: 800,
      borderRadius: 2,
      bgcolor: '#10b981',
      boxShadow: 'none'
    },
    onClick: () => handleApprove(row.re_id),
    disabled: actionLoading
  }, "Aprobar"), /*#__PURE__*/React.createElement(Button, {
    variant: "outlined",
    color: "error",
    size: "small",
    sx: {
      fontWeight: 800,
      borderRadius: 2
    },
    onClick: () => openRejectDialog(row),
    disabled: actionLoading
  }, "Rechazar")) : /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: '11px',
      color: '#94a3b8',
      fontWeight: 700
    }
  }, "Procesada")))))))), /*#__PURE__*/React.createElement(Dialog, {
    open: rejectDialogOpen,
    onClose: closeRejectDialog
  }, /*#__PURE__*/React.createElement(DialogTitle, null, "Rechazar Solicitud"), /*#__PURE__*/React.createElement(DialogContent, null, /*#__PURE__*/React.createElement(TextField, {
    autoFocus: true,
    margin: "dense",
    label: "Motivo de rechazo (opcional)",
    fullWidth: true,
    variant: "outlined",
    value: rejectReason,
    onChange: e => setRejectReason(e.target.value)
  })), /*#__PURE__*/React.createElement(DialogActions, null, /*#__PURE__*/React.createElement(Button, {
    onClick: closeRejectDialog
  }, "Cancelar"), /*#__PURE__*/React.createElement(Button, {
    onClick: handleReject,
    color: "error",
    variant: "contained"
  }, "Confirmar Rechazo"))));
}
const root = ReactDOM.createRoot(document.getElementById('react-approval-app'));
root.render(/*#__PURE__*/React.createElement(ReservationApprovalApp, null));
</script>

<?php include 'footer.php'; ?>
