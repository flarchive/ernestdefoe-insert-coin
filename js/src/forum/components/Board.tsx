import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { ban, board, wipeGame } from '../../common/api';
import type { Board as BoardData, GameDetail, Period } from '../../common/api';
import { formatScore } from '../../common/format';
import { personLink } from './Person';
import { t } from '../t';

export interface BoardAttrs extends ComponentAttrs {
  game: GameDetail;
  /** Bumped by the page to make the board reload (a run ended, a realtime push). */
  version: number;
}

/** The leaderboard rail beside the cabinet: all time, this month, this week. */
export default class Board extends Component<BoardAttrs> {
  period: Period = 'all';
  data: BoardData | null = null;
  loading = false;
  version = 0;
  asked = 0;

  oninit(vnode: Mithril.Vnode<BoardAttrs, this>) {
    super.oninit(vnode);
    this.data = this.attrs.game.board;
    this.version = this.attrs.version;
  }

  onbeforeupdate(vnode: Mithril.VnodeDOM<BoardAttrs, this>) {
    super.onbeforeupdate(vnode);
    if (vnode.attrs.version !== this.version) {
      this.version = vnode.attrs.version;
      this.load();
    }
  }

  load(more = false) {
    const ask = ++this.asked;
    this.loading = true;

    board(this.attrs.game.id, this.period, more && this.data?.next ? this.data.next : 0).then(
      (data) => {
        if (ask !== this.asked) return;
        this.data = more && this.data ? { ...data, rows: [...this.data.rows, ...data.rows] } : data;
        this.loading = false;
        m.redraw();
      },
      () => {
        this.loading = false;
        m.redraw();
      }
    );
  }

  view() {
    const game = this.attrs.game;
    const data = this.data;
    const moderator = app.forum.attribute('canModerateArcade');

    return (
      <section className="InsertCoinBoard" aria-label={extractText(t('board.title'))}>
        <h3 className="InsertCoinBoard-title">
          <Icon name="fas fa-ranking-star" /> {t('board.title')}
        </h3>
        <div className="InsertCoinTabs InsertCoinBoard-tabs" role="tablist">
          {(['all', 'month', 'week'] as Period[]).map((period) => (
            <Button
              className={'Button Button--flat' + (this.period === period ? ' active' : '')}
              role="tab"
              aria-selected={this.period === period}
              onclick={() => {
                this.period = period;
                this.load();
              }}
            >
              {t('board.' + period)}
            </Button>
          ))}
        </div>

        {data?.me ? (
          <div className="InsertCoinBoard-me">
            <span>{t('board.your_best')}</span>
            <b>{formatScore(data.me.score, game.scoreLabel)}</b>
            <span className="InsertCoinBoard-myRank">#{data.me.rank}</span>
          </div>
        ) : null}

        {data && data.rows.length ? (
          <ol className="InsertCoinBoard-rows">
            {data.rows.map((row) => (
              <li
                className={
                  'InsertCoinBoard-row' +
                  (row.rank <= 3 ? ` InsertCoinBoard-row--${row.rank}` : '') +
                  (row.user.id === Number(app.session.user?.id()) ? ' is-me' : '')
                }
              >
                <span className="InsertCoinBoard-rank">{row.rank}</span>
                {personLink(row.user, row.rank === 1 && this.period === 'all')}
                <span className="InsertCoinBoard-score">{formatScore(row.score, game.scoreLabel, false)}</span>
                {moderator && row.user.id !== Number(app.session.user?.id()) ? this.controls(row.user.id, row.user.displayName) : null}
              </li>
            ))}
          </ol>
        ) : this.loading ? null : (
          <p className="InsertCoinBoard-empty">{t(this.period === 'all' ? 'board.empty' : 'board.empty_period')}</p>
        )}

        {this.loading ? <LoadingIndicator size="small" display="block" /> : null}
        {!this.loading && data?.next ? (
          <Button className="Button Button--link InsertCoinBoard-more" onclick={() => this.load(true)}>
            {t('board.more')}
          </Button>
        ) : null}
        {game.scoreOrder === 'low' ? <p className="InsertCoinBoard-note">{t('board.low_wins')}</p> : null}
      </section>
    );
  }

  controls(userId: number, name: string) {
    const game = this.attrs.game;
    const done = () => {
      this.load();
      m.redraw();
    };

    return (
      <Dropdown
        className="InsertCoinBoard-controls"
        buttonClassName="Button Button--icon Button--flat"
        icon="fas fa-ellipsis-vertical"
        label={t('moderate.actions')}
        accessibleToggleLabel={extractText(t('moderate.actions'))}
        menuClassName="Dropdown-menu--right"
      >
        <Button
          icon="fas fa-eraser"
          onclick={() =>
            confirm(extractText(t('moderate.wipe_user_game_confirm', { player: name, game: game.title }))) && wipeGame(game.id, userId).then(done)
          }
        >
          {t('moderate.wipe_user_game')}
        </Button>
        <Button icon="fas fa-ban" onclick={() => confirm(extractText(t('moderate.ban_confirm', { player: name }))) && ban(userId, true).then(done)}>
          {t('moderate.ban_and_wipe')}
        </Button>
      </Dropdown>
    );
  }
}
