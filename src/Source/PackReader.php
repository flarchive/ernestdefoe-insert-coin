<?php

namespace ErnestDefoe\InsertCoin\Source;

use ZipArchive;

/**
 * Recognises an uploaded pack that has no insertcoin.json, such as an old
 * arcade's Flash pack, and describes it as a manifest would. Return null when
 * the pack isn't yours.
 */
interface PackReader
{
    /**
     * @param list<string> $files paths in the pack, already checked for traversal
     * @return array<string, mixed>|null a manifest, plus 'source' for your renderer
     */
    public function read(ZipArchive $zip, array $files): ?array;

    /**
     * File extensions your packs may carry beyond the arcade's own list.
     *
     * @return list<string>
     */
    public function extensions(): array;
}
