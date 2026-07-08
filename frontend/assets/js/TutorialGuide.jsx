import React, { useState, useEffect, useRef } from 'react';
import { Box, Paper, Typography, Button, LinearProgress } from '@mui/material';
import ReactDOM from 'react-dom';

/**
 * TutorialGuide Component
 *
 * An interactive, step-by-step tutorial guide component built on React and Material UI.
 * Highlights page elements using a 4-panel backdrop cutout, preventing overlap between
 * the target element and the informational popover.
 *
 * Props:
 * @param {Array} steps - The tutorial steps definition. Each step is an object:
 *   { target: string, title: string, description: string, position: string }
 * @param {string} moduleId - Identifier of the module (e.g. 'dashboard'), used for localStorage key tutorialVisto_...
 * @param {boolean} open - Optional controlled open state.
 * @param {Function} onClose - Optional callback triggered when the tutorial is closed or skipped.
 * @param {Function} onComplete - Optional callback triggered when the tutorial finishes successfully.
 */
export default function TutorialGuide({ steps = [], moduleId, open, onClose, onComplete }) {
  if (!steps || steps.length === 0) return null;

  // Internal states
  const [internalOpen, setInternalOpen] = useState(false);
  const [activeStep, setActiveStep] = useState(0);
  const [boundingRect, setBoundingRect] = useState(null);
  const [popoverStyle, setPopoverStyle] = useState({ opacity: 0 });
  const [placement, setPlacement] = useState('bottom');
  const [windowSize, setWindowSize] = useState({ w: window.innerWidth, h: window.innerHeight });

  const popoverRef = useRef(null);

  // Controlled mode check
  const isControlled = open !== undefined;
  const isOpen = isControlled ? open : internalOpen;

  // CSS classes initialization
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

  // Helper to retrieve the next step index with a valid/existing element in DOM
  const getNextValidStep = (startIndex, direction = 1) => {
    let curr = startIndex;
    while (curr >= 0 && curr < steps.length) {
      const step = steps[curr];
      // If step has no target, it's a global overview step (always valid)
      if (!step.target) return curr;
      // If target exists in current DOM, return this step index
      const el = document.querySelector(step.target);
      if (el) return curr;
      // Move to next step
      curr += direction;
    }
    return -1;
  };

  // Auto-start on first load of the module (via localStorage)
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

  // Expose global manual triggers and callbacks
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

    // Provide a unified global hook for the sidebar drawer to trigger the current view's tutorial
    window.triggerTutorialGuide = handleTrigger;

    return () => {
      window.removeEventListener(`tutorial_guide_reset_${moduleId}`, handleReset);
      window.removeEventListener(`tutorial_guide_trigger_${moduleId}`, handleTrigger);
      if (window.triggerTutorialGuide === handleTrigger) {
        window.triggerTutorialGuide = null;
      }
    };
  }, [moduleId, steps]);

  // Notify header when tutorial is active to display / hide the Help Center button and topbar button dynamically
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

  // Positioning Engine: Coordinates calculation with collision checking
  const calculatePosition = () => {
    const step = steps[activeStep];
    if (!step || !isOpen) return;

    const el = step.target ? document.querySelector(step.target) : null;

    // 1. Center popover if no target element exists (global screen step)
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

    // 2. Measure target element bounding box
    const rect = el.getBoundingClientRect();
    setBoundingRect(rect);

    const popWidth = popoverRef.current ? popoverRef.current.offsetWidth : 320;
    const popHeight = popoverRef.current ? popoverRef.current.offsetHeight : 180;
    const margin = 16;
    const highlightPad = 6;
    const safetyMargin = 12;

    // Boundaries of highlighted gap
    const topCutout = rect.top - highlightPad;
    const bottomCutout = rect.bottom + highlightPad;
    const leftCutout = rect.left - highlightPad;
    const rightCutout = rect.right + highlightPad;

    const spaceAbove = topCutout - margin;
    const spaceBelow = window.innerHeight - bottomCutout - margin;
    const spaceLeft = leftCutout - margin;
    const spaceRight = window.innerWidth - rightCutout - margin;

    // Check if popover fits in a specific direction without clipping
    const fits = (pos) => {
      if (pos === 'top') return spaceAbove >= popHeight;
      if (pos === 'bottom') return spaceBelow >= popHeight;
      if (pos === 'left') return spaceLeft >= popWidth;
      if (pos === 'right') return spaceRight >= popWidth;
      return false;
    };

    const preferred = step.position || 'bottom';
    let chosen = preferred;

    // Fallback queue logic if preferred position overlaps or overflows
    if (!fits(chosen)) {
      const fallbackOrder = ['top', 'bottom', 'left', 'right'];
      const found = fallbackOrder.find(pos => pos !== chosen && fits(pos));
      if (found) {
        chosen = found;
      } else {
        // Choose the side with the maximum available space
        const spaceSizes = { top: spaceAbove, bottom: spaceBelow, left: spaceLeft, right: spaceRight };
        chosen = Object.keys(spaceSizes).reduce((a, b) => spaceSizes[a] > spaceSizes[b] ? a : b);
      }
    }

    let top = 0;
    let left = 0;

    // Compute coordinate positions based on chosen alignment
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

    // Clamp values to keep popover fully visible inside the viewport bounds
    left = Math.max(safetyMargin, Math.min(left, window.innerWidth - popWidth - safetyMargin));
    top = Math.max(safetyMargin, Math.min(top, window.innerHeight - popHeight - safetyMargin));

    // Responsive design override: on narrow viewports, position at top/bottom opposite to target center
    if (window.innerWidth < 600) {
      const targetCenterY = rect.top + rect.height / 2;
      left = (window.innerWidth - popWidth) / 2;
      if (targetCenterY > window.innerHeight / 2) {
        // Target is at the bottom: place popover near top
        top = safetyMargin + 70; // Offset to avoid covering top navbar
      } else {
        // Target is at the top: place popover near bottom
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

  // Handle step updates, scrolling, and resize listeners
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

    const triggerRecalc = () => {
      calculatePosition();
    };

    // Recalculate across multiple phases to account for smooth scroll delay
    triggerRecalc();
    const t1 = setTimeout(triggerRecalc, 50);
    const t2 = setTimeout(triggerRecalc, 150);
    const t3 = setTimeout(triggerRecalc, 450);

    window.addEventListener('resize', triggerRecalc);
    window.addEventListener('scroll', triggerRecalc, true);

    const handleResizeUpdate = () => {
      setWindowSize({ w: window.innerWidth, h: window.innerHeight });
      triggerRecalc();
    };
    window.addEventListener('resize', handleResizeUpdate);

    return () => {
      clearTimeout(t1);
      clearTimeout(t2);
      clearTimeout(t3);
      window.removeEventListener('resize', triggerRecalc);
      window.removeEventListener('scroll', triggerRecalc, true);
      window.removeEventListener('resize', handleResizeUpdate);
    };
  }, [activeStep, isOpen]);

  // Step traversals
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
    if (isControlled) {
      if (onComplete) onComplete();
      if (onClose) onClose();
    } else {
      setInternalOpen(false);
    }
  };

  if (!isOpen) return null;

  const currentStep = steps[activeStep];
  if (!currentStep) return null;

  // Dynamic coordinates for 4 backdrop cutout panels
  const pad = 6;
  const topY = Math.max(0, Math.min(windowSize.h, (boundingRect ? boundingRect.top : 0) - pad));
  const bottomY = Math.max(0, Math.min(windowSize.h, (boundingRect ? boundingRect.bottom : 0) + pad));
  const leftX = Math.max(0, Math.min(windowSize.w, (boundingRect ? boundingRect.left : 0) - pad));
  const rightX = Math.max(0, Math.min(windowSize.w, (boundingRect ? boundingRect.right : 0) + pad));

  const hasTarget = !!(currentStep.target && document.querySelector(currentStep.target) && boundingRect);

  const backdropBaseStyle = {
    position: 'fixed',
    backgroundColor: 'rgba(15, 23, 42, 0.72)', // slate-900 transparent
    backdropFilter: 'blur(1px)',
    zIndex: 100000,
    pointerEvents: 'auto',
    transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
  };

  return ReactDOM.createPortal(
    <Box>
      {/* 4-Panel Backdrop Masking to preserve element clickability */}
      {!hasTarget ? (
        // Global overlay without target
        <div
          style={{
            ...backdropBaseStyle,
            top: 0,
            left: 0,
            width: '100vw',
            height: '100vh',
          }}
          className="tutorial-guide-overlay-panel"
        />
      ) : (
        // Targeted overlay cutouts
        <React.Fragment>
          {/* Top Mask */}
          <div
            style={{
              ...backdropBaseStyle,
              top: 0,
              left: 0,
              width: '100vw',
              height: `${topY}px`,
            }}
            className="tutorial-guide-overlay-panel"
          />
          {/* Bottom Mask */}
          <div
            style={{
              ...backdropBaseStyle,
              top: `${bottomY}px`,
              left: 0,
              width: '100vw',
              height: `calc(100vh - ${bottomY}px)`,
            }}
            className="tutorial-guide-overlay-panel"
          />
          {/* Left Mask */}
          <div
            style={{
              ...backdropBaseStyle,
              top: `${topY}px`,
              left: 0,
              width: `${leftX}px`,
              height: `${bottomY - topY}px`,
            }}
            className="tutorial-guide-overlay-panel"
          />
          {/* Right Mask */}
          <div
            style={{
              ...backdropBaseStyle,
              top: `${topY}px`,
              left: `${rightX}px`,
              width: `calc(100vw - ${rightX}px)`,
              height: `${bottomY - topY}px`,
            }}
            className="tutorial-guide-overlay-panel"
          />
          {/* Pulsing Outline over element Cutout */}
          <div
            style={{
              position: 'fixed',
              top: `${topY}px`,
              left: `${leftX}px`,
              width: `${rightX - leftX}px`,
              height: `${bottomY - topY}px`,
              border: '3px solid #2563eb',
              borderRadius: '8px',
              pointerEvents: 'none',
              zIndex: 100001,
              boxShadow: '0 0 20px rgba(37, 99, 235, 0.5), inset 0 0 10px rgba(37, 99, 235, 0.25)',
              transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
            }}
            className="tutorial-guide-highlight-pulse"
          />
        </React.Fragment>
      )}

      {/* Info popover dialogue */}
      <Paper
        ref={popoverRef}
        elevation={8}
        style={popoverStyle}
        sx={{
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
        }}
      >
        {/* Header */}
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Typography
            variant="subtitle1"
            sx={{
              fontWeight: 800,
              color: 'var(--text-primary, #1e293b)',
              fontSize: '14.5px',
              fontFamily: 'Inter, sans-serif',
            }}
          >
            {currentStep.title}
          </Typography>
          <Typography
            variant="caption"
            sx={{
              fontWeight: 700,
              color: 'var(--text-muted, #94a3b8)',
              fontFamily: 'Inter, sans-serif',
            }}
          >
            {`${activeStep + 1} de ${steps.length}`}
          </Typography>
        </Box>

        {/* Progress Bar */}
        <LinearProgress
          variant="determinate"
          value={((activeStep + 1) / steps.length) * 100}
          sx={{
            height: '4px',
            borderRadius: '2px',
            backgroundColor: 'var(--border-color, #f1f5f9)',
            '& .MuiLinearProgress-bar': {
              backgroundColor: '#2563eb',
              borderRadius: '2px',
            },
          }}
        />

        {/* Description body */}
        <Typography
          variant="body2"
          sx={{
            color: 'var(--text-secondary, #64748b)',
            lineHeight: 1.6,
            fontSize: '13px',
            fontFamily: 'Inter, sans-serif',
            mt: 0.5,
            mb: 0.5,
          }}
          dangerouslySetInnerHTML={{ __html: currentStep.description }}
        />

        {/* Actions bar */}
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mt: 1 }}>
          <Button
            size="small"
            onClick={handleSkip}
            sx={{
              textTransform: 'none',
              fontWeight: 700,
              color: 'var(--text-secondary, #64748b)',
              fontSize: '12.5px',
              fontFamily: 'Inter, sans-serif',
              '&:hover': {
                backgroundColor: 'rgba(0, 0, 0, 0.04)',
              },
            }}
          >
            Omitir
          </Button>
          <Box sx={{ display: 'flex', gap: 1 }}>
            {activeStep > 0 && (
              <Button
                size="small"
                variant="outlined"
                onClick={handleBack}
                sx={{
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
                  },
                }}
              >
                Atrás
              </Button>
            )}
            <Button
              size="small"
              variant="contained"
              onClick={handleNext}
              sx={{
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
                },
              }}
            >
              {activeStep === steps.length - 1 ? 'Finalizar' : 'Siguiente'}
            </Button>
          </Box>
        </Box>
      </Paper>
    </Box>,
    document.body
  );
}
