import app from 'flarum/admin/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import { manage, upload } from '../api';
import type { Category, Game } from '../../common/api';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-insert-coin.admin.game.${key}`, params);

interface GameModalAttrs extends IFormModalAttrs {
  game: Game | null;
  categories: Category[];
  onsave: () => void;
}

/** Edit a game, or add one played from a URL on another site (game: null). */
export default class GameModal extends FormModal<GameModalAttrs> {
  fields: Record<string, Stream<any>> = {};
  thumbnail: File | null = null;
  pack: File | null = null;

  oninit(vnode: Mithril.Vnode<GameModalAttrs, this>) {
    super.oninit(vnode);
    const g = this.attrs.game;

    this.fields = {
      title: Stream(g?.title || ''),
      slug: Stream(g?.slug || ''),
      description: Stream(g?.description || ''),
      categoryId: Stream(g?.categoryId ? String(g.categoryId) : ''),
      url: Stream(g?.url || ''),
      thumbnailUrl: Stream(g?.source === 'url' ? g.thumbnailUrl || '' : ''),
      width: Stream(String(g?.width ?? 16)),
      height: Stream(String(g?.height ?? 9)),
      mobile: Stream(g?.mobile ?? true),
      scoreOrder: Stream(g?.scoreOrder || 'high'),
      scoreLabel: Stream(g?.scoreLabel || ''),
      scoreCeiling: Stream(g?.scoreCeiling != null ? String(g.scoreCeiling) : ''),
      minSeconds: Stream(String(g?.minSeconds ?? 3)),
      maxSeconds: Stream(String(g?.maxSeconds ?? 10800)),
      crownApproval: Stream(g?.crownApproval ?? false),
    };
  }

  className() {
    return 'InsertCoinGameModal Modal--large';
  }

  title() {
    return this.attrs.game ? t('edit_title', { title: this.attrs.game.title }) : t('add_title');
  }

  isUrl() {
    return !this.attrs.game || this.attrs.game.source === 'url';
  }

  content() {
    const f = this.fields;
    const text = (key: string, label: string, attrs: Record<string, unknown> = {}) => (
      <div className="Form-group">
        <label>{t(label)}</label>
        <input className="FormControl" bidi={f[key]} {...attrs} />
      </div>
    );

    return (
      <div className="Modal-body">
        <div className="Form">
          {text('title', 'title', { required: true, maxlength: 150 })}
          {text('slug', 'slug', { maxlength: 100, placeholder: extractText(t('slug_placeholder')) })}
          <div className="Form-group">
            <label>{t('description')}</label>
            <textarea className="FormControl" rows={3} maxlength={2000} bidi={f.description} />
          </div>
          <div className="Form-group">
            <label>{t('category')}</label>
            <select className="FormControl" bidi={f.categoryId}>
              <option value="">{extractText(t('no_category'))}</option>
              {this.attrs.categories.map((c) => (
                <option value={String(c.id)}>{c.name}</option>
              ))}
            </select>
          </div>

          {this.isUrl() ? (
            <>
              {text('url', 'url', { type: 'url', required: true, placeholder: 'https://' })}
              <p className="helpText">{t('url_help')}</p>
              {text('thumbnailUrl', 'thumbnail_url', { type: 'url', placeholder: 'https://' })}
            </>
          ) : null}

          {this.attrs.game ? (
            <div className="Form-group">
              <label>{t('thumbnail')}</label>
              <input
                className="FormControl"
                type="file"
                accept="image/png,image/jpeg,image/gif,image/webp"
                onchange={(e: Event) => (this.thumbnail = (e.target as HTMLInputElement).files?.[0] || null)}
              />
              <p className="helpText">{t('thumbnail_help')}</p>
            </div>
          ) : null}

          {this.attrs.game?.source === 'pack' ? (
            <div className="Form-group">
              <label>{t('replace_pack')}</label>
              <input
                className="FormControl"
                type="file"
                accept=".zip,application/zip"
                onchange={(e: Event) => (this.pack = (e.target as HTMLInputElement).files?.[0] || null)}
              />
              <p className="helpText">{t('replace_pack_help')}</p>
            </div>
          ) : null}

          <div className="InsertCoinGameModal-row">
            {text('width', 'width', { type: 'number', min: 1, max: 4000 })}
            {text('height', 'height', { type: 'number', min: 1, max: 4000 })}
          </div>
          <p className="helpText">{t('aspect_help')}</p>

          <div className="InsertCoinGameModal-row">
            <div className="Form-group">
              <label>{t('score_order')}</label>
              <select className="FormControl" bidi={f.scoreOrder}>
                <option value="high">{extractText(t('score_high'))}</option>
                <option value="low">{extractText(t('score_low'))}</option>
              </select>
            </div>
            {text('scoreLabel', 'score_label', { maxlength: 30, placeholder: extractText(t('score_label_placeholder')) })}
          </div>

          <h4>{t('fair_play')}</h4>
          <div className="InsertCoinGameModal-row">
            {text('scoreCeiling', 'ceiling', { type: 'number', min: 0, step: 'any' })}
            {text('minSeconds', 'min_seconds', { type: 'number', min: 0, max: 3600 })}
            {text('maxSeconds', 'max_seconds', { type: 'number', min: 10, max: 86400 })}
          </div>
          <p className="helpText">{t('fair_play_help')}</p>

          <div className="Form-group">
            <Switch state={f.mobile()} onchange={f.mobile}>
              {t('mobile')}
            </Switch>
          </div>
          <div className="Form-group">
            <Switch state={f.crownApproval()} onchange={f.crownApproval}>
              {t('crown_approval')}
            </Switch>
            <p className="helpText">{t('crown_approval_help')}</p>
          </div>

          <div className="Form-group">
            <Button className="Button Button--primary" type="submit" loading={this.loading}>
              {app.translator.trans('core.admin.settings.submit_button')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    this.loading = true;

    const f = this.fields;
    const body: Record<string, unknown> = {
      title: f.title(),
      slug: f.slug(),
      description: f.description(),
      categoryId: f.categoryId() ? Number(f.categoryId()) : null,
      width: Number(f.width()),
      height: Number(f.height()),
      mobile: f.mobile(),
      scoreOrder: f.scoreOrder(),
      scoreLabel: f.scoreLabel(),
      scoreCeiling: f.scoreCeiling() === '' ? null : Number(f.scoreCeiling()),
      minSeconds: Number(f.minSeconds()),
      maxSeconds: Number(f.maxSeconds()),
      crownApproval: f.crownApproval(),
    };

    if (this.isUrl()) {
      body.url = f.url();
      body.thumbnailUrl = f.thumbnailUrl();
    }

    const game = this.attrs.game;
    const files = (id: number) => {
      const steps: Promise<unknown>[] = [];

      if (this.thumbnail) {
        const form = new FormData();
        form.append('thumbnail', this.thumbnail);
        steps.push(upload(`/games/${id}/thumbnail`, form));
      }

      if (this.pack) {
        const form = new FormData();
        form.append('pack', this.pack);
        form.append('game', String(id));
        steps.push(upload('/packs', form));
      }

      return Promise.all(steps);
    };

    manage<{ data: Game }>(game ? 'PATCH' : 'POST', game ? `/games/${game.id}` : '/games', body)
      .then((r) => files(r.data.id))
      .then(
        () => {
          this.attrs.onsave();
          this.hide();
        },
        (error: any) => {
          this.loading = false;
          this.onerror(error);
        }
      );
  }
}
