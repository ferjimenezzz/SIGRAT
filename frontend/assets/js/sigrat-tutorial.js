/**
 * @file sigrat-tutorial.js
 * @summary Componente de recorrido guiado interactivo reutilizable para React y Material UI.
 * @description Crea un tour animado con un Backdrop de 4 paneles para resaltar elementos,
 *              posicionamiento inteligente para evitar colisiones y superposiciones,
 *              soporte móvil y persistencia en localStorage.
 */

(function () {
  const { useState, useEffect, useRef } = React;
  const { Box, Paper, Typography, Button, LinearProgress } = MaterialUI;
  const h = React.createElement;

  // Inyectar estilos CSS obligatorios para las animaciones y efectos visuales
  const injectStyles = () => {
    if (document.getElementById('sigrat-tutorial-styles')) return;
    const styleEl = document.createElement('style');
    styleEl.id = 'sigrat-tutorial-styles';
    styleEl.innerHTML = `
      @keyframes sigrat-tutorial-pulse {
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
      .sigrat-tutorial-highlight-pulse {
        animation: sigrat-tutorial-pulse 2s infinite ease-in-out;
      }
      .sigrat-tutorial-overlay-panel {
        pointer-events: auto !important;
      }
    `;
    document.head.appendChild(styleEl);
  };

  function SigratTutorial({ steps = [], moduleId, open, onClose, onComplete }) {
    if (!steps || steps.length === 0) return null;

    // Inicializar estilos CSS al montar
    useEffect(() => {
      injectStyles();
    }, []);

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

    // Encontrar siguiente/anterior paso válido (omite elementos inexistentes)
    const getNextValidStep = (index, direction = 1) => {
      let curr = index;
      while (curr >= 0 && curr < steps.length) {
        const step = steps[curr];
        if (!step.selector) return curr; // Bienvenido o pasos globales son siempre válidos
        const el = document.querySelector(step.selector);
        if (el) return curr; // El elemento existe en el DOM actual
        curr += direction;
      }
      return -1;
    };

    // Verificar si ya se completó el tour la primera vez
    useEffect(() => {
      if (!isControlled && moduleId) {
        const completed = localStorage.getItem(`sigrat_tour_completed_${moduleId}`);
        if (completed !== 'true') {
          const firstValid = getNextValidStep(0, 1);
          if (firstValid !== -1) {
            setActiveStep(firstValid);
            setInternalOpen(true);
          }
        }
      }
    }, [moduleId, isControlled]);

    // Escuchador de reinicio manual mediante evento personalizado de ventana
    useEffect(() => {
      const handleReset = () => {
        if (moduleId) {
          localStorage.removeItem(`sigrat_tour_completed_${moduleId}`);
        }
        const firstValid = getNextValidStep(0, 1);
        if (firstValid !== -1) {
          setActiveStep(firstValid);
          if (isControlled && onClose) {
            onClose(false);
          }
          setInternalOpen(true);
        }
      };

      window.addEventListener(`sigrat_tour_reset_${moduleId}`, handleReset);
      return () => {
        window.removeEventListener(`sigrat_tour_reset_${moduleId}`, handleReset);
      };
    }, [moduleId, isControlled, steps]);

    // Recalcular dimensiones y posiciones del elemento activo y el popover
    const calculatePosition = () => {
      const step = steps[activeStep];
      if (!step || !isOpen) return;

      const el = step.selector ? document.querySelector(step.selector) : null;

      // Si no hay elemento o no existe, centrar como modal
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

      // Si el elemento existe, medir su bounding rect
      const rect = el.getBoundingClientRect();
      setBoundingRect(rect);

      const popWidth = popoverRef.current ? popoverRef.current.offsetWidth : 320;
      const popHeight = popoverRef.current ? popoverRef.current.offsetHeight : 180;
      const margin = 14;
      const pad = 6;

      const spaceAbove = rect.top - pad - margin;
      const spaceBelow = window.innerHeight - (rect.bottom + pad + margin);
      const spaceLeft = rect.left - pad - margin;
      const spaceRight = window.innerWidth - (rect.right + pad + margin);

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
        const fallbackOrder = ['bottom', 'top', 'right', 'left'];
        const found = fallbackOrder.find(p => p !== chosen && fits(p));
        if (found) {
          chosen = found;
        } else {
          // Si no cabe en ninguna, usar la dirección con mayor espacio disponible
          const spaces = { top: spaceAbove, bottom: spaceBelow, left: spaceLeft, right: spaceRight };
          chosen = Object.keys(spaces).reduce((a, b) => spaces[a] > spaces[b] ? a : b);
        }
      }

      let top = 0;
      let left = 0;

      if (chosen === 'bottom') {
        top = rect.bottom + pad + margin;
        left = rect.left - pad + (rect.width + 2 * pad - popWidth) / 2;
      } else if (chosen === 'top') {
        top = rect.top - pad - margin - popHeight;
        left = rect.left - pad + (rect.width + 2 * pad - popWidth) / 2;
      } else if (chosen === 'right') {
        top = rect.top - pad + (rect.height + 2 * pad - popHeight) / 2;
        left = rect.right + pad + margin;
      } else if (chosen === 'left') {
        top = rect.top - pad + (rect.height + 2 * pad - popHeight) / 2;
        left = rect.left - pad - margin - popWidth;
      }

      // Restricción para mantener el popover siempre dentro del viewport (Mobile & Desktop)
      const screenPadding = 12;
      left = Math.max(screenPadding, Math.min(left, window.innerWidth - popWidth - screenPadding));
      top = Math.max(screenPadding, Math.min(top, window.innerHeight - popHeight - screenPadding));

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

    // Auto-scroll y actualización de posición al cambiar de paso o al redimensionar/desplazar
    useEffect(() => {
      if (isOpen) {
        const step = steps[activeStep];
        if (step && step.selector) {
          const el = document.querySelector(step.selector);
          if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }

        const handleRecalc = () => {
          calculatePosition();
        };

        // Recalcular posiciones en varias fases para mitigar retardos de desplazamiento fluido
        handleRecalc();
        const t1 = setTimeout(handleRecalc, 50);
        const t2 = setTimeout(handleRecalc, 150);
        const t3 = setTimeout(handleRecalc, 400);

        window.addEventListener('resize', handleRecalc);
        window.addEventListener('scroll', handleRecalc, true); // capture = true para contenedores intermedios

        // Guardar dimensiones de pantalla para recalcular backdrop
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
      }
    }, [activeStep, isOpen]);

    // Acciones de los botones
    const handleNext = () => {
      const nextIndex = getNextValidStep(activeStep + 1, 1);
      if (nextIndex === -1 || nextIndex >= steps.length) {
        handleFinish();
      } else {
        setActiveStep(nextIndex);
      }
    };

    const handleBack = () => {
      const prevIndex = getNextValidStep(activeStep - 1, -1);
      if (prevIndex !== -1 && prevIndex >= 0) {
        setActiveStep(prevIndex);
      }
    };

    const handleSkip = () => {
      handleFinish();
    };

    const handleFinish = () => {
      if (moduleId) {
        localStorage.setItem(`sigrat_tour_completed_${moduleId}`, 'true');
      }
      if (isControlled) {
        if (onComplete) onComplete();
        if (onClose) onClose();
      } else {
        setInternalOpen(false);
      }
    };

    if (!isOpen) return null;

    const step = steps[activeStep];
    if (!step) return null;

    // Calcular las coordenadas del recorte del Backdrop de 4 paneles
    const pad = 6;
    const topY = Math.max(0, Math.min(windowSize.h, (boundingRect ? boundingRect.top : 0) - pad));
    const bottomY = Math.max(0, Math.min(windowSize.h, (boundingRect ? boundingRect.bottom : 0) + pad));
    const leftX = Math.max(0, Math.min(windowSize.w, (boundingRect ? boundingRect.left : 0) - pad));
    const rightX = Math.max(0, Math.min(windowSize.w, (boundingRect ? boundingRect.right : 0) + pad));

    const hasTarget = !!(step.selector && document.querySelector(step.selector) && boundingRect);

    const backdropCommon = {
      position: 'fixed',
      backgroundColor: 'rgba(15, 23, 42, 0.72)', // Slate oscuro
      backdropFilter: 'blur(1px)',
      zIndex: 100000,
      pointerEvents: 'auto',
      transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
    };

    // Renderizado usando ReactDOM Portal al final del body para evitar conflictos de apilamiento
    return ReactDOM.createPortal(
      h(Box, null,
        // BACKDROP PANEL CON RECORTE MULTI-DIV
        !hasTarget ? (
          // Paso global sin objetivo: Backdrop completo de una pieza
          h('div', {
            style: {
              ...backdropCommon,
              top: 0,
              left: 0,
              width: '100vw',
              height: '100vh',
            },
            className: 'sigrat-tutorial-overlay-panel'
          })
        ) : (
          // Paso con objetivo: 4 paneles que enmarcan el elemento
          h(React.Fragment, null,
            // Panel Superior
            h('div', {
              style: {
                ...backdropCommon,
                top: 0,
                left: 0,
                width: '100vw',
                height: `${topY}px`,
              },
              className: 'sigrat-tutorial-overlay-panel'
            }),
            // Panel Inferior
            h('div', {
              style: {
                ...backdropCommon,
                top: `${bottomY}px`,
                left: 0,
                width: '100vw',
                height: `calc(100vh - ${bottomY}px)`,
              },
              className: 'sigrat-tutorial-overlay-panel'
            }),
            // Panel Izquierdo
            h('div', {
              style: {
                ...backdropCommon,
                top: `${topY}px`,
                left: 0,
                width: `${leftX}px`,
                height: `${bottomY - topY}px`,
              },
              className: 'sigrat-tutorial-overlay-panel'
            }),
            // Panel Derecho
            h('div', {
              style: {
                ...backdropCommon,
                top: `${topY}px`,
                left: `${rightX}px`,
                width: `calc(100vw - ${rightX}px)`,
                height: `${bottomY - topY}px`,
              },
              className: 'sigrat-tutorial-overlay-panel'
            }),
            // CUADRO DE ENFOQUE Y RESALTADO (Sitúa por encima del Backdrop)
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
                boxShadow: '0 0 20px rgba(37, 99, 235, 0.6), inset 0 0 10px rgba(37, 99, 235, 0.3)',
                transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
              },
              className: 'sigrat-tutorial-highlight-pulse'
            })
          )
        ),

        // TARJETA DE DIALOGO/TOOLTIP INFORMATIVO
        h(Paper, {
          ref: popoverRef,
          elevation: 6,
          style: popoverStyle,
          sx: {
            p: 2.5,
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
          }, step.title),
          h(Typography, {
            variant: 'caption',
            sx: {
              fontWeight: 700,
              color: 'var(--text-muted, #94a3b8)',
              fontFamily: 'Inter, sans-serif'
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
        // Cuerpo informativo
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
          dangerouslySetInnerHTML: { __html: step.content }
        }),
        // Acciones / Footer
        h(Box, { sx: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 1 } },
          h(Button, {
            size: 'small',
            onClick: handleSkip,
            sx: {
              textTransform: 'none',
              fontWeight: 700,
              color: 'var(--text-secondary, #64748b)',
              fontSize: '12.5px',
              fontFamily: 'Inter, sans-serif',
              '&:hover': {
                backgroundColor: 'rgba(0, 0, 0, 0.04)',
              }
            }
          }, 'Saltar'),
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

  // Exportación del componente de forma global
  window.SigratTutorial = SigratTutorial;
})();
