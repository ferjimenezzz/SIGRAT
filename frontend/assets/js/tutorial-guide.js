/**
 * @file tutorial-guide.js
 * @summary Componente de recorrido guiado interactivo reutilizable para React y Material UI.
 * @description Crea un tour animado con un Backdrop de 4 paneles para resaltar elementos,
 *              posicionamiento inteligente para evitar colisiones y superposiciones,
 *              soporte móvil y persistencia en localStorage.
 */

(function () {
  const { useState, useEffect, useRef } = React;
  const { Box, Paper, Typography, Button, LinearProgress, IconButton } = MaterialUI;
  const h = React.createElement;

  function TutorialGuide({ steps = [], moduleId, open, onClose, onComplete }) {
    if (!steps || steps.length === 0) return null;

    // Estados internos
    const [internalOpen, setInternalOpen] = useState(false);
    const [activeStep, setActiveStep] = useState(0);
    const [boundingRect, setBoundingRect] = useState(null);
    const [popoverStyle, setPopoverStyle] = useState({ opacity: 0 });
    const [placement, setPlacement] = useState('bottom');
    const [windowSize, setWindowSize] = useState({ w: window.innerWidth, h: window.innerHeight });

    const popoverRef = useRef(null);

    // Modo controlado vs in-controlado
    const isControlled = open !== undefined;
    const isOpen = isControlled ? open : internalOpen;

    // Inicializar estilos CSS al montar
    useEffect(() => {
      if (document.getElementById('tutorial-guide-styles')) return;
      const styleEl = document.createElement('style');
      styleEl.id = 'tutorial-guide-styles';
      styleEl.innerHTML = `
        @keyframes tutorial-guide-pulse {
          0% {
            box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7), inset 0 0 0 0 rgba(37, 99, 235, 0.3);
          }
          70% {
            box-shadow: 0 0 0 10px rgba(37, 99, 235, 0), inset 0 0 8px rgba(37, 99, 235, 0);
          }
          100% {
            box-shadow: 0 0 0 0 rgba(37, 99, 235, 0), inset 0 0 0 0 rgba(37, 99, 235, 0);
          }
        }
        .tutorial-guide-highlight-pulse {
          animation: tutorial-guide-pulse 2s infinite ease-in-out;
        }
        .tutorial-guide-overlay-panel {
          pointer-events: auto !important;
        }
      `;
      document.head.appendChild(styleEl);
    }, []);

    // Encontrar siguiente/anterior paso válido (omite elementos inexistentes en el DOM)
    const getNextValidStep = (startIndex, direction = 1) => {
      let curr = startIndex;
      while (curr >= 0 && curr < steps.length) {
        const step = steps[curr];
        // Si no tiene target, es un paso global (modal flotante) que siempre es válido
        if (!step.target) return curr;
        // Si el selector existe en la vista actual, es válido
        const el = document.querySelector(step.target);
        if (el) return curr;
        curr += direction;
      }
      return -1;
    };

    // Verificar si ya se completó el tour la primera vez
    useEffect(() => {
      if (!isControlled && moduleId) {
        const key = `tutorialVisto_${moduleId}`;
        const hasSeen = localStorage.getItem(key);
        if (hasSeen !== 'true') {
          const firstValid = getNextValidStep(0, 1);
          if (firstValid !== -1) {
            setActiveStep(firstValid);
            setInternalOpen(true);
          }
        }
      }
    }, [moduleId, isControlled, steps]);

    // Escuchadores de eventos para reinicio manual y trigger global
    useEffect(() => {
      const handleReset = () => {
        if (moduleId) {
          localStorage.removeItem(`tutorialVisto_${moduleId}`);
        }
        const firstValid = getNextValidStep(0, 1);
        if (firstValid !== -1) {
          setActiveStep(firstValid);
          setInternalOpen(true);
        }
      };

      const handleTrigger = () => {
        const firstValid = getNextValidStep(0, 1);
        if (firstValid !== -1) {
          setActiveStep(firstValid);
          setInternalOpen(true);
        }
      };

      window.addEventListener(`tutorial_guide_reset_${moduleId}`, handleReset);
      window.addEventListener(`tutorial_guide_trigger_${moduleId}`, handleTrigger);

      // Proveer hook global para el botón de ayuda general
      window.triggerTutorialGuide = handleTrigger;

      return () => {
        window.removeEventListener(`tutorial_guide_reset_${moduleId}`, handleReset);
        window.removeEventListener(`tutorial_guide_trigger_${moduleId}`, handleTrigger);
        if (window.triggerTutorialGuide === handleTrigger) {
          window.triggerTutorialGuide = null;
        }
      };
    }, [moduleId, steps]);

    // Mostrar/ocultar los botones de recorrido guiado en la interfaz
    useEffect(() => {
      const hcTourBtn = document.getElementById('hcTourBtn');
      const topbarTourBtn = document.getElementById('topbarTourBtn');
      if (hcTourBtn) {
        hcTourBtn.style.display = 'flex';
      }
      if (topbarTourBtn) {
        topbarTourBtn.style.display = 'flex';
      }
      // Ocultar los botones cuando el componente se desmonte
      return () => {
        const btn = document.getElementById('hcTourBtn');
        if (btn) {
          btn.style.display = 'none';
        }
        const tBtn = document.getElementById('topbarTourBtn');
        if (tBtn) {
          tBtn.style.display = 'none';
        }
      };
    }, []);

    // Recalcular posiciones del elemento resaltado y el cuadro informativo
    const calculatePosition = () => {
      const step = steps[activeStep];
      if (!step || !isOpen) return;

      const el = step.target ? document.querySelector(step.target) : null;

      // 1. Centrar cuadro si no hay elemento objetivo
      if (!el) {
        setBoundingRect(null);
        const wWidth = window.innerWidth;
        const wHeight = window.innerHeight;
        const popWidth = popoverRef.current ? popoverRef.current.offsetWidth : 320;
        const popHeight = popoverRef.current ? popoverRef.current.offsetHeight : 180;

        setPopoverStyle({
          position: 'fixed',
          top: `${(wHeight - popHeight) / 2}px`,
          left: `${(wWidth - popWidth) / 2}px`,
          zIndex: 100002,
          opacity: 1,
          transition: 'all 0.3s ease-in-out',
        });
        setPlacement('center');
        return;
      }

      // 2. Medir elemento objetivo
      const rect = el.getBoundingClientRect();
      setBoundingRect(rect);

      const popWidth = popoverRef.current ? popoverRef.current.offsetWidth : 320;
      const popHeight = popoverRef.current ? popoverRef.current.offsetHeight : 180;
      const margin = 16;
      const highlightPad = 6;
      const safetyMargin = 12;

      // Coordenadas con padding de recorte
      const topCutout = rect.top - highlightPad;
      const bottomCutout = rect.bottom + highlightPad;
      const leftCutout = rect.left - highlightPad;
      const rightCutout = rect.right + highlightPad;

      const spaceAbove = topCutout - margin;
      const spaceBelow = window.innerHeight - bottomCutout - margin;
      const spaceLeft = leftCutout - margin;
      const spaceRight = window.innerWidth - rightCutout - margin;

      const preferred = step.position || 'bottom';
      let chosen = preferred;

      const fits = (pos) => {
        if (pos === 'top') return spaceAbove >= popHeight;
        if (pos === 'bottom') return spaceBelow >= popHeight;
        if (pos === 'left') return spaceLeft >= popWidth;
        if (pos === 'right') return spaceRight >= popWidth;
        return false;
      };

      // Si no cabe en la posición preferida, buscar la primera alternativa que quepa
      if (!fits(chosen)) {
        const fallbackOrder = ['top', 'bottom', 'left', 'right'];
        const found = fallbackOrder.find(p => p !== chosen && fits(p));
        if (found) {
          chosen = found;
        } else {
          // Usar el espacio con mayor dimensión disponible
          const spaces = { top: spaceAbove, bottom: spaceBelow, left: spaceLeft, right: spaceRight };
          chosen = Object.keys(spaces).reduce((a, b) => spaces[a] > spaces[b] ? a : b);
        }
      }

      let top = 0;
      let left = 0;

      if (chosen === 'bottom') {
        top = bottomCutout + margin;
        left = leftCutout + (rect.width + 2 * highlightPad - popWidth) / 2;
      } else if (chosen === 'top') {
        top = topCutout - margin - popHeight;
        left = leftCutout + (rect.width + 2 * highlightPad - popWidth) / 2;
      } else if (chosen === 'right') {
        top = topCutout + (rect.height + 2 * highlightPad - popHeight) / 2;
        left = rightCutout + margin;
      } else if (chosen === 'left') {
        top = topCutout + (rect.height + 2 * highlightPad - popHeight) / 2;
        left = leftCutout - margin - popWidth;
      }

      // Restricción para mantener el popover siempre dentro del viewport
      left = Math.max(safetyMargin, Math.min(left, window.innerWidth - popWidth - safetyMargin));
      top = Math.max(safetyMargin, Math.min(top, window.innerHeight - popHeight - safetyMargin));

      // Sobrescribir si es pantalla móvil estrecha (ancho < 600px) para evitar colisión vertical
      if (window.innerWidth < 600) {
        const targetCenterY = rect.top + rect.height / 2;
        left = (window.innerWidth - popWidth) / 2;
        if (targetCenterY > window.innerHeight / 2) {
          // El objetivo está en la mitad inferior: colocar popover arriba
          top = safetyMargin + 70; // Espaciado para librar la cabecera móvil
        } else {
          // El objetivo está en la mitad superior: colocar popover abajo
          top = window.innerHeight - popHeight - safetyMargin;
        }
      }

      setPopoverStyle({
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
        zIndex: 100002,
        opacity: 1,
        transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
      });
      setPlacement(chosen);
    };

    // Auto-scroll y actualización al cambiar de paso o redimensionar
    useEffect(() => {
      if (!isOpen) return;

      const step = steps[activeStep];
      if (step) {
        if (step.actionSelectorClick) {
          const clickEl = document.querySelector(step.actionSelectorClick);
          if (clickEl) {
            clickEl.click();
          }
        }
        if (step.target) {
          const el = document.querySelector(step.target);
          if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
      }

      const handleRecalc = () => {
        calculatePosition();
      };

      // Carga multi-fase para mitigar efectos de desplazamiento
      handleRecalc();
      const t1 = setTimeout(handleRecalc, 50);
      const t2 = setTimeout(handleRecalc, 150);
      const t3 = setTimeout(handleRecalc, 450);

      window.addEventListener('resize', handleRecalc);
      window.addEventListener('scroll', handleRecalc, true);

      const handleResize = () => {
        setWindowSize({ w: window.innerWidth, h: window.innerHeight });
        handleRecalc();
      };
      window.addEventListener('resize', handleResize);

      return () => {
        clearTimeout(t1);
        clearTimeout(t2);
        clearTimeout(t3);
        window.removeEventListener('resize', handleRecalc);
        window.removeEventListener('scroll', handleRecalc, true);
        window.removeEventListener('resize', handleResize);
      };
    }, [activeStep, isOpen]);

    const handleNext = () => {
      const nextIdx = getNextValidStep(activeStep + 1, 1);
      if (nextIdx === -1 || nextIdx >= steps.length) {
        handleFinish();
      } else {
        setActiveStep(nextIdx);
      }
    };

    const handleBack = () => {
      const prevIdx = getNextValidStep(activeStep - 1, -1);
      if (prevIdx !== -1 && prevIdx >= 0) {
        setActiveStep(prevIdx);
      }
    };

    const handleSkip = () => {
      handleFinish();
    };

    const handleFinish = () => {
      if (moduleId) {
        localStorage.setItem(`tutorialVisto_${moduleId}`, 'true');
      }
      if (onComplete) onComplete();
      if (onClose) onClose();
      if (!isControlled) {
        setInternalOpen(false);
      }
    };

    if (!isOpen) return null;

    const currentStep = steps[activeStep];
    if (!currentStep) return null;

    const pad = 6;
    const topY = Math.max(0, Math.min(windowSize.h, (boundingRect ? boundingRect.top : 0) - pad));
    const bottomY = Math.max(0, Math.min(windowSize.h, (boundingRect ? boundingRect.bottom : 0) + pad));
    const leftX = Math.max(0, Math.min(windowSize.w, (boundingRect ? boundingRect.left : 0) - pad));
    const rightX = Math.max(0, Math.min(windowSize.w, (boundingRect ? boundingRect.right : 0) + pad));

    const hasTarget = !!(currentStep.target && document.querySelector(currentStep.target) && boundingRect);

    const backdropBaseStyle = {
      position: 'fixed',
      backgroundColor: 'rgba(15, 23, 42, 0.72)', // Slate oscuro
      backdropFilter: 'blur(1px)',
      zIndex: 100000,
      pointerEvents: 'auto',
      transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
    };

    return ReactDOM.createPortal(
      h(Box, null,
        // BACKDROP PANEL CON RECORTE MULTI-DIV
        !hasTarget ? (
          h('div', {
            style: {
              ...backdropBaseStyle,
              top: 0,
              left: 0,
              width: '100vw',
              height: '100vh',
            },
            className: 'tutorial-guide-overlay-panel'
          })
        ) : (
          h(React.Fragment, null,
            // Panel Superior
            h('div', {
              style: {
                ...backdropBaseStyle,
                top: 0,
                left: 0,
                width: '100vw',
                height: `${topY}px`,
              },
              className: 'tutorial-guide-overlay-panel'
            }),
            // Panel Inferior
            h('div', {
              style: {
                ...backdropBaseStyle,
                top: `${bottomY}px`,
                left: 0,
                width: '100vw',
                height: `calc(100vh - ${bottomY}px)`,
              },
              className: 'tutorial-guide-overlay-panel'
            }),
            // Panel Izquierdo
            h('div', {
              style: {
                ...backdropBaseStyle,
                top: `${topY}px`,
                left: 0,
                width: `${leftX}px`,
                height: `${bottomY - topY}px`,
              },
              className: 'tutorial-guide-overlay-panel'
            }),
            // Panel Derecho
            h('div', {
              style: {
                ...backdropBaseStyle,
                top: `${topY}px`,
                left: `${rightX}px`,
                width: `calc(100vw - ${rightX}px)`,
                height: `${bottomY - topY}px`,
              },
              className: 'tutorial-guide-overlay-panel'
            }),
            // CUADRO DE ENFOQUE Y RESALTADO
            h('div', {
              style: {
                position: 'fixed',
                top: `${topY}px`,
                left: `${leftX}px`,
                width: `${rightX - leftX}px`,
                height: `${bottomY - topY}px`,
                border: '3px solid #2563eb', // Azul SIGRAT
                borderRadius: '8px',
                pointerEvents: 'none',
                zIndex: 100001,
                boxShadow: '0 0 20px rgba(37, 99, 235, 0.5), inset 0 0 10px rgba(37, 99, 235, 0.25)',
                transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
              },
              className: 'tutorial-guide-highlight-pulse'
            })
          )
        ),

        // TARJETA DE DIALOGO/TOOLTIP INFORMATIVO
        h(Paper, {
          ref: popoverRef,
          elevation: 8,
          style: popoverStyle,
          sx: {
            p: 2.5,
            position: 'relative',
            boxSizing: 'border-box',
            display: 'flex',
            flexDirection: 'column',
            gap: 1.5,
            minWidth: '290px',
            maxWidth: '340px',
            borderRadius: '16px',
            backgroundColor: 'var(--card-bg, #ffffff)',
            color: 'var(--text-primary, #1e293b)',
            border: '1px solid var(--border-color, #e2e8f0)',
            boxShadow: '0 12px 30px -5px rgba(0,0,0,0.15), 0 8px 10px -7px rgba(0,0,0,0.1)',
          }
        },
          // Botón Cerrar (X)
          h(IconButton, {
            onClick: handleSkip,
            sx: {
              position: 'absolute',
              right: 8,
              top: 8,
              color: 'var(--text-secondary, #64748b)',
              '&:hover': {
                color: 'var(--text-primary, #1e293b)',
              }
            }
          }, h('i', { className: 'bi bi-x-lg', style: { fontSize: '13px' } })),
          // Cabecera del Tooltip
          h(Box, { sx: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
            h(Typography, {
              variant: 'subtitle1',
              sx: {
                fontWeight: 800,
                color: 'var(--text-primary, #1e293b)',
                fontSize: '14.5px',
                fontFamily: 'Inter, sans-serif'
              }
            }, currentStep.title),
            h(Typography, {
              variant: 'caption',
              sx: {
                fontWeight: 700,
                color: 'var(--text-muted, #94a3b8)',
                fontFamily: 'Inter, sans-serif',
                mr: 3
              }
            }, `${activeStep + 1} de ${steps.length}`)
          ),
          // Barra de Progreso Lineal
          h(LinearProgress, {
            variant: 'determinate',
            value: ((activeStep + 1) / steps.length) * 100,
            sx: {
              height: '4px',
              borderRadius: '2px',
              backgroundColor: 'var(--border-color, #f1f5f9)',
              '& .MuiLinearProgress-bar': {
                backgroundColor: '#2563eb',
                borderRadius: '2px',
              }
            }
          }),
          // Cuerpo informativo (HTML Seguro)
          h(Typography, {
            variant: 'body2',
            sx: {
              color: 'var(--text-secondary, #64748b)',
              lineHeight: 1.6,
              fontSize: '13px',
              fontFamily: 'Inter, sans-serif',
              mt: 0.5,
              mb: 0.5
            },
            dangerouslySetInnerHTML: { __html: currentStep.description }
          }),
          // Acciones / Footer
          h(Box, { sx: { display: 'flex', justifyContent: 'flex-end', alignItems: 'center', mt: 1 } },
            h(Box, { sx: { display: 'flex', gap: 1 } },
              activeStep > 0 && h(Button, {
                size: 'small',
                variant: 'outlined',
                onClick: handleBack,
                sx: {
                  textTransform: 'none',
                  fontWeight: 800,
                  borderRadius: '8px',
                  fontSize: '12.5px',
                  fontFamily: 'Inter, sans-serif',
                  borderColor: 'var(--border-color, #e2e8f0)',
                  color: 'var(--text-secondary, #64748b)',
                  '&:hover': {
                    borderColor: 'var(--text-muted, #94a3b8)',
                    backgroundColor: 'rgba(0, 0, 0, 0.02)',
                  }
                }
              }, 'Atrás'),
              h(Button, {
                size: 'small',
                variant: 'contained',
                onClick: handleNext,
                sx: {
                  textTransform: 'none',
                  fontWeight: 800,
                  borderRadius: '8px',
                  fontSize: '12.5px',
                  fontFamily: 'Inter, sans-serif',
                  backgroundColor: '#2563eb',
                  boxShadow: 'none',
                  '&:hover': {
                    backgroundColor: '#1d4ed8',
                    boxShadow: 'none',
                  }
                }
              }, activeStep === steps.length - 1 ? 'Finalizar' : 'Siguiente')
            )
          )
        )
      ),
      document.body
    );
  }

  // Exportar el componente globalmente
  window.TutorialGuide = TutorialGuide;
})();
