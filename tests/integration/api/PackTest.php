<?php

namespace ErnestDefoe\InsertCoin\Tests\integration\api;

use ErnestDefoe\InsertCoin\Tests\integration\ArcadeTestCase;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use ZipArchive;

/** Game packs: what is installed, and everything that is refused. */
class PackTest extends ArcadeTestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param array<string, string> $files
     * @param array<string, int> $attributes unix mode bits per name
     */
    private function zip(array $files, array $attributes = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'icpack').'.zip';
        $this->temp[] = $path;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        foreach ($attributes as $name => $mode) {
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $mode << 16);
        }

        $zip->close();

        return $path;
    }

    /** @return array{0: int, 1: array<string, mixed>|null, 2: string} */
    private function upload(string $zip, int $actor = 1, ?int $game = null): array
    {
        $request = $this->request('POST', '/api/insert-coin/manage/packs', ['authenticatedAs' => $actor])
            ->withUploadedFiles(['pack' => new UploadedFile($zip, filesize($zip), UPLOAD_ERR_OK, 'pack.zip', 'application/zip')])
            ->withParsedBody($game ? ['game' => (string) $game] : []);
        $response = $this->send($request);
        $raw = (string) $response->getBody();

        return [$response->getStatusCode(), json_decode($raw, true), $raw];
    }

    private function manifest(array $extra = []): string
    {
        return (string) json_encode($extra + ['title' => 'Space Pong', 'width' => 16, 'height' => 9, 'scoreOrder' => 'high', 'ceiling' => 999, 'thumbnail' => 'cover.png', 'category' => 'Arcade']);
    }

    #[Test]
    public function a_pack_installs_switched_off_with_its_files_in_assets_and_its_page_in_the_frame(): void
    {
        $zip = $this->zip([
            'space-pong/insertcoin.json' => $this->manifest(),
            'space-pong/index.html' => '<!doctype html><html><head><title>x</title></head><body><script src="game.js"></script></body></html>',
            'space-pong/game.js' => 'InsertCoin.ready();',
            'space-pong/cover.png' => 'png',
            'space-pong/sfx/hit.ogg' => 'ogg',
            '__MACOSX/space-pong/._game.js' => 'junk',
            'space-pong/.DS_Store' => 'junk',
        ]);

        [$status, $body, $raw] = $this->upload($zip);
        $this->assertSame(201, $status, $raw);
        $game = $body['data'];
        $this->assertSame('space-pong', $game['slug']);
        $this->assertFalse($game['enabled']);
        $this->assertSame(999.0, (float) $game['scoreCeiling']);
        $this->assertStringContainsString('/assets/insert-coin/packs/'.$game['path'].'/cover.png', (string) $game['thumbnailUrl']);

        $assets = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets');
        $this->assertTrue($assets->exists('insert-coin/packs/'.$game['path'].'/game.js'));
        $this->assertTrue($assets->exists('insert-coin/packs/'.$game['path'].'/sfx/hit.ogg'));
        // 🚨 The page itself is never a public file.
        $this->assertFalse($assets->exists('insert-coin/packs/'.$game['path'].'/index.html'));
        $this->assertFalse($assets->exists('insert-coin/packs/'.$game['path'].'/._game.js'));

        $frame = $this->send($this->request('GET', '/arcade/frame/'.$game['id'], ['authenticatedAs' => 1]));
        $html = (string) $frame->getBody();
        $this->assertSame(200, $frame->getStatusCode());
        $this->assertStringContainsString('<base href="', $html);
        $this->assertStringContainsString('/assets/insert-coin/packs/'.$game['path'].'/', $html);
        $this->assertStringContainsString('sandbox', $frame->getHeaderLine('Content-Security-Policy'));

        // Switched off: members don't see it yet.
        $frame = $this->send($this->request('GET', '/arcade/frame/'.$game['id'], ['authenticatedAs' => 2]));
        $this->assertSame(404, $frame->getStatusCode());
    }

    #[Test]
    public function the_example_pack_in_the_guide_installs(): void
    {
        $dir = dirname(__DIR__, 3).'/examples/hello-arcade';
        $files = [];
        foreach (scandir($dir) as $name) {
            if (is_file("$dir/$name")) {
                $files["hello-arcade/$name"] = (string) file_get_contents("$dir/$name");
            }
        }

        [$status, $body, $raw] = $this->upload($this->zip($files));
        $this->assertSame(201, $status, $raw);
        $this->assertSame('hello-arcade', $body['data']['slug']);
        $this->assertSame('hits', $body['data']['scoreLabel']);
        $this->assertNotNull($body['data']['thumbnailUrl']);
    }

    #[Test]
    public function replacing_a_pack_keeps_the_game_and_removes_the_old_files(): void
    {
        [, $first] = $this->upload($this->zip(['insertcoin.json' => $this->manifest(), 'index.html' => '<html><head></head></html>', 'a.js' => '1']));
        [$status, $second, $raw] = $this->upload($this->zip(['insertcoin.json' => $this->manifest(['title' => 'Space Pong 2']), 'index.html' => '<html><head></head></html>', 'b.js' => '2']), 1, $first['data']['id']);

        $this->assertSame(200, $status, $raw);
        $this->assertSame($first['data']['id'], $second['data']['id']);
        $this->assertSame('space-pong', $second['data']['slug']);
        $assets = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets');
        $this->assertFalse($assets->exists('insert-coin/packs/'.$first['data']['path'].'/a.js'));
        $this->assertTrue($assets->exists('insert-coin/packs/'.$second['data']['path'].'/b.js'));
    }

    #[Test]
    public function unsafe_packs_are_refused_and_nothing_is_written(): void
    {
        $page = '<html><head></head></html>';
        $cases = [
            'traversal' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, '../../evil.js' => 'x']],
            'dot segment' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'a/./b.js' => 'x']],
            'absolute' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, '/etc/evil.js' => 'x']],
            'backslash' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'a\\..\\b.js' => 'x']],
            'php' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'shell.php' => '<?php system($_GET[1]);']],
            'phtml' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'x.phtml' => 'x']],
            'second html page' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'other.html' => '<script>x</script>']],
            'svg' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'logo.svg' => '<svg onload="alert(1)"/>']],
            'htaccess' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, '.htaccess' => 'AddType application/x-httpd-php .js']],
            'symlink' => [['insertcoin.json' => $this->manifest(), 'index.html' => $page, 'link.js' => '/etc/passwd'], ['link.js' => 0120777]],
            'no manifest' => [['index.html' => $page]],
            'manifest without title' => [['insertcoin.json' => '{"width": 4}', 'index.html' => $page]],
            'missing entry' => [['insertcoin.json' => $this->manifest(['entry' => 'play.html']), 'index.html' => $page]],
            'entry is not html' => [['insertcoin.json' => $this->manifest(['entry' => 'game.js']), 'game.js' => 'x']],
        ];

        // Other tests' packs share the test install's assets folder.
        $assets = $this->app()->getContainer()->make('filesystem')->disk('flarum-assets');
        $before = $assets->directories('insert-coin/packs');

        foreach ($cases as $name => $case) {
            [$status, , $raw] = $this->upload($this->zip(...$case));
            $this->assertSame(422, $status, $name.': '.$raw);
        }

        $this->assertSame(7, $this->database()->table('insertcoin_games')->count());
        $this->assertSame($before, $assets->directories('insert-coin/packs'));
    }

    #[Test]
    public function not_a_zip_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'icpack');
        $this->temp[] = $path;
        file_put_contents($path, 'not a zip');

        [$status] = $this->upload($path);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function only_admins_upload_or_manage(): void
    {
        [$status] = $this->upload($this->zip(['insertcoin.json' => $this->manifest(), 'index.html' => '<html></html>']), 2);
        $this->assertSame(403, $status);

        [$status] = $this->json('GET', '/api/insert-coin/manage', 2);
        $this->assertSame(403, $status);
    }

    #[Test]
    public function a_url_game_must_live_on_another_site(): void
    {
        [$status] = $this->json('POST', '/api/insert-coin/manage/games', 1, ['title' => 'Mine', 'url' => 'http://localhost/evil']);
        $this->assertSame(422, $status);

        [$status] = $this->json('POST', '/api/insert-coin/manage/games', 1, ['title' => 'Mine', 'url' => 'javascript:alert(1)']);
        $this->assertSame(422, $status);

        [$status, $body, $raw] = $this->json('POST', '/api/insert-coin/manage/games', 1, ['title' => 'Elsewhere', 'url' => 'https://games.example.com/play']);
        $this->assertSame(201, $status, $raw);
        $this->assertSame('https://games.example.com/play', $body['data']['externalUrl']);
        $this->assertNull($body['data']['frameUrl']);
    }

    #[Test]
    public function flipping_who_wins_rebuilds_the_crown(): void
    {
        $this->play(2, 'coil', 500);
        $this->play(3, 'coil', 900);

        [$status] = $this->json('PATCH', '/api/insert-coin/manage/games/'.$this->gameId('coil'), 1, ['scoreOrder' => 'low']);
        $this->assertSame(200, $status);
        $this->assertSame(2, $this->championOf('coil'));
        $this->assertSame(1, $this->crowns(2));
        $this->assertSame(0, $this->crowns(3));
    }
}
