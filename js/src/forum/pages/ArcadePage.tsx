import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { home } from '../../common/api';
import type { Game, Home } from '../../common/api';
import { formatScore } from '../../common/format';
import ArcadeHero from '../components/ArcadeHero';
import GameCard from '../components/GameCard';
import { avatar, personLink } from '../components/Person';
import { t } from '../t';

type Sort = 'featured' | 'popular' | 'newest' | 'title';

/** /arcade — the arcade floor: every game, what you played lately, the Hall of Fame and the latest crowns. */
export default class ArcadePage extends Page {
  data: Home | null = null;
  failed = false;
  category: number | null = null;
  sort: Sort = 'featured';
  query = '';

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(extractText(t('home.title')));
    app.history.push('insert-coin', extractText(t('home.title')));

    home().then(
      (data) => {
        this.data = data;
        m.redraw();
      },
      () => {
        this.failed = true;
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    return (
      <PageStructure
        className="InsertCoinPage InsertCoinArcadePage"
        hero={() => <ArcadeHero title={t('home.title')} subtitle={t('home.subtitle')} />}
        sidebar={() => <IndexSidebar />}
      >
        {this.data ? (
          this.content(this.data)
        ) : this.failed ? (
          <p className="InsertCoinEmpty">{t('home.failed')}</p>
        ) : (
          <LoadingIndicator display="block" />
        )}
      </PageStructure>
    );
  }

  content(data: Home): Mithril.Children {
    const byId = new Map(data.games.map((g) => [g.id, g]));
    const recent = data.recent.map((id) => byId.get(id)).filter(Boolean) as Game[];
    const categories = new Map(data.categories.map((c) => [c.id, c]));
    const games = this.filtered(data.games);
    const used = data.categories.filter((c) => data.games.some((g) => g.categoryId === c.id));

    return (
      <div className="InsertCoinFloor">
        <div className="InsertCoinFloor-main">
          {recent.length ? (
            <section className="InsertCoinSection InsertCoinRecent">
              <h2 className="InsertCoinSection-title">{t('home.recent')}</h2>
              <div className="InsertCoinRecent-list">
                {recent.map((game) => (
                  <GameCard key={game.id} game={game} compact />
                ))}
              </div>
            </section>
          ) : null}

          <div className="InsertCoinToolbar">
            <div className="InsertCoinTabs" role="tablist" aria-label={extractText(t('home.categories'))}>
              {[{ id: null, name: t('home.all'), icon: 'fas fa-border-all' }, ...used].map((c: any) => (
                <Button
                  className={'Button Button--flat' + (this.category === c.id ? ' active' : '')}
                  role="tab"
                  aria-selected={this.category === c.id}
                  icon={c.icon || undefined}
                  onclick={() => (this.category = c.id)}
                >
                  {c.name}
                </Button>
              ))}
            </div>
            <div className="InsertCoinToolbar-end">
              <select
                className="FormControl InsertCoinToolbar-sort"
                aria-label={extractText(t('home.sort'))}
                value={this.sort}
                onchange={(e: Event) => (this.sort = (e.target as HTMLSelectElement).value as Sort)}
              >
                {(['featured', 'popular', 'newest', 'title'] as Sort[]).map((s) => (
                  <option value={s}>{extractText(t('home.sort_' + s))}</option>
                ))}
              </select>
              <input
                className="FormControl InsertCoinToolbar-search"
                type="search"
                placeholder={extractText(t('home.search'))}
                aria-label={extractText(t('home.search'))}
                value={this.query}
                oninput={(e: InputEvent) => (this.query = (e.target as HTMLInputElement).value)}
              />
            </div>
          </div>

          {games.length ? (
            <div className="InsertCoinGrid">
              {games.map((game) => (
                <GameCard key={game.id} game={game} category={game.categoryId ? categories.get(game.categoryId) : null} />
              ))}
            </div>
          ) : (
            <p className="InsertCoinEmpty">{t(data.games.length ? 'home.no_match' : 'home.empty')}</p>
          )}
        </div>

        <aside className="InsertCoinFloor-side">
          {app.forum.attribute('canModerateArcade') ? (
            <LinkButton className="Button InsertCoinFloor-moderate" icon="fas fa-shield-halved" href={app.route('insert-coin.moderate')}>
              {t('moderate.title')}
            </LinkButton>
          ) : null}

          <section className="InsertCoinPanel">
            <h3 className="InsertCoinPanel-title">
              <Icon name="fas fa-trophy" /> {t('home.hall_of_fame')}
            </h3>
            {data.hallOfFame.length ? (
              <ol className="InsertCoinHall">
                {data.hallOfFame.map((person, i) => (
                  <li className={'InsertCoinHall-row' + (i < 3 ? ' InsertCoinHall-row--' + (i + 1) : '')}>
                    <span className="InsertCoinHall-rank">{i + 1}</span>
                    {personLink(person, i === 0)}
                    <span className="InsertCoinHall-crowns">
                      <Icon name="fas fa-crown" /> {person.crowns}
                    </span>
                  </li>
                ))}
              </ol>
            ) : (
              <p className="InsertCoinPanel-empty">{t('home.hall_empty')}</p>
            )}
          </section>

          <section className="InsertCoinPanel">
            <h3 className="InsertCoinPanel-title">
              <Icon name="fas fa-crown" /> {t('home.recent_crowns')}
            </h3>
            {data.recentCrowns.length ? (
              <ul className="InsertCoinCrowns">
                {data.recentCrowns.map((crown) => {
                  const game = byId.get(crown.gameId);
                  if (!game || !crown.user) return null;

                  return (
                    <li className="InsertCoinCrowns-row">
                      {avatar(crown.user, true)}
                      <div className="InsertCoinCrowns-text">
                        <div>
                          {t(crown.previous ? 'home.crown_took' : 'home.crown_first', {
                            player: <b>{crown.user.displayName}</b>,
                            previous: crown.previous ? <b>{crown.previous.displayName}</b> : '',
                            game: <Link href={app.route('insert-coin.game', { slug: game.slug })}>{game.title}</Link>,
                          })}
                        </div>
                        <div className="InsertCoinCrowns-meta">
                          {formatScore(crown.score, game.scoreLabel)} · {humanTime(new Date(crown.at))}
                        </div>
                      </div>
                    </li>
                  );
                })}
              </ul>
            ) : (
              <p className="InsertCoinPanel-empty">{t('home.crowns_empty')}</p>
            )}
          </section>
        </aside>
      </div>
    );
  }

  filtered(games: Game[]): Game[] {
    const q = this.query.trim().toLowerCase();
    const list = games.filter(
      (g) =>
        (this.category === null || g.categoryId === this.category) &&
        (!q || g.title.toLowerCase().includes(q) || (g.description || '').toLowerCase().includes(q))
    );

    // The server sends the admin's order; sorting is stable, so ties keep it.
    if (this.sort === 'featured') return list;
    if (this.sort === 'popular') return list.sort((a, b) => b.plays - a.plays);
    if (this.sort === 'newest') return list.sort((a, b) => b.id - a.id);

    return list.sort((a, b) => a.title.localeCompare(b.title));
  }
}
