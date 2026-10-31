import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import type { Category, Game } from '../../common/api';
import { formatScore } from '../../common/format';
import { avatar } from './Person';
import { t } from '../t';

export interface GameCardAttrs extends ComponentAttrs {
  game: Game;
  category?: Category | null;
  compact?: boolean;
}

/** A game on the arcade floor: big art, the champion and their crown. */
export default class GameCard extends Component<GameCardAttrs> {
  view() {
    const { game, category, compact } = this.attrs;
    const champion = game.champion;

    return (
      <Link className={'InsertCoinCard' + (compact ? ' InsertCoinCard--compact' : '')} href={app.route('insert-coin.game', { slug: game.slug })}>
        <div className="InsertCoinCard-art">
          {game.thumbnailUrl ? (
            <img src={game.thumbnailUrl} alt="" loading="lazy" decoding="async" />
          ) : (
            <span className="InsertCoinCard-placeholder" style={{ '--ic-hue': String((game.id * 47) % 360) }}>
              {game.title.charAt(0)}
            </span>
          )}
          <span className="InsertCoinCard-play">
            <Icon name="fas fa-play" /> {t('card.play')}
          </span>
          {champion?.user && !compact ? (
            <span className="InsertCoinCard-champion" title={champion.user.displayName}>
              {avatar(champion.user, true)}
            </span>
          ) : null}
          {!game.enabled ? <span className="InsertCoinCard-off">{t('card.disabled')}</span> : null}
        </div>
        <div className="InsertCoinCard-body">
          <h3 className="InsertCoinCard-title">{game.title}</h3>
          {compact ? null : (
            <div className="InsertCoinCard-meta">
              {category ? <span>{category.name}</span> : null}
              <span
                title={extractText(t(game.mobile ? 'card.mobile' : 'card.keyboard'))}
                aria-label={extractText(t(game.mobile ? 'card.mobile' : 'card.keyboard'))}
              >
                <Icon name={game.mobile ? 'fas fa-mobile-screen' : 'fas fa-keyboard'} />
              </span>
            </div>
          )}
          {champion?.user && !compact ? (
            <div className="InsertCoinCard-record">
              <Icon name="fas fa-crown" /> <b>{formatScore(champion.score, game.scoreLabel)}</b> · {champion.user.displayName}
            </div>
          ) : compact ? null : (
            <div className="InsertCoinCard-record InsertCoinCard-record--open">{t('card.unclaimed')}</div>
          )}
        </div>
      </Link>
    );
  }
}
