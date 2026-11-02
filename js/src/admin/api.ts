import app from 'flarum/admin/app';
import type { Category, Game } from '../common/api';

export interface Manage {
  games: Game[];
  categories: Category[];
  queue: number;
}

const url = (path: string) => `${app.forum.attribute('apiUrl')}/insert-coin/manage${path}`;

export function manage<T>(method: string, path: string, body?: object): Promise<T> {
  return app.request<T>({ method, url: url(path), body });
}

/** Multipart upload: the browser sets the boundary, Flarum's CSRF header is added by app.request. */
export function upload<T>(path: string, form: FormData): Promise<T> {
  return app.request<T>({ method: 'POST', url: url(path), body: form, serialize: (raw: any) => raw });
}
