import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';
import { call } from '../../common/api';
import type { Person } from '../../common/api';
import { formatScore } from '../../common/format';
import { avatar } from './Person';
import { t } from '../t';

interface Crown {
  user: Person;
  game: { slug: string; title: string; thumbnailUrl: string | null; scoreLabel: string | null };
  score: number;
  at: string;
}

// Module-level: a widget host may remount the widget; the list survives it.
const cache: Record<number, Crown[]> = {};

/** The latest crowns, for a sidebar. */
export default class WidgetBody extends Component<ComponentAttrs & { limit: number }> {
  oninit(vnode: Mithril.Vnode<ComponentAttrs & { limit: number }, this>) {
    super.oninit(vnode);
    if (!cache[this.attrs.limit]) {
      call<{ data: Crown[] }>('GET', '/crowns', undefined, { limit: this.attrs.limit }).then((r) => {
        cache[this.attrs.limit] = r.data;
        m.redraw();
      });
    }
  }

  view() {
    const crowns = cache[this.attrs.limit];
    if (!crowns) return <LoadingIndicator size="small" display="block" />;
    if (!crowns.length) return <p className="InsertCoinWidget-empty">{t('home.crowns_empty')}</p>;

    return (
      <ul className="InsertCoinWidget InsertCoinCrowns">
        {crowns.map((c) => (
          <li className="InsertCoinCrowns-row">
            {avatar(c.user, true)}
            <div className="InsertCoinCrowns-text">
              <div>
                <b>{c.user.displayName}</b> · <Link href={app.route('insert-coin.game', { slug: c.game.slug })}>{c.game.title}</Link>
              </div>
              <div className="InsertCoinCrowns-meta">
                {formatScore(c.score, c.game.scoreLabel)} · {humanTime(new Date(c.at))}
              </div>
            </div>
          </li>
        ))}
      </ul>
    );
  }
}
