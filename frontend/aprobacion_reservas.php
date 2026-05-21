<?php
/**
 * @file aprobacion_reservas.php
 * @summary Módulo de Aprobación de Reservas.
 * @description Interfaz administrativa para aceptar o rechazar solicitudes de reservas, usando React y Material-UI (MUI).
 */
session_start();
if (!isset($_SESSION['us_id'])) {
    header("Location: login.php");
    exit;
}
include 'header.php';
?>

<!-- Dependencias de React, ReactDOM, Babel y Material-UI -->
<script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>
<script src="https://unpkg.com/lucide@latest"></script>

<!-- Contenedor donde se montará la App de React -->
<div id="react-approval-app"></div>

<script type="text/babel">
    const { useState, useEffect } = React;
    const {
        Container, Typography, Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
        Button, Chip, Dialog, DialogTitle, DialogContent, DialogActions, TextField, CircularProgress, Alert
    } = MaterialUI;

    /**
     * @function ReservationApprovalApp
     * @summary Componente principal para gestionar reservas pendientes.
     * @return {JSX.Element}
     */
    function ReservationApprovalApp() {
        const [reservations, setReservations] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);
        
        const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
        const [selectedReservation, setSelectedReservation] = useState(null);
        const [rejectReason, setRejectReason] = useState("");
        const [actionLoading, setActionLoading] = useState(false);

        // Fetch de las reservas pendientes
        const fetchReservations = async () => {
            setLoading(true);
            try {
                // Llamada a nuestro nuevo endpoint
                const response = await fetch('../backend/api/index.php/reservations/pending');
                if (!response.ok) {
                    throw new Error("Error al obtener las reservas pendientes del servidor.");
                }
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

        /**
         * @function handleApprove
         * @param {number} id ID de la reserva a aprobar
         */
        const handleApprove = async (id) => {
            if(!confirm("¿Seguro que deseas APROBAR esta reserva?")) return;
            setActionLoading(true);
            try {
                const response = await fetch(`../backend/api/index.php/reservations/${id}/approve`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || "Error al aprobar la reserva");
                }
                
                alert("Reserva aprobada exitosamente.");
                fetchReservations();
            } catch (err) {
                alert(err.message);
            } finally {
                setActionLoading(false);
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

        /**
         * @function handleReject
         * @summary Envía la solicitud para rechazar una reserva con motivo opcional.
         */
        const handleReject = async () => {
            if (!selectedReservation) return;
            setActionLoading(true);
            try {
                const response = await fetch(`../backend/api/index.php/reservations/${selectedReservation.re_id}/reject`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason: rejectReason })
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || "Error al rechazar la reserva");
                }
                
                alert("Reserva rechazada exitosamente.");
                closeRejectDialog();
                fetchReservations();
            } catch (err) {
                alert(err.message);
            } finally {
                setActionLoading(false);
            }
        };

        return (
            <Container maxWidth="lg" sx={{ mt: 5, mb: 5, fontFamily: 'Outfit, sans-serif' }}>
                <Typography variant="h4" gutterBottom sx={{ fontWeight: 900, color: "#1e293b", display: 'flex', alignItems: 'center', gap: 2 }}>
                    <i data-lucide="check-square"></i> Aprobación de Reservas
                </Typography>
                
                <Typography variant="body1" sx={{ color: "#64748b", mb: 4 }}>
                    Revisa y gestiona las solicitudes de espacio pendientes de aprobación.
                </Typography>
                
                {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}
                
                <Paper elevation={2} sx={{ borderRadius: 3, overflow: "hidden", border: '1px solid #e2e8f0' }}>
                    {loading ? (
                        <div style={{ padding: 60, textAlign: "center" }}>
                            <CircularProgress sx={{ color: '#3b82f6' }} />
                        </div>
                    ) : (
                        <TableContainer>
                            <Table>
                                <TableHead sx={{ bgcolor: "#f8fafc" }}>
                                    <TableRow>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem', textTransform: 'uppercase' }}>ID</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem', textTransform: 'uppercase' }}>Solicitante</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem', textTransform: 'uppercase' }}>Espacio</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem', textTransform: 'uppercase' }}>Horario</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem', textTransform: 'uppercase' }}>Estado</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem', textTransform: 'uppercase' }} align="center">Acciones</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {reservations.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} align="center" sx={{ py: 6, color: "#94a3b8", fontWeight: 700 }}>
                                                <i data-lucide="inbox" style={{ width: 48, height: 48, opacity: 0.5, marginBottom: 12, display: 'block', margin: '0 auto' }}></i>
                                                No hay reservas pendientes en este momento.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        reservations.map((row) => (
                                            <TableRow key={row.re_id} hover>
                                                <TableCell sx={{ fontWeight: 700, color: '#64748b' }}>#{row.re_id}</TableCell>
                                                <TableCell sx={{ fontWeight: 700 }}>{row.usuario_nombre || 'Desconocido'}</TableCell>
                                                <TableCell sx={{ fontWeight: 700, color: '#334155' }}>{row.espacio_nombre || 'Desconocido'}</TableCell>
                                                <TableCell>
                                                    <div style={{ fontWeight: 800 }}>{row.fecha_uso}</div>
                                                    <div style={{ fontSize: 12, color: "#64748b", fontWeight: 600 }}>{row.hora_ent} a {row.hora_sal}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <Chip label="PENDIENTE" size="small" sx={{ fontWeight: 800, bgcolor: '#fef3c7', color: '#d97706', borderRadius: 2 }} />
                                                </TableCell>
                                                <TableCell align="center">
                                                    <Button 
                                                        variant="contained" 
                                                        size="small" 
                                                        sx={{ mr: 1, fontWeight: 800, borderRadius: 2, bgcolor: '#10b981', '&:hover': { bgcolor: '#059669' }, boxShadow: 'none' }}
                                                        onClick={() => handleApprove(row.re_id)}
                                                        disabled={actionLoading}
                                                    >
                                                        Aprobar
                                                    </Button>
                                                    <Button 
                                                        variant="outlined" 
                                                        color="error" 
                                                        size="small"
                                                        sx={{ fontWeight: 800, borderRadius: 2, borderWidth: 2, '&:hover': { borderWidth: 2 } }}
                                                        onClick={() => openRejectDialog(row)}
                                                        disabled={actionLoading}
                                                    >
                                                        Rechazar
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    )}
                </Paper>

                <Dialog open={rejectDialogOpen} onClose={closeRejectDialog} PaperProps={{ sx: { borderRadius: 3, p: 1 } }}>
                    <DialogTitle sx={{ fontWeight: 900, color: '#0f172a' }}>Rechazar Solicitud</DialogTitle>
                    <DialogContent>
                        <Typography sx={{ mb: 3, color: '#475569' }}>
                            Por favor ingresa un motivo para rechazar la reserva de <strong>{selectedReservation?.usuario_nombre}</strong>. Este motivo quedará registrado en bitácora.
                        </Typography>
                        <TextField
                            autoFocus
                            margin="dense"
                            label="Motivo de rechazo (opcional)"
                            type="text"
                            fullWidth
                            variant="outlined"
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            sx={{ '& .MuiOutlinedInput-root': { borderRadius: 2 } }}
                        />
                    </DialogContent>
                    <DialogActions sx={{ p: 2, pt: 0 }}>
                        <Button onClick={closeRejectDialog} sx={{ fontWeight: 800, color: '#64748b' }}>Cancelar</Button>
                        <Button onClick={handleReject} color="error" variant="contained" disabled={actionLoading} sx={{ fontWeight: 800, borderRadius: 2, boxShadow: 'none' }}>
                            Confirmar Rechazo
                        </Button>
                    </DialogActions>
                </Dialog>
            </Container>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('react-approval-app'));
    root.render(<ReservationApprovalApp />);
    
    // Inicializar íconos
    setTimeout(() => {
        if(window.lucide) window.lucide.createIcons();
    }, 500);
</script>

<?php include 'footer.php'; ?>
