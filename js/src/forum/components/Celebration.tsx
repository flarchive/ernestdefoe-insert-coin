import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import type Mithril from 'mithril';
import type { Person } from '../../common/api';
import { avatar } from './Person';
import { t } from '../t';

export interface CelebrationAttrs extends ComponentAttrs {
  champion: Person | null;
  previous: Person | null;
  score: Mithril.Children;
  ondone: () => void;
}

/**
 * "New champion!": the crown drops onto the winner's avatar and confetti
 * falls. With reduced motion asked for, it is the same card, still.
 */
export default class Celebration extends Component<CelebrationAttrs> {
  frame = 0;
  timer = 0;

  oncreate(vnode: Mithril.VnodeDOM<CelebrationAttrs, this>) {
    super.oncreate(vnode);
    this.timer = window.setTimeout(() => this.attrs.ondone(), 6000);

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) this.confetti(vnode.dom.querySelector('canvas')!);
  }

  onremove() {
    cancelAnimationFrame(this.frame);
    clearTimeout(this.timer);
  }

  view() {
    return (
      <div className="InsertCoinCelebration" role="status" onclick={() => this.attrs.ondone()}>
        <canvas className="InsertCoinCelebration-confetti" aria-hidden="true" />
        <div className="InsertCoinCelebration-card">
          <div className="InsertCoinCelebration-avatar">
            <Icon name="fas fa-crown" className="InsertCoinCelebration-crown" />
            {avatar(this.attrs.champion)}
          </div>
          <div className="InsertCoinCelebration-title">{t('cabinet.new_champion')}</div>
          <div className="InsertCoinCelebration-score">{this.attrs.score}</div>
          {this.attrs.previous ? (
            <div className="InsertCoinCelebration-sub">{t('cabinet.dethroned', { player: this.attrs.previous.displayName })}</div>
          ) : null}
        </div>
      </div>
    );
  }

  confetti(canvas: HTMLCanvasElement) {
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dpr = Math.min(2, window.devicePixelRatio || 1);
    const w = (canvas.width = canvas.offsetWidth * dpr);
    const h = (canvas.height = canvas.offsetHeight * dpr);
    const colors = ['#facc15', '#f472b6', '#38bdf8', '#4ade80', '#fb923c', '#a78bfa'];
    const bits = Array.from({ length: 120 }, () => ({
      x: Math.random() * w,
      y: -Math.random() * h * 0.6,
      vx: (Math.random() - 0.5) * 2 * dpr,
      vy: (2 + Math.random() * 3) * dpr,
      r: Math.random() * Math.PI,
      vr: (Math.random() - 0.5) * 0.3,
      s: (4 + Math.random() * 5) * dpr,
      c: colors[Math.floor(Math.random() * colors.length)],
    }));
    const started = performance.now();

    const tick = (now: number) => {
      ctx.clearRect(0, 0, w, h);
      ctx.globalAlpha = Math.max(0, 1 - (now - started) / 5500);
      for (const b of bits) {
        b.x += b.vx + Math.sin((now + b.s * 100) / 400) * 0.6 * dpr;
        b.y += b.vy;
        b.r += b.vr;
        ctx.save();
        ctx.translate(b.x, b.y);
        ctx.rotate(b.r);
        ctx.fillStyle = b.c;
        ctx.fillRect(-b.s / 2, -b.s / 4, b.s, b.s / 2);
        ctx.restore();
      }
      if (now - started < 5500) this.frame = requestAnimationFrame(tick);
    };

    this.frame = requestAnimationFrame(tick);
  }
}
