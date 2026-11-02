/*!
 * Insert Coin score SDK, v1. MIT. https://github.com/ernestdefoe/insert-coin
 *
 * A game in an Insert Coin arcade runs in a sandboxed iframe and talks to the
 * cabinet around it with postMessage. This file is the whole of that
 * conversation; the arcade adds it to every game it serves, and a game may
 * include it itself (it loads once).
 *
 *   InsertCoin.ready()          the game has loaded and can start
 *   InsertCoin.on('start', fn)  the player pressed start: reset and begin a run
 *   InsertCoin.submitScore(n)   the run's final score
 *   InsertCoin.gameOver([n])    the run is over (n is submitted if submitScore wasn't)
 *   InsertCoin.on('mute', fn)   the cabinet's sound switch; InsertCoin.muted holds it
 *
 * Without the SDK, a game can post the same messages itself:
 *   parent.postMessage({ insertcoin: 1, type: 'score', data: { score: 1234 } }, '*')
 */
(function (w) {
  if (w.InsertCoin) return;

  // In the arcade's sandbox a game has no origin of its own, so the browser
  // refuses it localStorage, sessionStorage and cookies, and many games
  // written for the open web crash on their first high-score save. Stand in
  // with storage that lasts for the visit; the arcade's boards keep scores.
  function memoryStorage() {
    var data = {};
    return {
      get length() {
        return Object.keys(data).length;
      },
      key: function (i) {
        return Object.keys(data)[i] || null;
      },
      getItem: function (k) {
        return Object.prototype.hasOwnProperty.call(data, k) ? data[k] : null;
      },
      setItem: function (k, v) {
        data[k] = String(v);
      },
      removeItem: function (k) {
        delete data[k];
      },
      clear: function () {
        data = {};
      },
    };
  }

  ['localStorage', 'sessionStorage'].forEach(function (name) {
    try {
      w[name].getItem('insertcoin');
    } catch (e) {
      try {
        Object.defineProperty(w, name, { value: memoryStorage(), configurable: true });
      } catch (e2) {}
    }
  });

  try {
    void w.document.cookie;
  } catch (e) {
    try {
      var jar = '';
      Object.defineProperty(w.document, 'cookie', {
        get: function () {
          return jar;
        },
        set: function (v) {
          jar = String(v).split(';')[0];
        },
        configurable: true,
      });
    } catch (e2) {}
  }

  var host = w.parent && w.parent !== w ? w.parent : null;
  var handlers = {};
  var muted = false;

  function send(type, data) {
    if (host) host.postMessage({ insertcoin: 1, type: type, data: data || {} }, '*');
  }

  function emit(type, data) {
    (handlers[type] || []).forEach(function (fn) {
      try {
        fn(data);
      } catch (e) {
        if (w.console) w.console.error(e);
      }
    });
  }

  w.addEventListener('message', function (e) {
    var m = e.data;
    if (!host || e.source !== host || !m || m.insertcoin !== 1) return;
    if (m.type === 'mute' || m.type === 'init') muted = !!(m.data && m.data.muted);
    emit(m.type, m.data || {});
  });

  w.InsertCoin = {
    version: 1,
    /** True when the page was opened on its own, with no cabinet around it. */
    standalone: !host,
    get muted() {
      return muted;
    },
    ready: function () {
      send('ready');
    },
    submitScore: function (score) {
      score = Number(score);
      if (isFinite(score)) send('score', { score: score });
    },
    gameOver: function (score) {
      score = Number(score);
      send('over', isFinite(score) ? { score: score } : {});
    },
    on: function (type, fn) {
      (handlers[type] = handlers[type] || []).push(fn);
    },
  };
})(window);
