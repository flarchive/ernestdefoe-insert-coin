import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import UserPage from 'flarum/forum/components/UserPage';
import UserCard from 'flarum/forum/components/UserCard';
import LinkButton from 'flarum/common/components/LinkButton';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import type ItemList from 'flarum/common/utils/ItemList';
import registerWidgets from './widgets';
import bindRealtime from './realtime';
import { t } from './t';

export { default as extend } from './extend';

/*
 * Everything here runs on every page for every visitor, so it is only the
 * wiring: a nav link, the profile tab, a crown on user cards (read off the
 * user the page already has), the alert type and the widget.
 */
app.initializers.add('ernestdefoe-insert-coin', () => {
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('canPlayArcade') || app.forum.attribute('insertCoinShowNav') === false) return;

    items.add(
      'arcade',
      <LinkButton href={app.route('insert-coin.home')} icon="fas fa-gamepad">
        {t('nav')}
      </LinkButton>,
      4
    );
  });

  extend(UserPage.prototype, 'navItems', function (this: UserPage, items) {
    if (!app.forum.attribute('canPlayArcade') || !this.user) return;

    const crowns = this.user.attribute<number>('arcadeCrowns') || 0;

    items.add(
      'arcade',
      <LinkButton href={app.route('user.arcade', { username: this.user.slug() })} icon="fas fa-gamepad">
        {t('user.tab')}
        {crowns ? <span className="Button-badge">{crowns}</span> : null}
      </LinkButton>,
      40
    );
  });

  // A champion's crown, on the profile card and the hover card alike.
  extend(UserCard.prototype, 'infoItems', function (this: any, items: ItemList<any>) {
    const user = this.attrs.user;
    const crowns = user?.attribute?.('arcadeCrowns') || 0;
    if (!crowns || !app.forum.attribute('canPlayArcade')) return;

    items.add(
      'arcadeCrowns',
      <Link className="InsertCoinCrownChip" href={app.route('user.arcade', { username: user.slug() })}>
        <Icon name="fas fa-crown" /> {t('user.crowns', { count: crowns })}
      </Link>,
      -5
    );
  });

  // 🚨 By module name: NotificationGrid is in a lazy chunk in Flarum 2.
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', (items: ItemList<any>) => {
    if (!app.forum.attribute('canSubmitArcade')) return;
    items.add('insertCoinDethroned', { name: 'insertCoinDethroned', icon: 'fas fa-crown', label: t('settings.notify_dethroned') });
  });

  registerWidgets();

  if ('flarum-realtime' in ((globalThis as any).flarum?.extensions || {})) bindRealtime();
});
