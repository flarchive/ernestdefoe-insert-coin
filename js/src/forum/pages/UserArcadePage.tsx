import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { ban, profile, unban, wipeUser } from '../../common/api';
import type { Profile } from '../../common/api';
import { formatScore } from '../../common/format';
import GameCard from '../components/GameCard';
import { t } from '../t';

/** /u/:username/arcade — a member's crowns and best scores. */
export default class UserArcadePage extends UserPage {
  data: Profile | null = null;
  loadingFor: string | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: any) {
    super.show(user);

    if (this.loadingFor !== user.id()) {
      this.loadingFor = user.id();
      this.data = null;
      this.reload();
    }
  }

  reload() {
    profile(this.loadingFor!).then((data) => {
      this.data = data;
      m.redraw();
    });
  }

  content() {
    if (!app.forum.attribute('canPlayArcade')) return null;
    const data = this.data;
    if (!data) return <LoadingIndicator display="block" />;

    const self = Number(app.session.user?.id()) === data.user.id;
    const moderator = app.forum.attribute('canModerateArcade') && !self;

    return (
      <div className="InsertCoinProfile">
        <div className="InsertCoinProfile-stats">
          <div className="InsertCoinStat InsertCoinStat--crowns">
            <Icon name="fas fa-crown" />
            <b>{data.user.crowns}</b>
            <span>{t('user.crowns_held', { count: data.user.crowns })}</span>
          </div>
          <div className="InsertCoinStat">
            <Icon name="fas fa-gamepad" />
            <b>{data.played}</b>
            <span>{t('user.games_played', { count: data.played })}</span>
          </div>
          {moderator ? (
            <div className="InsertCoinProfile-mod">
              {data.banned ? <span className="InsertCoinBadge InsertCoinBadge--danger">{t('user.banned')}</span> : null}
              <Button
                className="Button"
                icon="fas fa-ban"
                onclick={() => {
                  const done = data.banned
                    ? unban(data.user.id)
                    : confirm(extractText(t('moderate.ban_confirm', { player: data.user.displayName })))
                      ? ban(data.user.id, false)
                      : null;
                  done?.then(() => this.reload());
                }}
              >
                {t(data.banned ? 'moderate.unban' : 'moderate.ban')}
              </Button>
              <Button
                className="Button"
                icon="fas fa-eraser"
                onclick={() =>
                  confirm(extractText(t('moderate.wipe_user_confirm', { player: data.user.displayName }))) &&
                  wipeUser(data.user.id).then(() => this.reload())
                }
              >
                {t('moderate.wipe_user')}
              </Button>
            </div>
          ) : null}
        </div>

        {data.crowns.length ? (
          <section className="InsertCoinSection">
            <h3 className="InsertCoinSection-title">
              <Icon name="fas fa-crown" /> {t('user.crowns_title')}
            </h3>
            <div className="InsertCoinGrid InsertCoinGrid--small">
              {data.crowns.map((game) => (
                <GameCard key={game.id} game={game} />
              ))}
            </div>
          </section>
        ) : null}

        <section className="InsertCoinSection">
          <h3 className="InsertCoinSection-title">
            <Icon name="fas fa-star" /> {t('user.bests_title')}
          </h3>
          {data.bests.length ? (
            <ul className="InsertCoinBests">
              {data.bests.map((best) => (
                <li className="InsertCoinBests-row">
                  <Link href={app.route('insert-coin.game', { slug: best.game.slug })} className="InsertCoinBests-game">
                    {best.game.thumbnailUrl ? <img src={best.game.thumbnailUrl} alt="" loading="lazy" /> : <span className="InsertCoinBests-blank" />}
                    <span>{best.game.title}</span>
                  </Link>
                  <span className="InsertCoinBests-score">
                    {best.champion ? <Icon name="fas fa-crown" className="InsertCoinBests-crown" /> : null}
                    {formatScore(best.score, best.game.scoreLabel)}
                  </span>
                  <span className="InsertCoinBests-at">{humanTime(new Date(best.at))}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="InsertCoinEmpty">{t(self ? 'user.empty_self' : 'user.empty')}</p>
          )}
        </section>
      </div>
    );
  }
}
