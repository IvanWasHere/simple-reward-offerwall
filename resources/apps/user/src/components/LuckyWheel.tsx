import { useEffect, useRef, useState } from 'react';
import { useApiData } from '../hooks/useApiData';
import { useAuth } from '../hooks/useAuth';
import { api, ApiError } from '../lib/api';
import { toast } from '../store/toast';
import { Loading } from './States';

interface WheelSegment {
  label: string;
  coins: number;
}
interface WheelState {
  segments: WheelSegment[];
  canSpin: boolean;
  lastSpin: { coins: number } | null;
  nextSpinAt: string | null;
}

function cssVar(name: string, fallback: string): string {
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

function drawWheel(canvas: HTMLCanvasElement, rotation: number, segments: WheelSegment[]) {
  const ctx = canvas.getContext('2d');
  if (!ctx || segments.length === 0) return;
  const size = 280;
  const dpr = window.devicePixelRatio || 1;
  canvas.width = size * dpr;
  canvas.height = size * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

  const cx = size / 2;
  const cy = size / 2;
  const r = size / 2 - 4;
  const segAngle = (2 * Math.PI) / segments.length;
  const accent = cssVar('--accent', '#00b67a');
  const altColor = cssVar('--wheel-seg-alt', '#003e60');
  const textOnAccent = cssVar('--wheel-text-on-accent', '#ffffff');
  const textOnAlt = cssVar('--wheel-text-on-alt', '#00b67a');

  ctx.clearRect(0, 0, size, size);
  ctx.save();
  ctx.beginPath();
  ctx.arc(cx, cy, r + 2, 0, 2 * Math.PI);
  ctx.strokeStyle = cssVar('--highlight', '#ff2d6c');
  ctx.lineWidth = 3;
  ctx.stroke();
  ctx.restore();

  segments.forEach((seg, i) => {
    const startA = rotation + i * segAngle - Math.PI / 2;
    const endA = startA + segAngle;
    const isAlt = i % 2 === 1;

    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, r, startA, endA);
    ctx.closePath();
    ctx.fillStyle = isAlt ? altColor : accent;
    ctx.fill();

    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(cx + r * Math.cos(startA), cy + r * Math.sin(startA));
    ctx.strokeStyle = 'rgba(255,255,255,0.15)';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    const textAngle = startA + segAngle / 2;
    const textR = r * 0.65;
    ctx.save();
    ctx.translate(cx + textR * Math.cos(textAngle), cy + textR * Math.sin(textAngle));
    ctx.rotate(textAngle + Math.PI / 2);
    ctx.fillStyle = isAlt ? textOnAlt : textOnAccent;
    ctx.font = 'bold ' + (seg.coins >= 100 ? 14 : 16) + 'px "Space Grotesk", sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(seg.label, 0, 0);
    ctx.restore();
  });
}

export function LuckyWheel() {
  const { refreshBalance } = useAuth();
  const { data, loading, refetch } = useApiData<WheelState>('/wheel');
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const rotationRef = useRef(0);
  const [spinning, setSpinning] = useState(false);
  const [result, setResult] = useState<number | null>(null);

  const segments = data?.segments ?? [];

  useEffect(() => {
    if (canvasRef.current && segments.length) {
      drawWheel(canvasRef.current, rotationRef.current, segments);
    }
  }, [segments]);

  const animateTo = (targetRotation: number) =>
    new Promise<void>((resolve) => {
      const start = rotationRef.current;
      const duration = 4000;
      let startTs: number | null = null;
      const step = (ts: number) => {
        if (startTs === null) startTs = ts;
        const p = Math.min((ts - startTs) / duration, 1);
        rotationRef.current = start + (targetRotation - start) * (1 - Math.pow(1 - p, 3));
        if (canvasRef.current) drawWheel(canvasRef.current, rotationRef.current, segments);
        if (p < 1) requestAnimationFrame(step);
        else resolve();
      };
      requestAnimationFrame(step);
    });

  const spin = async () => {
    if (spinning || !data?.canSpin || segments.length === 0) return;
    setSpinning(true);
    setResult(null);
    try {
      const res = await api<{ index: number; coins: number }>('/wheel/spin', { method: 'POST' });
      const segAngleDeg = 360 / segments.length;
      const targetCenter = res.index * segAngleDeg + segAngleDeg / 2;
      const spins = 5;
      const targetDeg =
        (rotationRef.current * 180) / Math.PI + spins * 360 + (360 - targetCenter);
      await animateTo((targetDeg * Math.PI) / 180);
      rotationRef.current = (((targetDeg % 360) + 360) % 360) * (Math.PI / 180);
      setResult(res.coins);
      toast(`You won ${res.coins} coins!`, 'success');
      refreshBalance();
      refetch();
    } catch (e) {
      toast(e instanceof ApiError ? e.message : 'Spin failed.', 'error');
    } finally {
      setSpinning(false);
    }
  };

  if (loading) return <Loading label="Loading wheel…" />;

  const alreadySpun = !data?.canSpin;
  const nextSpin = data?.nextSpinAt ? new Date(data.nextSpinAt) : null;
  const nextSpinLabel =
    nextSpin && !isNaN(nextSpin.getTime())
      ? nextSpin.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
      : null;

  return (
    <div className="wheel-container">
      <div className="wheel-wrapper">
        <div className="wheel-pointer" />
        <canvas ref={canvasRef} className="wheel-canvas" width={280} height={280} />
        <div className="wheel-center">SPIN</div>
      </div>
      <button className="spin-btn" disabled={spinning || alreadySpun} onClick={spin}>
        {spinning
          ? 'Spinning…'
          : alreadySpun
            ? nextSpinLabel
              ? `Next spin ${nextSpinLabel}`
              : 'Come back later'
            : 'Spin the Wheel'}
      </button>
      {result !== null && !spinning && (
        <div className="spin-result">
          You won <strong style={{ color: 'var(--accent)' }}>+{result} coins</strong>!
        </div>
      )}
      {result === null && alreadySpun && data?.lastSpin && (
        <div className="spin-result">
          You won <strong style={{ color: 'var(--accent)' }}>+{data.lastSpin.coins} coins</strong> this week — one spin every 7 days.
        </div>
      )}
    </div>
  );
}
