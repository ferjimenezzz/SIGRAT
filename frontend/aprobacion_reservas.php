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
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
<script src="https://unpkg.com/@mui/material@5/umd/material-ui.production.min.js" crossorigin></script>

<!-- Script React original adaptado -->
<script type="text/babel">
    const { useState, useEffect } = React;
    const { Container, Typography, Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Button, Chip, Dialog, DialogTitle, DialogContent, DialogActions, TextField, CircularProgress, Alert } = MaterialUI;

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
                const response = await fetch('../backend/api/index.php/reservations/pending', { credentials: 'same-origin' });
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

        useEffect(() => { fetchReservations(); }, []);

        const handleApprove = async (id) => {
            if(!confirm("¿Seguro que deseas APROBAR esta reserva?")) return;
            setActionLoading(true);
            try {
                const response = await fetch(`../backend/api/index.php/reservations/${id}/approve`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (!response.ok) throw new Error("Error al aprobar");
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

        const handleReject = async () => {
            if (!selectedReservation) return;
            setActionLoading(true);
            try {
                const response = await fetch(`../backend/api/index.php/reservations/${selectedReservation.re_id}/reject`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason: rejectReason })
                });
                if (!response.ok) throw new Error("Error al rechazar");
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
            <div style={{ marginTop: 10, fontFamily: 'Outfit, sans-serif' }}>
                {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}
                <Paper elevation={0} sx={{ borderRadius: 3, overflow: "hidden", border: '1px solid #e2e8f0' }}>
                    {loading ? (
                        <div style={{ padding: 60, textAlign: "center" }}>
                            <CircularProgress sx={{ color: '#3b82f6' }} />
                        </div>
                    ) : (
                        <TableContainer>
                            <Table>
                                <TableHead sx={{ bgcolor: "#f8fafc" }}>
                                    <TableRow>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>ID</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Solicitante</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Espacio</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Horario</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }}>Estado</TableCell>
                                        <TableCell sx={{ fontWeight: 800, color: "#475569", fontSize: '0.85rem' }} align="center">Acciones</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {reservations.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} align="center" sx={{ py: 6, color: "#94a3b8", fontWeight: 700 }}>
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
                                                    <Button variant="contained" size="small" sx={{ mr: 1, fontWeight: 800, borderRadius: 2, bgcolor: '#10b981', boxShadow: 'none' }} onClick={() => handleApprove(row.re_id)} disabled={actionLoading}>Aprobar</Button>
                                                    <Button variant="outlined" color="error" size="small" sx={{ fontWeight: 800, borderRadius: 2 }} onClick={() => openRejectDialog(row)} disabled={actionLoading}>Rechazar</Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </TableContainer>
                    )}
                </Paper>

                <Dialog open={rejectDialogOpen} onClose={closeRejectDialog}>
                    <DialogTitle>Rechazar Solicitud</DialogTitle>
                    <DialogContent>
                        <TextField autoFocus margin="dense" label="Motivo de rechazo (opcional)" fullWidth variant="outlined" value={rejectReason} onChange={(e) => setRejectReason(e.target.value)} />
                    </DialogContent>
                    <DialogActions>
                        <Button onClick={closeRejectDialog}>Cancelar</Button>
                        <Button onClick={handleReject} color="error" variant="contained">Confirmar Rechazo</Button>
                    </DialogActions>
                </Dialog>
            </div>
        );
    }
    const root = ReactDOM.createRoot(document.getElementById('react-approval-app'));
    root.render(<ReservationApprovalApp />);
</script>

<?php include 'footer.php'; ?>
