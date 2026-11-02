import Extend from 'flarum/common/extenders';
import Model from 'flarum/common/Model';
import DethronedNotification from './components/DethronedNotification';

/** A game as a dethroned alert's subject carries it. */
export class ArcadeGame extends Model {
  title = Model.attribute<string>('title');
  slug = Model.attribute<string>('slug');
  thumbnailUrl = Model.attribute<string | null>('thumbnailUrl');
  scoreLabel = Model.attribute<string | null>('scoreLabel');
}

function withStyles<T>(page: Promise<T>): Promise<T> {
  return Promise.all([page, import('./styles')]).then(([module]) => module);
}

export default [
  new Extend.Store().add('insert-coin-games', ArcadeGame),

  new Extend.Notification().add('insertCoinDethroned', DethronedNotification),

  // Every page is its own chunk, and its styles one more, shared by all of
  // them: nothing of the arcade loads on the forum's other pages.
  new Extend.Routes()
    .add('insert-coin.home', '/arcade', () => withStyles(import('./pages/ArcadePage')))
    .add('insert-coin.moderate', '/arcade/moderate', () => withStyles(import('./pages/ModeratePage')))
    .add('insert-coin.game', '/arcade/:slug', () => withStyles(import('./pages/GamePage')))
    .add('user.arcade', '/u/:username/arcade', () => withStyles(import('./pages/UserArcadePage'))),
];
