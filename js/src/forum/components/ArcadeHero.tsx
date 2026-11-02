import Hero, { type IHeroAttrs } from 'flarum/forum/components/Hero';
import Icon from 'flarum/common/components/Icon';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

export interface ArcadeHeroAttrs extends IHeroAttrs {
  title: Mithril.Children;
  subtitle?: Mithril.Children;
}

/**
 * The arcade's hero, built like core's own (Hero, Hero-title, Hero-subtitle),
 * so every theme styles it the way it styles the welcome hero.
 */
export default class ArcadeHero extends Hero<ArcadeHeroAttrs> {
  className() {
    return 'InsertCoinHero';
  }

  bodyItems() {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'content',
      <div className="containerNarrow">
        <h1 className="Hero-title">
          <Icon name="fas fa-gamepad" /> {this.attrs.title}
        </h1>
        {this.attrs.subtitle ? <div className="Hero-subtitle">{this.attrs.subtitle}</div> : null}
      </div>,
      80
    );

    return items;
  }
}
