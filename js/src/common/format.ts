import app from 'flarum/common/app';

/** A score as people read it: grouped digits, decimals only when there are any. */
export function formatScore(score: number | null | undefined, label?: string | null, withLabel = true): string {
  if (score === null || score === undefined) return '—';
  const locale = (app.translator as any).getLocale?.() || document.documentElement.lang || undefined;
  const text = new Intl.NumberFormat(locale, { maximumFractionDigits: 3 }).format(score);

  return withLabel && label ? `${text} ${label}` : text;
}
