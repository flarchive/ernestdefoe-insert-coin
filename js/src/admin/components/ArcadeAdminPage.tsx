import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { manage, upload } from '../api';
import type { Manage } from '../api';
import type { Category, Game } from '../../common/api';
import GameModal from './GameModal';
import CategoryModal from './CategoryModal';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-insert-coin.admin.${key}`, params);

/**
 * The arcade's admin page: the games (drag to order, switch on and off, edit,
 * upload a pack, add a game from another site) and categories, above core's
 * settings and permissions. Moderation lives on the forum, at /arcade/moderate.
 */
export default class ArcadeAdminPage extends ExtensionPage {
  data: Manage | null = null;
  dragging: { kind: 'game' | 'category'; id: number } | null = null;
  uploading = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.reload();
  }

  reload() {
    return manage<{ data: Manage }>('GET', '').then((r) => {
      this.data = r.data;
      m.redraw();
    });
  }

  sections(vnode: Mithril.Vnode) {
    const items = super.sections(vnode);
    items.add('games', this.gamesSection(), 95);
    items.add('categories', this.categoriesSection(), 90);
    items.add('more', this.moreSection(), 85);
    return items;
  }

  gamesSection(): Mithril.Children {
    const data = this.data;

    return (
      <div className="ExtensionPage-settings InsertCoinAdmin">
        <div className="container">
          <h3 className="ExtensionTitle">{t('games.title')}</h3>
          <p className="helpText">{t('games.help')}</p>
          {data && data.queue ? (
            <p className="InsertCoinAdmin-queue">
              <Icon name="fas fa-shield-halved" />{' '}
              <a href={app.forum.attribute('baseUrl') + '/arcade/moderate'} target="_blank" rel="noopener">
                {t('games.queue', { count: data.queue })}
              </a>
            </p>
          ) : null}
          <div className="InsertCoinAdmin-actions">
            <label className={'Button Button--primary' + (this.uploading ? ' disabled' : '')}>
              <Icon name="fas fa-file-zipper" className="Button-icon" />
              <span className="Button-label">{this.uploading ? t('games.uploading') : t('games.upload')}</span>
              <input type="file" accept=".zip,application/zip" hidden disabled={this.uploading} onchange={(e: Event) => this.uploadPack(e)} />
            </label>
            <Button className="Button" icon="fas fa-link" onclick={() => this.edit(null)}>
              {t('games.add_url')}
            </Button>
            <Button className="Button" icon="fas fa-rotate-left" onclick={() => this.restore()}>
              {t('games.restore')}
            </Button>
          </div>
          <p className="helpText InsertCoinAdmin-legal">{t('games.legal')}</p>
          {!data ? <LoadingIndicator /> : data.games.length ? this.gameList(data) : <p>{t('games.empty')}</p>}
        </div>
      </div>
    );
  }

  gameList(data: Manage): Mithril.Children {
    const categories = new Map(data.categories.map((c) => [c.id, c.name]));

    return (
      <ol className="InsertCoinAdmin-list">
        {data.games.map((game) => (
          <li
            key={game.id}
            className={'InsertCoinAdmin-row' + (this.dragging?.kind === 'game' && this.dragging.id === game.id ? ' is-dragging' : '')}
            {...this.dnd('game', game.id)}
          >
            <Icon name="fas fa-grip-vertical" className="InsertCoinAdmin-grip" />
            <span className="InsertCoinAdmin-thumb">
              {game.thumbnailUrl ? <img src={game.thumbnailUrl} alt="" loading="lazy" /> : <Icon name="fas fa-gamepad" />}
            </span>
            <span className="InsertCoinAdmin-name">
              <b>{game.title}</b>
              <span className="helpText">
                {t('games.source_' + (['bundled', 'pack', 'url'].includes(game.source) ? game.source : 'other'))}
                {game.categoryId && categories.get(game.categoryId) ? ' · ' + categories.get(game.categoryId) : ''}
                {' · '}
                {t('games.plays', { count: game.plays })}
                {game.crownApproval ? [' · ', t('games.crown_approval_on')] : null}
              </span>
            </span>
            <Switch state={game.enabled} onchange={(on: boolean) => this.save(game, { enabled: on })}>
              <span className="sr-only">{t('games.enabled')}</span>
            </Switch>
            <Button className="Button Button--icon" icon="fas fa-pen" aria-label={extractText(t('games.edit'))} onclick={() => this.edit(game)} />
            <Button
              className="Button Button--icon"
              icon="far fa-trash-can"
              aria-label={extractText(t('games.delete'))}
              onclick={() => this.remove(game)}
            />
          </li>
        ))}
      </ol>
    );
  }

  categoriesSection(): Mithril.Children {
    const data = this.data;

    return (
      <div className="ExtensionPage-settings InsertCoinAdmin">
        <div className="container">
          <h3 className="ExtensionTitle">{t('categories.title')}</h3>
          <p className="helpText">{t('categories.help')}</p>
          {data ? (
            <ol className="InsertCoinAdmin-list">
              {data.categories.map((c) => (
                <li
                  key={c.id}
                  className={'InsertCoinAdmin-row' + (this.dragging?.kind === 'category' && this.dragging.id === c.id ? ' is-dragging' : '')}
                  {...this.dnd('category', c.id)}
                >
                  <Icon name="fas fa-grip-vertical" className="InsertCoinAdmin-grip" />
                  <span className="InsertCoinAdmin-thumb">
                    <Icon name={c.icon || 'fas fa-folder'} />
                  </span>
                  <span className="InsertCoinAdmin-name">
                    <b>{c.name}</b>
                    <span className="helpText">{t('categories.games', { count: data.games.filter((g) => g.categoryId === c.id).length })}</span>
                  </span>
                  <Button
                    className="Button Button--icon"
                    icon="fas fa-pen"
                    aria-label={extractText(t('categories.edit'))}
                    onclick={() => this.editCategory(c)}
                  />
                  <Button
                    className="Button Button--icon"
                    icon="far fa-trash-can"
                    aria-label={extractText(t('categories.delete'))}
                    onclick={() => this.removeCategory(c)}
                  />
                </li>
              ))}
            </ol>
          ) : (
            <LoadingIndicator />
          )}
          <Button className="Button" icon="fas fa-plus" onclick={() => this.editCategory(null)}>
            {t('categories.add')}
          </Button>
        </div>
      </div>
    );
  }

  moreSection(): Mithril.Children {
    const docs = 'https://github.com/ernestdefoe/insert-coin/blob/main/docs/MAKING-GAMES.md';
    const links: [string, string][] = [
      ['guide', docs],
      ['js13k', 'https://js13kgames.com'],
      ['github', 'https://github.com/topics/html5-game'],
      ['itch', 'https://itch.io/games/free/html5'],
      ['example', 'https://github.com/ernestdefoe/insert-coin/tree/main/examples/hello-arcade'],
    ];

    return (
      <div className="ExtensionPage-settings InsertCoinAdmin">
        <div className="container">
          <h3 className="ExtensionTitle">{t('more.title')}</h3>
          <p className="helpText">{t('more.help')}</p>
          <ul className="InsertCoinAdmin-more">
            {links.map(([key, href]) => (
              <li>
                <a href={href} target="_blank" rel="noopener noreferrer">
                  {t('more.' + key)}
                </a>
                <span className="helpText">{t('more.' + key + '_help')}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    );
  }

  /** Drag and drop for a row: dropping A on B moves A to B's place. */
  dnd(kind: 'game' | 'category', id: number) {
    return {
      draggable: true,
      ondragstart: (e: DragEvent) => {
        this.dragging = { kind, id };
        e.dataTransfer!.effectAllowed = 'move';
        e.dataTransfer!.setData('text/plain', String(id));
      },
      ondragend: () => (this.dragging = null),
      ondragover: (e: DragEvent) => this.dragging?.kind === kind && e.preventDefault(),
      ondrop: (e: DragEvent) => {
        e.preventDefault();
        if (this.dragging?.kind === kind) this.drop(kind, this.dragging.id, id);
      },
    };
  }

  drop(kind: 'game' | 'category', moving: number, target: number) {
    if (!this.data || moving === target) return;
    const list: (Game | Category)[] = kind === 'game' ? this.data.games : this.data.categories;
    const from = list.findIndex((x) => x.id === moving);
    const to = list.findIndex((x) => x.id === target);
    const [item] = list.splice(from, 1);
    list.splice(to, 0, item);
    manage('POST', kind === 'game' ? '/games/order' : '/categories/order', { ids: list.map((x) => x.id) });
  }

  uploadPack(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    const form = new FormData();
    form.append('pack', file);
    this.uploading = true;

    upload<{ data: Game }>('/packs', form)
      .then((r) => {
        app.alerts.show({ type: 'success' }, t('games.uploaded', { title: r.data.title }));
        return this.reload();
      })
      .finally(() => {
        this.uploading = false;
        m.redraw();
      });
  }

  save(game: Game, changes: Partial<Game>) {
    Object.assign(game, changes);
    manage('PATCH', `/games/${game.id}`, changes).catch(() => this.reload());
  }

  edit(game: Game | null) {
    app.modal.show(GameModal, { game, categories: this.data?.categories || [], onsave: () => this.reload() });
  }

  remove(game: Game) {
    if (!confirm(extractText(t('games.delete_confirm', { title: game.title })))) return;
    manage('DELETE', `/games/${game.id}`).then(() => this.reload());
  }

  restore() {
    manage<{ data: { added: number } }>('POST', '/starters').then((r) => {
      app.alerts.show({ type: 'success' }, t('games.restored', { count: r.data.added }));
      this.reload();
    });
  }

  editCategory(category: Category | null) {
    app.modal.show(CategoryModal, { category, onsave: () => this.reload() });
  }

  removeCategory(c: Category) {
    if (!confirm(extractText(t('categories.delete_confirm', { name: c.name })))) return;
    manage('DELETE', `/categories/${c.id}`).then(() => this.reload());
  }
}
