import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { showGame, wipeGame } from '../../common/api';
import type { GameDetail } from '../../common/api';
import { formatScore } from '../../common/format';
import Cabinet from '../components/Cabinet';
import Board from '../components/Board';
import { personLink } from '../components/Person';
import { t } from '../t';

/** /arcade/:slug — the game in its cabinet, with the leaderboard rail beside it. */
export default class GamePage extends Page {
  game: GameDetail | null = null;
  missing = false;
  version = 0;

  onPush = (e: Event) => {
    const gameId = (e as CustomEvent).detail?.gameId;
    if (this.game && gameId === this.game.id) this.refresh();
  };

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.history.push('insert-coin.game', extractText(t('home.title')));

    showGame(m.route.param('slug')).then(
      (game) => {
        this.game = game;
        app.setTitle(game.title);
        m.redraw();
      },
      () => {
        this.missing = true;
        m.redraw();
      }
    );
  }

  oncreate(vnode: Mithril.VnodeDOM) {
    super.oncreate(vnode);
    window.addEventListener('insertcoin:score', this.onPush);
  }

  onremove(vnode: Mithril.VnodeDOM) {
    super.onremove(vnode);
    window.removeEventListener('insertcoin:score', this.onPush);
  }

  /** The champion line and the board, after a run or a realtime push. */
  refresh() {
    this.version++;
    if (!this.game) return;

    showGame(this.game.slug).then((game) => {
      if (this.game) this.game.champion = game.champion;
      m.redraw();
    });
  }

  view(): Mithril.Children {
    const game = this.game;

    return (
      <PageStructure className="InsertCoinPage InsertCoinGamePage" sidebar={() => <IndexSidebar />}>
        {game ? this.content(game) : this.missing ? <p className="InsertCoinEmpty">{t('game.missing')}</p> : <LoadingIndicator display="block" />}
      </PageStructure>
    );
  }

  content(game: GameDetail): Mithril.Children {
    const champion = game.champion;

    return (
      <div className="InsertCoinGame">
        <nav className="InsertCoinGame-crumbs">
          <Link href={app.route('insert-coin.home')}>
            <Icon name="fas fa-arrow-left" /> {t('home.title')}
          </Link>
          {game.category ? <span>{game.category.name}</span> : null}
        </nav>

        <div className="InsertCoinGame-head">
          <h2 className="InsertCoinGame-title">{game.title}</h2>
          <div className="InsertCoinGame-badges">
            <span className="InsertCoinBadge">
              <Icon name={game.mobile ? 'fas fa-mobile-screen' : 'fas fa-keyboard'} /> {t(game.mobile ? 'card.mobile' : 'card.keyboard')}
            </span>
            <span className="InsertCoinBadge">
              <Icon name="fas fa-play" /> {t('game.plays', { count: game.plays })}
            </span>
            {game.crownApproval ? (
              <span className="InsertCoinBadge">
                <Icon name="fas fa-shield-halved" /> {t('game.crown_approval')}
              </span>
            ) : null}
          </div>
        </div>

        <div className="InsertCoinGame-body">
          <div className="InsertCoinGame-main">
            <Cabinet game={game} onfinish={() => this.refresh()} />

            <div className="InsertCoinGame-champion">
              {champion?.user ? (
                <>
                  {personLink(champion.user, true)}
                  <span className="InsertCoinGame-championText">
                    {t('game.champion', { score: <b>{formatScore(champion.score, game.scoreLabel)}</b> })}
                    {champion.since ? <span className="InsertCoinGame-since"> · {humanTime(new Date(champion.since))}</span> : null}
                  </span>
                </>
              ) : (
                <span className="InsertCoinGame-championText">
                  <Icon name="fas fa-crown" /> {t('game.no_champion')}
                </span>
              )}
            </div>

            {game.description ? <p className="InsertCoinGame-description">{game.description}</p> : null}

            {app.forum.attribute('canModerateArcade') ? (
              <Button
                className="Button Button--link InsertCoinGame-wipe"
                icon="fas fa-eraser"
                onclick={() =>
                  confirm(extractText(t('moderate.wipe_game_confirm', { game: game.title }))) && wipeGame(game.id).then(() => this.refresh())
                }
              >
                {t('moderate.wipe_game')}
              </Button>
            ) : null}
          </div>

          <aside className="InsertCoinGame-rail">
            <Board game={game} version={this.version} />
          </aside>
        </div>
      </div>
    );
  }
}
