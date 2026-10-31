Anyone who ran a forum in the 2000s remembers the arcade. IBArcade, ibProArcade and v3arcade put a games room inside the forum: a high-score table for every game, a crown for the champion, and the sting of a PM saying someone had just taken it. Those arcades ran on Flash, and when Flash died they went with it. Flarum never had one.

**Insert Coin** brings it back, rebuilt for today: HTML5 games that play on phones as well as desktops, run in a sandbox, and use scores the server can check.

![The arcade floor on the default Flarum theme](https://raw.githubusercontent.com/ernestdefoe/insert-coin/main/.github/screenshots/arcade.png)

## Seven original games, ready the minute you switch it on

Insert Coin ships with seven games written for it, each with its own art and sound, touch controls and keyboard controls:

- **Coil**, a snake that glides between cells;
- **Brickfall**, a brick-breaker with combos and power-ups;
- **Twofold**, a 2048-style merge puzzle;
- **Rock Belt**, an asteroids-style shooter with saucers that shoot back;
- **Rooftop Dash**, an endless runner across a neon skyline;
- **Gem Rush**, ninety seconds of match-3;
- **Quickdraw**, a reaction duel where the lowest time wins.

None of them is a copy of anything, so there's nobody's copyright in the package.

![Gem Rush in the cabinet](https://raw.githubusercontent.com/ernestdefoe/insert-coin/main/.github/screenshots/gameplay.png)

## Every game gets a proper cabinet

Each game sits in a framed screen with CRT glass and scanlines (one switch turns them off). The attract screen shows the HI-SCORE and who holds it, with a blinking *INSERT COIN* and *Press start*. There's a sound switch, a theatre mode that dims the page, and full screen. Beside the screen sit the high scores for all time, this month and this week. Your best and your rank update the moment a run ends.

![A game in its cabinet with the high-score rail](https://raw.githubusercontent.com/ernestdefoe/insert-coin/main/.github/screenshots/cabinet.png)

## Crowns, and the alert you'll love to hate

Every game has a champion, with a crown on the game's card. Beat the record and you get a celebration with confetti, kept still for anyone who asks their device for reduced motion. The member you knocked off gets *"You've been dethroned on Rock Belt by…"* as an alert and an email. The arcade floor keeps a Hall of Fame (most crowns held) and the latest crowns. Every member's profile gets an Arcade tab with their crowns and best scores.

![The new champion celebration](https://raw.githubusercontent.com/ernestdefoe/insert-coin/main/.github/screenshots/celebration.png)

## Scores the server can stand behind

A browser game's score can always be faked by someone determined, so Insert Coin makes it hard and keeps it visible:

- every run needs a signed, single-use session from the server;
- each game has a shortest and longest honest run and a score ceiling;
- scores far beyond the record wait for a moderator;
- any game can require approval for every new crown;
- starting runs and posting scores are rate-limited.

Moderators get a review queue, can wipe a game's scores or one member's, and can ban a member from the arcade.

## More games: upload a pack, or link one

Any HTML5 game can join the arcade, and three lines of code put its scores on the boards. Upload it as a zip with a small `insertcoin.json` manifest, or add a game hosted on another site. [The guide](https://github.com/ernestdefoe/insert-coin/blob/main/docs/MAKING-GAMES.md) covers:

- where to find games with a licence that allows it (js13kGames, GitHub, itch.io);
- how to adapt one;
- the pack format;
- the sandbox.

There's also a complete example pack to copy.

Every game runs in a sandbox. It can't read your members' cookies, call your forum's API or touch the page around it. A pack is checked before anything is written: path tricks, zip bombs, and anything a server or browser would run are refused.

## Built on default Flarum, light on every page

The arcade uses Flarum's own hero, sidebar and colours, so it fits the default theme, dark mode and your theme with no extra CSS. It adds about 4 KB (gzipped) of JavaScript to every page and nothing to discussion, post or user queries. The arcade's pages and styles load only when someone opens them. It's translatable throughout.

Optional extras, when the extensions are installed:

- points for taking a crown, through FoF Gamification;
- live leaderboards with Flarum Realtime;
- a *Recent crowns* widget for Bespoke and FoF Forum Widgets.

## Install

```sh
composer require ernestdefoe/insert-coin
```

Then enable **Insert Coin** in the admin panel. The arcade is at `/arcade`.

Free and MIT licensed. Flash games through Ruffle are planned as a separate, optional add-on.

- [Try it on the showcase](https://ernestdefoe.dev/arcade)
- [Source code on GitHub](https://github.com/ernestdefoe/insert-coin)
- [Getting more games into Insert Coin](https://github.com/ernestdefoe/insert-coin/blob/main/docs/MAKING-GAMES.md)

☕ If you like the things I have built, consider [buying me a coffee](https://buymeacoffee.com/ernestdefoe).
