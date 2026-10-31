import app from 'flarum/forum/app';
// Resolved at runtime through flarum.reg; only called behind a check that
// flarum/realtime is enabled, so it is harmless without it.
import RealtimeExtend from 'ext:flarum/realtime/forum/extenders/Realtime';

/**
 * A score counted anywhere arrives as `insertCoinScore`, carrying the game;
 * an open cabinet for that game reloads its board.
 */
export default function bindRealtime() {
  new RealtimeExtend()
    .onPublicChannelEvent('insertCoinScore', (payload: any) => {
      const id = Number(payload?.data?.id ?? payload?.id);
      if (id) window.dispatchEvent(new CustomEvent('insertcoin:score', { detail: { gameId: id } }));
    })
    .extend(app, { name: 'ernestdefoe-insert-coin', exports: {} } as any);
}
