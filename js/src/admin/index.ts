import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import ArcadeAdminPage from './components/ArcadeAdminPage';
import { registerEditorWidgets } from '../common/fofWidgets';

const t = (key: string) => app.translator.trans(`ernestdefoe-insert-coin.admin.${key}`);

export const extend = [
  new Extend.Admin()
    .page(ArcadeAdminPage)
    .setting(() => ({ setting: 'ernestdefoe-insert-coin.show_nav', type: 'boolean', label: t('settings.show_nav') }))
    .setting(() => ({
      setting: 'ernestdefoe-insert-coin.outlier_factor',
      type: 'number',
      min: 1.1,
      step: 0.1,
      label: t('settings.outlier_factor'),
      help: t('settings.outlier_factor_help'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-insert-coin.outlier_players',
      type: 'number',
      min: 1,
      label: t('settings.outlier_players'),
      help: t('settings.outlier_players_help'),
    }))
    .setting(() => ({
      setting: 'ernestdefoe-insert-coin.crown_points',
      type: 'number',
      label: t('settings.crown_points'),
      help: t('settings.crown_points_help'),
    }))
    .permission(() => ({ icon: 'fas fa-gamepad', label: t('permissions.play'), permission: 'insertCoin.play', allowGuest: true }), 'view')
    .permission(() => ({ icon: 'fas fa-ranking-star', label: t('permissions.submit'), permission: 'insertCoin.submit' }), 'reply')
    .permission(() => ({ icon: 'fas fa-shield-halved', label: t('permissions.moderate'), permission: 'insertCoin.moderate' }), 'moderate'),
];

app.initializers.add('ernestdefoe-insert-coin', () => {
  // FoF's layout editor lists only widgets the admin registers too.
  registerEditorWidgets(app, 'ernestdefoe-insert-coin', [{ key: 'insertCoinCrowns', icon: 'fas fa-crown', title: () => t('widgets.crowns') }]);
});
