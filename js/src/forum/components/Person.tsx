import app from 'flarum/forum/app';
import Link from 'flarum/common/components/Link';
import Icon from 'flarum/common/components/Icon';
import stringToColor from 'flarum/common/utils/stringToColor';
import type { Person } from '../../common/api';

/** A member's avatar, from the arcade's plain user objects. */
export function avatar(person: Person | null, crown = false): any {
  if (!person) return <span className="Avatar InsertCoinAvatar" />;

  return (
    <span className={'InsertCoinAvatar' + (crown ? ' InsertCoinAvatar--crowned' : '')}>
      {person.avatarUrl ? (
        <img className="Avatar" src={person.avatarUrl} alt="" loading="lazy" decoding="async" />
      ) : (
        <span className="Avatar" style={{ '--avatar-bg': '#' + stringToColor(person.displayName) }}>
          {person.displayName.charAt(0).toUpperCase()}
        </span>
      )}
      {crown ? <Icon name="fas fa-crown" className="InsertCoinAvatar-crown" /> : null}
    </span>
  );
}

/** Avatar and name, linking to the member's arcade tab. */
export function personLink(person: Person | null, crown = false): any {
  if (!person) return <span className="InsertCoinPerson InsertCoinPerson--gone">{app.translator.trans('core.lib.username.deleted_text')}</span>;

  return (
    <Link className="InsertCoinPerson" href={app.route('user.arcade', { username: person.slug })}>
      {avatar(person, crown)}
      <span className="InsertCoinPerson-name">{person.displayName}</span>
    </Link>
  );
}
