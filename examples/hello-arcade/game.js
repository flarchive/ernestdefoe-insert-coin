// The smallest Insert Coin game. The arcade adds the SDK (window.InsertCoin)
// before this runs; opened on its own, the stand-in below lets it still play.
var IC = window.InsertCoin || { standalone: true, ready: function () {}, gameOver: function () {}, on: function () {} };
var hud = document.getElementById('hud');
var target = document.getElementById('target');
var hits = 0;
var timer = 0;

function move() {
  target.style.left = Math.random() * (innerWidth - 64) + 'px';
  target.style.top = 50 + Math.random() * (innerHeight - 114) + 'px';
}

function start() {
  hits = 0;
  target.hidden = false;
  move();
  var ends = Date.now() + 10000;
  clearInterval(timer);
  timer = setInterval(function () {
    var left = Math.max(0, Math.ceil((ends - Date.now()) / 1000));
    hud.textContent = hits + ' hits · ' + left + 's';
    if (left === 0) {
      clearInterval(timer);
      target.hidden = true;
      hud.textContent = 'Game over: ' + hits + ' hits';
      IC.gameOver(hits); // 1. report the run's score, once
    }
  }, 100);
}

target.addEventListener('pointerdown', function () {
  hits++;
  move();
});

IC.on('start', start); // 2. the cabinet's Press start (and every Play again)
if (IC.standalone) document.addEventListener('click', function () { if (target.hidden) start(); });
IC.ready(); // 3. tell the cabinet the game has loaded
