# Getting more games into Insert Coin

Insert Coin ships with seven original games. This guide covers everything after that:

- where to find more games;
- how to make an existing HTML5 game post its scores;
- how to package a game for upload;
- how to write a new game from scratch.

## Where to find games

Any HTML5 game can run in the arcade. To put scores on the leaderboards it needs three small calls, covered in [the next section](#make-a-game-post-scores). These are good places to look:

| Where | What you'll find |
| --- | --- |
| [js13kGames](https://js13kgames.com) | A yearly contest for browser games under 13 KB. Hundreds of polished arcade-style entries, many open source on GitHub. Small files make them ideal packs. |
| [GitHub: html5-game](https://github.com/topics/html5-game) and [js13k](https://github.com/topics/js13k) topics | Open-source games whose licence is in the repository, and whose code you can change. |
| [itch.io free HTML5 games](https://itch.io/games/free/html5) | The biggest catalogue of indie browser games. Many offer a download; check the licence, and ask the author before putting their game on your forum. |
| [Kenney](https://kenney.nl/assets) and [OpenGameArt](https://opengameart.org) | Free art and sound (much of it CC0), for building your own games. |

> **Licences matter.** A game you didn't make is someone else's work. Only upload games whose licence allows it (MIT, BSD, Apache, GPL, CC and similar), or that the author has said you may use. Keep their licence file in the pack. Insert Coin ships no third-party games for exactly this reason.

## Make a game post scores

The arcade adds its score SDK, `window.InsertCoin`, to every game it serves, so a game only has to use it. A game needs three calls:

```js
InsertCoin.ready();                     // 1. the game has loaded
InsertCoin.on('start', () => newGame()); // 2. the player pressed Start (and every Play again)
InsertCoin.gameOver(score);             // 3. the run is over: send its score, once
```

Optional extras:

```js
InsertCoin.submitScore(score);          // send the score early; gameOver() then just ends the run
InsertCoin.muted;                       // the cabinet's sound switch, true or false
InsertCoin.on('mute', ({ muted }) => …); // the switch changed
InsertCoin.standalone;                  // true when the page was opened on its own, outside the arcade
```

### Adapting an existing game

Most games already have a "new game" function and a "game over" moment. Find them and add the calls. For example, in an open-source 2048 clone with a `GameManager`:

```js
// Where the game decides it is over:
if (this.over) InsertCoin.gameOver(this.score);

// Once, after the game is set up:
InsertCoin.on('start', () => this.restart());
InsertCoin.ready();
```

Names differ from game to game; look for `gameOver`, `lose`, `die`, `restart` or `reset`. Three things to check:

- **Start on Start.** If the game starts on its own as soon as it loads, keep the attract screen or menu up until the `start` event.
- **Report once per run.** Call `gameOver` exactly once, with a number, and don't restart until the next `start`.
- **Lower-is-better games** (times, moves) report the time or count as the score and set `"scoreOrder": "low"` in the manifest.

Without the SDK, a game can post the same messages itself:

```js
parent.postMessage({ insertcoin: 1, type: 'ready' }, '*');
parent.postMessage({ insertcoin: 1, type: 'over', data: { score: 1234 } }, '*');
```

## Package it

A pack is a `.zip` holding the game's files and an `insertcoin.json` manifest. Everything can sit at the top of the zip or inside one folder. Upload it under **Admin → Insert Coin → Upload a game pack**. A new pack starts switched off, so you can play it before your members do.

```json
{
  "title": "Hello Arcade",
  "description": "Click the target as many times as you can in ten seconds.",
  "entry": "index.html",
  "width": 4,
  "height": 3,
  "mobile": true,
  "scoreOrder": "high",
  "scoreLabel": "hits",
  "ceiling": 200,
  "minSeconds": 9,
  "thumbnail": "cover.png",
  "category": "Skill"
}
```

| Field | Meaning | Default |
| --- | --- | --- |
| `title` | The game's name. **Required.** | |
| `slug` | Its URL name, `/arcade/<slug>` | made from the title |
| `description` | One or two sentences for the game card and page | |
| `entry` | The page that starts the game | `index.html` |
| `width`, `height` | The screen's shape: pixels or a ratio (`16`, `9`) | `4`, `3` |
| `mobile` | Playable with touch on a phone | `false` |
| `scoreOrder` | `high` (bigger wins) or `low` (smaller wins: times, moves) | `high` |
| `scoreLabel` | What a score counts: `points`, `ms`, `moves` | |
| `ceiling` | The highest score an honest run can reach; anything above is refused | none |
| `minSeconds`, `maxSeconds` | The shortest and longest an honest run can take | `3`, `10800` |
| `thumbnail` | A PNG, JPEG, GIF or WebP in the pack, ideally 16 × 10 | |
| `category` | An existing category's name | none |

A complete example pack is in [`examples/hello-arcade`](../examples/hello-arcade): zip that folder and upload it.

### What a pack may contain

Scripts, styles, data and media: `js`, `mjs`, `css`, `json`, `wasm`, `txt`, `csv`, images (`png`, `jpg`, `gif`, `webp`, `avif`), sound and video (`mp3`, `ogg`, `wav`, `m4a`, `webm`, `mp4` and more), fonts, and common game data (`atlas`, `fnt`, `bin`, `dat`, `pck`, shaders).

It may not contain other HTML pages, SVG, XML, PHP or anything else a server or browser could run outside the game. Hidden files are not allowed either. The upload says which files it refused. Packs are capped at 3,000 files and 256 MB unpacked.

## The sandbox

Every game runs in a sandboxed frame with no access to your forum: no cookies, no API, no reading the page around it. Most games never notice. These are the differences:

- **Saving works for the visit.** Browsers refuse `localStorage`, `sessionStorage` and cookies to a sandboxed page, so the SDK stands in for them with storage that lasts until the page is closed. Games that save a high score keep running; the arcade's leaderboards keep the real scores.
- **Loading its own files with `fetch` or XHR** (some engines load level data or sounds this way) needs your server to send `Access-Control-Allow-Origin: *` for the arcade's pack folder. `<script>`, `<img>` and `<audio>` tags work without it. Apache picks up the `.htaccess` file Insert Coin writes there. On nginx, add:

  ```nginx
  location /assets/insert-coin/packs/ {
      add_header Access-Control-Allow-Origin "*";
      add_header X-Content-Type-Options "nosniff";
  }
  ```

- **No popups or page changes.** A game can't open windows or navigate the forum.
- **Keyboard focus.** The cabinet gives the game focus when the player presses Start.

## Games hosted on another site

A game on its own website can be added under **Add a game from another site** and plays in the same cabinet. To post scores, the page needs the SDK. Include it from your forum:

```html
<script src="https://your-forum.example/assets/extensions/ernestdefoe-insert-coin/sdk/insertcoin.js"></script>
```

You can also copy [`assets/sdk/insertcoin.js`](../assets/sdk/insertcoin.js) next to the game. The site must allow being shown in a frame, so no `X-Frame-Options: DENY`.

## Testing a game before you upload

[`games/harness.html`](../games/harness.html) plays a game the way the arcade does: in the same sandbox, with the SDK added. It has Start and Mute buttons and a log of every message the game sends. Serve the repository's folder and open it:

```sh
python3 -m http.server 8765
# then open http://localhost:8765/games/harness.html?game=coil
```

To try your own game in it, put it in `games/<name>/index.html`.

## Fair play

A browser game's score can always be faked by someone determined. Insert Coin makes it hard and keeps it visible:

- every run needs a signed session from the server, and that session is used once;
- runs shorter than `minSeconds` or longer than `maxSeconds` are refused;
- scores above the `ceiling` are refused;
- scores far beyond the record are held for a moderator;
- games can require a moderator to approve every new crown.

Setting an honest `ceiling` and `minSeconds` for each game is the best protection.
