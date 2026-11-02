import css from '../../generated/pagesCss';

// The arcade's page styles, added once, by the first arcade page shown.
if (!document.getElementById('insert-coin-styles')) {
  const style = document.createElement('style');
  style.id = 'insert-coin-styles';
  style.textContent = css;
  document.head.appendChild(style);
}

export {};
