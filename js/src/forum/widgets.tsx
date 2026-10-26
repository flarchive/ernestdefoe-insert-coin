import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { fof, hasFofWidgets, placed } from '../common/fofWidgets';

/**
 * "Recent crowns", offered to the widget hosts there are: Bespoke and FoF
 * Forum Widgets.
 *
 * 🚨 Neither host is a build-time dependency: each is found at runtime, so the
 * bundle is the same with both, one or neither installed. What the widget
 * shows is its own lazy chunk, fetched only by a page that places it.
 */

const EXT = 'ernestdefoe-insert-coin';
let body: any = null;

class CrownsWidget extends Component<{ limit?: number }> {
  oninit(vnode: Mithril.Vnode<{ limit?: number }, this>) {
    super.oninit(vnode);
    if (!body) {
      import('./components/WidgetBody').then((mod) => {
        body = mod.default;
        m.redraw();
      });
    }
  }

  view(): Mithril.Children {
    if (!app.forum.attribute('canPlayArcade')) return null;

    return body ? m(body, { limit: Math.max(1, Math.min(20, Number(this.attrs.limit) || 5)) }) : <div className="InsertCoinWidget" />;
  }
}

export default function registerWidgets(): void {
  const flarum = (globalThis as any).flarum;
  const has = (id: string) => !!flarum?.extensions && id in flarum.extensions;

  if (hasFofWidgets() && (app as any).widgets) {
    const Widgets = fof('common/extend/Widgets');
    const WidgetBase = fof('common/components/Widget');

    // A version whose internals moved: decline quietly rather than throw out
    // of an initializer and take the forum's JS down with it.
    if (Widgets && WidgetBase) {
      class FofCrownsWidget extends WidgetBase {
        className() {
          return 'InsertCoinFofWidget';
        }
        icon() {
          return 'fas fa-crown';
        }
        title() {
          return app.translator.trans('ernestdefoe-insert-coin.lib.widget.title');
        }
        content() {
          return <CrownsWidget />;
        }
      }

      new Widgets()
        .add({
          key: 'insertCoinCrowns',
          component: FofCrownsWidget,
          // 🚨 Opt-in: only where the admin placed it in FoF's layout editor.
          isDisabled: () => !placed(app, EXT, 'insertCoinCrowns') || !app.forum.attribute('canPlayArcade'),
          isUnique: true,
          placement: 'end',
          position: 1,
        })
        .extend(app, EXT);
    }
  }

  if (has('ernestdefoe-bespoke')) {
    const g = globalThis as any;
    g.BespokeWidgetQueue = g.BespokeWidgetQueue || [];
    // Labels are translation KEYS: Bespoke resolves them in the reader's locale.
    g.BespokeWidgetQueue.push({
      type: 'insert-coin-crowns',
      label: 'ernestdefoe-insert-coin.lib.widget.title',
      icon: 'fas fa-crown',
      zones: ['sidebar', 'above-list', 'below-list', 'footer'],
      schema: [
        { key: 'heading', type: 'text', label: 'ernestdefoe-insert-coin.lib.widget.heading', default: '' },
        { key: 'limit', type: 'number', label: 'ernestdefoe-insert-coin.lib.widget.limit', default: 5 },
      ],
      component: {
        view(vnode: any) {
          const s = vnode.attrs?.settings || {};
          return (
            <div className="InsertCoinBespokeWidget">
              <h4 className="InsertCoinWidget-heading">{s.heading || app.translator.trans('ernestdefoe-insert-coin.lib.widget.title')}</h4>
              <CrownsWidget limit={s.limit} />
            </div>
          );
        },
      },
    });
  }
}
