import app from 'flarum/common/app';

export interface Person {
  id: number;
  username: string;
  displayName: string;
  avatarUrl: string | null;
  slug: string;
  crowns: number;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
}

export interface Game {
  id: number;
  slug: string;
  title: string;
  description: string | null;
  categoryId: number | null;
  thumbnailUrl: string | null;
  width: number;
  height: number;
  mobile: boolean;
  scoreOrder: 'high' | 'low';
  scoreLabel: string | null;
  plays: number;
  enabled: boolean;
  crownApproval: boolean;
  source: string;
  frameUrl: string | null;
  externalUrl: string | null;
  champion: { user: Person | null; score: number; since: string | null } | null;
  // Admin only
  position?: number;
  scoreCeiling?: number | null;
  minSeconds?: number;
  maxSeconds?: number;
  path?: string | null;
  url?: string | null;
}

export type Period = 'all' | 'month' | 'week';

export interface Standing {
  rank: number;
  score: number;
}

export interface Row {
  rank: number;
  user: Person;
  score: number;
  at: string | null;
}

export interface Board {
  period: Period;
  rows: Row[];
  next: number | null;
  me: Standing | null;
}

export interface GameDetail extends Game {
  category: { id: number; name: string; slug: string } | null;
  canSubmit: boolean;
  banned: boolean;
  board: Board;
}

export interface CrownEvent {
  gameId: number;
  user: Person | null;
  previous: Person | null;
  score: number;
  at: string;
}

export interface Home {
  categories: Category[];
  games: Game[];
  recent: number[];
  hallOfFame: Person[];
  recentCrowns: CrownEvent[];
}

export interface Session {
  id: number;
  token: string;
  expiresAt: string;
}

export interface Submitted {
  id: number;
  score: number;
  status: 'approved' | 'flagged' | 'pending';
  reason: string | null;
  personalBest: boolean;
  crown: boolean;
  previous: Person | null;
  me: Standing | null;
}

export interface Profile {
  user: Person;
  played: number;
  banned: boolean;
  crowns: (Game & { crownScore: number })[];
  bests: {
    game: { id: number; slug: string; title: string; thumbnailUrl: string | null; scoreLabel: string | null };
    score: number;
    champion: boolean;
    at: string;
  }[];
}

export interface Held {
  id: number;
  score: number;
  duration: number;
  status: 'flagged' | 'pending';
  reason: string | null;
  at: string;
  user: Person | null;
  game: { id: number; slug: string; title: string; scoreLabel: string | null; championScore: number | null } | null;
}

const url = (path: string) => app.forum.attribute<string>('apiUrl') + '/insert-coin' + path;

/** `quiet`: the caller shows the error itself, so no alert pops up as well. */
export function call<T>(method: string, path: string, body?: object, params?: Record<string, unknown>, quiet = false): Promise<T> {
  return app.request<T>({
    method,
    url: url(path),
    body,
    params,
    errorHandler: quiet
      ? (error: unknown) => {
          throw error;
        }
      : undefined,
  });
}

const data = <T>(p: Promise<{ data: T }>) => p.then((r) => r.data);

export const home = () => data(call<{ data: Home }>('GET', ''));
export const showGame = (slug: string) => data(call<{ data: GameDetail }>('GET', `/games/${slug}`));
export const board = (gameId: number, period: Period, offset = 0, limit = 10) =>
  data(call<{ data: Board }>('GET', `/games/${gameId}/scores`, undefined, { period, offset, limit }));
export const startSession = (gameId: number) => data(call<{ data: Session }>('POST', `/games/${gameId}/sessions`));
export const submitScore = (session: Session, score: number) =>
  data(call<{ data: Submitted }>('POST', `/sessions/${session.id}/score`, { token: session.token, score }, undefined, true));
export const profile = (userId: number | string) => data(call<{ data: Profile }>('GET', `/users/${userId}`));
export const queue = (offset = 0) => call<{ data: Held[]; meta: { next: number | null } }>('GET', '/queue', undefined, { offset });
export const approve = (id: number) => call<void>('POST', `/scores/${id}/approve`);
export const removeScore = (id: number) => call<void>('DELETE', `/scores/${id}`);
export const wipeGame = (gameId: number, userId?: number) =>
  call<void>('DELETE', `/games/${gameId}/scores`, undefined, userId ? { user: userId } : undefined);
export const wipeUser = (userId: number) => call<void>('DELETE', `/users/${userId}/scores`);
export const ban = (userId: number, wipe: boolean) => call<void>('POST', `/users/${userId}/ban`, { wipe });
export const unban = (userId: number) => call<void>('DELETE', `/users/${userId}/ban`);
