import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { approve, queue, removeScore } from '../../common/api';
import type { Held } from '../../common/api';
import { formatScore } from '../../common/format';
import ArcadeHero from '../components/ArcadeHero';
import { personLink } from '../components/Person';
import { t } from '../t';

/** /arcade/moderate — scores held back: outliers, and crowns waiting for approval. */
export default class ModeratePage extends Page {
  rows: Held[] = [];
  next: number | null = null;
  loading = true;
  busy: number | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(extractText(t('moderate.title')));
    app.history.push('insert-coin.moderate', extractText(t('moderate.title')));
    this.load();
  }

  load(more = false) {
    this.loading = true;
    queue(more && this.next ? this.next : 0).then(
      (r) => {
        this.rows = more ? [...this.rows, ...r.data] : r.data;
        this.next = r.meta.next;
        this.loading = false;
        m.redraw();
      },
      () => {
        this.loading = false;
        m.redraw();
      }
    );
  }

  act(row: Held, action: (id: number) => Promise<void>) {
    this.busy = row.id;
    action(row.id).then(
      () => {
        this.rows = this.rows.filter((r) => r.id !== row.id);
        this.busy = null;
        m.redraw();
      },
      () => {
        this.busy = null;
        m.redraw();
      }
    );
  }

  view(): Mithril.Children {
    if (!app.forum.attribute('canModerateArcade')) {
      return (
        <PageStructure className="InsertCoinPage" sidebar={() => <IndexSidebar />}>
          <p className="InsertCoinEmpty">{t('moderate.forbidden')}</p>
        </PageStructure>
      );
    }

    return (
      <PageStructure
        className="InsertCoinPage InsertCoinModeratePage"
        hero={() => <ArcadeHero title={t('moderate.title')} subtitle={t('moderate.subtitle')} />}
        sidebar={() => <IndexSidebar />}
      >
        <ul className="InsertCoinQueue">
          {this.rows.map((row) => (
            <li className="InsertCoinQueue-row">
              {personLink(row.user)}
              <div className="InsertCoinQueue-what">
                {row.game ? <Link href={app.route('insert-coin.game', { slug: row.game.slug })}>{row.game.title}</Link> : null}
                <b>{formatScore(row.score, row.game?.scoreLabel)}</b>
                <span className="InsertCoinQueue-meta">
                  {t('moderate.reason_' + (row.reason || 'outlier'))} ·{' '}
                  {t('moderate.champion_score', { score: formatScore(row.game?.championScore, row.game?.scoreLabel) })} ·{' '}
                  {t('moderate.duration', { seconds: row.duration })} · {humanTime(new Date(row.at))}
                </span>
              </div>
              <div className="InsertCoinQueue-actions">
                <Button className="Button Button--primary" icon="fas fa-check" loading={this.busy === row.id} onclick={() => this.act(row, approve)}>
                  {t('moderate.approve')}
                </Button>
                <Button className="Button" icon="far fa-trash-can" disabled={this.busy === row.id} onclick={() => this.act(row, removeScore)}>
                  {t('moderate.reject')}
                </Button>
              </div>
            </li>
          ))}
        </ul>
        {this.loading ? <LoadingIndicator display="block" /> : null}
        {!this.loading && !this.rows.length ? <p className="InsertCoinEmpty">{t('moderate.empty')}</p> : null}
        {!this.loading && this.next ? (
          <Button className="Button" onclick={() => this.load(true)}>
            {t('board.more')}
          </Button>
        ) : null}
      </PageStructure>
    );
  }
}
