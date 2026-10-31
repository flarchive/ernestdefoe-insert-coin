import app from 'flarum/forum/app';

export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-insert-coin.forum.${key}`, params);
