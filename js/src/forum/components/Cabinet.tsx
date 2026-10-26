import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import { startSession, submitScore } from '../../common/api';
import type { GameDetail, Session, Submitted } from '../../common/api';
import { formatScore } from '../../common/format';
import Celebration from './Celebration';
import { t } from '../t';

export interface CabinetAttrs extends ComponentAttrs {
  game: GameDetail;
  /** A run ended: the board should reload. */
  onfinish: (result: Submitted | null) => void;
}

type State = 'idle' | 'starting' | 'playing' | 'over';

const MUTE_KEY = 'insertcoin-muted';
const CRT_KEY = 'insertcoin-crt';

function stored(key: string, fallback: boolean): boolean {
  try {
    const value = localStorage.getItem(key);
    return value === null ? fallback : value === '1';
  } catch (e) {
    return fallback;
  }
}

function store(key: string, on: boolean) {
  try {
    localStorage.setItem(key, on ? '1' : '0');
  } catch (e) {
    // Private mode: the switch still works for this visit.
  }
}

/**
 * The cabinet: the game in a framed screen, behind an Insert coin / Press
 * start overlay, with sound, theatre and full screen switches.
 *
 * 🚨 The game runs in a sandboxed iframe with no allow-same-origin, and the
 * session token never enters it: the cabinet holds the token and posts the
 * score itself. A message is listened to only when it comes from that
 * iframe's own window.
 */
export default class Cabinet extends Component<CabinetAttrs> {
  phase: State = 'idle';
  session: Session | null = null;
  lastScore: number | null = null;
  finalScore: number | null = null;
  result: Submitted | null = null;
  submitting = false;
  failed: string | null = null;
  muted = stored(MUTE_KEY, false);
  /** Scanlines and curved glass over the screen: the old cabinet look, on by default. */
  crt = stored(CRT_KEY, true);
  credits = 0;
  theatre = false;
  fullscreen = false;
  celebrate = false;
  toast: Mithril.Children = null;
  toastTimer = 0;
  frame?: HTMLIFrameElement;

  onMessage = (e: MessageEvent) => {
    if (!this.frame || e.source !== this.frame.contentWindow) return;
    const msg = e.data;
    if (!msg || msg.insertcoin !== 1) return;

    if (msg.type === 'ready') {
      this.post('init', { muted: this.muted });
    } else if (msg.type === 'score' && this.phase === 'playing') {
      this.lastScore = Number(msg.data?.score);
    } else if (msg.type === 'over' && this.phase === 'playing') {
      this.finish(msg.data && 'score' in msg.data ? Number(msg.data.score) : this.lastScore);
    }
  };

  onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && this.theatre) this.setTheatre(false);
  };

  onFullscreen = () => {
    this.fullscreen = document.fullscreenElement === this.screen();
    m.redraw();
  };

  oncreate(vnode: Mithril.VnodeDOM<CabinetAttrs, this>) {
    super.oncreate(vnode);
    window.addEventListener('message', this.onMessage);
    window.addEventListener('keydown', this.onKey);
    document.addEventListener('fullscreenchange', this.onFullscreen);
  }

  onremove() {
    window.removeEventListener('message', this.onMessage);
    window.removeEventListener('keydown', this.onKey);
    document.removeEventListener('fullscreenchange', this.onFullscreen);
    document.body.classList.remove('InsertCoin--theatre');
    clearTimeout(this.toastTimer);
  }

  post(type: string, data: object = {}) {
    // '*': the frame's origin is opaque, so there is no origin to name.
    this.frame?.contentWindow?.postMessage({ insertcoin: 1, type, data }, '*');
  }

  start() {
    if (this.phase === 'starting' || this.submitting) return;

    this.result = null;
    this.failed = null;
    this.lastScore = null;
    this.finalScore = null;
    this.celebrate = false;

    if (!this.attrs.game.canSubmit) return this.go();

    this.phase = 'starting';
    startSession(this.attrs.game.id).then(
      (session) => {
        this.session = session;
        this.go();
      },
      () => {
        this.phase = 'idle';
        m.redraw();
      }
    );
  }

  go() {
    this.credits++;
    this.phase = 'playing';
    this.post('start');
    this.frame?.focus();
    m.redraw();
  }

  finish(score: number | null) {
    const session = this.session;
    this.session = null;
    this.phase = 'over';
    this.finalScore = score !== null && isFinite(score) ? score : null;

    if (!session || this.finalScore === null) {
      this.attrs.onfinish(null);
      m.redraw();
      return;
    }

    this.submitting = true;
    submitScore(session, this.finalScore).then(
      (result) => {
        this.result = result;
        this.submitting = false;
        if (result.crown) this.celebrate = true;
        else if (result.personalBest) this.showToast(t('cabinet.personal_best'));
        this.attrs.onfinish(result);
        m.redraw();
      },
      (error: any) => {
        this.submitting = false;
        // The server says why (too short, over the ceiling…): say it here, where the player is looking.
        this.failed = error?.response?.errors?.[0]?.detail || extractText(t('cabinet.not_saved'));
        m.redraw();
      }
    );
    m.redraw();
  }

  showToast(text: Mithril.Children) {
    this.toast = text;
    clearTimeout(this.toastTimer);
    this.toastTimer = window.setTimeout(() => {
      this.toast = null;
      m.redraw();
    }, 4000);
  }

  toggleMute() {
    this.muted = !this.muted;
    store(MUTE_KEY, this.muted);
    this.post('mute', { muted: this.muted });
  }

  setTheatre(on: boolean) {
    this.theatre = on;
    document.body.classList.toggle('InsertCoin--theatre', on);
  }

  screen(): HTMLElement | null {
    return this.element?.querySelector('.InsertCoinScreen') || null;
  }

  toggleFullscreen() {
    const el = this.screen();
    if (!el) return;

    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else if (el.requestFullscreen) {
      el.requestFullscreen().catch(() => this.setTheatre(true));
    } else {
      // iPhone Safari has no element full screen: theatre fills the viewport instead.
      this.setTheatre(!this.theatre);
    }
    this.frame?.focus();
  }

  view() {
    const game = this.attrs.game;
    const src = game.frameUrl || game.externalUrl;
    const ratio = `${game.width} / ${game.height}`;

    return (
      <div className={classList('InsertCoinCabinet', { 'is-theatre': this.theatre, 'is-playing': this.phase === 'playing' })}>
        {this.theatre ? <div className="InsertCoinTheatre-backdrop" onclick={() => this.setTheatre(false)} /> : null}
        <div
          className={classList('InsertCoinScreen', { 'InsertCoinScreen--fluid': game.source === 'bundled', 'InsertCoinScreen--crt': this.crt })}
          style={{ '--ic-ratio': ratio, '--ic-w': String(game.width), '--ic-h': String(game.height) }}
        >
          {src ? (
            <iframe
              className="InsertCoinScreen-frame"
              src={src}
              title={game.title}
              // 🚨 Never allow-same-origin for our own frames: a game from a
              // pack must not run with the forum's origin. A game on another
              // site keeps its own origin, which is not ours.
              sandbox={game.frameUrl ? 'allow-scripts allow-pointer-lock' : 'allow-scripts allow-same-origin allow-pointer-lock'}
              allow="autoplay; fullscreen; gamepad"
              referrerpolicy="no-referrer"
              oncreate={(v: Mithril.VnodeDOM) => (this.frame = v.dom as HTMLIFrameElement)}
            />
          ) : null}
          {this.phase !== 'playing' && !this.celebrate ? this.overlay() : null}
          {this.toast ? (
            <div className="InsertCoinToast" role="status">
              <Icon name="fas fa-star" /> {this.toast}
            </div>
          ) : null}
          {this.celebrate && this.result ? (
            <Celebration
              champion={app.session.user ? this.me() : null}
              previous={this.result.previous}
              score={formatScore(this.result.score, game.scoreLabel)}
              ondone={() => {
                this.celebrate = false;
                m.redraw();
              }}
            />
          ) : null}
        </div>

        <div className="InsertCoinCabinet-controls">
          <Button className="Button Button--flat" icon={this.muted ? 'fas fa-volume-xmark' : 'fas fa-volume-high'} onclick={() => this.toggleMute()}>
            {t(this.muted ? 'cabinet.unmute' : 'cabinet.mute')}
          </Button>
          <Button
            className={'Button Button--flat' + (this.crt ? ' active' : '')}
            icon="fas fa-tv"
            aria-pressed={this.crt}
            onclick={() => {
              this.crt = !this.crt;
              store(CRT_KEY, this.crt);
            }}
          >
            {t('cabinet.crt')}
          </Button>
          <Button
            className={'Button Button--flat' + (this.theatre ? ' active' : '')}
            icon="fas fa-film"
            onclick={() => this.setTheatre(!this.theatre)}
          >
            {t('cabinet.theatre')}
          </Button>
          <Button
            className="Button Button--flat"
            icon={this.fullscreen ? 'fas fa-compress' : 'fas fa-expand'}
            onclick={() => this.toggleFullscreen()}
          >
            {t(this.fullscreen ? 'cabinet.exit_fullscreen' : 'cabinet.fullscreen')}
          </Button>
          {this.phase === 'playing' ? (
            <Button className="Button Button--flat InsertCoinCabinet-quit" icon="fas fa-power-off" onclick={() => this.quit()}>
              {t('cabinet.quit')}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  /** Give up on a run: reload the game, nothing is submitted. */
  quit() {
    this.session = null;
    this.phase = 'idle';
    if (this.frame) this.frame.src = this.frame.src;
  }

  me() {
    const user = app.session.user!;
    return {
      id: Number(user.id()),
      username: user.username(),
      displayName: user.displayName(),
      avatarUrl: user.avatarUrl() || null,
      slug: user.slug(),
      crowns: 0,
    };
  }

  overlay(): Mithril.Children {
    const game = this.attrs.game;

    if (this.phase === 'starting') {
      return (
        <div className="InsertCoinOverlay">
          <LoadingIndicator />
        </div>
      );
    }

    if (this.phase === 'over') {
      const r = this.result;

      return (
        <div className="InsertCoinOverlay InsertCoinOverlay--over">
          <div className="InsertCoinOverlay-marquee">{t('cabinet.game_over')}</div>
          {this.finalScore !== null ? <div className="InsertCoinOverlay-score">{formatScore(this.finalScore, game.scoreLabel)}</div> : null}
          <div className="InsertCoinOverlay-lines">
            {this.submitting ? <LoadingIndicator size="small" /> : null}
            {r?.crown ? (
              <p className="InsertCoinOverlay-line InsertCoinOverlay-line--crown">
                <Icon name="fas fa-crown" /> {t('cabinet.you_are_champion')}
              </p>
            ) : null}
            {r?.personalBest && !r.crown ? (
              <p className="InsertCoinOverlay-line InsertCoinOverlay-line--best">
                <Icon name="fas fa-star" /> {t('cabinet.personal_best')}
              </p>
            ) : null}
            {r && r.status !== 'approved' ? (
              <p className="InsertCoinOverlay-line">{t(r.status === 'pending' ? 'cabinet.held_crown' : 'cabinet.held_review')}</p>
            ) : null}
            {r?.me ? (
              <p className="InsertCoinOverlay-line">{t('cabinet.rank', { rank: r.me.rank, best: formatScore(r.me.score, game.scoreLabel) })}</p>
            ) : null}
            {this.failed ? <p className="InsertCoinOverlay-line InsertCoinOverlay-line--error">{this.failed}</p> : null}
            {!game.canSubmit ? <p className="InsertCoinOverlay-line">{this.hint()}</p> : null}
          </div>
          <Button
            className="Button Button--primary InsertCoinOverlay-start"
            icon="fas fa-rotate-right"
            onclick={() => this.start()}
            disabled={this.submitting}
          >
            {t('cabinet.play_again')}
          </Button>
        </div>
      );
    }

    const champion = game.champion;

    return (
      <div className="InsertCoinOverlay">
        <div className="InsertCoinOverlay-hiscore">
          <span>{t('cabinet.hi_score')}</span>
          <b>{champion ? formatScore(champion.score, null, false) : '—'}</b>
          {champion?.user ? <span>{champion.user.displayName}</span> : null}
        </div>
        <div className="InsertCoinOverlay-marquee InsertCoinOverlay-marquee--blink">{t('cabinet.insert_coin')}</div>
        <div className="InsertCoinOverlay-title">{game.title}</div>
        <Button className="Button Button--primary InsertCoinOverlay-start" icon="fas fa-play" onclick={() => this.start()}>
          {t('cabinet.press_start')}
        </Button>
        {!game.canSubmit ? <p className="InsertCoinOverlay-line">{this.hint()}</p> : null}
        {!game.mobile ? (
          <p className="InsertCoinOverlay-line InsertCoinOverlay-line--keys">
            <Icon name="fas fa-keyboard" /> {t('cabinet.keyboard')}
          </p>
        ) : null}
        <div className="InsertCoinOverlay-credit">{t('cabinet.credit', { count: String(this.credits).padStart(2, '0') })}</div>
      </div>
    );
  }

  hint(): Mithril.Children {
    if (!app.session.user) {
      return (
        <Button className="Button Button--link" onclick={() => app.modal.show(() => import('flarum/forum/components/LogInModal'))}>
          {t('cabinet.log_in')}
        </Button>
      );
    }

    return extractText(t(this.attrs.game.banned ? 'cabinet.banned' : 'cabinet.no_permission'));
  }
}
