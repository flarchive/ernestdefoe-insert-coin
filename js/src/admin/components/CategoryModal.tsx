import app from 'flarum/admin/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import { manage } from '../api';
import type { Category } from '../../common/api';

const t = (key: string) => app.translator.trans(`ernestdefoe-insert-coin.admin.categories.${key}`);

interface CategoryModalAttrs extends IFormModalAttrs {
  category: Category | null;
  onsave: () => void;
}

export default class CategoryModal extends FormModal<CategoryModalAttrs> {
  name = Stream('');
  icon = Stream('');

  oninit(vnode: Mithril.Vnode<CategoryModalAttrs, this>) {
    super.oninit(vnode);
    this.name(this.attrs.category?.name || '');
    this.icon(this.attrs.category?.icon || '');
  }

  className() {
    return 'Modal--small';
  }

  title() {
    return t(this.attrs.category ? 'edit' : 'add');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{t('name')}</label>
            <input className="FormControl" bidi={this.name} required maxlength={100} />
          </div>
          <div className="Form-group">
            <label>{t('icon')}</label>
            <input className="FormControl" bidi={this.icon} placeholder="fas fa-gamepad" maxlength={100} />
            <p className="helpText">{t('icon_help')}</p>
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
    const c = this.attrs.category;

    manage(c ? 'PATCH' : 'POST', c ? `/categories/${c.id}` : '/categories', { name: this.name(), icon: this.icon() }).then(
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
